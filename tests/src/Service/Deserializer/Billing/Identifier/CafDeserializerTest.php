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
}
