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

namespace libredte\lib\CoreDispatcher\Service\Deserializer;

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

        if (!empty($data['data']) && !empty($data['password'])) {
            return $this->certificateLoader->loadFromData(
                data: $this->decodeBase64($data['data'], 'data'),
                password: $data['password'],
            );
        }

        if (!empty($data['certificate']) && !empty($data['privateKey'])) {
            return $this->certificateLoader->loadFromKeys(
                certificate: $data['certificate'],
                privateKey: $data['privateKey'],
            );
        }

        throw new InvalidArgumentException('Se requieren los datos del certificado (data y password) o sus llaves (certificate y privateKey).');
    }
}
