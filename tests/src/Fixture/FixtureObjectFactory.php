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

namespace libredte\lib\TestsCoreDispatcher\Fixture;

use Derafu\BackboneDispatcher\Contract\DeserializerInterface;
use Derafu\BackboneDispatcher\Contract\ObjectFactoryInterface;
use Derafu\Kernel\Environment;
use libredte\lib\Core\Application;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Entrega el `ObjectFactory` real del dispatcher (el que resuelve cada
 * deserializador registrado) y los propios deserializadores, para los tests dirigidos por fixtures YAML.
 *
 * El servicio no es público en el contenedor de producción: se obtiene con
 * el proyecto fixture `tests/fixtures/services`, que lo expone como
 * `test.object_factory`, y los deserializadores como `test.deserializers`.
 */
final class FixtureObjectFactory
{
    private static ?Application $application = null;

    /**
     * Crea el `ObjectFactory` del dispatcher.
     */
    public static function create(): ObjectFactoryInterface
    {
        $factory = self::application()->getService('test.object_factory');

        if (!$factory instanceof ObjectFactoryInterface) {
            throw new RuntimeException(sprintf(
                'The service "test.object_factory" must be an instance of %s.',
                ObjectFactoryInterface::class,
            ));
        }

        return $factory;
    }

    /**
     * Entrega el deserializador registrado para `$type`, para llamarlo
     * directamente: a diferencia de `ObjectFactory::create()`, no convierte
     * en `NoDeserializerFoundException` el rechazo de un dato no soportado.
     */
    public static function deserializer(string $type): DeserializerInterface
    {
        $service = self::application()->getService('test.deserializers');

        if (!$service instanceof DeserializerLocator) {
            throw new RuntimeException(sprintf(
                'The service "test.deserializers" must be an instance of %s.',
                DeserializerLocator::class,
            ));
        }

        $locator = $service->deserializers;

        if (!$locator->has($type)) {
            throw new RuntimeException(sprintf('No deserializer is registered for "%s".', $type));
        }

        $deserializer = $locator->get($type);

        if (!$deserializer instanceof DeserializerInterface) {
            throw new RuntimeException(sprintf(
                'The deserializer of "%s" must be an instance of %s.',
                $type,
                DeserializerInterface::class,
            ));
        }

        return $deserializer;
    }

    /**
     * Elimina el contenedor compilado que genera el proyecto fixture.
     */
    public static function cleanup(): void
    {
        self::$application = null;
        (new Filesystem())->remove(self::projectDir() . '/var');
    }

    private static function application(): Application
    {
        return self::$application ??= new Application(new Environment('test', true, [
            'PROJECT_DIR' => self::projectDir(),
        ]));
    }

    private static function projectDir(): string
    {
        return dirname(__DIR__, 2) . '/fixtures/services';
    }
}
