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

use Derafu\Certificate\Contract\CertificateInterface;
use Derafu\Certificate\Service\CertificateFaker;
use Derafu\Certificate\Service\CertificateLoader;
use libredte\lib\Core\Application;
use libredte\lib\Core\Package\Billing\Component\Book\Contract\BookInterface;
use libredte\lib\Core\Package\Billing\Component\Book\Contract\BuilderWorkerInterface as BookBuilderWorkerInterface;
use libredte\lib\Core\Package\Billing\Component\Book\Enum\TipoLibro;
use libredte\lib\Core\Package\Billing\Component\Book\Support\BookBag;
use libredte\lib\Core\Package\Billing\Component\Document\Contract\BuilderWorkerInterface;
use libredte\lib\Core\Package\Billing\Component\Document\Contract\DispatcherWorkerInterface;
use libredte\lib\Core\Package\Billing\Component\Document\Contract\DocumentEnvelopeInterface;
use libredte\lib\Core\Package\Billing\Component\Document\Contract\DocumentInterface;
use libredte\lib\Core\Package\Billing\Component\Document\Support\DocumentBag;
use libredte\lib\Core\Package\Billing\Component\Exchange\Abstract\AbstractExchangeDocument;
use libredte\lib\Core\Package\Billing\Component\Exchange\Contract\DocumentResponseWorkerInterface;
use libredte\lib\Core\Package\Billing\Component\Exchange\Enum\TipoDocumentoRespuesta;
use libredte\lib\Core\Package\Billing\Component\Exchange\Support\ExchangeDocumentBag;
use libredte\lib\Core\Package\Billing\Component\Identifier\Contract\CafFakerWorkerInterface;
use libredte\lib\Core\Package\Billing\Component\Identifier\Contract\CafInterface;
use libredte\lib\Core\Package\Billing\Component\OwnershipTransfer\Contract\AecWorkerInterface;
use libredte\lib\Core\Package\Billing\Component\OwnershipTransfer\Entity\Aec;
use libredte\lib\Core\Package\Billing\Component\OwnershipTransfer\Support\AecBag;
use libredte\lib\Core\Package\Billing\Component\TradingParties\Entity\AutorizacionDte;
use libredte\lib\Core\Package\Billing\Component\TradingParties\Entity\Emisor;
use Throwable;

/**
 * Provee, bajo demanda y memoizados, los valores dinámicos que los fixtures
 * YAML de `OperationFixturesTest` referencian como placeholders `{{método}}`
 * (ej. `certificate: "{{certificate}}"`).
 *
 * Cada método es independiente: un fixture solo paga el costo (y declara la
 * dependencia) de lo que realmente necesita, no de un `$context` con todo.
 * Un método puede devolver `null` si no puede proveer su valor en el
 * entorno actual — `OperationFixturesTest` interpreta eso como "saltar este
 * fixture". Ningún método lo usa hoy: incluso los del SII real
 * (`sii*`) siempre devuelven un valor, cayendo a un certificado autofirmado
 * (`CertificateFaker`) cuando no hay uno real configurado — ver
 * `siiCertificateObject()`.
 *
 * Usa directamente los workers reales de `libredte-lib-core` (vía
 * `Application::getInstance('test', true)`), nunca el `SafeDispatcher` —
 * evita depender circularmente de las mismas operaciones que se están
 * probando.
 */
final class FixtureContext
{
    /**
     * Identidad de prueba pública y ya commiteada en `libredte-lib-core`
     * (ver auditoría de RUTs en `CLAUDE.md`), reutilizada acá como emisor
     * canónico de todos los fixtures que necesiten uno.
     */
    public const string EMISOR_RUT = '76192083-9';

    public const string EMISOR_RAZON_SOCIAL = 'SASCO SpA';

    /**
     * Contraparte genérica (receptor/cesionario/etc.) para los fixtures que
     * necesiten una segunda identidad.
     */
    public const string GENERICO_RUT = '66666666-6';

    public const string GENERICO_RAZON_SOCIAL = 'Cliente de Prueba';

    /**
     * RUT de una persona natural (usuario), distinto del RUT del
     * contribuyente (`EMISOR_RUT`) — para campos como `RutEnvia` de la
     * carátula de un libro, que identifican a quien envía, no a la empresa.
     */
    public const string USUARIO_RUT = '12345678-2';

    /** @var array{certificate: string, privateKey: string}|null */
    private ?array $certificatePair = null;

    private ?CertificateInterface $certificateObject = null;

    /** @var array<string, CafInterface> Cache de CAF por "codigo:desde:hasta". */
    private array $cafCache = [];

    private ?DocumentInterface $facturaAfectaDocument = null;

    private ?DocumentInterface $boletaAfectaDocument = null;

    private ?CertificateInterface $siiCertificateObject = null;

    private bool $siiCertificateIsReal = false;

    private ?DocumentInterface $siiFacturaAfectaDocument = null;

    private ?string $siiAecXml = null;

    private ?DocumentEnvelopeInterface $sobreEnvio = null;

    private ?BookInterface $libroVenta = null;

    private ?AbstractExchangeDocument $envioRecibos = null;

    private ?Aec $aec = null;

    /**
     * Certificado digital autofirmado, real pero desechable (nunca se
     * commitea a disco) — ver `CertificateFixture::generate()`. Forma
     * `{certificate, privateKey}` (PEM), una de las dos que acepta
     * `CertificateDeserializer`.
     *
     * @return array{certificate: string, privateKey: string}
     */
    public function certificate(): array
    {
        return $this->certificatePair ??= CertificateFixture::generate(self::EMISOR_RUT);
    }

    /**
     * El mismo certificado fresco de `certificate()`, ya como
     * `CertificateInterface` real — para construir objetos internamente
     * (ej. un `DocumentBag`) sin pasar por la deserialización.
     */
    public function certificateObject(): CertificateInterface
    {
        if ($this->certificateObject === null) {
            $pair = $this->certificate();
            $this->certificateObject = (new CertificateLoader())->loadFromKeys(
                $pair['certificate'],
                $pair['privateKey'],
            );
        }

        return $this->certificateObject;
    }

    /**
     * CAF (Código de Autorización de Folios) fresco para el emisor
     * canónico, generado en tiempo de test — nunca se commitea un CAF real
     * a disco (ver política de datos sensibles en `CLAUDE.md`).
     *
     * @return string XML del CAF, codificado en base64.
     */
    public function cafFacturaAfecta(): string
    {
        return base64_encode($this->freshCafFacturaAfecta()->getXml());
    }

    /**
     * Los datos crudos (`Encabezado`/`Detalle`) de una factura afecta (33)
     * mínima, para el emisor canónico y el receptor genérico — la forma que
     * espera `DocumentBagInterface.parsedData` (ver
     * `DocumentBagDeserializer` y el bug ya corregido en
     * `Document\BuilderWorker::build`'s `#[Operation]`).
     *
     * @return array<string, mixed>
     */
    public function facturaAfectaParsedData(): array
    {
        return [
            'Encabezado' => [
                'IdDoc' => [
                    'TipoDTE' => 33,
                    'Folio' => 1,
                ],
                'Emisor' => [
                    'RUTEmisor' => self::EMISOR_RUT,
                    'RznSoc' => self::EMISOR_RAZON_SOCIAL,
                    'GiroEmis' => 'Servicios',
                    'DirOrigen' => 'Dirección 123',
                    'CmnaOrigen' => 'Santiago',
                ],
                'Receptor' => [
                    'RUTRecep' => self::GENERICO_RUT,
                    'RznSocRecep' => self::GENERICO_RAZON_SOCIAL,
                    'GiroRecep' => 'Servicios',
                    'DirRecep' => 'Santiago',
                    'CmnaRecep' => 'Santiago',
                ],
            ],
            'Detalle' => [
                [
                    'NmbItem' => 'Servicio de prueba',
                    'QtyItem' => 1,
                    'PrcItem' => 10000,
                ],
            ],
        ];
    }

    /**
     * Una factura afecta (33) completa — construida, timbrada con un CAF
     * fresco y firmada con el certificado fresco — lista para operaciones
     * que necesitan un DTE ya armado (`dispatcher::create`,
     * `renderer::render`, primera cesión de `AecBag`, etc.), no los datos
     * crudos que consume `builder::build`.
     *
     * @return string XML del documento, codificado en base64.
     */
    public function facturaAfectaXml(): string
    {
        return base64_encode($this->freshFacturaAfectaDocument()->saveXml());
    }

    /**
     * Un archivo CSV de emisión masiva con un único documento (la misma
     * factura afecta de `facturaAfectaParsedData()`), para las operaciones de
     * procesamiento en lote (`batch_processor::parse`).
     *
     * @return string CSV con el encabezado y una fila, codificado en base64.
     */
    public function emisionMasivaCsv(): string
    {
        return base64_encode(implode("\n", [
            'TipoDTE;Folio;FchEmis;FchVenc;RUTRecep;RznSocRecep;GiroRecep;Telefono;CorreoRecep;DirRecep;CmnaRecep;VlrCodigo;IndExe;NmbItem;DscItem;QtyItem;UnmdItem;PrcItem',
            '33;1;;;' . self::GENERICO_RUT . ';' . self::GENERICO_RAZON_SOCIAL . ';Servicios;;;Santiago;Santiago;;;Servicio de prueba;;1;;10000',
            '',
        ]));
    }

    /**
     * Un documento en XML codificado en ISO-8859-1 (con su declaración y
     * caracteres con tilde), para las operaciones que reciben el contenido
     * original de los datos de entrada (`inputData`).
     *
     * @return string XML codificado en base64.
     */
    public function documentoXmlIso88591(): string
    {
        return base64_encode(
            (string) file_get_contents(
                __DIR__ . '/../../fixtures/inputs/documento_iso_8859_1.xml'
            )
        );
    }

    private function freshFacturaAfectaDocument(): DocumentInterface
    {
        if ($this->facturaAfectaDocument === null) {
            $bag = new DocumentBag(
                parsedData: $this->facturaAfectaParsedData(),
                caf: $this->freshCafFacturaAfecta(),
                certificate: $this->certificateObject(),
            );

            $this->facturaAfectaDocument = $this->builderWorker()->build($bag)->getDocument();
        }

        return $this->facturaAfectaDocument;
    }

    /**
     * Los datos crudos de una boleta afecta (39) mínima, para el emisor
     * canónico — mismo propósito que `facturaAfectaParsedData()`, pero para
     * un tipo de documento que NO admite acuse de recibo
     * (`TipoDocumento::requiresAcuseRecibo()` es `false` para boletas), útil
     * para probar que `renderer::render` omite en silencio una presentación
     * `cedible` solicitada para este tipo de documento.
     *
     * @return array<string, mixed>
     */
    public function boletaAfectaParsedData(): array
    {
        return [
            'Encabezado' => [
                'IdDoc' => [
                    'TipoDTE' => 39,
                    'Folio' => 1,
                ],
                'Emisor' => [
                    'RUTEmisor' => self::EMISOR_RUT,
                    'RznSocEmisor' => self::EMISOR_RAZON_SOCIAL,
                    'GiroEmisor' => 'Servicios',
                    'DirOrigen' => 'Dirección 123',
                    'CmnaOrigen' => 'Santiago',
                ],
                'Receptor' => [
                    'RUTRecep' => self::GENERICO_RUT,
                    'RznSocRecep' => self::GENERICO_RAZON_SOCIAL,
                    'DirRecep' => 'Santiago',
                    'CmnaRecep' => 'Santiago',
                ],
            ],
            'Detalle' => [
                [
                    'NmbItem' => 'Producto de prueba',
                    'QtyItem' => 1,
                    'PrcItem' => 1190,
                ],
            ],
        ];
    }

    /**
     * Una boleta afecta (39) completa — construida, timbrada y firmada,
     * mismo propósito que `facturaAfectaXml()` pero para un tipo de
     * documento sin acuse de recibo.
     *
     * @return string XML del documento, codificado en base64.
     */
    public function boletaAfectaXml(): string
    {
        return base64_encode($this->freshBoletaAfectaDocument()->saveXml());
    }

    private function freshBoletaAfectaDocument(): DocumentInterface
    {
        if ($this->boletaAfectaDocument === null) {
            $bag = new DocumentBag(
                parsedData: $this->boletaAfectaParsedData(),
                caf: $this->freshCaf(codigoDocumento: 39, folioDesde: 1, folioHasta: 100),
                certificate: $this->certificateObject(),
            );

            $this->boletaAfectaDocument = $this->builderWorker()->build($bag)->getDocument();
        }

        return $this->boletaAfectaDocument;
    }

    /**
     * Un sobre de envío (`EnvioDTE`) completo — construido a partir de la
     * factura afecta fresca, firmado con el certificado fresco — para
     * operaciones que necesitan un sobre ya armado
     * (`dispatcher::validate/validateSchema/validateSignature`), no el DTE
     * suelto que da `facturaAfectaXml()`.
     *
     * @return string XML del sobre, codificado en base64.
     */
    public function sobreEnvioXml(): string
    {
        return base64_encode($this->freshSobreEnvio()->getXmlDocument()->saveXml());
    }

    private function freshSobreEnvio(): DocumentEnvelopeInterface
    {
        if ($this->sobreEnvio === null) {
            // El emisor se pasa ya con `AutorizacionDte` asignada porque
            // `DispatcherWorker::create()` no la infiere sola (solo lo hace
            // al recargar un sobre ya existente vía `loadXml()`, leyendo su
            // propia `Caratula`) — mismo dato que usa `dispatcher/create.yaml`.
            $emisor = new Emisor(
                self::EMISOR_RUT,
                self::EMISOR_RAZON_SOCIAL,
                autorizacion_dte: new AutorizacionDte('2014-08-22', 80),
            );

            $bag = new DocumentBag(
                xmlDocument: $this->freshFacturaAfectaDocument()->getXmlDocument(),
                certificate: $this->certificateObject(),
                emisor: $emisor,
            );

            $this->sobreEnvio = $this->dispatcherWorker()->create($bag);
        }

        return $this->sobreEnvio;
    }

    private function dispatcherWorker(): DispatcherWorkerInterface
    {
        $worker = Application::getInstance('test', true)
            ->getPackageRegistry()
            ->getPackage('billing')
            ->getComponent('document')
            ->getWorker('dispatcher')
        ;
        assert($worker instanceof DispatcherWorkerInterface);

        return $worker;
    }

    /**
     * Un libro de ventas (mensual) completo — construido y firmado con el
     * certificado fresco — para operaciones que necesitan un libro ya
     * armado (`book.validator::validateSchema/validateSignature`).
     *
     * `RutEmisorLibro` se incluye explícito en la carátula porque
     * `BuilderWorker::build()` no lo infiere solo (eso lo hace
     * `book.loader::load()`, que no se encadena acá) — mismo dato que usa
     * `book/builder/build.yaml`.
     *
     * @return string XML del libro, codificado en base64.
     */
    public function libroVentaXml(): string
    {
        return base64_encode($this->freshLibroVenta()->getXml());
    }

    private function freshLibroVenta(): BookInterface
    {
        if ($this->libroVenta === null) {
            $bag = new BookBag(
                tipo: TipoLibro::VENTAS,
                caratula: [
                    // `RutEnvia`/`FchResol`/`NroResol` son requeridos por el
                    // XSD (LibroCV_v10.xsd), en este orden, aunque `build()`
                    // no los exige — sin ellos, el libro se construye pero
                    // no valida contra el esquema.
                    'RutEmisorLibro' => self::EMISOR_RUT,
                    'RutEnvia' => self::USUARIO_RUT,
                    'FchResol' => '2014-08-22',
                    'NroResol' => 80,
                    'PeriodoTributario' => '2024-01',
                ],
                detalle: [
                    [
                        'TpoDoc' => 33,
                        'NroDoc' => 1,
                        'TasaImp' => 19,
                        'FchDoc' => '2024-01-10',
                        'RUTDoc' => self::GENERICO_RUT,
                        'RznSoc' => self::GENERICO_RAZON_SOCIAL,
                        'MntNeto' => 100000,
                        'MntIVA' => 19000,
                        'MntTotal' => 119000,
                    ],
                ],
                certificate: $this->certificateObject(),
                emisor: new Emisor(self::EMISOR_RUT, self::EMISOR_RAZON_SOCIAL),
            );

            $this->libroVenta = $this->bookBuilderWorker()->build($bag)->getBook();
        }

        return $this->libroVenta;
    }

    private function bookBuilderWorker(): BookBuilderWorkerInterface
    {
        $worker = Application::getInstance('test', true)
            ->getPackageRegistry()
            ->getPackage('billing')
            ->getComponent('book')
            ->getWorker('builder')
        ;
        assert($worker instanceof BookBuilderWorkerInterface);

        return $worker;
    }

    /**
     * Un `EnvioRecibos` (recibo de mercaderías/servicios) completo —
     * construido y firmado con el certificado fresco — para operaciones que
     * necesitan un documento de respuesta ya armado
     * (`document_response::validateSchema/validateSignature`).
     *
     * @return string XML del EnvioRecibos, codificado en base64.
     */
    public function envioRecibosXml(): string
    {
        return base64_encode($this->freshEnvioRecibos()->getXml());
    }

    private function freshEnvioRecibos(): AbstractExchangeDocument
    {
        if ($this->envioRecibos === null) {
            $bag = new ExchangeDocumentBag(
                tipo: TipoDocumentoRespuesta::ENVIO_RECIBOS,
                caratula: [
                    'RutResponde' => self::EMISOR_RUT,
                    'RutRecibe' => self::GENERICO_RUT,
                ],
                data: [
                    [
                        'TipoDoc' => 33,
                        'Folio' => 1,
                        'FchEmis' => '2024-01-15',
                        'RUTEmisor' => self::GENERICO_RUT,
                        'RUTRecep' => self::EMISOR_RUT,
                        'MntTotal' => 100000,
                        'Recinto' => 'Oficina central',
                        'RutFirma' => self::EMISOR_RUT,
                    ],
                ],
                certificate: $this->certificateObject(),
            );

            $this->envioRecibos = $this->documentResponseWorker()->buildEnvioRecibos($bag);
        }

        return $this->envioRecibos;
    }

    private function documentResponseWorker(): DocumentResponseWorkerInterface
    {
        $worker = Application::getInstance('test', true)
            ->getPackageRegistry()
            ->getPackage('billing')
            ->getComponent('exchange')
            ->getWorker('document_response')
        ;
        assert($worker instanceof DocumentResponseWorkerInterface);

        return $worker;
    }

    /**
     * Un AEC de primera cesión completo — construido y firmado con el
     * certificado fresco, cediendo la factura afecta fresca — para
     * `ownership_transfer.aec::build` (primera cesión) y como `source` de
     * `aecRecesionXml()` (re-cesión).
     *
     * @return string XML del AEC, codificado en base64.
     */
    public function aecXml(): string
    {
        return base64_encode($this->freshAec()->saveXml());
    }

    /**
     * Una re-cesión completa del AEC de `aecXml()` — el mismo AEC, cedido
     * una segunda vez a un tercer cesionario — para probar la otra rama del
     * `AecBagDeserializer` (`source` como `Aec`, no `DocumentInterface`).
     *
     * @return string XML de la re-cesión, codificado en base64.
     */
    public function aecRecesionXml(): string
    {
        $aec = $this->aecWorker()->build(new AecBag(
            source: $this->freshAec(),
            cedente: [
                'RUT' => self::GENERICO_RUT,
                'RazonSocial' => self::GENERICO_RAZON_SOCIAL,
                'Direccion' => 'Direccion 456',
                'eMail' => 'cliente@example.com',
                'RUTAutorizado' => [
                    'RUT' => self::GENERICO_RUT,
                    'Nombre' => self::GENERICO_RAZON_SOCIAL,
                ],
            ],
            cesionario: [
                'RUT' => self::USUARIO_RUT,
                'RazonSocial' => 'Segundo Cesionario SpA',
                'Direccion' => 'Direccion 789',
                'eMail' => 'segundo@example.com',
            ],
            cesion: [
                'MontoCesion' => 119000,
                'UltimoVencimiento' => '2024-03-14',
            ],
            certificate: $this->certificateObject(),
        ));

        return base64_encode($aec->saveXml());
    }

    private function freshAec(): Aec
    {
        if ($this->aec === null) {
            $this->aec = $this->aecWorker()->build(new AecBag(
                source: $this->freshFacturaAfectaDocument(),
                cedente: [
                    'RUT' => self::EMISOR_RUT,
                    'RazonSocial' => self::EMISOR_RAZON_SOCIAL,
                    'Direccion' => 'Santa Cruz, Chile',
                    'eMail' => 'cedente@example.com',
                    'RUTAutorizado' => [
                        'RUT' => self::EMISOR_RUT,
                        'Nombre' => 'Administrador',
                    ],
                ],
                cesionario: [
                    'RUT' => self::GENERICO_RUT,
                    'RazonSocial' => self::GENERICO_RAZON_SOCIAL,
                    'Direccion' => 'Providencia, Santiago',
                    'eMail' => 'cliente@example.com',
                ],
                cesion: [
                    'MontoCesion' => 119000,
                    'UltimoVencimiento' => '2024-02-14',
                ],
                certificate: $this->certificateObject(),
            ));
        }

        return $this->aec;
    }

    private function freshCafFacturaAfecta(): CafInterface
    {
        return $this->freshCaf(codigoDocumento: 33, folioDesde: 1, folioHasta: 100);
    }

    private function freshCaf(
        int $codigoDocumento,
        int $folioDesde,
        ?int $folioHasta = null,
    ): CafInterface {
        $key = sprintf('%d:%d:%s', $codigoDocumento, $folioDesde, $folioHasta ?? '');

        if (!isset($this->cafCache[$key])) {
            $this->cafCache[$key] = $this->cafFakerWorker()->create(
                new Emisor(self::EMISOR_RUT, self::EMISOR_RAZON_SOCIAL),
                $codigoDocumento,
                $folioDesde,
                $folioHasta,
            );
        }

        return $this->cafCache[$key];
    }

    /**
     * Certificado para las operaciones que llaman al SII real. Usa el
     * certificado real de `LIBREDTE_CERTIFICATE_FILE`/
     * `LIBREDTE_CERTIFICATE_PASS` si está configurado (mismo patrón que
     * `libredte-lib-core`/`tests/env-tests` — copiar `tests/env-tests-dist`
     * a `tests/env-tests` y hacer `source` antes de correr los tests);
     * nunca se commitea nada, son variables de entorno de quien ejecuta los
     * tests. Sin certificado real configurado, cae a uno autofirmado y
     * desechable (`CertificateFaker`, mismo mecanismo que ya usa
     * `libredte-lib-core`/`tests/.../SiiAuthenticateFakeCertificateTest.php`)
     * — el SII lo rechazará siempre por no estar registrado, lo que
     * garantiza ejercitar la ruta de error real de estas operaciones sin
     * depender de credenciales. En ningún caso devuelve `null`: estos
     * fixtures nunca se saltan, solo cambia si pueden llegar a tener éxito.
     *
     * @return array{data: string, password: string}|array{certificate: string, privateKey: string}
     */
    public function siiCertificateRequest(): array
    {
        $certificate = $this->siiCertificateObject();

        if ($this->siiCertificateIsReal) {
            $file = (string) getenv('LIBREDTE_CERTIFICATE_FILE');
            $password = (string) getenv('LIBREDTE_CERTIFICATE_PASS');

            return [
                'data' => base64_encode((string) file_get_contents($file)),
                'password' => $password,
            ];
        }

        return [
            'certificate' => $certificate->getCertificate(),
            'privateKey' => $certificate->getPrivateKey(),
        ];
    }

    /**
     * RUT del titular del certificado de `siiCertificateRequest()` (real o
     * autofirmado), usado como parámetro `company`/`emisor` por las
     * operaciones que necesitan identificar a la empresa que llama al SII.
     */
    public function siiCompanyRut(): string
    {
        return $this->siiCertificateObject()->getId();
    }

    /**
     * Factura afecta (33) — timbrada con un CAF fake (nunca uno real
     * commiteado) y firmada con el certificado de `siiCertificateRequest()`
     * — para las operaciones del SII que necesitan un `doc`
     * (`sii_dte::sendXmlDocument`).
     *
     * @return string XML del documento, codificado en base64.
     */
    public function siiFacturaAfectaXml(): string
    {
        return base64_encode($this->freshSiiFacturaAfectaDocument()->saveXml());
    }

    /**
     * AEC (Cesión) de primera cesión, construido y firmado con el
     * certificado de `siiCertificateRequest()` sobre una factura afecta
     * fresca — para `sii_rtc::sendAec`, que necesita un AEC ya armado, no
     * solo un DTE.
     *
     * @return string XML del AEC, codificado en base64.
     */
    public function siiAecXml(): string
    {
        if ($this->siiAecXml === null) {
            $certificate = $this->siiCertificateObject();
            $dte = $this->freshSiiFacturaAfectaDocument();
            $emisorRut = $certificate->getId();

            $aec = $this->aecWorker()->build(new AecBag(
                source: $dte,
                cedente: [
                    'RUT' => $emisorRut,
                    'RazonSocial' => $certificate->getName(),
                    'Direccion' => 'Dirección 123',
                    'eMail' => $certificate->getEmail(),
                    'RUTAutorizado' => [
                        'RUT' => $emisorRut,
                        'Nombre' => $certificate->getName(),
                    ],
                ],
                cesionario: [
                    'RUT' => self::EMISOR_RUT,
                    'RazonSocial' => self::EMISOR_RAZON_SOCIAL,
                    'Direccion' => 'Direccion 456',
                    'eMail' => 'cesionario@example.com',
                ],
                cesion: [
                    'MontoCesion' => $dte->getMontoTotal(),
                    'UltimoVencimiento' => date('Y-m-d', strtotime('+30 days')),
                ],
                certificate: $certificate,
            ));

            $this->siiAecXml = base64_encode($aec->saveXml());
        }

        return $this->siiAecXml;
    }

    /**
     * Credenciales SMTP/IMAP para los fixtures `*_real.yaml` de
     * `Exchange` (`sender::send`/`receiver::receive`) — a diferencia de
     * `siiCertificateRequest()`, no hay un valor falso de respaldo: el host
     * SMTP/IMAP por defecto es Gmail real (no un ambiente de
     * certificación/sandbox como el del SII), así que sin
     * `MAIL_USERNAME`/`MAIL_PASSWORD` configurados se entrega `null` y el
     * fixture que lo use se salta (`FixturePlaceholderUnavailableException`).
     *
     * @return array{username: string, password: string}|null
     */
    public function mailTransport(): ?array
    {
        $username = (string) getenv('MAIL_USERNAME');
        $password = (string) getenv('MAIL_PASSWORD');

        if ($username === '' || $password === '') {
            return null;
        }

        return [
            'username' => $username,
            'password' => $password,
        ];
    }

    /**
     * Correo de `MAIL_USERNAME` — usado como remitente y destinatario en
     * `sender::send_real.yaml` (se manda un correo real a la misma cuenta,
     * nunca a un tercero). `null` si no está configurado, igual que
     * `mailTransport()`.
     */
    public function mailUsername(): ?string
    {
        $username = (string) getenv('MAIL_USERNAME');

        return $username !== '' ? $username : null;
    }

    /**
     * Contraseña de `MAIL_PASSWORD` — usada junto a `mailUsername()` en
     * `receiver::receive_real.yaml` para poder agregar, además de las
     * credenciales, opciones de búsqueda no destructivas
     * (`search.markAsSeen: false`) sin depender del objeto completo que
     * entrega `mailTransport()`. `null` si no está configurado, igual que
     * `mailTransport()`.
     */
    public function mailPassword(): ?string
    {
        $password = (string) getenv('MAIL_PASSWORD');

        return $password !== '' ? $password : null;
    }

    private function siiCertificateObject(): CertificateInterface
    {
        if ($this->siiCertificateObject === null) {
            $file = getenv('LIBREDTE_CERTIFICATE_FILE');
            $password = getenv('LIBREDTE_CERTIFICATE_PASS');

            if ($file !== false && $file !== '' && $password !== false) {
                try {
                    $this->siiCertificateObject = (new CertificateLoader())
                        ->loadFromFile($file, $password)
                    ;
                    $this->siiCertificateIsReal = true;
                } catch (Throwable) {
                    $this->siiCertificateObject = null;
                }
            }

            if ($this->siiCertificateObject === null) {
                $this->siiCertificateObject = (new CertificateFaker(new CertificateLoader()))
                    ->createFake(id: self::EMISOR_RUT, name: self::EMISOR_RAZON_SOCIAL)
                ;
                $this->siiCertificateIsReal = false;
            }
        }

        return $this->siiCertificateObject;
    }

    /**
     * Factura afecta (33), timbrada con un CAF fake (el CAF sigue siendo
     * generado en tiempo de test, nunca uno real commiteado) y firmada con
     * el certificado de `siiCertificateObject()` — sirve como DTE de origen
     * para `siiAecXml()`.
     */
    private function freshSiiFacturaAfectaDocument(): DocumentInterface
    {
        if ($this->siiFacturaAfectaDocument === null) {
            $certificate = $this->siiCertificateObject();
            $emisorRut = $certificate->getId();

            $caf = $this->cafFakerWorker()->create(
                new Emisor($emisorRut, $certificate->getName()),
                33,
                1,
            );

            $bag = new DocumentBag(
                parsedData: [
                    'Encabezado' => [
                        'IdDoc' => [
                            'TipoDTE' => 33,
                            'Folio' => 1,
                        ],
                        'Emisor' => [
                            'RUTEmisor' => $emisorRut,
                            'RznSoc' => $certificate->getName(),
                            'GiroEmis' => 'Servicios',
                            'DirOrigen' => 'Dirección 123',
                            'CmnaOrigen' => 'Santiago',
                        ],
                        'Receptor' => [
                            'RUTRecep' => self::GENERICO_RUT,
                            'RznSocRecep' => self::GENERICO_RAZON_SOCIAL,
                            'GiroRecep' => 'Servicios',
                            'DirRecep' => 'Santiago',
                            'CmnaRecep' => 'Santiago',
                        ],
                    ],
                    'Detalle' => [
                        [
                            'NmbItem' => 'Servicio de prueba',
                            'QtyItem' => 1,
                            'PrcItem' => 10000,
                        ],
                    ],
                ],
                caf: $caf,
                certificate: $certificate,
            );

            $this->siiFacturaAfectaDocument = $this->builderWorker()->build($bag)->getDocument();
        }

        return $this->siiFacturaAfectaDocument;
    }

    private function aecWorker(): AecWorkerInterface
    {
        $worker = Application::getInstance('test', true)
            ->getPackageRegistry()
            ->getPackage('billing')
            ->getComponent('ownership_transfer')
            ->getWorker('aec')
        ;
        assert($worker instanceof AecWorkerInterface);

        return $worker;
    }

    private function cafFakerWorker(): CafFakerWorkerInterface
    {
        $worker = Application::getInstance('test', true)
            ->getPackageRegistry()
            ->getPackage('billing')
            ->getComponent('identifier')
            ->getWorker('caf_faker')
        ;
        assert($worker instanceof CafFakerWorkerInterface);

        return $worker;
    }

    private function builderWorker(): BuilderWorkerInterface
    {
        $worker = Application::getInstance('test', true)
            ->getPackageRegistry()
            ->getPackage('billing')
            ->getComponent('document')
            ->getWorker('builder')
        ;
        assert($worker instanceof BuilderWorkerInterface);

        return $worker;
    }
}
