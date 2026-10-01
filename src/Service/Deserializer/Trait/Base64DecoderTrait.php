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
     * @return string|array<mixed>|null
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
