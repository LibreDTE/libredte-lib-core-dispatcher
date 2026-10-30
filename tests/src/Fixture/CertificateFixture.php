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

use OpenSSLAsymmetricKey;
use OpenSSLCertificate;
use OpenSSLCertificateSigningRequest;
use PHPUnit\Framework\Assert;

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

        Assert::assertInstanceOf(OpenSSLAsymmetricKey::class, $privateKey);

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

        Assert::assertInstanceOf(OpenSSLCertificateSigningRequest::class, $csr);

        $certificate = openssl_csr_sign($csr, null, $privateKey, 365, [
            'digest_alg' => 'sha256',
        ]);

        Assert::assertInstanceOf(OpenSSLCertificate::class, $certificate);
        Assert::assertTrue(openssl_x509_export($certificate, $certificatePem));
        Assert::assertTrue(openssl_pkey_export($privateKey, $privateKeyPem));

        return [
            'certificate' => $certificatePem,
            'privateKey' => $privateKeyPem,
        ];
    }
}
