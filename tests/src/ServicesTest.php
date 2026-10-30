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

use DateTimeImmutable;
use Derafu\BackboneDispatcher\Contract\ObjectFactoryInterface;
use Derafu\BackboneDispatcher\Contract\SafeDispatcherInterface;
use Derafu\BackboneDispatcher\Contract\SafeExplorerInterface;
use Derafu\BackboneDispatcher\Exception\OperationNotAllowedException;
use Derafu\BackboneDispatcher\ValueObject\OperationRequest;
use Derafu\Kernel\Environment;
use Derafu\Xml\Contract\XmlDocumentInterface;
use libredte\lib\Core\Application;
use libredte\lib\Core\Package\Billing\Component\Identifier\Contract\CafFakerWorkerInterface;
use libredte\lib\Core\Package\Billing\Component\TradingParties\Entity\Emisor;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Prueba el YAML publicado y aportes acumulativos de un consumidor real.
 */
#[CoversNothing]
final class ServicesTest extends TestCase
{
    protected function tearDown(): void
    {
        (new Filesystem())->remove(dirname(__DIR__) . '/fixtures/services/var');
    }

    public function testConsumerDeserializerAccumulatesWithoutReplacingLibraryDeserializers(): void
    {
        $application = $this->consumerApplication();
        $factory = $application->getService('test.object_factory');
        self::assertInstanceOf(ObjectFactoryInterface::class, $factory);
        $date = $factory->create('2026-10-29T12:34:56+00:00', DateTimeImmutable::class);
        self::assertInstanceOf(DateTimeImmutable::class, $date);
        self::assertSame('2026-10-29T12:34:56+00:00', $date->format(DATE_ATOM));

        $xml = $factory->create(base64_encode('<consumer>preserved</consumer>'), XmlDocumentInterface::class);
        self::assertInstanceOf(XmlDocumentInterface::class, $xml);
        self::assertStringContainsString('<consumer>preserved</consumer>', $xml->getXml());
    }

    public function testDiscoveryAndDispatchShareTheAttributePolicyAndConsumerEnvironment(): void
    {
        $application = $this->consumerApplication();
        $explorer = $application->getService(SafeExplorerInterface::class);
        self::assertInstanceOf(SafeExplorerInterface::class, $explorer);
        $allowed = $explorer->getOperation('billing', 'identifier', 'caf_loader', 'load');
        self::assertTrue($allowed->isSuccess());
        $denied = $explorer->getOperation('billing', 'integration', 'sii_lazy', 'authenticate');
        self::assertFalse($denied->isSuccess());
        $problem = $denied->getProblem();
        self::assertNotNull($problem);
        self::assertSame(OperationNotAllowedException::class, $problem->toArray()['title']);
        self::assertSame('test', $problem->getEnvironment());
        self::assertTrue($problem->isDebug());

        $dispatcher = $application->getService(SafeDispatcherInterface::class);
        self::assertInstanceOf(SafeDispatcherInterface::class, $dispatcher);
        $result = $dispatcher->dispatch(new OperationRequest('billing', 'integration', 'sii_lazy', 'authenticate'));
        self::assertFalse($result->isSuccess());
        $problem = $result->getProblem();
        self::assertNotNull($problem);
        self::assertSame(OperationNotAllowedException::class, $problem->toArray()['title']);
        self::assertSame('test', $problem->getEnvironment());
        self::assertTrue($problem->isDebug());
    }

    public function testInstalledYamlLoadsWithoutExtraSearchPathsAndDispatchesARealOperation(): void
    {
        $root = dirname(__DIR__, 2);
        $directory = sys_get_temp_dir() . '/libredte-dispatcher-services-' . bin2hex(random_bytes(8));
        $installed = $directory . '/vendor/libredte/libredte-lib-core-dispatcher';
        $filesystem = new Filesystem();
        $filesystem->mkdir([$directory . '/config', $installed . '/resources/config']);
        try {
            $filesystem->copy($root . '/resources/config/libredte-dispatcher-services.yaml', $installed . '/resources/config/libredte-dispatcher-services.yaml');
            $filesystem->symlink($root . '/vendor/derafu', $directory . '/vendor/derafu');
            $filesystem->symlink($root . '/vendor/libredte/libredte-lib-core', $directory . '/vendor/libredte/libredte-lib-core');
            $filesystem->dumpFile($directory . '/config/services.yaml', "imports:\n    - { resource: '../vendor/libredte/libredte-lib-core-dispatcher/resources/config/libredte-dispatcher-services.yaml' }\n");

            // El loader no recibe rutas auxiliares: todos los imports publicados
            // deben resolverse desde la estructura instalada por Composer.
            $container = new ContainerBuilder();
            (new YamlFileLoader($container, new FileLocator()))->load($directory . '/config/services.yaml');
            $application = new Application(new Environment('test', true, ['PROJECT_DIR' => $directory]));
            $worker = $application->getPackageRegistry()->getPackage('billing')->getComponent('identifier')->getWorker('caf_faker');
            self::assertInstanceOf(CafFakerWorkerInterface::class, $worker);
            $caf = $worker->create(new Emisor('76192083-9'), 46, 1, 100);
            $dispatcher = $application->getService(SafeDispatcherInterface::class);
            self::assertInstanceOf(SafeDispatcherInterface::class, $dispatcher);
            $result = $dispatcher->dispatch(new OperationRequest('billing', 'identifier', 'caf_loader', 'load', ['xml' => base64_encode($caf->getXml())]));
            self::assertTrue($result->isSuccess());
            $value = $result->getValue();
            self::assertIsArray($value);
            self::assertSame(46, $value['tipoDocumento']);
        } finally {
            $filesystem->remove($directory);
        }
    }

    private function consumerApplication(): Application
    {
        return new Application(new Environment('test', true, [
            'PROJECT_DIR' => dirname(__DIR__) . '/fixtures/services',
        ]));
    }
}
