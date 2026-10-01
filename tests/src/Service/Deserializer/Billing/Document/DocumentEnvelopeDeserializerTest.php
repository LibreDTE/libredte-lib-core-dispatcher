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

namespace libredte\lib\TestsCoreDispatcher\Service\Deserializer\Billing\Document;

use Derafu\BackboneDispatcher\Exception\UnsupportedDataTypeException;
use libredte\lib\Core\Application;
use libredte\lib\Core\Package\Billing\Component\Document\Contract\DispatcherWorkerInterface;
use libredte\lib\Core\Package\Billing\Component\Document\Contract\DocumentEnvelopeInterface;
use libredte\lib\CoreDispatcher\Service\Deserializer\Billing\Document\DocumentEnvelopeDeserializer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * NOTA: acá solo se cubre el camino de rechazo. El camino feliz necesita un
 * XML de "sobre" (envelope) real y válidamente firmado — los propios
 * fixtures de `libredte-lib-core` para esto viven fuera del control de
 * versiones (`.gitignore`ados, ej. `tests/fixtures/caf/*.xml`) o son
 * artefactos generados en tiempo de test, así que ninguno sobrevive a un
 * `composer install` fresco de la forma en que los tests de este paquete lo
 * necesitan (CI público, sin un checkout de desarrollo local al cual
 * recurrir). Construir uno desde cero implica pasar por la construcción
 * completa y real de un documento firmado
 * (`DispatcherWorkerInterface::create()` a partir de un `DocumentBag` real),
 * que es una tarea más grande que la que necesitaron los demás
 * Deserializers de acá — queda como pendiente, registrado en
 * `docker-python3.14-caddy-server/sites/CLAUDE.md`.
 */
#[CoversClass(DocumentEnvelopeDeserializer::class)]
class DocumentEnvelopeDeserializerTest extends TestCase
{
    private DocumentEnvelopeDeserializer $deserializer;

    protected function setUp(): void
    {
        $packageRegistry = Application::getInstance('test', true)->getPackageRegistry();

        $dispatcherWorker = $packageRegistry
            ->getPackage('billing')
            ->getComponent('document')
            ->getWorker('dispatcher')
        ;
        assert($dispatcherWorker instanceof DispatcherWorkerInterface);

        $this->deserializer = new DocumentEnvelopeDeserializer($dispatcherWorker);
    }

    public function testRejectsNonStringData(): void
    {
        $this->expectException(UnsupportedDataTypeException::class);

        $this->deserializer->deserialize(['not' => 'a string'], DocumentEnvelopeInterface::class);
    }

    public function testRejectsDataThatIsNotValidBase64(): void
    {
        $this->expectException(UnsupportedDataTypeException::class);
        $this->expectExceptionMessage('requiere datos codificados en base64 válido');

        $this->deserializer->deserialize('<EnvioDTE/>', DocumentEnvelopeInterface::class);
    }
}
