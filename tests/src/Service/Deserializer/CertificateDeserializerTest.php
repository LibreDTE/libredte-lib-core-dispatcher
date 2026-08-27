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
        $this->expectExceptionMessage('Certificate data or keys are required.');

        $this->deserializer->deserialize([], CertificateInterface::class);
    }
}
