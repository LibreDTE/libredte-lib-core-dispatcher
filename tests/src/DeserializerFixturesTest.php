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
use Derafu\BackboneDispatcher\Service\Serialization\Serializer;
use Derafu\Value\ValueType;
use FilesystemIterator;
use libredte\lib\CoreDispatcher\Service\Deserializer\Billing\Book\BookBagDeserializer;
use libredte\lib\CoreDispatcher\Service\Deserializer\Billing\Document\DocumentBagDeserializer;
use libredte\lib\CoreDispatcher\Service\Deserializer\Billing\Document\DocumentBatchDeserializer;
use libredte\lib\CoreDispatcher\Service\Deserializer\Billing\Document\DocumentEnvelopeDeserializer;
use libredte\lib\CoreDispatcher\Service\Deserializer\Billing\Identifier\CafDeserializer;
use libredte\lib\CoreDispatcher\Service\Deserializer\Billing\Integration\SiiRequestDeserializer;
use libredte\lib\CoreDispatcher\Service\Deserializer\Billing\TradingParties\EmisorDeserializer;
use libredte\lib\CoreDispatcher\Service\Deserializer\Billing\TradingParties\MandatarioDeserializer;
use libredte\lib\CoreDispatcher\Service\Deserializer\Billing\TradingParties\ReceptorDeserializer;
use libredte\lib\CoreDispatcher\Service\Deserializer\CertificateDeserializer;
use libredte\lib\CoreDispatcher\Service\Deserializer\XmlDocumentDeserializer;
use libredte\lib\TestsCoreDispatcher\Fixture\FixtureContext;
use libredte\lib\TestsCoreDispatcher\Fixture\FixtureObjectFactory;
use libredte\lib\TestsCoreDispatcher\Fixture\FixturePlaceholderResolver;
use libredte\lib\TestsCoreDispatcher\Fixture\FixturePlaceholderUnavailableException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesNamespace;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Yaml\Yaml;
use Throwable;

/**
 * Prueba cada deserializador con el dato exacto que enviaría un usuario: o
 * construye el objeto esperado, o lo rechaza con la excepción esperada. Es de
 * un solo sentido; la ida y vuelta (`serialize` y `deserialize`) la prueba
 * `DeserializationRoundTripTest`.
 *
 * Llama directamente al deserializador registrado para el tipo (no pasa por
 * `ObjectFactory::create()`, que convierte el rechazo de un dato no
 * soportado en `NoDeserializerFoundException`), con sus dependencias reales.
 *
 * Cada fixture es un YAML en `tests/fixtures/deserializers/**\/*.yaml`:
 *
 *   description: string
 *   body: <el dato exacto que enviaría un usuario: string, mapa o lista;
 *          admite placeholders `{{método}}` de FixtureContext>
 *   test:
 *     type: string     # Tipo (FQCN) con el que está registrado el
 *                      # deserializador; el resultado debe ser instancia suya.
 *     expect: map      # Éxito: cada clave comprueba un valor del resultado.
 *     failure:         # Rechazo (en lugar de `expect`).
 *       exception: string   # FQCN de la excepción.
 *       message: string     # Opcional: texto que debe contener el mensaje.
 *
 * Las claves de `expect` son una de estas:
 *
 *   ruta.en.salida: valor      # Ruta en la serialización del resultado.
 *   getAlgo(): valor           # Lo que devuelve el método del resultado.
 *   getAlgo().ruta: valor      # Ruta en la serialización de lo que devuelve.
 *
 * Un valor `{instanceof: FQCN}` comprueba la clase en vez de la igualdad.
 */
#[CoversClass(BookBagDeserializer::class)]
#[CoversClass(CafDeserializer::class)]
#[CoversClass(CertificateDeserializer::class)]
#[CoversClass(DocumentBagDeserializer::class)]
#[CoversClass(DocumentBatchDeserializer::class)]
#[CoversClass(DocumentEnvelopeDeserializer::class)]
#[CoversClass(EmisorDeserializer::class)]
#[CoversClass(MandatarioDeserializer::class)]
#[CoversClass(ReceptorDeserializer::class)]
#[CoversClass(SiiRequestDeserializer::class)]
#[CoversClass(XmlDocumentDeserializer::class)]
#[UsesNamespace('libredte\lib\CoreDispatcher\Service\Deserializer')]
class DeserializerFixturesTest extends TestCase
{
    private static FixturePlaceholderResolver $placeholders;

    public static function setUpBeforeClass(): void
    {
        self::$placeholders = new FixturePlaceholderResolver(new FixtureContext());
    }

    public static function tearDownAfterClass(): void
    {
        FixtureObjectFactory::cleanup();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function fixtureProvider(): iterable
    {
        $baseDir = dirname(__DIR__) . '/fixtures/deserializers';

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
    public function testDeserializer(string $path): void
    {
        $fixture = ArrayReader::fromValue(Yaml::parseFile($path), source: $path);
        $test = $fixture->requireReader('test');
        $type = $test->requireString('type');

        try {
            $body = self::$placeholders->resolve(
                $fixture->require('body', ValueType::oneOf(ValueType::string(), ValueType::map()))
            );
        } catch (FixturePlaceholderUnavailableException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $this->assertTrue(
            $test->hasValue('expect') !== $test->hasValue('failure'),
            'The fixture "test" must have exactly one of: expect, failure.'
        );

        $deserializer = FixtureObjectFactory::deserializer($type);
        $input = is_array($body)
            ? ValueType::map()->parse($body, 'body', $path)
            : $this->assertIsStringAndReturn($body);

        if ($test->hasValue('failure')) {
            $failure = $test->requireReader('failure');
            $this->assertFailure(
                $failure->requireString('exception'),
                $failure->getString('message'),
                fn () => $deserializer->deserialize($input, $type),
            );

            return;
        }

        $object = $deserializer->deserialize($input, $type);

        $this->assertInstanceOf($this->className($type), $object);
        foreach ($test->requireMap('expect') as $key => $expected) {
            $this->assertExpected((string) $key, $expected, $object);
        }
    }

    /**
     * Comprueba que `$name` es una clase o interfaz que existe.
     *
     * @return class-string
     */
    private function className(string $name): string
    {
        if (!class_exists($name) && !interface_exists($name)) {
            $this->fail(sprintf('The class or interface "%s" does not exist.', $name));
        }

        return $name;
    }

    private function assertIsStringAndReturn(mixed $value): string
    {
        $this->assertIsString($value, 'The "body" must be a string or a map.');

        return $value;
    }

    /**
     * Comprueba que `$callback` lanza `$exception` y, si se indica, que su
     * mensaje contiene `$message`.
     *
     */
    private function assertFailure(string $exception, ?string $message, callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $e) {
            $this->assertInstanceOf($this->className($exception), $e, sprintf(
                'Expected %s, got %s: %s',
                $exception,
                $e::class,
                $e->getMessage(),
            ));
            if ($message !== null) {
                $this->assertStringContainsString($message, $e->getMessage());
            }

            return;
        }

        $this->fail(sprintf('Expected %s to be thrown, but nothing was.', $exception));
    }

    /**
     * Comprueba una clave de `expect` contra el resultado.
     */
    private function assertExpected(string $key, mixed $expected, object $object): void
    {
        if (preg_match('/^(\w+)\(\)(?:\.(.+))?$/', $key, $match) === 1) {
            $this->assertTrue(
                method_exists($object, $match[1]),
                sprintf('The result has no method "%s()".', $match[1])
            );
            $actual = $object->{$match[1]}();
            $path = $match[2] ?? null;
        } else {
            $actual = $object;
            $path = $key;
        }

        if ($path !== null) {
            $serialized = (new Serializer())->serialize($actual);
            $this->assertIsArray($serialized, sprintf('The value of "%s" is not serializable to a map.', $key));
            $reader = ArrayReader::fromArray($serialized);
            $this->assertTrue($reader->hasValue($path), sprintf('Missing "%s" in the serialization.', $key));
            $actual = $reader->get($path, ValueType::mixed());
        }

        if (is_array($expected) && array_keys($expected) === ['instanceof']) {
            $class = $expected['instanceof'];
            $this->assertIsString($class);
            $this->assertInstanceOf($this->className($class), $actual, sprintf('Class of "%s" does not match.', $key));

            return;
        }

        $this->assertSame($expected, $actual, sprintf('Value of "%s" does not match.', $key));
    }
}
