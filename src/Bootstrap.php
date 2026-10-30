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

namespace libredte\lib\CoreDispatcher;

use Derafu\BackboneDispatcher\Contract\SafeDispatcherInterface;
use Derafu\BackboneDispatcher\Contract\SafeExplorerInterface;
use Derafu\Kernel\Contract\EnvironmentInterface;
use libredte\lib\Core\Application;

/**
 * Construye un `SafeDispatcherInterface`/`SafeExplorerInterface` cableados
 * para exponer/explorar las operaciones de `libredte-lib-core`.
 *
 * Autocontenido: lo único que necesita de `libredte-lib-core` es su propia
 * `Application`. Todo servicio que provee este paquete —los Deserializers,
 * la cadena de dispatch propia de `derafu/backbone-dispatcher`— está
 * cableado de forma declarativa en
 * `resources/config/libredte-dispatcher-services.yaml` de este paquete.
 * Esta clase solo le pide el resultado final al contenedor.
 *
 * `boot()` y `bootExplorer()` son el punto de entrada público de este
 * paquete: cualquier consumidor externo los invoca por nombre —código PHP
 * normal, un wrapper de API/consola, o un binding hacia otro lenguaje que
 * los resuelve dinámicamente, sin chequeo de tipos en tiempo de
 * compilación—. Su firma (nombre y orden de los parámetros posicionales)
 * es parte del contrato público: cambiarla es un breaking change aunque
 * PHP mismo no se queje.
 */
final class Bootstrap
{
    public static function boot(
        string $environment = EnvironmentInterface::PRODUCTION,
        bool $debug = false,
    ): SafeDispatcherInterface {
        $dispatcher = Application::getInstance($environment, $debug)
            ->getService(SafeDispatcherInterface::class)
        ;

        assert($dispatcher instanceof SafeDispatcherInterface);

        return $dispatcher;
    }

    public static function bootExplorer(
        string $environment = EnvironmentInterface::PRODUCTION,
        bool $debug = false,
    ): SafeExplorerInterface {
        $explorer = Application::getInstance($environment, $debug)
            ->getService(SafeExplorerInterface::class)
        ;

        assert($explorer instanceof SafeExplorerInterface);

        return $explorer;
    }
}
