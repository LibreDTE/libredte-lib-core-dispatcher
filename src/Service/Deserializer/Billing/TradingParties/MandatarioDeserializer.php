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

namespace libredte\lib\CoreDispatcher\Service\Deserializer\Billing\TradingParties;

use Derafu\BackboneDispatcher\Abstract\AbstractDeserializer;
use libredte\lib\Core\Package\Billing\Component\TradingParties\Contract\MandatarioFactoryInterface;

/**
 * Construye un `Mandatario` a partir de datos de un arreglo, delegando en
 * el servicio `MandatarioFactoryInterface` real.
 */
class MandatarioDeserializer extends AbstractDeserializer
{
    public function __construct(
        private readonly MandatarioFactoryInterface $mandatarioFactory,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function deserialize(array|string $data, string $class): object
    {
        $data = $this->assertArray($data);

        return $this->mandatarioFactory->create($data);
    }
}
