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

namespace libredte\lib\CoreDispatcher\Service\Deserializer;

use Derafu\BackboneDispatcher\Abstract\AbstractDeserializer;
use Derafu\Xml\XmlDocument;

/**
 * Construye un `XmlDocument` a partir de un string XML codificado en
 * base64.
 */
class XmlDocumentDeserializer extends AbstractDeserializer
{
    /**
     * {@inheritDoc}
     */
    public function deserialize(array|string $data, string $class): object
    {
        $data = $this->assertString($data);

        $document = new XmlDocument();
        $document->loadXml(base64_decode($data));

        return $document;
    }
}
