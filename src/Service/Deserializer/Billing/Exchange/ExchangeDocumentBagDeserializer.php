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

use Derafu\Array\ArrayReader;
use Derafu\BackboneDispatcher\Abstract\AbstractDeserializer;
use Derafu\BackboneDispatcher\Contract\ObjectFactoryInterface;
use Derafu\Certificate\Contract\CertificateInterface;
use Derafu\Value\ValueCaster;
use Derafu\Value\ValueType;
use libredte\lib\Core\Package\Billing\Component\Exchange\Enum\TipoDocumentoRespuesta;
use libredte\lib\Core\Package\Billing\Component\Exchange\Support\ExchangeDocumentBag;

/**
 * Construye un `ExchangeDocumentBag` a partir de datos de un arreglo,
 * deserializando recursivamente su campo anidado `certificate` a través del
 * `ObjectFactoryInterface` inyectado, y `tipo` desde su valor string
 * (ej. `'envio_recibos'`) al case correspondiente de `TipoDocumentoRespuesta`.
 *
 * `document` no se construye aquí: no tiene un deserializador propio (se
 * obtiene siempre como resultado de `DocumentResponseWorker::buildEnvioRecibos()`/
 * `buildRespuestaEnvio()`), así que siempre se pasa como `null`.
 */
class ExchangeDocumentBagDeserializer extends AbstractDeserializer
{
    public function __construct(
        private readonly ObjectFactoryInterface $objectFactory,
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * @return ExchangeDocumentBag
     */
    public function deserialize(array|string $data, string $class): object
    {
        $data = $this->assertArray($data);
        $reader = ArrayReader::fromArray($data, source: static::class);

        $this->assertKeys($data, ['tipo']);

        return new ExchangeDocumentBag(
            tipo: TipoDocumentoRespuesta::from($reader->require('tipo', ValueType::oneOf(ValueType::int(), ValueType::string()))),
            caratula: $reader->get('caratula', ValueType::map(), []),
            data: $reader->get('data', ValueType::array(), []),
            certificate: ValueCaster::getObject(
                $this->objectFactory->create(
                    $reader->get('certificate', ValueType::oneOf(ValueType::map(), ValueType::string())),
                    CertificateInterface::class,
                ),
                CertificateInterface::class,
            ),
        );
    }
}
