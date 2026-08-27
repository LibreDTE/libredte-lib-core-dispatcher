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

namespace libredte\lib\CoreDispatcher\Service\Deserializer\Billing\OwnershipTransfer;

use Derafu\BackboneDispatcher\Abstract\AbstractDeserializer;
use Derafu\BackboneDispatcher\Contract\ObjectFactoryInterface;
use Derafu\BackboneDispatcher\Exception\UnsupportedDataTypeException;
use Derafu\Certificate\Contract\CertificateInterface;
use Derafu\Xml\Contract\XmlDocumentInterface;
use libredte\lib\Core\Package\Billing\Component\Document\Contract\DocumentBagManagerWorkerInterface;
use libredte\lib\Core\Package\Billing\Component\Document\Contract\DocumentInterface;
use libredte\lib\Core\Package\Billing\Component\OwnershipTransfer\Entity\Aec;
use libredte\lib\Core\Package\Billing\Component\OwnershipTransfer\Support\AecBag;

/**
 * Construye un `AecBag` a partir de datos de un arreglo, deserializando
 * recursivamente su campo anidado `certificate` a través del
 * `ObjectFactoryInterface` inyectado.
 *
 * `source` (`DocumentInterface|Aec`) se resuelve inspeccionando la
 * etiqueta raíz del XML recibido: `AEC` construye un `Aec` directamente
 * (re-cesión, su constructor es trivial); cualquier otra (asumido `DTE`)
 * pasa por `DocumentBagManagerWorkerInterface::create()` y usa el
 * documento resultante (primera cesión).
 */
class AecBagDeserializer extends AbstractDeserializer
{
    public function __construct(
        private readonly ObjectFactoryInterface $objectFactory,
        private readonly DocumentBagManagerWorkerInterface $documentBagManager,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function deserialize(array|string $data, string $class): object
    {
        $data = $this->assertArray($data);

        $this->assertKeys($data, ['source', 'cedente', 'cesionario', 'cesion']);

        return new AecBag(
            source: $this->resolveSource($data['source']),
            cedente: $data['cedente'],
            cesionario: $data['cesionario'],
            cesion: $data['cesion'],
            certificate: $this->objectFactory->create(
                $data['certificate'] ?? null,
                CertificateInterface::class,
            ),
        );
    }

    private function resolveSource(mixed $source): DocumentInterface|Aec
    {
        $xmlDocument = $this->objectFactory->create(
            $source,
            XmlDocumentInterface::class,
        );

        if (!$xmlDocument instanceof XmlDocumentInterface) {
            throw UnsupportedDataTypeException::forDeserializer(
                static::class,
                XmlDocumentInterface::class,
            );
        }

        $root = $xmlDocument->getDomDocument()->documentElement->localName ?? '';

        if ($root === 'AEC') {
            return new Aec($xmlDocument);
        }

        return $this->documentBagManager->create($xmlDocument)->getDocument();
    }
}
