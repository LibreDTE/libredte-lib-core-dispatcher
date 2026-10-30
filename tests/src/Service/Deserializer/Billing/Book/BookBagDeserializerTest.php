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

namespace libredte\lib\TestsCoreDispatcher\Service\Deserializer\Billing\Book;

use Derafu\BackboneDispatcher\Exception\UnsupportedDataTypeException;
use Derafu\BackboneDispatcher\Service\Deserialization\FromArrayDeserializer;
use Derafu\BackboneDispatcher\Service\Deserialization\ObjectFactoryRegistry;
use libredte\lib\Core\Package\Billing\Component\Book\Contract\BookBagInterface;
use libredte\lib\CoreDispatcher\Service\Deserializer\Billing\Book\BookBagDeserializer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BookBagDeserializer::class)]
class BookBagDeserializerTest extends TestCase
{
    private BookBagDeserializer $deserializer;

    protected function setUp(): void
    {
        $objectFactory = new ObjectFactoryRegistry(
            deserializers: [],
            fallback: new FromArrayDeserializer(),
        );

        $this->deserializer = new BookBagDeserializer($objectFactory);
    }

    public function testDecodesAStringInputDataFromBase64(): void
    {
        $csv = mb_convert_encoding("Fecha;Total\n2025-01-01;1000\nAsesoría;1\n", 'ISO-8859-1', 'UTF-8');

        $bag = $this->deserializer->deserialize([
            'tipo' => 'libro_ventas',
            'inputData' => base64_encode($csv),
        ], BookBagInterface::class);

        $this->assertSame($csv, $bag->getInputData());
    }

    public function testKeepsAnArrayInputData(): void
    {
        $bag = $this->deserializer->deserialize([
            'tipo' => 'libro_ventas',
            'inputData' => ['a' => 1],
        ], BookBagInterface::class);

        $this->assertSame(['a' => 1], $bag->getInputData());
    }

    public function testInputDataDefaultsToAnEmptyArray(): void
    {
        $bag = $this->deserializer->deserialize([
            'tipo' => 'libro_ventas',
        ], BookBagInterface::class);

        $this->assertSame([], $bag->getInputData());
    }

    public function testRejectsAStringInputDataThatIsNotValidBase64(): void
    {
        $this->expectException(UnsupportedDataTypeException::class);
        $this->expectExceptionMessage('requiere el campo inputData codificado en base64 válido');

        $this->deserializer->deserialize([
            'tipo' => 'libro_ventas',
            'inputData' => 'Fecha;Total',
        ], BookBagInterface::class);
    }
}
