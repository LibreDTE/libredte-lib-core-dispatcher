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

namespace libredte\lib\TestsCoreDispatcher\Service\Deserializer\Billing\Integration;

use Derafu\BackboneDispatcher\Service\FromArrayDeserializer;
use Derafu\BackboneDispatcher\Service\ObjectFactoryRegistry;
use Derafu\Certificate\Contract\CertificateInterface;
use Derafu\Certificate\Service\CertificateLoader;
use InvalidArgumentException;
use libredte\lib\Core\Package\Billing\Component\Integration\Contract\SiiRequestInterface;
use libredte\lib\CoreDispatcher\Service\Deserializer\Billing\Integration\SiiRequestDeserializer;
use libredte\lib\CoreDispatcher\Service\Deserializer\CertificateDeserializer;
use libredte\lib\TestsCoreDispatcher\Fixture\CertificateFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SiiRequestDeserializer::class)]
#[UsesClass(CertificateDeserializer::class)]
class SiiRequestDeserializerTest extends TestCase
{
    private SiiRequestDeserializer $deserializer;

    protected function setUp(): void
    {
        $objectFactory = new ObjectFactoryRegistry(
            deserializers: [
                CertificateInterface::class => new CertificateDeserializer(new CertificateLoader()),
            ],
            fallback: new FromArrayDeserializer(),
        );

        $this->deserializer = new SiiRequestDeserializer($objectFactory);
    }

    public function testDeserializesArrayDataRecursivelyDeserializingTheCertificate(): void
    {
        $pair = CertificateFixture::generate();

        $siiRequest = $this->deserializer->deserialize([
            'certificate' => [
                'certificate' => $pair['certificate'],
                'privateKey' => $pair['privateKey'],
            ],
            'options' => ['retries' => 3],
        ], SiiRequestInterface::class);

        $this->assertInstanceOf(SiiRequestInterface::class, $siiRequest);
        $this->assertInstanceOf(CertificateInterface::class, $siiRequest->getCertificate());
        $this->assertSame(3, $siiRequest->getRetries());
    }

    public function testRejectsNonArrayData(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->deserializer->deserialize('not-an-array', SiiRequestInterface::class);
    }
}
