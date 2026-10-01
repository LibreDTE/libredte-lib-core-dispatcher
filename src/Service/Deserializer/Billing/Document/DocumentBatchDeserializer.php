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

namespace libredte\lib\CoreDispatcher\Service\Deserializer\Billing\Document;

use Derafu\BackboneDispatcher\Abstract\AbstractDeserializer;
use Derafu\BackboneDispatcher\Contract\ObjectFactoryInterface;
use Derafu\BackboneDispatcher\Exception\UnsupportedDataTypeException;
use Derafu\Certificate\Contract\CertificateInterface;
use libredte\lib\Core\Package\Billing\Component\Document\Support\DocumentBatch;
use libredte\lib\Core\Package\Billing\Component\TradingParties\Contract\EmisorInterface;

/**
 * Construye un `DocumentBatch` a partir de datos de un arreglo.
 *
 * El contenido del lote (`inputData`) llega codificado en base64, porque puede
 * ser binario (por ejemplo una planilla XLSX), y se decodifica aquí. La ruta de
 * un archivo (`inputFile`) nunca se toma del arreglo: un cliente no puede
 * indicar un archivo del servidor.
 *
 * Los campos anidados opcionales (`emisor` y `certificate`) se deserializan a
 * través del `ObjectFactoryInterface` inyectado.
 */
class DocumentBatchDeserializer extends AbstractDeserializer
{
    public function __construct(
        private readonly ObjectFactoryInterface $objectFactory,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function deserialize(array|string $data, string $class): object
    {
        $data = $this->assertArray($data);

        $batch = new DocumentBatch(
            inputData: $this->decodeInputData($data['inputData'] ?? null),
            options: $data['options'] ?? null,
        );

        $batch->setEmisor($this->objectFactory->create(
            $data['emisor'] ?? null,
            EmisorInterface::class,
        ));
        $batch->setCertificate($this->objectFactory->create(
            $data['certificate'] ?? null,
            CertificateInterface::class,
        ));

        return $batch;
    }

    /**
     * Decodifica el contenido del lote, que debe venir en base64 válido.
     *
     * @param mixed $inputData Contenido en base64, o `null` si no se indicó.
     * @return string|null Contenido decodificado, o `null` si no se indicó.
     * @throws UnsupportedDataTypeException Si no es un string en base64 válido.
     */
    private function decodeInputData(mixed $inputData): ?string
    {
        if ($inputData === null) {
            return null;
        }

        $decoded = is_string($inputData)
            ? base64_decode($inputData, true)
            : false
        ;

        if ($decoded === false) {
            throw new UnsupportedDataTypeException([
                '{deserializer} requires the inputData field encoded in base64.',
                'deserializer' => static::class,
            ]);
        }

        return $decoded;
    }
}
