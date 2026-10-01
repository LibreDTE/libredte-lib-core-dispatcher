<?php

declare(strict_types=1);

/**
 * LibreDTE: Conector (Dispatcher) para la Biblioteca PHP (Core).
 * Copyright (C) LibreDTE <https://www.libredte.cl>
 *
 * Este programa es software libre: usted puede redistribuirlo y/o modificarlo
 * bajo los términos de la Licencia Pública General Affero de GNU publicada por
 * la Fundación para el Software Libre, ya sea la versión 3 de la Licencia, o
 * (a su elección) cualquier versión posterior de la misma.
 *
 * Este programa se distribuye con la esperanza de que sea útil, pero SIN
 * GARANTÍA ALGUNA; ni siquiera la garantía implícita MERCANTIL o de APTITUD
 * PARA UN PROPÓSITO DETERMINADO. Consulte los detalles de la Licencia Pública
 * General Affero de GNU para obtener una información más detallada.
 *
 * Debería haber recibido una copia de la Licencia Pública General Affero de
 * GNU junto a este programa.
 *
 * En caso contrario, consulte <http://www.gnu.org/licenses/agpl.html>.
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
