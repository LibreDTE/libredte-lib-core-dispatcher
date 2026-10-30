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

/**
 * Archivo de configuración para PHP CS Fixer.
 */

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$dir = __DIR__;

$finder = Finder::create()
    ->in($dir)
    ->exclude('var')
    ->exclude('tmp')
;

return (new Config())
    // Permitir reglas riesgosas que cambian la lógica del código.
    ->setRiskyAllowed(true)
    // Basado en PSR-12, la última recomendación de estilo.
    ->setRules([
        '@PSR12' => true,
        // Añadir "declare(strict_types=1);" a los archivos.
        // Esto los añade así <?php declare(strict_types=1); sin embargo se
        // recomienda editarlos o añadirlos manualmente en líneas separadas.
        'declare_strict_types' => true,
        // Indentar con espacios.
        'indentation_type' => true,
        // Ordenar los "use" alfabéticamente.
        'ordered_imports' => [
            'sort_algorithm' => 'alpha',
        ],
        // Eliminar imports no usados.
        'no_unused_imports' => true,
        // Un import por declaración.
        'single_import_per_statement' => true,
        // Convertir arreglos a la sintaxis corta "[]".
        'array_syntax' => [
            'syntax' => 'short',
        ],
        // Añadir comas finales en arreglos de varias líneas.
        'trailing_comma_in_multiline' => true,
        // Separar constantes y propiedades.
        'class_attributes_separation' => [
            'elements' => [
                'const' => 'one',
                'property' => 'one',
                'method' => 'one',
            ],
        ],
        // Reemplazar strpos con un return de bool.
        // Ejemplo usar: str_contains().
        'modernize_strpos' => true,
        // Convertir funciones anónimas a funciones flecha.
        'use_arrow_functions' => true,
        // Usar constructores de PHPUnit en lugar de métodos de fábrica.
        'php_unit_construct' => true,
        // Usar aserciones más estrictas en PHPUnit.
        // Ejemplo: usar assertSame() en vez de assertEquals().
        'php_unit_strict' => true,
    ])
    ->setLineEnding("\n")
    ->setCacheFile($dir . '/var/cache/php-cs-fixer.cache')
    ->setFinder($finder)
;
