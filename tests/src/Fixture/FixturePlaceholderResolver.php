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
 * Reemplaza los placeholders `"{{método}}"` de un fixture YAML por el
 * resultado de invocar ese método (sin argumentos) en `FixtureContext`.
 *
 * Lo comparten todos los tests dirigidos por fixtures YAML.
 */
final class FixturePlaceholderResolver
{
    public function __construct(private readonly FixtureContext $context)
    {
    }

    /**
     * Reemplaza recursivamente cualquier string `"{{método}}"` por el
     * resultado de invocar ese método en `FixtureContext`. Si el método
     * devuelve `null`, lanza `FixturePlaceholderUnavailableException`, para
     * que el test que lo usa pueda saltarse en vez de recibir `null`
     * silenciosamente.
     *
     * @throws FixturePlaceholderUnavailableException
     */
    public function resolve(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn (mixed $item) => $this->resolve($item), $value);
        }

        if (is_string($value) && preg_match('/^\{\{(\w+)\}\}$/', $value, $matches) === 1) {
            $method = $matches[1];

            if (!method_exists($this->context, $method)) {
                throw new RuntimeException(sprintf(
                    'Fixture placeholder "{{%s}}" does not exist on %s.',
                    $method,
                    FixtureContext::class,
                ));
            }

            $resolved = $this->context->{$method}();

            if ($resolved === null) {
                throw new FixturePlaceholderUnavailableException($method);
            }

            return $resolved;
        }

        return $value;
    }
}
