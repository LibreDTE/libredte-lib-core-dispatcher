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

namespace libredte\lib\TestsCoreDispatcher\Service\Deserializer;

use Derafu\BackboneDispatcher\Exception\UnsupportedDataTypeException;
use Derafu\Certificate\Contract\CertificateInterface;
use Derafu\Certificate\Service\CertificateLoader;
use InvalidArgumentException;
use libredte\lib\CoreDispatcher\Service\Deserializer\CertificateDeserializer;
use libredte\lib\TestsCoreDispatcher\Fixture\CertificateFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CertificateDeserializer::class)]
class CertificateDeserializerTest extends TestCase
{
    private CertificateDeserializer $deserializer;

    protected function setUp(): void
    {
        $this->deserializer = new CertificateDeserializer(new CertificateLoader());
    }

    public function testDeserializesFromAPemCertificateAndPrivateKey(): void
    {
        $pair = CertificateFixture::generate();

        $certificate = $this->deserializer->deserialize([
            'certificate' => $pair['certificate'],
            'privateKey' => $pair['privateKey'],
        ], CertificateInterface::class);

        $this->assertInstanceOf(CertificateInterface::class, $certificate);
        $this->assertSame('LibreDTE Dispatcher Test', $certificate->getName());
    }

    public function testDeserializesFromBase64EncodedPkcs12DataAndAPassword(): void
    {
        $pair = CertificateFixture::generate();

        openssl_pkcs12_export(
            $pair['certificate'],
            $pkcs12,
            $pair['privateKey'],
            'i_love_libredte',
        );

        $certificate = $this->deserializer->deserialize([
            'data' => base64_encode($pkcs12),
            'password' => 'i_love_libredte',
        ], CertificateInterface::class);

        $this->assertInstanceOf(CertificateInterface::class, $certificate);
        $this->assertSame('LibreDTE Dispatcher Test', $certificate->getName());
    }

    public function testRejectsNonArrayData(): void
    {
        $this->expectException(UnsupportedDataTypeException::class);

        $this->deserializer->deserialize('not-an-array', CertificateInterface::class);
    }

    public function testRejectsDataWithNeitherKeysNorData(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Se requieren los datos del certificado (data y password) o sus llaves (certificate y privateKey).');

        $this->deserializer->deserialize([], CertificateInterface::class);
    }

    public function testRejectsPkcs12DataThatIsNotValidBase64(): void
    {
        $this->expectException(UnsupportedDataTypeException::class);
        $this->expectExceptionMessage('requiere el campo data codificado en base64 válido');

        $this->deserializer->deserialize([
            'data' => 'no es base64!',
            'password' => 'clave',
        ], CertificateInterface::class);
    }
}
