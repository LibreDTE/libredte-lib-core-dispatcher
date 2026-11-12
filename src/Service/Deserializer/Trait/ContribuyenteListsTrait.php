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

namespace libredte\lib\CoreDispatcher\Service\Deserializer\Trait;

/**
 * Lectura de las listas de actividades económicas y teléfonos de un
 * contribuyente (`Emisor` o `Receptor`).
 *
 * La serialización de un contribuyente entrega `actividades_economicas` y
 * `telefonos` como listas, mientras que sus factories leen `actividad_economica`
 * y `telefono` como valores únicos. Este trait permite recibir también las
 * listas, tomando su primer elemento.
 */
trait ContribuyenteListsTrait
{
    /**
     * Agrega `actividad_economica` y `telefono` desde las listas
     * `actividades_economicas` y `telefonos`.
     *
     * Solo se completa un valor si no viene ninguno de los nombres con los que
     * la factory lo lee (`actividad_economica` o `Acteco`; `telefono`,
     * `Telefono` o `Contacto`), así lo que ya se entrega no cambia.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function withFirstOfLists(array $data): array
    {
        $names = [
            'actividades_economicas' => [
                'actividad_economica',
                ['actividad_economica', 'Acteco'],
            ],
            'telefonos' => [
                'telefono',
                ['telefono', 'Telefono', 'Contacto'],
            ],
        ];

        foreach ($names as $list => [$target, $scalars]) {
            if (!is_array($data[$list] ?? null) || $data[$list] === []) {
                continue;
            }

            foreach ($scalars as $scalar) {
                if (array_key_exists($scalar, $data)) {
                    continue 2;
                }
            }

            $data[$target] = reset($data[$list]);
        }

        return $data;
    }
}
