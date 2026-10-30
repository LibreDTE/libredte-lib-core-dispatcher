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
