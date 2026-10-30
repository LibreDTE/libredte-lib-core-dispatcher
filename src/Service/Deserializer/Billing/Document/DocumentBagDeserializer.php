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

namespace libredte\lib\CoreDispatcher\Service\Deserializer\Billing\Document;

use Derafu\Array\ArrayReader;
use Derafu\BackboneDispatcher\Abstract\AbstractDeserializer;
use Derafu\BackboneDispatcher\Contract\ObjectFactoryInterface;
use Derafu\Certificate\Contract\CertificateInterface;
use Derafu\Config\Contract\OptionsInterface;
use Derafu\Value\ValueCaster;
use Derafu\Value\ValueType;
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
     *
     * @return DocumentBag
     */
    public function deserialize(array|string $data, string $class): object
    {
        $data = $this->assertArray($data);
        $reader = ArrayReader::fromArray($data, source: static::class);

        return new DocumentBag(
            inputData: $this->decodeInputData($data['inputData'] ?? null),
            parsedData: $reader->getArray('parsedData'),
            normalizedData: $reader->getArray('normalizedData'),
            libredteData: $reader->getArray('libredteData'),
            options: $reader->get('options', ValueType::oneOf(ValueType::array(), ValueType::instanceOf(OptionsInterface::class))),
            xmlDocument: ValueCaster::getObject(
                $this->objectFactory->create(
                    $reader->get('xmlDocument', ValueType::oneOf(ValueType::map(), ValueType::string())),
                    XmlDocumentInterface::class,
                ),
                XmlDocumentInterface::class,
            ),
            caf: ValueCaster::getObject(
                $this->objectFactory->create(
                    $reader->get('caf', ValueType::oneOf(ValueType::map(), ValueType::string())),
                    CafInterface::class,
                ),
                CafInterface::class,
            ),
            certificate: ValueCaster::getObject(
                $this->objectFactory->create(
                    $reader->get('certificate', ValueType::oneOf(ValueType::map(), ValueType::string())),
                    CertificateInterface::class,
                ),
                CertificateInterface::class,
            ),
            document: ValueCaster::getObject(
                $this->objectFactory->create(
                    $reader->get('document', ValueType::oneOf(ValueType::map(), ValueType::string())),
                    DocumentInterface::class,
                ),
                DocumentInterface::class,
            ),
            documentType: ValueCaster::getObject(
                $this->objectFactory->create(
                    $reader->get('documentType', ValueType::oneOf(ValueType::map(), ValueType::string())),
                    TipoDocumentoInterface::class,
                ),
                TipoDocumentoInterface::class,
            ),
            emisor: ValueCaster::getObject(
                $this->objectFactory->create(
                    $reader->get('emisor', ValueType::oneOf(ValueType::map(), ValueType::string())),
                    EmisorInterface::class,
                ),
                EmisorInterface::class,
            ),
            receptor: ValueCaster::getObject(
                $this->objectFactory->create(
                    $reader->get('receptor', ValueType::oneOf(ValueType::map(), ValueType::string())),
                    ReceptorInterface::class,
                ),
                ReceptorInterface::class,
            ),
        );
    }
}
