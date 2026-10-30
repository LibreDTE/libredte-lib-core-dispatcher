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
use libredte\lib\Core\Package\Billing\Component\Document\Contract\DocumentBatchInterface;
use libredte\lib\Core\Package\Billing\Component\Document\Exception\BatchProcessorException;
use libredte\lib\Core\Package\Billing\Component\TradingParties\Contract\EmisorInterface;
use libredte\lib\Core\Package\Billing\Component\TradingParties\Factory\EmisorFactory;
use libredte\lib\CoreDispatcher\Service\Deserializer\Billing\Document\DocumentBatchDeserializer;
use libredte\lib\CoreDispatcher\Service\Deserializer\Billing\TradingParties\EmisorDeserializer;
use libredte\lib\CoreDispatcher\Service\Deserializer\CertificateDeserializer;
use libredte\lib\TestsCoreDispatcher\Fixture\CertificateFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DocumentBatchDeserializer::class)]
#[UsesClass(CertificateDeserializer::class)]
#[UsesClass(EmisorDeserializer::class)]
class DocumentBatchDeserializerTest extends TestCase
{
    private DocumentBatchDeserializer $deserializer;

    protected function setUp(): void
    {
        $objectFactory = new ObjectFactoryRegistry(
            deserializers: [
                CertificateInterface::class => new CertificateDeserializer(new CertificateLoader()),
                EmisorInterface::class => new EmisorDeserializer(new EmisorFactory()),
            ],
            fallback: new FromArrayDeserializer(),
        );

        $this->deserializer = new DocumentBatchDeserializer($objectFactory);
    }

    public function testDeserializesTheBase64ContentAndTheOptionalNestedFields(): void
    {
        $content = "TipoDTE;Folio\n33;1\n";
        $pair = CertificateFixture::generate();

        $batch = $this->deserializer->deserialize([
            'inputData' => base64_encode($content),
            'emisor' => ['rut' => '76192083-9', 'razon_social' => 'SASCO SpA'],
            'certificate' => [
                'certificate' => $pair['certificate'],
                'privateKey' => $pair['privateKey'],
            ],
            'options' => ['batch_processor' => ['strategy' => 'spreadsheet.csv']],
        ], DocumentBatchInterface::class);

        $this->assertInstanceOf(DocumentBatchInterface::class, $batch);
        $this->assertSame($content, $batch->getInputData());
        $this->assertNull($batch->getInputFile());
        $this->assertInstanceOf(EmisorInterface::class, $batch->getEmisor());
        $this->assertInstanceOf(CertificateInterface::class, $batch->getCertificate());
        $this->assertSame(
            ['strategy' => 'spreadsheet.csv'],
            $batch->getBatchProcessorOptions()
        );
    }

    public function testEmisorAndCertificateAreOptional(): void
    {
        $batch = $this->deserializer->deserialize([
            'inputData' => base64_encode('contenido'),
        ], DocumentBatchInterface::class);

        $this->assertInstanceOf(DocumentBatchInterface::class, $batch);
        $this->assertNull($batch->getEmisor());
        $this->assertNull($batch->getCertificate());
    }

    public function testDecodesBinaryContent(): void
    {
        $binary = "\x00\x01\x02PK\xff\xfe";

        $batch = $this->deserializer->deserialize([
            'inputData' => base64_encode($binary),
        ], DocumentBatchInterface::class);

        $this->assertSame($binary, $batch->getInputData());
    }

    public function testIgnoresTheInputFileKey(): void
    {
        $batch = $this->deserializer->deserialize([
            'inputData' => base64_encode('contenido'),
            'inputFile' => '/etc/passwd',
        ], DocumentBatchInterface::class);

        $this->assertNull($batch->getInputFile());
        $this->assertSame('contenido', $batch->getInputData());
    }

    public function testDoesNotReadAFileWhenOnlyTheInputFileKeyIsGiven(): void
    {
        $this->expectException(BatchProcessorException::class);

        $this->deserializer->deserialize([
            'inputFile' => '/etc/passwd',
        ], DocumentBatchInterface::class);
    }

    public function testRejectsInputDataThatIsNotValidBase64(): void
    {
        $this->expectException(UnsupportedDataTypeException::class);

        $this->deserializer->deserialize([
            'inputData' => 'esto no es base64!',
        ], DocumentBatchInterface::class);
    }

    public function testRejectsInputDataThatIsNotAString(): void
    {
        $this->expectException(UnsupportedDataTypeException::class);

        $this->deserializer->deserialize([
            'inputData' => ['no' => 'string'],
        ], DocumentBatchInterface::class);
    }

    public function testRejectsNonArrayData(): void
    {
        $this->expectException(UnsupportedDataTypeException::class);

        $this->deserializer->deserialize('not-an-array', DocumentBatchInterface::class);
    }
}
