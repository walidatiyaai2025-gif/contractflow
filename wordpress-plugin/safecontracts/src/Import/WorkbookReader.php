<?php

declare(strict_types=1);

namespace SafeContracts\Import;

use DOMDocument;
use DOMElement;
use DOMXPath;
use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

final class WorkbookReader
{
    private const MAIN_NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    private const REL_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const PACKAGE_REL_NS = 'http://schemas.openxmlformats.org/package/2006/relationships';
    private const MAX_ZIP_ENTRIES = 512;
    private const MAX_UNCOMPRESSED_BYTES = 67108864;
    private const MAX_SHEETS = 32;
    private const MAX_COLUMNS = 128;
    private const MAX_ROWS = 50000;

    public function discover(string $path): array
    {
        $zip = $this->open($path);
        try {
            $shared = $this->sharedStrings($zip);
            $sheets = [];
            foreach ($this->sheetDefinitions($zip) as $definition) {
                $header = $this->firstHeader($this->sheetRows($zip, $definition['path'], $shared, 20));
                $sheets[] = ['name' => $definition['name'], 'path' => $definition['path'], 'header_row' => $header['row_number'], 'headers' => $header['headers']];
            }
            if ($sheets === []) {
                throw new InvalidArgumentException('Workbook does not contain importable worksheets.');
            }
            return ['sheets' => $sheets];
        } finally {
            $zip->close();
        }
    }

    public function rows(string $path, string $sheetName, int $headerRow, int $limit = 5000): array
    {
        $limit = max(1, min(self::MAX_ROWS, $limit));
        $zip = $this->open($path);
        try {
            $definition = null;
            foreach ($this->sheetDefinitions($zip) as $candidate) {
                if ($candidate['name'] === $sheetName) {
                    $definition = $candidate;
                    break;
                }
            }
            if ($definition === null) {
                throw new InvalidArgumentException('Selected workbook sheet was not found.');
            }
            $rows = $this->sheetRows($zip, $definition['path'], $this->sharedStrings($zip), min(self::MAX_ROWS, $limit + max(1, $headerRow)));
            return array_values(array_filter($rows, static fn (array $row): bool => $row['row_number'] > $headerRow));
        } finally {
            $zip->close();
        }
    }

    public static function normalizeHeader(string $header): string
    {
        $header = trim(strip_tags($header));
        $header = preg_replace('/\s+/u', ' ', $header) ?? '';
        return function_exists('mb_strtolower') ? mb_strtolower($header, 'UTF-8') : strtolower($header);
    }

    private function open(string $path): ZipArchive
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('SafeContracts XLSX import requires PHP ZipArchive.');
        }
        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidArgumentException('Workbook file is unavailable.');
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new InvalidArgumentException('Workbook XLSX package cannot be opened.');
        }
        if ($zip->numFiles <= 0 || $zip->numFiles > self::MAX_ZIP_ENTRIES) {
            $zip->close();
            throw new InvalidArgumentException('Workbook package contains an unsafe number of entries.');
        }
        $total = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (! is_array($stat)) { continue; }
            $name = str_replace('\\', '/', (string) ($stat['name'] ?? ''));
            if ($name === '' || str_starts_with($name, '/') || str_contains($name, '../')) {
                $zip->close();
                throw new InvalidArgumentException('Workbook package contains an unsafe path.');
            }
            $lower = strtolower($name);
            if (str_contains($lower, 'vbaproject.bin') || str_starts_with($lower, 'xl/externallinks/') || $lower === 'xl/connections.xml') {
                $zip->close();
                throw new InvalidArgumentException('Macros, external links and workbook connections are not supported for import.');
            }
            $total += max(0, (int) ($stat['size'] ?? 0));
            if ($total > self::MAX_UNCOMPRESSED_BYTES) {
                $zip->close();
                throw new InvalidArgumentException('Workbook expands beyond the SafeContracts import limit.');
            }
        }
        return $zip;
    }

    private function sheetDefinitions(ZipArchive $zip): array
    {
        $relDoc = $this->document($this->xml($zip, 'xl/_rels/workbook.xml.rels'));
        $relXpath = new DOMXPath($relDoc);
        $relXpath->registerNamespace('r', self::PACKAGE_REL_NS);
        $targets = [];
        foreach ($relXpath->query('//r:Relationship') ?: [] as $node) {
            if ($node instanceof DOMElement) { $targets[$node->getAttribute('Id')] = $node->getAttribute('Target'); }
        }

        $doc = $this->document($this->xml($zip, 'xl/workbook.xml'));
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('s', self::MAIN_NS);
        $definitions = [];
        foreach ($xpath->query('//s:sheets/s:sheet') ?: [] as $node) {
            if (! $node instanceof DOMElement) { continue; }
            $name = trim($node->getAttribute('name'));
            $target = ltrim(str_replace('\\', '/', (string) ($targets[$node->getAttributeNS(self::REL_NS, 'id')] ?? '')), '/');
            $path = str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
            if ($name === '' || ! str_starts_with($path, 'xl/worksheets/') || str_contains($path, '../')) { continue; }
            $definitions[] = ['name' => substr($name, 0, 191), 'path' => $path];
            if (count($definitions) > self::MAX_SHEETS) { throw new InvalidArgumentException('Workbook contains too many worksheets.'); }
        }
        return $definitions;
    }

    private function sharedStrings(ZipArchive $zip): array
    {
        if ($zip->locateName('xl/sharedStrings.xml') === false) { return []; }
        $doc = $this->document($this->xml($zip, 'xl/sharedStrings.xml'));
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('s', self::MAIN_NS);
        $values = [];
        foreach ($xpath->query('//s:si') ?: [] as $item) {
            $parts = [];
            foreach ($xpath->query('.//s:t', $item) ?: [] as $text) { $parts[] = $text->textContent; }
            $values[] = implode('', $parts);
            if (count($values) > 100000) { throw new InvalidArgumentException('Workbook shared string table is too large.'); }
        }
        return $values;
    }

    private function sheetRows(ZipArchive $zip, string $path, array $shared, int $limit): array
    {
        $doc = $this->document($this->xml($zip, $path));
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('s', self::MAIN_NS);
        $rows = [];
        foreach ($xpath->query('//s:sheetData/s:row') ?: [] as $rowNode) {
            if (! $rowNode instanceof DOMElement) { continue; }
            $rowNumber = (int) $rowNode->getAttribute('r');
            if ($rowNumber <= 0) { $rowNumber = count($rows) + 1; }
            $cells = [];
            $position = 0;
            foreach ($xpath->query('./s:c', $rowNode) ?: [] as $cell) {
                if (! $cell instanceof DOMElement) { continue; }
                $position++;
                if (($xpath->query('./s:f', $cell)?->length ?? 0) > 0) {
                    throw new InvalidArgumentException('Workbook formulas are not supported for SafeContracts import.');
                }
                $ref = strtoupper($cell->getAttribute('r'));
                if (preg_match('/^([A-Z]{1,3})[0-9]+$/', $ref, $match)) {
                    $column = $match[1];
                } else {
                    $column = $this->columnLetters($position);
                }
                if ($this->columnNumber($column) > self::MAX_COLUMNS) {
                    throw new InvalidArgumentException('Workbook contains too many columns.');
                }
                $cells[$column] = $this->cellValue($xpath, $cell, $shared);
            }
            if ($cells !== []) { $rows[] = ['row_number' => $rowNumber, 'cells' => $cells]; }
            if (count($rows) >= $limit) { break; }
        }
        return $rows;
    }

    private function firstHeader(array $rows): array
    {
        foreach ($rows as $row) {
            $headers = [];
            foreach ($row['cells'] as $column => $value) {
                $original = trim(strip_tags((string) $value));
                if ($original === '') { continue; }
                $headers[] = ['column' => $column, 'original' => substr($original, 0, 191), 'normalized' => self::normalizeHeader($original)];
            }
            if ($headers !== []) { return ['row_number' => (int) $row['row_number'], 'headers' => $headers]; }
        }
        throw new InvalidArgumentException('Workbook sheet does not contain a usable header row.');
    }

    private function cellValue(DOMXPath $xpath, DOMElement $cell, array $shared): string
    {
        $type = $cell->getAttribute('t');
        if ($type === 'inlineStr') {
            $parts = [];
            foreach ($xpath->query('.//s:is//s:t', $cell) ?: [] as $text) { $parts[] = $text->textContent; }
            return implode('', $parts);
        }
        $value = $xpath->query('./s:v', $cell)?->item(0)?->textContent ?? '';
        if ($type === 's') {
            $index = filter_var($value, FILTER_VALIDATE_INT);
            return $index !== false && isset($shared[$index]) ? (string) $shared[$index] : '';
        }
        if ($type === 'b') { return $value === '1' ? '1' : '0'; }
        return $value;
    }

    private function xml(ZipArchive $zip, string $path): string
    {
        $xml = $zip->getFromName($path);
        if (! is_string($xml) || $xml === '' || strlen($xml) > 16777216) { throw new InvalidArgumentException('Workbook XML part is missing or too large.'); }
        if (stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) { throw new InvalidArgumentException('Workbook XML declarations are not allowed.'); }
        return $xml;
    }

    private function document(string $xml): DOMDocument
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $doc = new DOMDocument();
            if (! $doc->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT)) { throw new InvalidArgumentException('Workbook XML is invalid.'); }
            return $doc;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function columnNumber(string $column): int
    {
        $number = 0;
        foreach (str_split($column) as $letter) { $number = ($number * 26) + (ord($letter) - 64); }
        return $number;
    }

    private function columnLetters(int $number): string
    {
        $letters = '';
        while ($number > 0) {
            $number--;
            $letters = chr(65 + ($number % 26)) . $letters;
            $number = intdiv($number, 26);
        }
        return $letters;
    }
}
