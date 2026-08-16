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

namespace libredte\lib\TestsCoreDispatcher\Service\Deserializer\Billing\Document;

use Derafu\BackboneDispatcher\Service\FromArrayDeserializer;
use Derafu\BackboneDispatcher\Service\ObjectFactoryRegistry;
use Derafu\Certificate\Contract\CertificateInterface;
use Derafu\Certificate\Service\CertificateLoader;
use Derafu\Xml\Contract\XmlDocumentInterface;
use InvalidArgumentException;
use libredte\lib\Core\Application;
use libredte\lib\Core\Package\Billing\Component\Document\Contract\DocumentBagInterface;
use libredte\lib\Core\Package\Billing\Component\Identifier\Contract\CafFakerWorkerInterface;
use libredte\lib\Core\Package\Billing\Component\Identifier\Contract\CafInterface;
use libredte\lib\Core\Package\Billing\Component\Identifier\Contract\CafLoaderWorkerInterface;
use libredte\lib\Core\Package\Billing\Component\TradingParties\Contract\EmisorInterface;
use libredte\lib\Core\Package\Billing\Component\TradingParties\Contract\ReceptorInterface;
use libredte\lib\Core\Package\Billing\Component\TradingParties\Entity\Emisor;
use libredte\lib\Core\Package\Billing\Component\TradingParties\Factory\EmisorFactory;
use libredte\lib\Core\Package\Billing\Component\TradingParties\Factory\ReceptorFactory;
use libredte\lib\CoreDispatcher\Service\Deserializer\Billing\Document\DocumentBagDeserializer;
use libredte\lib\CoreDispatcher\Service\Deserializer\Billing\Identifier\CafDeserializer;
use libredte\lib\CoreDispatcher\Service\Deserializer\Billing\TradingParties\EmisorDeserializer;
use libredte\lib\CoreDispatcher\Service\Deserializer\Billing\TradingParties\ReceptorDeserializer;
use libredte\lib\CoreDispatcher\Service\Deserializer\CertificateDeserializer;
use libredte\lib\CoreDispatcher\Service\Deserializer\XmlDocumentDeserializer;
use libredte\lib\TestsCoreDispatcher\Fixture\CertificateFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DocumentBagDeserializer::class)]
#[UsesClass(CafDeserializer::class)]
#[UsesClass(CertificateDeserializer::class)]
#[UsesClass(EmisorDeserializer::class)]
#[UsesClass(ReceptorDeserializer::class)]
#[UsesClass(XmlDocumentDeserializer::class)]
class DocumentBagDeserializerTest extends TestCase
{
    private DocumentBagDeserializer $deserializer;

    protected function setUp(): void
    {
        $packageRegistry = Application::getInstance('test', true)->getPackageRegistry();

        $cafLoaderWorker = $packageRegistry
            ->getPackage('billing')
            ->getComponent('identifier')
            ->getWorker('caf_loader')
        ;
        assert($cafLoaderWorker instanceof CafLoaderWorkerInterface);

        $objectFactory = new ObjectFactoryRegistry(
            deserializers: [
                XmlDocumentInterface::class => new XmlDocumentDeserializer(),
                CafInterface::class => new CafDeserializer($cafLoaderWorker),
                CertificateInterface::class => new CertificateDeserializer(new CertificateLoader()),
                EmisorInterface::class => new EmisorDeserializer(new EmisorFactory()),
                ReceptorInterface::class => new ReceptorDeserializer(new ReceptorFactory()),
            ],
            fallback: new FromArrayDeserializer(),
        );

        $this->deserializer = new DocumentBagDeserializer($objectFactory);
    }

    public function testDeserializesArrayDataRecursivelyDeserializingItsNestedFields(): void
    {
        $cafFakerWorker = Application::getInstance('test', true)
            ->getPackageRegistry()
            ->getPackage('billing')
            ->getComponent('identifier')
            ->getWorker('caf_faker')
        ;
        assert($cafFakerWorker instanceof CafFakerWorkerInterface);

        $fakeCaf = $cafFakerWorker->create(new Emisor('76192083-9'), 33, 1, 100);
        $pair = CertificateFixture::generate();

        $bag = $this->deserializer->deserialize([
            'caf' => base64_encode($fakeCaf->getXml()),
            'certificate' => [
                'certificate' => $pair['certificate'],
                'privateKey' => $pair['privateKey'],
            ],
            'emisor' => ['rut' => '76192083-9', 'razon_social' => 'SASCO SpA'],
            'receptor' => ['rut' => '66666666-6', 'razon_social' => 'Cliente de Prueba'],
        ], DocumentBagInterface::class);

        $this->assertInstanceOf(DocumentBagInterface::class, $bag);
        $this->assertInstanceOf(CafInterface::class, $bag->getCaf());
        $this->assertInstanceOf(CertificateInterface::class, $bag->getCertificate());
        $this->assertInstanceOf(EmisorInterface::class, $bag->getEmisor());
        $this->assertInstanceOf(ReceptorInterface::class, $bag->getReceptor());
        $this->assertNull($bag->getXmlDocument());
    }

    public function testRejectsNonArrayData(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->deserializer->deserialize('not-an-array', DocumentBagInterface::class);
    }
}
