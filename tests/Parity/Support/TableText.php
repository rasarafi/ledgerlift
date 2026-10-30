<?php

namespace Tests\Parity\Support;

use DOMDocument;
use DOMXPath;

/**
 * The cell texts of a page's table, for comparing two apps' lists. Whitespace runs collapse to
 * one space and the ends are trimmed; nothing else is touched.
 */
final class TableText
{
    /**
     * @return list<list<string>> One list of cell texts (th and td) per row of the first table matching `$xpath`.
     */
    public static function rows(string $html, string $xpath = '//table'): array
    {
        $document = new DOMDocument;
        $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $page = new DOMXPath($document);

        $rows = [];

        foreach ($page->query($xpath.'[1]//tr') ?: [] as $row) {
            $cells = [];

            foreach ($page->query('./th|./td', $row) ?: [] as $cell) {
                $cells[] = trim((string) preg_replace('/\s+/u', ' ', $cell->textContent));
            }

            $rows[] = $cells;
        }

        return $rows;
    }
}
