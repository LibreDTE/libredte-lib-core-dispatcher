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
use Derafu\BackboneDispatcher\Contract\SafeDispatcherInterface;
use Derafu\BackboneDispatcher\Contract\SafeExplorerInterface;
use Derafu\BackboneDispatcher\ValueObject\OperationRequest;
use libredte\lib\Core\Application;
use libredte\lib\Core\Package\Billing\Component\Document\Exception\DocumentException;
use libredte\lib\Core\Package\Billing\Component\Identifier\Contract\CafFakerWorkerInterface;
use libredte\lib\Core\Package\Billing\Component\TradingParties\Entity\Emisor;
use libredte\lib\CoreDispatcher\Bootstrap;
use libredte\lib\CoreDispatcher\Service\Deserializer\Billing\Book\BookBagDeserializer;
use libredte\lib\CoreDispatcher\Service\Deserializer\Billing\Document\DocumentBagDeserializer;
use libredte\lib\CoreDispatcher\Service\Deserializer\Billing\Document\DocumentBatchDeserializer;
use libredte\lib\CoreDispatcher\Service\Deserializer\Billing\Document\DocumentEnvelopeDeserializer;
use libredte\lib\CoreDispatcher\Service\Deserializer\Billing\Exchange\ExchangeDocumentBagDeserializer;
use libredte\lib\CoreDispatcher\Service\Deserializer\Billing\Identifier\CafDeserializer;
use libredte\lib\CoreDispatcher\Service\Deserializer\Billing\Integration\SiiRequestDeserializer;
use libredte\lib\CoreDispatcher\Service\Deserializer\Billing\OwnershipTransfer\AecBagDeserializer;
use libredte\lib\CoreDispatcher\Service\Deserializer\Billing\TradingParties\EmisorDeserializer;
use libredte\lib\CoreDispatcher\Service\Deserializer\Billing\TradingParties\MandatarioDeserializer;
use libredte\lib\CoreDispatcher\Service\Deserializer\Billing\TradingParties\ReceptorDeserializer;
use libredte\lib\CoreDispatcher\Service\Deserializer\CertificateDeserializer;
use libredte\lib\CoreDispatcher\Service\Deserializer\XmlDocumentDeserializer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Test de integración de punta a punta: inicializa la
 * `libredte\lib\Core\Application` real (su propio `services.yaml`
 * incluido, importado por el `config/services.yaml` de este paquete) y
 * ejercita la cadena de dispatch completa y real que cablea
 * `Bootstrap::boot()` — sin mocks en ningún punto de la cadena.
 *
 * Cada Deserializer está declarado con `#[UsesClass]` acá: `Bootstrap::boot()`
 * los construye todos de antemano para poblar el `ObjectFactoryRegistry`,
 * así que sus constructores se ejecutan sin importar qué operación se
 * despache.
 */
#[CoversClass(Bootstrap::class)]
#[UsesClass(AecBagDeserializer::class)]
#[UsesClass(BookBagDeserializer::class)]
#[UsesClass(CafDeserializer::class)]
#[UsesClass(CertificateDeserializer::class)]
#[UsesClass(DocumentBagDeserializer::class)]
#[UsesClass(DocumentEnvelopeDeserializer::class)]
#[UsesClass(EmisorDeserializer::class)]
#[UsesClass(ExchangeDocumentBagDeserializer::class)]
#[UsesClass(MandatarioDeserializer::class)]
#[UsesClass(ReceptorDeserializer::class)]
#[UsesClass(SiiRequestDeserializer::class)]
#[UsesClass(XmlDocumentDeserializer::class)]
#[UsesClass(DocumentBatchDeserializer::class)]
class BootstrapTest extends TestCase
{
    private SafeDispatcherInterface $dispatcher;

    protected function setUp(): void
    {
        $this->dispatcher = Bootstrap::boot('test', true);
    }

    public function testBootReturnsASafeDispatcher(): void
    {
        $this->assertInstanceOf(SafeDispatcherInterface::class, $this->dispatcher);
    }

    public function testDispatchesARealOperationThroughTheFullChainAndSerializesTheResult(): void
    {
        $cafFakerWorker = Application::getInstance('test', true)
            ->getPackageRegistry()
            ->getPackage('billing')
            ->getComponent('identifier')
            ->getWorker('caf_faker')
        ;
        assert($cafFakerWorker instanceof CafFakerWorkerInterface);

        $fakeCaf = $cafFakerWorker->create(new Emisor('76192083-9'), 46, 1, 100);

        $request = new OperationRequest(
            'billing',
            'identifier',
            'caf_loader',
            'load',
            ['xml' => base64_encode($fakeCaf->getXml())],
        );

        $result = $this->dispatcher->dispatch($request);

        $this->assertTrue($result->isSuccess());
        $this->assertIsArray($result->getValue());
        $this->assertSame(46, $result->getValue()['tipoDocumento']);
    }

    public function testNeverThrowsAndReturnsAProblemDetailForAnUnknownOperation(): void
    {
        $request = OperationRequest::fromId('unknown_package.unknown_component.unknown_worker::operation');

        $result = $this->dispatcher->dispatch($request);

        $this->assertFalse($result->isSuccess());
        $this->assertNotNull($result->getProblem());
    }

    /**
     * `Derafu\BackboneDispatcher\Contract\ObjectFactoryInterface` está
     * registrado `lazy: true` en config/services.yaml precisamente porque
     * `DocumentBagDeserializer`/`SiiRequestDeserializer` lo necesitan de
     * vuelta (para deserializar sus propios campos anidados) a la vez que
     * son entradas de su propio mapa $deserializers — una referencia
     * circular. `billing.document.dispatcher:create` es la única operación
     * de esta suite cuyo parámetro es `DocumentBagInterface` en sí, así que
     * es la que realmente dereferencia ese proxy lazy por primera vez.
     *
     * Si ese cableado estuviera roto (por ejemplo, si se quitara el flag
     * `lazy: true`), esto fallaría con un error de referencia circular del
     * contenedor antes de siquiera llegar a las aserciones de abajo. Llegar
     * en cambio a la falla de nivel de negocio —un rechazo por regla de
     * negocio, no relacionado al cableado— demuestra que el CAF/Emisor/
     * Receptor se deserializaron todos correctamente primero.
     */
    public function testResolvesARecursivelyDeserializedDocumentBagThroughTheLazyObjectFactoryRegistry(): void
    {
        $cafFakerWorker = Application::getInstance('test', true)
            ->getPackageRegistry()
            ->getPackage('billing')
            ->getComponent('identifier')
            ->getWorker('caf_faker')
        ;
        assert($cafFakerWorker instanceof CafFakerWorkerInterface);

        $fakeCaf = $cafFakerWorker->create(new Emisor('76192083-9'), 33, 1, 100);

        $request = new OperationRequest('billing', 'document', 'dispatcher', 'create', [
            'bag' => [
                'caf' => base64_encode($fakeCaf->getXml()),
                'emisor' => ['rut' => '76192083-9', 'razon_social' => 'SASCO SpA'],
                'receptor' => ['rut' => '66666666-6', 'razon_social' => 'Cliente de prueba'],
            ],
        ]);

        $result = $this->dispatcher->dispatch($request);

        $this->assertFalse($result->isSuccess());
        $problem = $result->getProblem();
        $this->assertNotNull($problem);
        $this->assertSame(DocumentException::class, $problem->toArray()['title']);
    }

    public function testBootExplorerReturnsASafeExplorer(): void
    {
        $explorer = Bootstrap::bootExplorer('test', true);

        $this->assertInstanceOf(SafeExplorerInterface::class, $explorer);
    }

    public function testBootExplorerListsTheRealBillingPackage(): void
    {
        $explorer = Bootstrap::bootExplorer('test', true);

        $result = $explorer->getPackage('billing');

        $this->assertTrue($result->isSuccess());
        $this->assertSame('billing', ArrayReader::fromValue($result->getValue())->requireString('id'));
    }

    public function testBootExplorerNeverThrowsAndReturnsAProblemForAnUnknownPackage(): void
    {
        $explorer = Bootstrap::bootExplorer('test', true);

        $result = $explorer->getComponents('unknown_package');

        $this->assertFalse($result->isSuccess());
        $this->assertNotNull($result->getProblem());
    }
}
