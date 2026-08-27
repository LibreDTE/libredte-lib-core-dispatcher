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

use RuntimeException;

/**
 * Señala que un placeholder `{{método}}` de un fixture no pudo resolverse en
 * este entorno (el método de `FixtureContext` devolvió `null`, ej. sin
 * certificado real disponible) — `OperationFixturesTest` la captura para
 * marcar el test como saltado, en vez de fallarlo.
 */
final class FixturePlaceholderUnavailableException extends RuntimeException
{
    public function __construct(string $placeholder)
    {
        parent::__construct(sprintf(
            'Placeholder "{{%s}}" is not available in this environment.',
            $placeholder,
        ));
    }
}
