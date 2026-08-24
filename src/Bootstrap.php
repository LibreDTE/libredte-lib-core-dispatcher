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
 * cableado de forma declarativa en el `config/services.yaml` de este
 * paquete, de la misma forma en que `libredte-lib-core` cablea sus propios
 * servicios; esta clase solo le pide el resultado final al contenedor.
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
