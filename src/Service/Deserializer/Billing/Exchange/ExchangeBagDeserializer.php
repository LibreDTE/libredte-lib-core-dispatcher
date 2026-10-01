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

namespace libredte\lib\CoreDispatcher\Service\Deserializer\Billing\Exchange;

use DateTimeImmutable;
use Derafu\BackboneDispatcher\Abstract\AbstractDeserializer;
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
     */
    public function deserialize(array|string $data, string $class): object
    {
        $data = $this->assertArray($data);

        $bag = new ExchangeBag($data['options'] ?? []);

        foreach ($data['envelopes'] ?? [] as $envelopeData) {
            $bag->addEnvelope($this->buildEnvelope($envelopeData));
        }

        return $bag;
    }

    private function buildEnvelope(array $data): Envelope
    {
        $this->assertKeys($data, ['sender', 'receiver']);

        return new Envelope(
            sender: $this->buildParty(Sender::class, $data['sender']),
            receiver: $this->buildParty(Receiver::class, $data['receiver']),
            documentType: isset($data['documentType'])
                ? DocumentType::from($data['documentType'])
                : DocumentType::B2B,
            process: isset($data['process'])
                ? ProcessType::from($data['process'])
                : ProcessType::BILLING,
            businessMessageID: $data['businessMessageID'] ?? null,
            originalBusinessMessageID: $data['originalBusinessMessageID'] ?? null,
            creationDateAndTime: isset($data['creationDateAndTime'])
                ? new DateTimeImmutable($data['creationDateAndTime'])
                : null,
            documents: array_map(
                fn (array $documentData) => $this->buildDocument($documentData),
                $data['documents'] ?? [],
            ),
            metadata: $data['metadata'] ?? [],
        );
    }

    /**
     * @template T of PartyInterface
     * @param class-string<T> $partyClass `Sender::class` o `Receiver::class`
     * — ambas comparten el mismo constructor heredado de `AbstractParty`.
     * @return T
     */
    private function buildParty(string $partyClass, array $data): PartyInterface
    {
        $this->assertKeys($data, ['identifier']);

        $identifier = new PartyIdentifier(
            value: $data['identifier']['value'],
            schemeId: $data['identifier']['schemeId'] ?? 'CL-RUT',
        );

        $endpoints = array_map(
            fn (array $endpointData) => new PartyEndpoint(
                value: $endpointData['value'],
                schemeId: $endpointData['schemeId'] ?? 'EMAIL',
            ),
            $data['endpoints'] ?? [],
        );

        return new $partyClass($identifier, $endpoints);
    }

    private function buildDocument(array $data): Document
    {
        return new Document(
            content: isset($data['content'])
                ? $this->decodeBase64($data['content'], 'content')
                : '',
            attachments: array_map(
                fn (array $attachmentData) => $this->buildAttachment($attachmentData),
                $data['attachments'] ?? [],
            ),
            type: isset($data['type'])
                ? DocumentType::from($data['type'])
                : DocumentType::B2B,
            metadata: $data['metadata'] ?? [],
        );
    }

    private function buildAttachment(array $data): Attachment
    {
        $this->assertKeys($data, ['data']);

        return new Attachment(
            body: $this->decodeBase64($data['data'], 'data'),
            filename: $data['name'] ?? null,
            contentType: $data['type'] ?? null,
        );
    }
}
