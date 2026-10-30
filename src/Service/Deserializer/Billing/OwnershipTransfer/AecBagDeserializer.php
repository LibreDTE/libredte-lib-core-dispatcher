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

namespace libredte\lib\CoreDispatcher\Service\Deserializer\Billing\OwnershipTransfer;

use Derafu\Array\ArrayReader;
use Derafu\BackboneDispatcher\Abstract\AbstractDeserializer;
use Derafu\BackboneDispatcher\Contract\ObjectFactoryInterface;
use Derafu\BackboneDispatcher\Exception\UnsupportedDataTypeException;
use Derafu\Certificate\Contract\CertificateInterface;
use Derafu\Value\ValueCaster;
use Derafu\Value\ValueType;
use Derafu\Xml\Contract\XmlDocumentInterface;
use libredte\lib\Core\Package\Billing\Component\Document\Contract\DocumentBagManagerWorkerInterface;
use libredte\lib\Core\Package\Billing\Component\Document\Contract\DocumentInterface;
use libredte\lib\Core\Package\Billing\Component\OwnershipTransfer\Entity\Aec;
use libredte\lib\Core\Package\Billing\Component\OwnershipTransfer\Support\AecBag;

/**
 * Construye un `AecBag` a partir de datos de un arreglo, deserializando
 * recursivamente su campo anidado `certificate` a través del
 * `ObjectFactoryInterface` inyectado.
 *
 * `source` (`DocumentInterface|Aec`) se resuelve inspeccionando la
 * etiqueta raíz del XML recibido: `AEC` construye un `Aec` directamente
 * (re-cesión, su constructor es trivial); cualquier otra (asumido `DTE`)
 * pasa por `DocumentBagManagerWorkerInterface::create()` y usa el
 * documento resultante (primera cesión).
 */
class AecBagDeserializer extends AbstractDeserializer
{
    public function __construct(
        private readonly ObjectFactoryInterface $objectFactory,
        private readonly DocumentBagManagerWorkerInterface $documentBagManager,
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * @return AecBag
     */
    public function deserialize(array|string $data, string $class): object
    {
        $data = $this->assertArray($data);
        $reader = ArrayReader::fromArray($data, source: static::class);

        $this->assertKeys($data, ['source', 'cedente', 'cesionario', 'cesion']);

        return new AecBag(
            source: $this->resolveSource($reader->require('source', ValueType::oneOf(ValueType::map(), ValueType::string()))),
            cedente: $reader->requireMap('cedente'),
            cesionario: $reader->requireMap('cesionario'),
            cesion: $reader->requireMap('cesion'),
            certificate: ValueCaster::getObject(
                $this->objectFactory->create(
                    $reader->get('certificate', ValueType::oneOf(ValueType::map(), ValueType::string())),
                    CertificateInterface::class,
                ),
                CertificateInterface::class,
            ),
        );
    }

    /**
     * @param array<string, mixed>|string $source Datos del XML de origen.
     */
    private function resolveSource(array|string $source): DocumentInterface|Aec
    {
        $xmlDocument = $this->objectFactory->create(
            $source,
            XmlDocumentInterface::class,
        );

        if (!$xmlDocument instanceof XmlDocumentInterface) {
            throw UnsupportedDataTypeException::forDeserializer(
                static::class,
                XmlDocumentInterface::class,
            );
        }

        $root = $xmlDocument->getDomDocument()->documentElement->localName ?? '';

        if ($root === 'AEC') {
            return new Aec($xmlDocument);
        }

        return ValueCaster::requireObject(
            $this->documentBagManager->create($xmlDocument)->getDocument(),
            DocumentInterface::class,
        );
    }
}
