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
use Derafu\BackboneDispatcher\Exception\UnsupportedDataTypeException;
use libredte\lib\Core\Application;
use libredte\lib\Core\Package\Billing\Component\Document\Contract\DocumentBagManagerWorkerInterface;
use libredte\lib\Core\Package\Billing\Component\OwnershipTransfer\Support\AecBag;
use libredte\lib\CoreDispatcher\Service\Deserializer\Billing\OwnershipTransfer\AecBagDeserializer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * El rechazo de un `source` que la fábrica no construye como documento XML.
 *
 * No se prueba con un fixture YAML: con el `ObjectFactory` real `source` es
 * obligatorio y siempre se construye como documento XML o la fábrica lanza su
 * propia excepción, así que este rechazo solo se alcanza con una fábrica que
 * devuelva otro objeto.
 */
#[CoversClass(AecBagDeserializer::class)]
class AecBagDeserializerTest extends TestCase
{
    public function testRejectsASourceThatTheFactoryDoesNotBuildAsAnXmlDocument(): void
    {
        $factory = new class () implements ObjectFactoryInterface {
            public function create(array|string|null $data, string $class): object
            {
                return new stdClass();
            }

            public function supports(string $class): bool
            {
                return true;
            }
        };

        $deserializer = new AecBagDeserializer($factory, $this->documentBagManager());

        $this->expectException(UnsupportedDataTypeException::class);

        $deserializer->deserialize([
            'source' => 'xml',
            'cedente' => [],
            'cesionario' => [],
            'cesion' => [],
        ], AecBag::class);
    }

    private function documentBagManager(): DocumentBagManagerWorkerInterface
    {
        $worker = Application::getInstance('test', true)
            ->getPackageRegistry()
            ->getPackage('billing')
            ->getComponent('document')
            ->getWorker('document_bag_manager')
        ;

        $this->assertInstanceOf(DocumentBagManagerWorkerInterface::class, $worker);

        return $worker;
    }
}
