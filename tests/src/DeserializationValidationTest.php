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

use Derafu\Array\Exception\ArrayException;
use Derafu\BackboneDispatcher\Contract\ObjectFactoryInterface;
use Derafu\Certificate\Contract\CertificateInterface;
use Derafu\Config\Options;
use Derafu\Kernel\Environment;
use libredte\lib\Core\Application;
use libredte\lib\Core\Package\Billing\Component\Book\Contract\BookBagInterface;
use libredte\lib\Core\Package\Billing\Component\Document\Contract\DocumentBagInterface;
use libredte\lib\Core\Package\Billing\Component\Document\Contract\DocumentBatchInterface;
use libredte\lib\Core\Package\Billing\Component\Exchange\Contract\ExchangeBagInterface;
use libredte\lib\Core\Package\Billing\Component\Exchange\Enum\DocumentType;
use libredte\lib\Core\Package\Billing\Component\Exchange\Enum\ProcessType;
use libredte\lib\Core\Package\Billing\Component\Exchange\Support\ExchangeDocumentBag;
use libredte\lib\Core\Package\Billing\Component\Integration\Contract\SiiRequestInterface;
use libredte\lib\Core\Package\Billing\Component\OwnershipTransfer\Support\AecBag;
use libredte\lib\CoreDispatcher\Service\Deserializer\Billing\Book\BookBagDeserializer;
use libredte\lib\CoreDispatcher\Service\Deserializer\Billing\Document\DocumentBagDeserializer;
use libredte\lib\CoreDispatcher\Service\Deserializer\Billing\Document\DocumentBatchDeserializer;
use libredte\lib\CoreDispatcher\Service\Deserializer\Billing\Exchange\ExchangeBagDeserializer;
use libredte\lib\CoreDispatcher\Service\Deserializer\Billing\Exchange\ExchangeDocumentBagDeserializer;
use libredte\lib\CoreDispatcher\Service\Deserializer\Billing\Integration\SiiRequestDeserializer;
use libredte\lib\CoreDispatcher\Service\Deserializer\Billing\OwnershipTransfer\AecBagDeserializer;
use libredte\lib\CoreDispatcher\Service\Deserializer\CertificateDeserializer;
use libredte\lib\TestsCoreDispatcher\Fixture\FixtureContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesNamespace;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Valida entradas reales a través del registro configurado en el YAML público.
 */
#[CoversClass(BookBagDeserializer::class)]
#[CoversClass(DocumentBagDeserializer::class)]
#[CoversClass(DocumentBatchDeserializer::class)]
#[CoversClass(ExchangeBagDeserializer::class)]
#[CoversClass(ExchangeDocumentBagDeserializer::class)]
#[CoversClass(SiiRequestDeserializer::class)]
#[CoversClass(AecBagDeserializer::class)]
#[CoversClass(CertificateDeserializer::class)]
#[UsesNamespace('libredte\lib\CoreDispatcher')]
final class DeserializationValidationTest extends TestCase
{
    protected function tearDown(): void
    {
        (new Filesystem())->remove(dirname(__DIR__) . '/fixtures/services/var');
    }

    /**
     * @return iterable<string, array{class-string, array<string, mixed>, string}>
     */
    public static function invalidFields(): iterable
    {
        yield 'parsed document data' => [DocumentBagInterface::class, ['parsedData' => 'invalid'], 'parsedData'];
        yield 'document options' => [DocumentBagInterface::class, ['options' => 'invalid'], 'options'];
        yield 'nested emitter' => [DocumentBagInterface::class, ['emisor' => 123], 'emisor'];
        yield 'batch options' => [DocumentBatchInterface::class, ['options' => 'invalid'], 'options'];
        yield 'book header' => [BookBagInterface::class, ['tipo' => 'libro_ventas', 'caratula' => 'invalid'], 'caratula'];
        yield 'book row' => [BookBagInterface::class, ['tipo' => 'libro_ventas', 'detalle' => [5 => 'invalid']], 'detalle.5'];
        yield 'exchange envelopes' => [ExchangeBagInterface::class, ['envelopes' => 'invalid'], 'envelopes'];
        yield 'exchange response rows' => [ExchangeDocumentBag::class, ['tipo' => 'envio_recibos', 'data' => 'invalid'], 'data'];
        yield 'sii request options' => [SiiRequestInterface::class, ['options' => 'invalid'], 'options'];
        yield 'certificate password' => [CertificateInterface::class, ['data' => base64_encode('pfx'), 'password' => ['invalid']], 'password'];
        yield 'certificate pem' => [CertificateInterface::class, ['certificate' => ['invalid'], 'privateKey' => 'pem'], 'certificate'];
        yield 'private key pem' => [CertificateInterface::class, ['certificate' => 'pem', 'privateKey' => ['invalid']], 'privateKey'];
        yield 'exchange document collection' => [ExchangeBagInterface::class, ['envelopes' => [[
            'sender' => ['identifier' => ['value' => '76192083-9']],
            'receiver' => ['identifier' => ['value' => '88888888-8']],
            'documents' => ['invalid'],
        ]]], 'documents.0'];
    }

    /**
     * @param class-string $class Tipo solicitado al registro.
     * @param array<string, mixed> $data Datos con un campo de tipo incorrecto.
     */
    #[DataProvider('invalidFields')]
    public function testRejectsIncorrectFieldTypesWithTheirPath(string $class, array $data, string $path): void
    {
        $factory = $this->factory();
        $this->expectException(ArrayException::class);
        $this->expectExceptionMessage($path);
        $factory->create($data, $class);
    }

    public function testOptionalFieldsAndOptionsObjectsKeepTheirMeaning(): void
    {
        $factory = $this->factory();
        $options = new Options(['consumer' => 'preserved']);
        $bag = $factory->create(['parsedData' => null, 'options' => $options], DocumentBagInterface::class);
        self::assertInstanceOf(DocumentBagInterface::class, $bag);
        self::assertNull($bag->getParsedData());
        self::assertSame($options, $bag->getOptions());

        $book = $factory->create(['tipo' => 'libro_ventas', 'inputData' => null, 'detalle' => [5 => ['TpoDoc' => 33]]], BookBagInterface::class);
        self::assertInstanceOf(BookBagInterface::class, $book);
        self::assertSame([], $book->getInputData());
        self::assertSame([5 => ['TpoDoc' => 33]], $book->getDetalle());

        $response = $factory->create(['tipo' => 'envio_recibos', 'data' => [['Folio' => 1]]], ExchangeDocumentBag::class);
        self::assertInstanceOf(ExchangeDocumentBag::class, $response);
        self::assertSame([['Folio' => 1]], $response->getData());
        self::assertNull($response->getCertificate());
    }

    public function testExchangeReconstructsBinaryDocumentsAndAttachmentsWithSparseKeys(): void
    {
        $data = [
            'envelopes' => [7 => [
                'sender' => ['identifier' => ['value' => '76192083-9'], 'endpoints' => [4 => ['value' => 'sender@example.com']]],
                'receiver' => ['identifier' => ['value' => '88888888-8']],
                'documentType' => DocumentType::B2B->value,
                'process' => ProcessType::BILLING->value,
                'businessMessageID' => 'message-123',
                'originalBusinessMessageID' => 'original-123',
                'creationDateAndTime' => '2026-10-29T12:34:56+00:00',
                'documents' => [5 => [
                    'content' => base64_encode("<document>\x00</document>"),
                    'type' => DocumentType::B2B->value,
                    'metadata' => ['document' => 'preserved'],
                    'attachments' => [3 => ['data' => base64_encode("attachment\x00"), 'name' => 'attachment.txt', 'type' => 'text/plain']],
                ]],
                'metadata' => ['envelope' => 'preserved'],
            ]],
            'results' => ['ignored previous result'],
        ];
        $bag = $this->factory()->create($data, ExchangeBagInterface::class);
        self::assertInstanceOf(ExchangeBagInterface::class, $bag);
        self::assertCount(1, $bag->getEnvelopes());
        $envelope = $bag->getEnvelopes()[0];
        self::assertSame('message-123', $envelope->getBusinessMessageID());
        self::assertSame('original-123', $envelope->getOriginalBusinessMessageID());
        self::assertSame('2026-10-29T12:34:56+00:00', $envelope->getCreationDateAndTime()->format(DATE_ATOM));
        self::assertSame('76192083-9', $envelope->getSender()->getIdentifier()->getValue());
        self::assertSame('sender@example.com', $envelope->getSender()->getEmails()[0]->getAddress());
        self::assertSame('preserved', $envelope->getMetadata()->get('envelope'));
        $document = $envelope->getDocuments()[0];
        self::assertSame("<document>\x00</document>", $document->getContent());
        self::assertSame('preserved', $document->getMetadata()->get('document'));
        self::assertSame("attachment\x00", $document->getAttachments()[3]->toArray()['data']);
        self::assertSame([], $bag->getResults());
    }

    public function testExchangeDefaultsArePreserved(): void
    {
        $bag = $this->factory()->create(['envelopes' => [[
            'sender' => ['identifier' => ['value' => '76192083-9']],
            'receiver' => ['identifier' => ['value' => '88888888-8']],
            'documents' => [[]],
        ]]], ExchangeBagInterface::class);
        self::assertInstanceOf(ExchangeBagInterface::class, $bag);
        $envelope = $bag->getEnvelopes()[0];
        self::assertSame(DocumentType::B2B, $envelope->getDocumentType());
        self::assertSame(ProcessType::BILLING, $envelope->getProcess());
        self::assertNull($envelope->getOriginalBusinessMessageID());
        self::assertSame('', $envelope->getDocuments()[0]->getContent());
        self::assertSame([], $envelope->getDocuments()[0]->getAttachments());
    }

    public function testAssignmentSourceAcceptsBothARealDocumentAndARealAec(): void
    {
        $factory = $this->factory();
        $context = new FixtureContext();
        $fields = ['cedente' => [], 'cesionario' => [], 'cesion' => []];
        $first = $factory->create(['source' => $context->facturaAfectaXml()] + $fields, AecBag::class);
        self::assertInstanceOf(AecBag::class, $first);
        self::assertFalse($first->isRecesion());
        self::assertNull($first->getCertificate());
        $second = $factory->create(['source' => $context->aecXml()] + $fields, AecBag::class);
        self::assertInstanceOf(AecBag::class, $second);
        self::assertTrue($second->isRecesion());
    }

    private function factory(): ObjectFactoryInterface
    {
        $application = new Application(new Environment('test', true, [
            'PROJECT_DIR' => dirname(__DIR__) . '/fixtures/services',
        ]));
        $factory = $application->getService('test.object_factory');
        self::assertInstanceOf(ObjectFactoryInterface::class, $factory);

        return $factory;
    }
}
