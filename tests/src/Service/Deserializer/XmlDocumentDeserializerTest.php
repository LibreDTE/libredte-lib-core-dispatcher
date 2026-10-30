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

namespace libredte\lib\TestsCoreDispatcher\Service\Deserializer;

use Derafu\BackboneDispatcher\Exception\UnsupportedDataTypeException;
use Derafu\Xml\XmlDocument;
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
        $this->expectException(UnsupportedDataTypeException::class);

        $this->deserializer->deserialize(['not' => 'a string'], XmlDocument::class);
    }

    public function testRejectsDataThatIsNotValidBase64(): void
    {
        $this->expectException(UnsupportedDataTypeException::class);
        $this->expectExceptionMessage('requiere datos codificados en base64 válido');

        $this->deserializer->deserialize('<Root/>', XmlDocument::class);
    }
}
