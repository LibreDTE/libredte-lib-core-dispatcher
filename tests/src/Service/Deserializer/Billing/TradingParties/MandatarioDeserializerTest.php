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
