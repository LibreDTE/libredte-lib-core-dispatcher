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

namespace libredte\lib\CoreDispatcher\Service\Deserializer\Billing\Exchange;

use DateTimeImmutable;
use Derafu\Array\ArrayReader;
use Derafu\BackboneDispatcher\Abstract\AbstractDeserializer;
use Derafu\Config\Contract\OptionsInterface;
use Derafu\Container\Contract\BagInterface;
use Derafu\Value\ValueType;
use libredte\lib\Core\Package\Billing\Component\Exchange\Contract\PartyInterface;
use libredte\lib\Core\Package\Billing\Component\Exchange\Entity\PartyEndpoint;
use libredte\lib\Core\Package\Billing\Component\Exchange\Entity\PartyIdentifier;
use libredte\lib\Core\Package\Billing\Component\Exchange\Entity\Receiver;
use libredte\lib\Core\Package\Billing\Component\Exchange\Entity\Sender;
use libredte\lib\Core\Package\Billing\Component\Exchange\Enum\DocumentType;
use libredte\lib\Core\Package\Billing\Component\Exchange\Enum\ProcessType;
use libredte\lib\Core\Package\Billing\Component\Exchange\Support\Attachment;
use libredte\lib\Core\Package\Billing\Component\Exchange\Support\Document;
use libredte\lib\Core\Package\Billing\Component\Exchange\Support\Envelope;
use libredte\lib\Core\Package\Billing\Component\Exchange\Support\ExchangeBag;
use libredte\lib\CoreDispatcher\Service\Deserializer\Trait\Base64DecoderTrait;

/**
 * Construye un `ExchangeBag` a partir de datos de un arreglo, reconstruyendo
 * a mano todo el árbol de entidades (`Envelope`, `Sender`/`Receiver`,
 * `PartyIdentifier`/`PartyEndpoint`, `Document`, `Attachment`) — a
 * diferencia de otros deserializadores de este proyecto, no delega en
 * `ObjectFactoryInterface` porque ninguna de esas entidades tiene más de
 * una forma de wire posible (no son uniones como `CertificateInterface` o
 * `XmlDocumentInterface`).
 *
 * La forma esperada de cada campo es la misma que produce
 * `ExchangeBag::jsonSerialize()` (no `toArray()`): `content` de cada
 * `Document` y `data` de cada `Attachment` llegan en base64, igual que el
 * resto de los campos XML/binarios en todo este proyecto.
 *
 * `results` (si viniera en el arreglo) se ignora deliberadamente: representa
 * resultados de un intercambio ya ocurrido, no un dato de entrada válido
 * para `receive()`/`send()` — `ExchangeBag::toArray()`/`jsonSerialize()`
 * exportan el estado completo del objeto (incluye `results`), pero un
 * deserializador solo necesita reconstruir lo que tiene sentido como
 * entrada de una operación nueva.
 */
class ExchangeBagDeserializer extends AbstractDeserializer
{
    use Base64DecoderTrait;

    /**
     * {@inheritDoc}
     *
     * @return ExchangeBag
     */
    public function deserialize(array|string $data, string $class): object
    {
        $data = $this->assertArray($data);

        $reader = ArrayReader::fromArray($data, source: static::class);
        $bag = new ExchangeBag($reader->get('options', ValueType::oneOf(ValueType::array(), ValueType::instanceOf(OptionsInterface::class)), []));

        foreach ($reader->getArrayOf('envelopes', ValueType::array()) ?? [] as $envelopeData) {
            $bag->addEnvelope($this->buildEnvelope($envelopeData));
        }

        return $bag;
    }

    /**
     * @param array<array-key, mixed> $data Datos recibidos sin validar.
     */
    private function buildEnvelope(array $data): Envelope
    {
        $this->assertKeys($data, ['sender', 'receiver']);
        $reader = ArrayReader::fromArray($data, source: static::class);

        return new Envelope(
            sender: $this->buildParty(Sender::class, $reader->requireArray('sender')),
            receiver: $this->buildParty(Receiver::class, $reader->requireArray('receiver')),
            documentType: isset($data['documentType'])
                ? DocumentType::from($reader->require('documentType', ValueType::oneOf(ValueType::int(), ValueType::string())))
                : DocumentType::B2B,
            process: isset($data['process'])
                ? ProcessType::from($reader->require('process', ValueType::oneOf(ValueType::int(), ValueType::string())))
                : ProcessType::BILLING,
            businessMessageID: $reader->getString('businessMessageID'),
            originalBusinessMessageID: $reader->getString('originalBusinessMessageID'),
            creationDateAndTime: isset($data['creationDateAndTime'])
                ? new DateTimeImmutable($reader->requireString('creationDateAndTime'))
                : null,
            documents: array_map(
                fn (array $documentData) => $this->buildDocument($documentData),
                $reader->getArrayOf('documents', ValueType::array()) ?? [],
            ),
            metadata: $reader->get('metadata', ValueType::oneOf(ValueType::array(), ValueType::instanceOf(BagInterface::class)), []),
        );
    }

    /**
     * @template T of PartyInterface
     * @param class-string<T> $partyClass `Sender::class` o `Receiver::class`
     * — ambas comparten el mismo constructor heredado de `AbstractParty`.
     * @param array<array-key, mixed> $data Datos recibidos sin validar.
     * @return T
     */
    private function buildParty(string $partyClass, array $data): PartyInterface
    {
        $this->assertKeys($data, ['identifier']);
        $reader = ArrayReader::fromArray($data, source: static::class);
        $identifierReader = $reader->requireReader('identifier');

        $identifier = new PartyIdentifier(
            value: $identifierReader->requireString('value'),
            schemeId: $identifierReader->getString('schemeId', 'CL-RUT'),
        );

        $endpoints = array_map(
            fn (ArrayReader $endpoint) => new PartyEndpoint(
                value: $endpoint->requireString('value'),
                schemeId: $endpoint->getString('schemeId', 'EMAIL'),
            ),
            $reader->getArrayReaders('endpoints') ?? [],
        );

        return new $partyClass($identifier, $endpoints);
    }

    /**
     * @param array<array-key, mixed> $data Datos recibidos sin validar.
     */
    private function buildDocument(array $data): Document
    {
        $reader = ArrayReader::fromArray($data, source: static::class);

        return new Document(
            content: isset($data['content'])
                ? $this->decodeBase64($data['content'], 'content')
                : '',
            attachments: array_map(
                fn (array $attachmentData) => $this->buildAttachment($attachmentData),
                $reader->getArrayOf('attachments', ValueType::array()) ?? [],
            ),
            type: isset($data['type'])
                ? DocumentType::from($reader->require('type', ValueType::oneOf(ValueType::int(), ValueType::string())))
                : DocumentType::B2B,
            metadata: $reader->get('metadata', ValueType::oneOf(ValueType::array(), ValueType::instanceOf(BagInterface::class)), []),
        );
    }

    /**
     * @param array<array-key, mixed> $data Datos recibidos sin validar.
     */
    private function buildAttachment(array $data): Attachment
    {
        $this->assertKeys($data, ['data']);
        $reader = ArrayReader::fromArray($data, source: static::class);

        return new Attachment(
            body: $this->decodeBase64($data['data'], 'data'),
            filename: $reader->getString('name'),
            contentType: $reader->getString('type'),
        );
    }
}
