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

namespace libredte\lib\TestsCoreDispatcher;

use Derafu\BackboneDispatcher\Contract\ObjectFactoryInterface;
use Derafu\BackboneDispatcher\Contract\SafeDispatcherInterface;
use Derafu\BackboneDispatcher\Testing\Fixture\FixtureRegistry;
use Derafu\BackboneDispatcher\Testing\ScenarioTestCase;
use Derafu\Mail\Testing\GreenMailServer;
use InvalidArgumentException;
use libredte\lib\CoreDispatcher\Bootstrap;
use libredte\lib\TestsCoreDispatcher\Fixture\CoreFixtures;
use libredte\lib\TestsCoreDispatcher\Fixture\TestServices;
use PHPUnit\Framework\Attributes\CoversNamespace;

/**
 * Ejercita el `SafeDispatcher` y el `ObjectFactory` reales
 * (`Bootstrap::boot('test', true)`, sin mocks en ningún punto de la cadena)
 * contra cada escenario YAML de `tests/fixtures/scenarios/**`:
 *
 * - `operations/`: una operación de `libredte-lib-core` por escenario, con el
 *   resultado que entrega el dispatcher (o el fallo con el que termina).
 * - `deserializers/`: un deserializador con el dato exacto que enviaría un
 *   usuario, que construye el objeto esperado o lo rechaza.
 * - `roundtrip/`: lo que un deserializador entrega como salida se puede volver
 *   a recibir como entrada.
 * - `dispatcher_roundtrip/`: lo que una operación entrega como resultado se
 *   vuelve a recibir como parámetro de otra que lo recibe y lo devuelve del
 *   mismo tipo.
 *
 * Declara la cobertura de todo `src/` del dispatcher (`CoversNamespace`):
 * según lo que se pruebe atraviesa el `Bootstrap`, el `SafeDispatcher` y los
 * deserializadores, y el conjunto de casos lo define el directorio de
 * escenarios, no esta clase. Los datos que referencian con `!fixture` los
 * construye `CoreFixtures`; los escenarios que necesitan Docker (el servidor de
 * correo) se saltan cuando falta.
 */
#[CoversNamespace('libredte\lib\CoreDispatcher')]
class ScenarioTest extends ScenarioTestCase
{
    private static ?CoreFixtures $data = null;

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        self::$data = null;
        TestServices::cleanup();
    }

    /**
     * Deja vacíos los buzones del servidor de correo de prueba (si algún
     * escenario ya lo levantó) para que ningún escenario dependa de los
     * correos que dejó otro.
     */
    protected function setUp(): void
    {
        parent::setUp();

        self::$data?->resetMail();
    }

    protected static function scenariosDirectory(): string
    {
        return dirname(__DIR__) . '/fixtures/scenarios';
    }

    protected static function dispatcher(): SafeDispatcherInterface
    {
        return Bootstrap::boot('test', true);
    }

    protected static function objectFactory(): ObjectFactoryInterface
    {
        return TestServices::objectFactory();
    }

    protected static function fixtures(FixtureRegistry $registry): void
    {
        $data = self::$data = new CoreFixtures();

        $registry
            ->requirement('docker', static fn (): ?string => GreenMailServer::isAvailable()
                ? null
                : 'Docker is not available, and it is needed to start the mail server.')
            ->onShutdown(static fn () => $data->shutdown())

            // Certificados y CAF.
            ->fixture('certificate', static fn (): array => $data->certificate())
            ->fixture('certificate_pkcs12', static fn (): array => $data->certificatePkcs12())
            ->fixture('caf_factura_afecta', static fn (): string => $data->cafFacturaAfecta())

            // Documentos, por tipo (`type`: factura_afecta o boleta_afecta).
            ->fixture('document_parsed_data', static fn (array $arguments): array => match ($arguments['type'] ?? null) {
                'factura_afecta' => $data->facturaAfectaParsedData(),
                'boleta_afecta' => $data->boletaAfectaParsedData(),
                default => throw new InvalidArgumentException('The fixture document_parsed_data needs the argument type: factura_afecta or boleta_afecta.'),
            })
            ->fixture('document_xml', static fn (array $arguments): string => match ($arguments['type'] ?? null) {
                'factura_afecta' => $data->facturaAfectaXml(),
                'boleta_afecta' => $data->boletaAfectaXml(),
                default => throw new InvalidArgumentException('The fixture document_xml needs the argument type: factura_afecta or boleta_afecta.'),
            })
            ->fixture('sobre_envio_xml', static fn (): string => $data->sobreEnvioXml())
            ->fixture('emision_masiva_csv', static fn (): string => $data->emisionMasivaCsv())
            ->fixture('documento_xml_iso_8859_1', static fn (): string => $data->documentoXmlIso88591())

            // Libros, intercambio y cesión.
            ->fixture('libro_venta_xml', static fn (): string => $data->libroVentaXml())
            ->fixture('envio_recibos_xml', static fn (): string => $data->envioRecibosXml())
            ->fixture('aec_xml', static fn (): string => $data->aecXml())
            ->fixture('aec_recesion_xml', static fn (): string => $data->aecRecesionXml())

            // SII real (con certificado autofirmado si no hay uno configurado).
            ->fixture('sii_certificate_request', static fn (): array => $data->siiCertificateRequest())
            ->fixture('sii_company_rut', static fn (): string => $data->siiCompanyRut())
            ->fixture('sii_factura_afecta_xml', static fn (): string => $data->siiFacturaAfectaXml())
            ->fixture('sii_aec_xml', static fn (): string => $data->siiAecXml())

            // Servidor de correo de prueba (GreenMail en Docker).
            ->fixture('mail_sender_transport', static fn (): array => $data->mailSenderTransport(), ['docker'])
            ->fixture('mail_sender_address', static fn (): string => $data->mailSenderAddress(), ['docker'])
            ->fixture('mail_receiver_transport', static fn (): array => $data->mailReceiverTransport(), ['docker'])
            ->fixture('mail_receiver_address', static fn (): string => $data->mailReceiverAddress(), ['docker'])
        ;
    }
}
