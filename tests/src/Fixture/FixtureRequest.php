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

namespace libredte\lib\TestsCoreDispatcher\Fixture;

use Derafu\Array\ArrayReader;
use Derafu\BackboneDispatcher\ValueObject\OperationRequest;
use Derafu\Value\ValueType;

/**
 * Arma la solicitud de una operación a partir de su descripción en un fixture
 * YAML: `package`, `component`, `worker`, `operation` y `parameters`.
 */
final class FixtureRequest
{
    /**
     * @param array<string, mixed>|null $parameters Si se entrega, reemplaza a
     * los `parameters` del fixture.
     * @throws FixturePlaceholderUnavailableException Si un placeholder de
     * `parameters` no tiene valor en el entorno actual.
     */
    public static function create(
        ArrayReader $requestData,
        FixturePlaceholderResolver $placeholders,
        string $path,
        ?array $parameters = null,
    ): OperationRequest {
        $parameters ??= $placeholders->resolve(
            $requestData->get('parameters', ValueType::map(), [])
        );

        return new OperationRequest(
            $requestData->requireString('package'),
            $requestData->requireString('component'),
            $requestData->requireString('worker'),
            $requestData->requireString('operation'),
            ValueType::map()->parse($parameters, 'request.parameters', $path),
        );
    }
}
