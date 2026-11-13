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
use Derafu\BackboneDispatcher\Contract\SafeExplorerInterface;
use Derafu\BackboneDispatcher\Testing\DispatcherConformanceTestCase;
use libredte\lib\CoreDispatcher\Bootstrap;
use libredte\lib\TestsCoreDispatcher\Fixture\TestServices;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Reglas que cumple todo dispatcher, comprobadas contra el explorador real
 * (`Bootstrap::bootExplorer()`) y la fábrica de objetos real, sin datos ni
 * archivos: nombres, documentación, ejemplos y tipos de las operaciones de
 * `libredte-lib-core`.
 */
#[CoversNothing]
class ConformanceTest extends DispatcherConformanceTestCase
{
    public static function tearDownAfterClass(): void
    {
        TestServices::cleanup();

        parent::tearDownAfterClass();
    }

    protected static function explorer(): SafeExplorerInterface
    {
        return Bootstrap::bootExplorer('test', true);
    }

    protected static function objectFactory(): ObjectFactoryInterface
    {
        return TestServices::objectFactory();
    }

    /**
     * El primer miembro de las uniones de `document_response` y `aec`
     * (`AbstractExchangeDocument` y `Aec`) es para quien llama desde PHP y ya
     * tiene el objeto; el dispatcher construye los otros miembros
     * (`XmlDocumentInterface` y `string`) desde el XML en base64, así que ese
     * miembro no tiene deserializador a propósito. Los registros del catálogo
     * son `object` porque su clase depende del repositorio consultado.
     */
    protected function exclusions(): array
    {
        $razon = 'La unión tiene un miembro solo para llamadores PHP; el dispatcher construye los demás desde base64.';

        $catalogo = 'El tipo del registro depende del parámetro `repository` y PHP no lo expresa; los registros de todos los repositorios se serializan con `toArray()` o `jsonSerialize()`.';

        return [
            'return_types_are_serializable' => [
                'system.repository.catalog::find' => $catalogo,
                'system.repository.catalog::find_one_by' => $catalogo,
            ],
            'object_parameters_have_a_deserializer' => [
                'billing.exchange.document_response::validate_schema' => $razon,
                'billing.exchange.document_response::validate_signature' => $razon,
                'billing.ownership_transfer.aec::validate_schema' => $razon,
                'billing.ownership_transfer.aec::validate_signature' => $razon,
            ],
        ];
    }

    /**
     * Los parámetros de entrada de las operaciones van en camelCase; las
     * salidas son las que van en snake_case.
     */
    protected function parameterNamePattern(): ?string
    {
        return '/^[a-z][a-zA-Z0-9]*$/';
    }
}
