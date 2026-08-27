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

namespace libredte\lib\TestsCoreDispatcher\Service\Deserializer\Billing\TradingParties;

use Derafu\BackboneDispatcher\Exception\UnsupportedDataTypeException;
use libredte\lib\Core\Package\Billing\Component\TradingParties\Contract\EmisorInterface;
use libredte\lib\Core\Package\Billing\Component\TradingParties\Factory\EmisorFactory;
use libredte\lib\CoreDispatcher\Service\Deserializer\Billing\TradingParties\EmisorDeserializer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EmisorDeserializer::class)]
class EmisorDeserializerTest extends TestCase
{
    private EmisorDeserializer $deserializer;

    protected function setUp(): void
    {
        $this->deserializer = new EmisorDeserializer(new EmisorFactory());
    }

    public function testDeserializesArrayDataIntoARealEmisor(): void
    {
        $emisor = $this->deserializer->deserialize([
            'rut' => '76192083-9',
            'razon_social' => 'SASCO SpA',
            'giro' => 'Desarrollo de software',
        ], EmisorInterface::class);

        $this->assertInstanceOf(EmisorInterface::class, $emisor);
        $this->assertSame('SASCO SpA', $emisor->getRazonSocial());
    }

    public function testRejectsNonArrayData(): void
    {
        $this->expectException(UnsupportedDataTypeException::class);

        $this->deserializer->deserialize('not-an-array', EmisorInterface::class);
    }
}
