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

use RuntimeException;

/**
 * Señala que un placeholder `{{método}}` de un fixture no pudo resolverse en
 * este entorno (el método de `FixtureContext` devolvió `null`, ej. sin
 * certificado real disponible) — `OperationFixturesTest` la captura para
 * marcar el test como saltado, en vez de fallarlo.
 */
final class FixturePlaceholderUnavailableException extends RuntimeException
{
    public function __construct(string $placeholder)
    {
        parent::__construct(sprintf(
            'Placeholder "{{%s}}" is not available in this environment.',
            $placeholder,
        ));
    }
}
