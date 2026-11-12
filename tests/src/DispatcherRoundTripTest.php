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
use Derafu\Value\ValueType;
use FilesystemIterator;
use libredte\lib\CoreDispatcher\Bootstrap;
use libredte\lib\TestsCoreDispatcher\Fixture\FixtureContext;
use libredte\lib\TestsCoreDispatcher\Fixture\FixturePlaceholderResolver;
use libredte\lib\TestsCoreDispatcher\Fixture\FixturePlaceholderUnavailableException;
use libredte\lib\TestsCoreDispatcher\Fixture\FixtureRequest;
use PHPUnit\Framework\Attributes\CoversNamespace;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Yaml\Yaml;

/**
 * Prueba la ida y vuelta por el `SafeDispatcher` real: lo que una operación
 * entrega como resultado se vuelve a recibir como parámetro de otra operación
 * que lo recibe y lo devuelve del mismo tipo, y los dos resultados deben ser
 * iguales.
 *
 * `body -> deserialize -> operación -> serialize -> deserialize -> operación
 * -> serialize`. A diferencia de `DeserializationRoundTripTest`, que usa el
 * `ObjectFactory` y el `Serializer`, aquí pasa todo por el dispatcher.
 *
 * Cada fixture es un YAML en `tests/fixtures/dispatcher_roundtrip/**\/*.yaml`:
 *
 *   description: string
 *   request:           # Primera operación, con el dato exacto del usuario.
 *     package: string
 *     component: string
 *     worker: string
 *     operation: string
 *     parameters: map  # Admite placeholders `{{método}}` de FixtureContext.
 *   then:              # Segunda operación, que recibe el resultado de la
 *     package: string  # primera como uno de sus parámetros.
 *     component: string
 *     worker: string
 *     operation: string
 *     parameter: string  # Nombre del parámetro que recibe el resultado.
 *     feed: string       # Opcional: ruta del resultado que se reenvía. Sin
 *                        # ella se reenvía el resultado completo.
 *   expect: map        # Obligatorio: ruta => valor del primer resultado.
 *
 * `expect` es obligatorio porque la igualdad entre los dos resultados sola no
 * basta: si el deserializador ignorara un dato desde la primera pasada, ambos
 * resultados lo perderían por igual y serían iguales.
 */
#[CoversNamespace('libredte\lib\CoreDispatcher')]
class DispatcherRoundTripTest extends TestCase
{
    private static SafeDispatcherInterface $dispatcher;

    private static FixturePlaceholderResolver $placeholders;

    public static function setUpBeforeClass(): void
    {
        self::$dispatcher = Bootstrap::boot('test', true);
        self::$placeholders = new FixturePlaceholderResolver(new FixtureContext());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function fixtureProvider(): iterable
    {
        $baseDir = dirname(__DIR__) . '/fixtures/dispatcher_roundtrip';

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
    public function testRoundTrip(string $path): void
    {
        $fixture = ArrayReader::fromValue(Yaml::parseFile($path), source: $path);
        $then = $fixture->requireReader('then');

        try {
            $first = $this->dispatch(FixtureRequest::create(
                $fixture->requireReader('request'),
                self::$placeholders,
                $path,
            ));
        } catch (FixturePlaceholderUnavailableException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $firstValue = $first->getValue();
        $this->assertIsArray($firstValue, 'The first result must be an array.');
        $this->assertExpected($fixture->requireMap('expect'), $firstValue);

        $feed = $then->getString('feed');
        $input = $feed === null
            ? $firstValue
            : ArrayReader::fromArray($firstValue)->get($feed, ValueType::mixed())
        ;
        $this->assertNotNull($input, sprintf('The path "%s" to feed back is not in the first result.', $feed));

        $second = $this->dispatch(FixtureRequest::create(
            $then,
            self::$placeholders,
            $path,
            [$then->requireString('parameter') => $input],
        ));

        $this->assertSame(
            $firstValue,
            $second->getValue(),
            'The result of the operation that received the first result differs from the first one.'
        );
    }

    /**
     * Despacha la solicitud y comprueba que la operación tuvo éxito.
     */
    private function dispatch(OperationRequest $request): OperationResultInterface
    {
        $result = self::$dispatcher->dispatch($request);

        $this->assertTrue(
            $result->isSuccess(),
            sprintf('The operation failed: %s', json_encode($result->getProblem()?->toArray()))
        );

        return $result;
    }

    /**
     * Comprueba que cada ruta de `$expected` está en `$actual` con ese valor.
     *
     * @param array<string, mixed> $expected
     * @param array<array-key, mixed> $actual
     */
    private function assertExpected(array $expected, array $actual): void
    {
        $this->assertNotEmpty($expected, 'The fixture "expect" cannot be empty.');

        $reader = ArrayReader::fromArray($actual);

        foreach ($expected as $path => $value) {
            $this->assertTrue(
                $reader->hasValue($path),
                sprintf('Missing "%s" in the first result.', $path)
            );
            $this->assertSame(
                $value,
                $reader->get($path, ValueType::mixed()),
                sprintf('Value at "%s" does not match.', $path)
            );
        }
    }
}
