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

namespace libredte\lib\TestsCoreDispatcher\Service\Deserializer\Billing\Document;

use Derafu\BackboneDispatcher\Exception\UnsupportedDataTypeException;
use Derafu\BackboneDispatcher\Service\Deserialization\FromArrayDeserializer;
use Derafu\BackboneDispatcher\Service\Deserialization\ObjectFactoryRegistry;
use Derafu\Certificate\Contract\CertificateInterface;
use Derafu\Certificate\Service\CertificateLoader;
use Derafu\Xml\Contract\XmlDocumentInterface;
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
        $this->expectException(UnsupportedDataTypeException::class);

        $this->deserializer->deserialize('not-an-array', DocumentBagInterface::class);
    }

    public function testDecodesAStringInputDataFromBase64(): void
    {
        $xml = mb_convert_encoding(
            '<?xml version="1.0" encoding="ISO-8859-1"?><DTE>Tecnología</DTE>',
            'ISO-8859-1',
            'UTF-8'
        );

        $bag = $this->deserializer->deserialize([
            'inputData' => base64_encode($xml),
        ], DocumentBagInterface::class);

        $this->assertSame($xml, $bag->getInputData());
    }

    public function testKeepsAnArrayInputDataAsJson(): void
    {
        $data = ['Encabezado' => ['IdDoc' => ['TipoDTE' => 33]]];

        $bag = $this->deserializer->deserialize([
            'inputData' => $data,
        ], DocumentBagInterface::class);

        $this->assertSame(json_encode($data), $bag->getInputData());
    }

    public function testInputDataIsOptional(): void
    {
        $bag = $this->deserializer->deserialize([
            'parsedData' => ['Encabezado' => []],
        ], DocumentBagInterface::class);

        $this->assertNull($bag->getInputData());
    }

    public function testRejectsAStringInputDataThatIsNotValidBase64(): void
    {
        $this->expectException(UnsupportedDataTypeException::class);
        $this->expectExceptionMessage('requiere el campo inputData codificado en base64 válido');

        $this->deserializer->deserialize([
            'inputData' => '<?xml version="1.0"?><DTE/>',
        ], DocumentBagInterface::class);
    }

    public function testRejectsAnInputDataThatIsNeitherAnArrayNorAString(): void
    {
        $this->expectException(UnsupportedDataTypeException::class);

        $this->deserializer->deserialize([
            'inputData' => 123,
        ], DocumentBagInterface::class);
    }
}
