<?php

declare(strict_types=1);

/**
 * LibreDTE: Connector (Dispatcher) for the PHP Library (Core).
 * Copyright (C) 2026 LibreDTE <https://www.libredte.cl>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace libredte\lib\TestsCoreDispatcher;

use Derafu\Array\ArrayReader;
use Derafu\BackboneDispatcher\Contract\OperationResultInterface;
use Derafu\BackboneDispatcher\Contract\SafeDispatcherInterface;
use Derafu\BackboneDispatcher\ValueObject\OperationRequest;
use Derafu\Selector\Selector;
use Derafu\Value\ValueType;
use FilesystemIterator;
use libredte\lib\CoreDispatcher\Bootstrap;
use libredte\lib\TestsCoreDispatcher\Fixture\FixtureContext;
use libredte\lib\TestsCoreDispatcher\Fixture\FixturePlaceholderUnavailableException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Yaml\Yaml;

/**
 * Ejercita el `SafeDispatcher` real (`Bootstrap::boot('test', true)`, sin
 * mocks en ningún punto de la cadena) contra cada fixture YAML bajo
 * `tests/fixtures/operations/**\/*.yaml` — un archivo por `#[Operation]` de
 * `libredte-lib-core`.
 *
 * Deliberadamente `#[CoversNothing]`: esto es un smoke test de integración
 * de punta a punta que atraviesa decenas de workers/jobs/estrategias reales
 * según qué operación se esté probando — enumerar cada clase tocada vía
 * `#[UsesClass]` (lo que exige `beStrictAboutCoverageMetadata` de
 * `phpunit.xml` para cualquier test con cobertura) sería impráctico y
 * frágil (se rompería con cualquier cambio interno de `libredte-lib-core`
 * que no afecte el comportamiento de la operación en sí).
 *
 * Formato de cada fixture, ver `CLAUDE.md` para el diseño completo:
 *
 *   description: string
 *   request:
 *     package: string
 *     component: string
 *     worker: string
 *     operation: string
 *     parameters: array   # valores, o placeholders `{{método}}` resueltos
 *                         # contra FixtureContext antes de despachar
 *   expect:
 *     success: {value_contains: {...}}       # y/o value_equals: <valor>, y/o
 *                                             # value_matches_jmespath: {...}
 *     failure: {problem_title: string}       # o
 *     either: {success: {...}, failure: {...}}  # cualquiera de los dos
 *                                                # cuenta como éxito del test
 *
 * `value_contains` recorre `getValue()` por path (`a.b.c`) y compara cada
 * hoja — pensado para un resultado tipo objeto/mapa. `value_equals`
 * compara el valor completo de `getValue()` tal cual, sin recorrer nada —
 * necesario para operaciones cuyo resultado es un arreglo simple (ej.
 * `sii_rcv::listDocumentEvents`, que retorna `[]` cuando no hay eventos:
 * no hay ningún path que recorrer, pero sí un valor exacto que afirmar).
 *
 * `value_matches_jmespath` evalúa cada clave como una expresión JMESPath
 * (vía `derafu/selector`) contra `getValue()` y compara el resultado con lo
 * esperado — a diferencia de `value_contains`, puede expresar cosas que un
 * path plano no puede: ausencia (`"length(renderings[?label=='cedible'])":
 * 0`), conteo exacto (`"length(renderings)": 2`) o filtros/proyecciones
 * (`"renderings[?label=='cedible'].mimeType | [0]": application/pdf`).
 * Reservado para esos casos — `value_contains` sigue siendo preferible para
 * comparar un valor puntual por path simple, es más fácil de leer.
 */
#[CoversNothing]
class OperationFixturesTest extends TestCase
{
    private static SafeDispatcherInterface $dispatcher;

    private static FixtureContext $context;

    public static function setUpBeforeClass(): void
    {
        self::$dispatcher = Bootstrap::boot('test', true);
        self::$context = new FixtureContext();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function fixtureProvider(): iterable
    {
        $baseDir = dirname(__DIR__) . '/fixtures/operations';

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($baseDir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            self::assertInstanceOf(SplFileInfo::class, $file);
            if ($file->getExtension() !== 'yaml') {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($baseDir) + 1);

            yield $relative => [$file->getPathname()];
        }
    }

    #[DataProvider('fixtureProvider')]
    public function testOperationFixture(string $path): void
    {
        $fixture = ArrayReader::fromValue(Yaml::parseFile($path), source: $path);
        $requestData = $fixture->requireReader('request');

        try {
            $parameters = $this->resolvePlaceholders($requestData->get('parameters', ValueType::map(), []));
        } catch (FixturePlaceholderUnavailableException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $request = new OperationRequest(
            $requestData->requireString('package'),
            $requestData->requireString('component'),
            $requestData->requireString('worker'),
            $requestData->requireString('operation'),
            ValueType::map()->parse($parameters, 'request.parameters', $path),
        );

        $result = self::$dispatcher->dispatch($request);

        $this->assertExpectation($fixture->get('expect', ValueType::map(), []), $result);
    }

    /**
     * Reemplaza recursivamente cualquier string `"{{método}}"` por el
     * resultado de invocar ese método (sin argumentos) en `FixtureContext`.
     * Si el método devuelve `null`, lanza
     * `FixturePlaceholderUnavailableException` — `testOperationFixture()` la
     * captura para saltar el test — en vez de devolver `null` silenciosamente
     * y señalarlo mutando estado de la instancia.
     *
     * @throws FixturePlaceholderUnavailableException
     */
    private function resolvePlaceholders(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn (mixed $item) => $this->resolvePlaceholders($item), $value);
        }

        if (is_string($value) && preg_match('/^\{\{(\w+)\}\}$/', $value, $matches) === 1) {
            $method = $matches[1];

            if (!method_exists(self::$context, $method)) {
                throw new RuntimeException(sprintf(
                    'Fixture placeholder "{{%s}}" does not exist on %s.',
                    $method,
                    FixtureContext::class,
                ));
            }

            $resolved = self::$context->{$method}();

            if ($resolved === null) {
                throw new FixturePlaceholderUnavailableException($method);
            }

            return $resolved;
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $expect
     */
    private function assertExpectation(array $expect, OperationResultInterface $result): void
    {
        $reader = ArrayReader::fromArray($expect);
        if ($reader->hasValue('either')) {
            $either = $reader->requireReader('either');
            if ($result->isSuccess()) {
                $this->assertSuccess($either->get('success', ValueType::map(), []), $result);
            } else {
                $this->assertFailure($either->get('failure', ValueType::map(), []), $result);
            }

            return;
        }

        if (isset($expect['success'])) {
            $this->assertTrue($result->isSuccess(), $this->describeFailure($result));
            $this->assertSuccess($reader->requireMap('success'), $result);

            return;
        }

        if (isset($expect['failure'])) {
            $this->assertFalse(
                $result->isSuccess(),
                'Expected the operation to fail, but it succeeded.'
            );
            $this->assertFailure($reader->requireMap('failure'), $result);

            return;
        }

        $this->fail('Fixture "expect" must have one of: success, failure, either.');
    }

    /**
     * @param array<string, mixed> $expect
     */
    private function assertSuccess(array $expect, OperationResultInterface $result): void
    {
        $value = $result->getValue();
        $reader = ArrayReader::fromArray($expect);

        if (array_key_exists('value_equals', $expect)) {
            $this->assertSame($expect['value_equals'], $value);
        }

        foreach ($reader->getArray('value_contains') ?? [] as $path => $expected) {
            $this->assertValueContains((string) $path, $expected, $value);
        }

        foreach ($reader->getArray('value_matches_jmespath') ?? [] as $expression => $expected) {
            $this->assertValueMatchesJmesPath((string) $expression, $expected, $value);
        }
    }

    /**
     * `problem_title` acepta un string (un único FQCN aceptado) o una lista
     * de strings (cualquiera de esos FQCN cuenta como éxito del test) — útil
     * cuando la misma fixture puede fallar en más de un punto legítimo
     * según el entorno (ej. las operaciones del SII real fallan siempre en
     * la autenticación con un certificado autofirmado, pero fallarían más
     * adelante — o tendrían éxito — con un certificado real configurado).
     *
     * @param array<string, mixed> $expect
     */
    private function assertFailure(array $expect, OperationResultInterface $result): void
    {
        $problem = $result->getProblem();
        $this->assertNotNull($problem, 'Expected a ProblemDetail on a failed result.');

        $reader = ArrayReader::fromArray($expect);
        if ($reader->hasValue('problem_title')) {
            $title = $reader->require('problem_title', ValueType::oneOf(
                ValueType::string(),
                ValueType::arrayOf(ValueType::string()),
            ));
            $expectedTitles = is_array($title) ? $title : [$title];
            $actualTitle = $problem->toArray()['title'];

            $this->assertContains($actualTitle, $expectedTitles, sprintf(
                'Expected the problem title to be one of [%s], got "%s".',
                implode(', ', $expectedTitles),
                $actualTitle,
            ));
        }
    }

    /**
     * Recorre `$actual` siguiendo el path `a.b.c` y compara contra
     * `$expected` — `'*'` solo exige que el valor final no sea `null`.
     */
    private function assertValueContains(string $path, mixed $expected, mixed $actual): void
    {
        $current = $actual;

        foreach (explode('.', $path) as $segment) {
            $this->assertIsArray($current, sprintf(
                'Expected an array while traversing "%s" (at "%s").',
                $path,
                $segment,
            ));
            $this->assertArrayHasKey($segment, $current, sprintf(
                'Missing key "%s" while traversing "%s".',
                $segment,
                $path,
            ));
            $current = $current[$segment];
        }

        if ($expected === '*') {
            $this->assertNotNull($current, sprintf('Expected "%s" to be non-null.', $path));

            return;
        }

        $this->assertSame($expected, $current, sprintf('Value at "%s" does not match.', $path));
    }

    /**
     * Evalúa una expresión JMESPath (vía `derafu/selector`) contra `$actual`
     * y compara el resultado con `$expected` — reservado para lo que
     * `assertValueContains()` no puede expresar con un path plano: ausencia,
     * conteo, o filtros/proyecciones (ver el docblock de la clase).
     */
    private function assertValueMatchesJmesPath(
        string $expression,
        mixed $expected,
        mixed $actual
    ): void {
        $this->assertIsArray($actual, sprintf(
            'Expected an array to evaluate the JMESPath expression "%s".',
            $expression,
        ));

        $result = Selector::get($actual, 'jmespath:' . $expression);

        $this->assertSame($expected, $result, sprintf(
            'JMESPath expression "%s" did not match the expected value.',
            $expression,
        ));
    }

    private function describeFailure(OperationResultInterface $result): string
    {
        $problem = $result->getProblem();

        return $problem !== null ? (string) $problem : 'Unknown failure (no ProblemDetail).';
    }
}
