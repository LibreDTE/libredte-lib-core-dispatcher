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
