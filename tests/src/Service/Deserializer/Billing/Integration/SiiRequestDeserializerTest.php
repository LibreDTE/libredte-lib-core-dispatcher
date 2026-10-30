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

namespace libredte\lib\TestsCoreDispatcher\Service\Deserializer\Billing\Integration;

use Derafu\BackboneDispatcher\Exception\UnsupportedDataTypeException;
use Derafu\BackboneDispatcher\Service\Deserialization\FromArrayDeserializer;
use Derafu\BackboneDispatcher\Service\Deserialization\ObjectFactoryRegistry;
use Derafu\Certificate\Contract\CertificateInterface;
use Derafu\Certificate\Service\CertificateLoader;
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
        $this->expectException(UnsupportedDataTypeException::class);

        $this->deserializer->deserialize('not-an-array', SiiRequestInterface::class);
    }
}
