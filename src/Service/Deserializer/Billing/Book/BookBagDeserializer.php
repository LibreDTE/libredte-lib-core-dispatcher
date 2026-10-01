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

namespace libredte\lib\CoreDispatcher\Service\Deserializer\Billing\Book;

use Derafu\BackboneDispatcher\Abstract\AbstractDeserializer;
use Derafu\BackboneDispatcher\Contract\ObjectFactoryInterface;
use Derafu\Certificate\Contract\CertificateInterface;
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
     */
    public function deserialize(array|string $data, string $class): object
    {
        $data = $this->assertArray($data);

        $this->assertKeys($data, ['tipo']);

        return new BookBag(
            tipo: TipoLibro::from($data['tipo']),
            inputData: $this->decodeInputData($data['inputData'] ?? []),
            caratula: $data['caratula'] ?? [],
            detalle: $data['detalle'] ?? [],
            options: $data['options'] ?? null,
            certificate: $this->objectFactory->create(
                $data['certificate'] ?? null,
                CertificateInterface::class,
            ),
            emisor: $this->objectFactory->create(
                $data['emisor'] ?? null,
                EmisorInterface::class,
            ),
        );
    }
}
