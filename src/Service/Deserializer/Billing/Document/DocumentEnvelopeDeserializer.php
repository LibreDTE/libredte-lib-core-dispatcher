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

use Derafu\BackboneDispatcher\Abstract\AbstractDeserializer;
use libredte\lib\Core\Package\Billing\Component\Document\Contract\DispatcherWorkerInterface;
use libredte\lib\CoreDispatcher\Service\Deserializer\Trait\Base64DecoderTrait;

/**
 * Construye un `DocumentEnvelope` a partir de un XML de "sobre" codificado
 * en base64, delegando en el `DispatcherWorkerInterface` real.
 */
class DocumentEnvelopeDeserializer extends AbstractDeserializer
{
    use Base64DecoderTrait;

    public function __construct(
        private readonly DispatcherWorkerInterface $dispatcherWorker,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function deserialize(array|string $data, string $class): object
    {
        $data = $this->assertString($data);

        return $this->dispatcherWorker->loadXml($this->decodeBase64($data));
    }
}
