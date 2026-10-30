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

namespace libredte\lib\TestsCoreDispatcher\Service\Deserializer\Billing\Identifier;

use Derafu\BackboneDispatcher\Exception\UnsupportedDataTypeException;
use libredte\lib\Core\Application;
use libredte\lib\Core\Package\Billing\Component\Identifier\Contract\CafFakerWorkerInterface;
use libredte\lib\Core\Package\Billing\Component\Identifier\Contract\CafInterface;
use libredte\lib\Core\Package\Billing\Component\Identifier\Contract\CafLoaderWorkerInterface;
use libredte\lib\Core\Package\Billing\Component\TradingParties\Entity\Emisor;
use libredte\lib\CoreDispatcher\Service\Deserializer\Billing\Identifier\CafDeserializer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * El "CAF real generado" que se usa acá viene del propio
 * `CafFakerWorkerInterface` de `libredte-lib-core` (no un archivo fixture):
 * XML con apariencia de firmado real, sin fixtures externos, sin mocks — un
 * servicio genuino de `libredte-lib-core`, que a la vez es parte de lo que
 * este test verifica que siga cableado correctamente.
 */
#[CoversClass(CafDeserializer::class)]
class CafDeserializerTest extends TestCase
{
    private CafDeserializer $deserializer;

    protected function setUp(): void
    {
        $packageRegistry = Application::getInstance('test', true)->getPackageRegistry();

        $cafLoaderWorker = $packageRegistry
            ->getPackage('billing')
            ->getComponent('identifier')
            ->getWorker('caf_loader')
        ;
        assert($cafLoaderWorker instanceof CafLoaderWorkerInterface);

        $this->deserializer = new CafDeserializer($cafLoaderWorker);
    }

    public function testDeserializesABase64EncodedCafXmlIntoARealCaf(): void
    {
        $cafFakerWorker = Application::getInstance('test', true)
            ->getPackageRegistry()
            ->getPackage('billing')
            ->getComponent('identifier')
            ->getWorker('caf_faker')
        ;
        assert($cafFakerWorker instanceof CafFakerWorkerInterface);

        $fakeCaf = $cafFakerWorker->create(new Emisor('76192083-9'), 33, 1, 100);

        $caf = $this->deserializer->deserialize(
            base64_encode($fakeCaf->getXml()),
            CafInterface::class,
        );

        $this->assertInstanceOf(CafInterface::class, $caf);
        $this->assertSame(33, $caf->getTipoDocumento());
        $this->assertSame(1, $caf->getFolioDesde());
        $this->assertSame(100, $caf->getFolioHasta());
    }

    public function testRejectsNonStringData(): void
    {
        $this->expectException(UnsupportedDataTypeException::class);

        $this->deserializer->deserialize(['not' => 'a string'], CafInterface::class);
    }

    public function testRejectsDataThatIsNotValidBase64(): void
    {
        $this->expectException(UnsupportedDataTypeException::class);
        $this->expectExceptionMessage('requiere datos codificados en base64 válido');

        $this->deserializer->deserialize('<AUTORIZACION/>', CafInterface::class);
    }
}
