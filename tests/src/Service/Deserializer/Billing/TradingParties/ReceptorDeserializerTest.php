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
use libredte\lib\Core\Package\Billing\Component\TradingParties\Contract\ReceptorInterface;
use libredte\lib\Core\Package\Billing\Component\TradingParties\Factory\ReceptorFactory;
use libredte\lib\CoreDispatcher\Service\Deserializer\Billing\TradingParties\ReceptorDeserializer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReceptorDeserializer::class)]
class ReceptorDeserializerTest extends TestCase
{
    private ReceptorDeserializer $deserializer;

    protected function setUp(): void
    {
        $this->deserializer = new ReceptorDeserializer(new ReceptorFactory());
    }

    public function testDeserializesArrayDataIntoARealReceptor(): void
    {
        $receptor = $this->deserializer->deserialize([
            'rut' => '66666666-6',
            'razon_social' => 'Cliente de Prueba',
        ], ReceptorInterface::class);

        $this->assertInstanceOf(ReceptorInterface::class, $receptor);
        $this->assertSame('Cliente de Prueba', $receptor->getRazonSocial());
    }

    public function testRejectsNonArrayData(): void
    {
        $this->expectException(UnsupportedDataTypeException::class);

        $this->deserializer->deserialize('not-an-array', ReceptorInterface::class);
    }
}
