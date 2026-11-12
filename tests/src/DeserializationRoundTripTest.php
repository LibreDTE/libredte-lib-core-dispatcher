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
use Derafu\BackboneDispatcher\Contract\ObjectFactoryInterface;
use Derafu\BackboneDispatcher\Service\Serialization\Serializer;
use Derafu\Value\ValueType;
use FilesystemIterator;
use libredte\lib\CoreDispatcher\Service\Deserializer\Billing\Document\DocumentEnvelopeDeserializer;
use libredte\lib\CoreDispatcher\Service\Deserializer\Billing\Exchange\ExchangeBagDeserializer;
use libredte\lib\CoreDispatcher\Service\Deserializer\Billing\Identifier\CafDeserializer;
use libredte\lib\CoreDispatcher\Service\Deserializer\Billing\TradingParties\EmisorDeserializer;
use libredte\lib\CoreDispatcher\Service\Deserializer\Billing\TradingParties\MandatarioDeserializer;
use libredte\lib\CoreDispatcher\Service\Deserializer\Billing\TradingParties\ReceptorDeserializer;
use libredte\lib\CoreDispatcher\Service\Deserializer\CertificateDeserializer;
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

/**
 * Prueba que lo que el dispatcher entrega como salida se puede volver a
 * recibir como entrada: `body -> deserialize -> serialize -> deserialize ->
 * serialize`, y las dos serializaciones deben ser iguales.
 *
 * Solo tiene fixtures lo que es un dato serializable (un objeto de valor).
 * Las bolsas que son el informe de un resultado, con claves derivadas y datos
 * de entrada que no se emiten, no tienen una ida y vuelta de identidad y no
 * se prueban aquí.
 *
 * El conjunto de casos lo define el directorio de fixtures, no esta clase: la
 * cobertura declara los deserializadores que esos casos ejercen.
 *
 * Cada fixture es un YAML en `tests/fixtures/roundtrip/**\/*.yaml`:
 *
 *   description: string
 *   body: <el dato exacto que enviaría un usuario: string o mapa; admite
 *          placeholders `{{método}}` de FixtureContext>
 *   test:
 *     type: string    # Interfaz (FQCN) que se deserializa.
 *     feed: string    # Opcional: ruta de la primera salida que se reenvía.
 *                     # Sin ella se reenvía la salida completa.
 *     expect: map     # Obligatorio: ruta => valor de la primera salida.
 *
 * `expect` es obligatorio porque la igualdad entre las dos serializaciones
 * sola no basta: si el deserializador ignorara un dato desde la primera
 * pasada, ambas salidas lo perderían por igual y serían iguales. `expect`
 * fija que la primera salida contiene lo que envió el usuario.
 */
#[CoversClass(CafDeserializer::class)]
#[CoversClass(CertificateDeserializer::class)]
#[CoversClass(DocumentEnvelopeDeserializer::class)]
#[CoversClass(EmisorDeserializer::class)]
#[CoversClass(ExchangeBagDeserializer::class)]
#[CoversClass(MandatarioDeserializer::class)]
#[CoversClass(ReceptorDeserializer::class)]
#[UsesNamespace('libredte\lib\CoreDispatcher\Service\Deserializer')]
class DeserializationRoundTripTest extends TestCase
{
    private static ObjectFactoryInterface $factory;

    private static FixturePlaceholderResolver $placeholders;

    public static function setUpBeforeClass(): void
    {
        self::$factory = FixtureObjectFactory::create();
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
        $baseDir = dirname(__DIR__) . '/fixtures/roundtrip';

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
        $test = $fixture->requireReader('test');
        $type = $test->requireString('type');

        try {
            $body = self::$placeholders->resolve(
                $fixture->require('body', ValueType::oneOf(ValueType::string(), ValueType::map()))
            );
        } catch (FixturePlaceholderUnavailableException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $serializer = new Serializer();

        $first = $serializer->serialize($this->deserialize($body, $type));
        $this->assertIsArray($first, 'The first serialization must be an array.');

        $this->assertExpected($test->requireMap('expect'), $first);

        $input = $first;
        $feed = $test->getString('feed');
        if ($feed !== null) {
            $input = ArrayReader::fromArray($first)->get($feed, ValueType::mixed());
            $this->assertNotNull($input, sprintf('The path "%s" to feed back is not in the first serialization.', $feed));
        }

        $second = $serializer->serialize($this->deserialize($input, $type));

        $this->assertSame(
            $first,
            $second,
            'The serialization of the deserialized output differs from the first one.'
        );
    }

    /**
     * Deserializa `$data` al `$type` con el `ObjectFactory` real.
     */
    private function deserialize(mixed $data, string $type): object
    {
        if (is_array($data)) {
            $data = ValueType::map()->parse($data, 'data', self::class);
        } else {
            $this->assertIsString($data, 'The data to deserialize must be a map or a string.');
        }

        $object = self::$factory->create($data, $type);
        $this->assertIsObject($object, sprintf('The data was not deserialized into an object of %s.', $type));

        return $object;
    }

    /**
     * Comprueba que cada ruta de `$expected` está en `$actual` con ese valor.
     *
     * @param array<string, mixed> $expected
     * @param array<array-key, mixed> $actual
     */
    private function assertExpected(array $expected, array $actual): void
    {
        $this->assertNotEmpty($expected, 'The fixture "test.expect" cannot be empty.');

        $reader = ArrayReader::fromArray($actual);

        foreach ($expected as $path => $value) {
            $this->assertTrue(
                $reader->hasValue($path),
                sprintf('Missing "%s" in the first serialization.', $path)
            );
            $this->assertSame(
                $value,
                $reader->get($path, ValueType::mixed()),
                sprintf('Value at "%s" does not match.', $path)
            );
        }
    }
}
