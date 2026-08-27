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

namespace libredte\lib\TestsCoreDispatcher\Fixture;

/**
 * Genera un par certificado/llave privada autofirmado real y desechable
 * (criptografía OpenSSL real, sin mocks ni archivos fixture) para tests que
 * necesiten un par PEM `certificate`/`privateKey` válido.
 */
final class CertificateFixture
{
    /**
     * @param string $rut RUT a incrustar en el atributo `serialNumber` del
     * subject del certificado — así `Derafu\Certificate\Certificate::getId()`
     * (que lo lee de ahí) funciona igual que con un certificado chileno
     * real, sin necesitar uno de verdad.
     * @param string $email Correo a incrustar en el atributo `emailAddress`
     * del subject — leído por `Certificate::getEmail()`.
     * @return array{certificate: string, privateKey: string}
     */
    public static function generate(
        string $rut = '76192083-9',
        string $email = 'contacto@example.com',
    ): array {
        $privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        $csr = openssl_csr_new(
            [
                'commonName' => 'LibreDTE Dispatcher Test',
                'countryName' => 'CL',
                'serialNumber' => $rut,
                'emailAddress' => $email,
            ],
            $privateKey,
            ['digest_alg' => 'sha256'],
        );

        $certificate = openssl_csr_sign($csr, null, $privateKey, 365, [
            'digest_alg' => 'sha256',
        ]);

        openssl_x509_export($certificate, $certificatePem);
        openssl_pkey_export($privateKey, $privateKeyPem);

        return [
            'certificate' => $certificatePem,
            'privateKey' => $privateKeyPem,
        ];
    }
}
