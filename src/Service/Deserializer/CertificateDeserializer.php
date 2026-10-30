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

namespace libredte\lib\CoreDispatcher\Service\Deserializer;

use Derafu\Array\ArrayReader;
use Derafu\BackboneDispatcher\Abstract\AbstractDeserializer;
use Derafu\Certificate\Contract\CertificateLoaderInterface;
use InvalidArgumentException;
use libredte\lib\CoreDispatcher\Service\Deserializer\Trait\Base64DecoderTrait;

/**
 * Construye un `Certificate` ya sea a partir de datos PKCS#12 codificados
 * en base64 con una contraseña, o de un certificado PEM y su llave privada,
 * delegando en el servicio `CertificateLoaderInterface` real.
 */
class CertificateDeserializer extends AbstractDeserializer
{
    use Base64DecoderTrait;

    public function __construct(
        private readonly CertificateLoaderInterface $certificateLoader,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function deserialize(array|string $data, string $class): object
    {
        $data = $this->assertArray($data);
        $reader = ArrayReader::fromArray($data, source: static::class);

        if (!empty($data['data']) && !empty($data['password'])) {
            return $this->certificateLoader->loadFromData(
                data: $this->decodeBase64($data['data'], 'data'),
                password: $reader->requireString('password'),
            );
        }

        if (!empty($data['certificate']) && !empty($data['privateKey'])) {
            return $this->certificateLoader->loadFromKeys(
                certificate: $reader->requireString('certificate'),
                privateKey: $reader->requireString('privateKey'),
            );
        }

        throw new InvalidArgumentException('Se requieren los datos del certificado (data y password) o sus llaves (certificate y privateKey).');
    }
}
