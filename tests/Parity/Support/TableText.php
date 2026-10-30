<?php

namespace Tests\Parity\Support;

use DOMDocument;
use DOMNode;
use DOMXPath;

/**
 * The cell texts of a page's table, for comparing two apps' lists. Whitespace runs collapse to
 * one space and the ends are trimmed; nothing else is touched.
 */
final class TableText
{
    /**
     * @return list<list<string>> One list of cell texts (th and td) per row of the first table matching `$xpath`;
     *                            rows of tables nested in it are left out.
     */
    public static function rows(string $html, string $xpath = '//table'): array
    {
        $document = new DOMDocument;
        $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $page = new DOMXPath($document);

        $rows = [];
        $table = $page->query("($xpath)[1]")?->item(0);

        foreach ($table === null ? [] : $page->query('.//tr', $table) as $row) {
            if (! self::belongsTo($row, $table)) {
                continue;
            }

            $cells = [];

            foreach ($page->query('./th|./td', $row) ?: [] as $cell) {
                $cells[] = trim((string) preg_replace('/\s+/u', ' ', $cell->textContent));
            }

            $rows[] = $cells;
        }

        return $rows;
    }

    private static function belongsTo(DOMNode $row, DOMNode $table): bool
    {
        for ($node = $row->parentNode; $node !== null; $node = $node->parentNode) {
            if ($node->nodeName === 'table') {
                return $node->isSameNode($table);
            }
        }

        return false;
    }
}
