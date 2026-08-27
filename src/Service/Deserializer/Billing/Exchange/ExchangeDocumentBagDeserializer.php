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

use Derafu\BackboneDispatcher\Abstract\AbstractDeserializer;
use Derafu\BackboneDispatcher\Contract\ObjectFactoryInterface;
use Derafu\Certificate\Contract\CertificateInterface;
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
     */
    public function deserialize(array|string $data, string $class): object
    {
        $data = $this->assertArray($data);

        $this->assertKeys($data, ['tipo']);

        return new ExchangeDocumentBag(
            tipo: TipoDocumentoRespuesta::from($data['tipo']),
            caratula: $data['caratula'] ?? [],
            data: $data['data'] ?? [],
            certificate: $this->objectFactory->create(
                $data['certificate'] ?? null,
                CertificateInterface::class,
            ),
        );
    }
}
