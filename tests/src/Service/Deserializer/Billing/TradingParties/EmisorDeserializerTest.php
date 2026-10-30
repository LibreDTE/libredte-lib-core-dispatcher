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
