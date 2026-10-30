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

use Derafu\BackboneDispatcher\Exception\UnsupportedDataTypeException;

/**
 * Decodificación de datos que llegan codificados en base64 a un deserializador.
 *
 * Todo dato en texto que no sea JSON (XML, CSV, binarios, etc.) viaja en
 * base64 para que el contenido original llegue intacto, sin importar su
 * codificación de caracteres.
 */
trait Base64DecoderTrait
{
    /**
     * Decodifica un valor en base64, de forma estricta.
     *
     * @param mixed $value Valor codificado en base64.
     * @param string|null $field Nombre del campo, o `null` si el dato completo
     * es el valor en base64.
     * @return string Contenido decodificado.
     * @throws UnsupportedDataTypeException Si no es un string en base64 válido.
     */
    protected function decodeBase64(mixed $value, ?string $field = null): string
    {
        $decoded = is_string($value) ? base64_decode($value, true) : false;

        if ($decoded === false) {
            throw new UnsupportedDataTypeException(
                $field === null
                    ? [
                        '{deserializer} requiere datos codificados en base64 válido.',
                        'deserializer' => static::class,
                    ]
                    : [
                        '{deserializer} requiere el campo {field} codificado en base64 válido.',
                        'deserializer' => static::class,
                        'field' => $field,
                    ]
            );
        }

        return $decoded;
    }

    /**
     * Decodifica el `inputData` de una bolsa.
     *
     * Un arreglo son los datos en JSON y se deja tal cual. Un string es el
     * contenido original en otro formato (XML, YAML, texto, etc.) y debe venir
     * en base64.
     *
     * @param mixed $inputData
     * @return ($inputData is null ? null : string|array<array-key, mixed>)
     * @throws UnsupportedDataTypeException Si es un string que no es base64
     * válido o no es un arreglo ni un string.
     */
    protected function decodeInputData(mixed $inputData): string|array|null
    {
        if ($inputData === null || is_array($inputData)) {
            return $inputData;
        }

        return $this->decodeBase64($inputData, 'inputData');
    }
}
