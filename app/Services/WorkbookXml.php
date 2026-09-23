<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;

trait WorkbookXml
{
    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private function document(string $xml): DOMDocument
    {
        $doc = new DOMDocument;
        if (! $doc->loadXML($xml, LIBXML_NONET)) {
            throw new RuntimeException('Invalid report template XML.');
        }

        return $doc;
    }

    private function xpath(DOMDocument $doc): DOMXPath
    {
        $xp = new DOMXPath($doc);
        $xp->registerNamespace('s', self::NS);

        return $xp;
    }

    private function cell(DOMElement $row, string $column, mixed $value): void
    {
        $doc = $row->ownerDocument;
        $ref = $column.$row->getAttribute('r');
        $xp = $this->xpath($doc);
        $cell = $xp->query('s:c[@r="'.$ref.'"]', $row)->item(0);
        if (! $cell) {
            $cell = $doc->createElementNS(self::NS, 'c');
            $cell->setAttribute('r', $ref);
            $row->appendChild($cell);
        }
        while ($cell->firstChild) {
            $cell->removeChild($cell->firstChild);
        }
        $cell->removeAttribute('t');
        if ($value === null || $value === '') {
            return;
        }
        if (is_int($value) || is_float($value)) {
            $cell->appendChild($doc->createElementNS(self::NS, 'v', (string) $value));

            return;
        }
        // Inline strings prevent formula injection and preserve literal references.
        $cell->setAttribute('t', 'inlineStr');
        $inline = $doc->createElementNS(self::NS, 'is');
        $text = $doc->createElementNS(self::NS, 't');
        $text->setAttribute('xml:space', 'preserve');
        $value = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', (string) $value);
        $text->appendChild($doc->createTextNode(mb_substr($value ?? '', 0, 32767)));
        $inline->appendChild($text);
        $cell->appendChild($inline);
    }
}
