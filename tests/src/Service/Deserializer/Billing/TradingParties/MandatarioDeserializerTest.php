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
use libredte\lib\Core\Package\Billing\Component\TradingParties\Contract\MandatarioInterface;
use libredte\lib\Core\Package\Billing\Component\TradingParties\Factory\MandatarioFactory;
use libredte\lib\CoreDispatcher\Service\Deserializer\Billing\TradingParties\MandatarioDeserializer;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MandatarioDeserializer::class)]
class MandatarioDeserializerTest extends TestCase
{
    private MandatarioDeserializer $deserializer;

    protected function setUp(): void
    {
        $this->deserializer = new MandatarioDeserializer(new MandatarioFactory());
    }

    public function testDeserializesArrayDataIntoARealMandatario(): void
    {
        $mandatario = $this->deserializer->deserialize([
            'run' => '11111111-1',
            'nombre' => 'Juan Pérez',
            'email' => 'juan@example.com',
        ], MandatarioInterface::class);

        $this->assertInstanceOf(MandatarioInterface::class, $mandatario);
        $this->assertSame('Juan Pérez', $mandatario->getNombre());
    }

    public function testRejectsNonArrayData(): void
    {
        $this->expectException(UnsupportedDataTypeException::class);

        $this->deserializer->deserialize('not-an-array', MandatarioInterface::class);
    }

    public function testPropagatesTheFactorysOwnValidationForMissingRequiredFields(): void
    {
        $this->expectException(LogicException::class);

        $this->deserializer->deserialize([], MandatarioInterface::class);
    }
}
