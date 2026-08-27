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

namespace libredte\lib\TestsCoreDispatcher;

use Derafu\BackboneDispatcher\Exception\OperationNotAllowedException;
use Derafu\BackboneDispatcher\ValueObject\OperationRequest;
use libredte\lib\CoreDispatcher\Bootstrap;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Confirma que `TaggedOperationPolicy` (activa en `config/services.yaml`)
 * efectivamente bloquea el despacho de un método público que no tiene el
 * atributo `#[Operation]` — `billing.integration.sii_lazy::authenticate` es
 * público (lo consumen otros workers para autenticarse contra el SII) pero
 * no está pensado para invocarse directamente desde afuera de la librería.
 */
#[CoversNothing]
class OperationPolicyTest extends TestCase
{
    public function testRejectsAPublicMethodWithoutTheOperationAttribute(): void
    {
        $dispatcher = Bootstrap::boot('test', true);

        $request = new OperationRequest(
            'billing',
            'integration',
            'sii_lazy',
            'authenticate',
            [],
        );

        $result = $dispatcher->dispatch($request);

        $this->assertFalse($result->isSuccess());

        $problem = $result->getProblem();
        $this->assertNotNull($problem);
        $this->assertSame(
            OperationNotAllowedException::class,
            $problem->toArray()['title'],
        );
    }
}
