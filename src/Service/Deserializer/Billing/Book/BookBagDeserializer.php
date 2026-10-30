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

namespace libredte\lib\CoreDispatcher\Service\Deserializer\Billing\Book;

use Derafu\Array\ArrayReader;
use Derafu\BackboneDispatcher\Abstract\AbstractDeserializer;
use Derafu\BackboneDispatcher\Contract\ObjectFactoryInterface;
use Derafu\Certificate\Contract\CertificateInterface;
use Derafu\Config\Contract\OptionsInterface;
use Derafu\Value\ValueCaster;
use Derafu\Value\ValueType;
use libredte\lib\Core\Package\Billing\Component\Book\Enum\TipoLibro;
use libredte\lib\Core\Package\Billing\Component\Book\Support\BookBag;
use libredte\lib\Core\Package\Billing\Component\TradingParties\Contract\EmisorInterface;
use libredte\lib\CoreDispatcher\Service\Deserializer\Trait\Base64DecoderTrait;

/**
 * Construye un `BookBag` a partir de datos de un arreglo, deserializando
 * recursivamente su campo anidado `certificate` y `emisor` a través del
 * `ObjectFactoryInterface` inyectado, y `tipo` desde su valor string
 * (ej. `'libro_ventas'`) al case correspondiente de `TipoLibro`.
 *
 * `book` no se construye aquí: no tiene un deserializador propio (se
 * obtiene siempre como resultado de `BuilderWorker::build()`), así que
 * siempre se pasa como `null`.
 */
class BookBagDeserializer extends AbstractDeserializer
{
    use Base64DecoderTrait;

    public function __construct(
        private readonly ObjectFactoryInterface $objectFactory,
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * @return BookBag
     */
    public function deserialize(array|string $data, string $class): object
    {
        $data = $this->assertArray($data);
        $reader = ArrayReader::fromArray($data, source: static::class);

        $this->assertKeys($data, ['tipo']);

        $detalle = [];
        foreach ($reader->get('detalle', ValueType::arrayOf(ValueType::map()), []) as $index => $row) {
            $detalle[ValueCaster::requireInt($index)] = $row;
        }

        return new BookBag(
            tipo: TipoLibro::from($reader->require('tipo', ValueType::oneOf(ValueType::int(), ValueType::string()))),
            inputData: $this->decodeInputData($data['inputData'] ?? []),
            caratula: $reader->get('caratula', ValueType::map(), []),
            detalle: $detalle,
            options: $reader->get('options', ValueType::oneOf(ValueType::array(), ValueType::instanceOf(OptionsInterface::class))),
            certificate: ValueCaster::getObject(
                $this->objectFactory->create(
                    $reader->get('certificate', ValueType::oneOf(ValueType::map(), ValueType::string())),
                    CertificateInterface::class,
                ),
                CertificateInterface::class,
            ),
            emisor: ValueCaster::getObject(
                $this->objectFactory->create(
                    $reader->get('emisor', ValueType::oneOf(ValueType::map(), ValueType::string())),
                    EmisorInterface::class,
                ),
                EmisorInterface::class,
            ),
        );
    }
}
