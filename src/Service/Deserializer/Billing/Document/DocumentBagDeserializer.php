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

namespace libredte\lib\CoreDispatcher\Service\Deserializer\Billing\Document;

use Derafu\BackboneDispatcher\Abstract\AbstractDeserializer;
use Derafu\BackboneDispatcher\Contract\ObjectFactoryInterface;
use Derafu\Certificate\Contract\CertificateInterface;
use Derafu\Xml\Contract\XmlDocumentInterface;
use libredte\lib\Core\Package\Billing\Component\Document\Contract\DocumentInterface;
use libredte\lib\Core\Package\Billing\Component\Document\Contract\TipoDocumentoInterface;
use libredte\lib\Core\Package\Billing\Component\Document\Support\DocumentBag;
use libredte\lib\Core\Package\Billing\Component\Identifier\Contract\CafInterface;
use libredte\lib\Core\Package\Billing\Component\TradingParties\Contract\EmisorInterface;
use libredte\lib\Core\Package\Billing\Component\TradingParties\Contract\ReceptorInterface;
use libredte\lib\CoreDispatcher\Service\Deserializer\Trait\Base64DecoderTrait;

/**
 * Construye un `DocumentBag` a partir de datos de un arreglo, deserializando
 * recursivamente sus campos anidados (`xmlDocument`, `caf`, `certificate`,
 * `emisor`, `receptor`) a través del `ObjectFactoryInterface` inyectado.
 *
 * `document` y `documentType` no se construyen aquí: sus propios
 * deserializadores todavía no están implementados, así que siempre se pasan
 * como `null`.
 */
class DocumentBagDeserializer extends AbstractDeserializer
{
    use Base64DecoderTrait;

    public function __construct(
        private readonly ObjectFactoryInterface $objectFactory,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function deserialize(array|string $data, string $class): object
    {
        $data = $this->assertArray($data);

        return new DocumentBag(
            inputData: $this->decodeInputData($data['inputData'] ?? null),
            parsedData: $data['parsedData'] ?? null,
            normalizedData: $data['normalizedData'] ?? null,
            libredteData: $data['libredteData'] ?? null,
            options: $data['options'] ?? null,
            xmlDocument: $this->objectFactory->create(
                $data['xmlDocument'] ?? null,
                XmlDocumentInterface::class,
            ),
            caf: $this->objectFactory->create(
                $data['caf'] ?? null,
                CafInterface::class,
            ),
            certificate: $this->objectFactory->create(
                $data['certificate'] ?? null,
                CertificateInterface::class,
            ),
            document: $this->objectFactory->create(
                $data['document'] ?? null,
                DocumentInterface::class,
            ),
            documentType: $this->objectFactory->create(
                $data['documentType'] ?? null,
                TipoDocumentoInterface::class,
            ),
            emisor: $this->objectFactory->create(
                $data['emisor'] ?? null,
                EmisorInterface::class,
            ),
            receptor: $this->objectFactory->create(
                $data['receptor'] ?? null,
                ReceptorInterface::class,
            ),
        );
    }
}
