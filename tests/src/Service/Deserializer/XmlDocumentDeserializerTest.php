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

namespace libredte\lib\TestsCoreDispatcher\Service\Deserializer;

use Derafu\Xml\XmlDocument;
use InvalidArgumentException;
use libredte\lib\CoreDispatcher\Service\Deserializer\XmlDocumentDeserializer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(XmlDocumentDeserializer::class)]
class XmlDocumentDeserializerTest extends TestCase
{
    private XmlDocumentDeserializer $deserializer;

    protected function setUp(): void
    {
        $this->deserializer = new XmlDocumentDeserializer();
    }

    public function testDeserializesABase64EncodedXmlStringIntoARealXmlDocument(): void
    {
        $xml = '<?xml version="1.0" encoding="ISO-8859-1"?><Root><Value>1</Value></Root>';

        $document = $this->deserializer->deserialize(
            base64_encode($xml),
            XmlDocument::class,
        );

        $this->assertInstanceOf(XmlDocument::class, $document);
        $this->assertSame('Root', $document->getName());
    }

    public function testRejectsNonStringData(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->deserializer->deserialize(['not' => 'a string'], XmlDocument::class);
    }
}
