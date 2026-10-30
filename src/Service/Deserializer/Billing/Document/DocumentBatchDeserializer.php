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
use libredte\lib\Core\Package\Billing\Component\Document\Support\DocumentBatch;
use libredte\lib\Core\Package\Billing\Component\TradingParties\Contract\EmisorInterface;
use libredte\lib\CoreDispatcher\Service\Deserializer\Trait\Base64DecoderTrait;

/**
 * Construye un `DocumentBatch` a partir de datos de un arreglo.
 *
 * El contenido del lote (`inputData`) llega codificado en base64, porque puede
 * ser binario (por ejemplo una planilla XLSX), y se decodifica aquí. La ruta de
 * un archivo (`inputFile`) nunca se toma del arreglo: un cliente no puede
 * indicar un archivo del servidor.
 *
 * Los campos anidados opcionales (`emisor` y `certificate`) se deserializan a
 * través del `ObjectFactoryInterface` inyectado.
 */
class DocumentBatchDeserializer extends AbstractDeserializer
{
    use Base64DecoderTrait;

    public function __construct(
        private readonly ObjectFactoryInterface $objectFactory,
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * @return DocumentBatch
     */
    public function deserialize(array|string $data, string $class): object
    {
        $data = $this->assertArray($data);
        $reader = ArrayReader::fromArray($data, source: static::class);

        $batch = new DocumentBatch(
            inputData: isset($data['inputData'])
                ? $this->decodeBase64($data['inputData'], 'inputData')
                : null,
            options: $reader->get('options', ValueType::oneOf(ValueType::array(), ValueType::instanceOf(OptionsInterface::class))),
        );

        $batch->setEmisor(ValueCaster::getObject(
            $this->objectFactory->create(
                $reader->get('emisor', ValueType::oneOf(ValueType::map(), ValueType::string())),
                EmisorInterface::class,
            ),
            EmisorInterface::class,
        ));
        $batch->setCertificate(ValueCaster::getObject(
            $this->objectFactory->create(
                $reader->get('certificate', ValueType::oneOf(ValueType::map(), ValueType::string())),
                CertificateInterface::class,
            ),
            CertificateInterface::class,
        ));

        return $batch;
    }
}
