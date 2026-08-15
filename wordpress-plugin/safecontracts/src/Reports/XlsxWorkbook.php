<?php

declare(strict_types=1);

namespace SafeContracts\Reports;

use InvalidArgumentException;

final class XlsxWorkbook
{
    /** @param array<string, list<array<int, scalar|null>>> $sheets */
    public function build(array $sheets): string
    {
        if ($sheets === []) {
            throw new InvalidArgumentException('SafeContracts XLSX workbook requires at least one sheet.');
        }

        $sheetFiles = [];
        $sheetEntries = [];
        $relationships = [];
        $index = 1;
        foreach ($sheets as $name => $rows) {
            $safeName = $this->sheetName((string) $name, $index);
            $sheetFiles["xl/worksheets/sheet{$index}.xml"] = $this->worksheet($rows);
            $sheetEntries[] = '<sheet name="' . $this->xml($safeName) . '" sheetId="' . $index . '" r:id="rId' . $index . '"/>';
            $relationships[] = '<Relationship Id="rId' . $index . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $index . '.xml"/>';
            $index++;
        }

        $styleRelationshipId = 'rId' . $index;
        $relationships[] = '<Relationship Id="' . $styleRelationshipId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        $files = [
            '[Content_Types].xml' => $this->contentTypes(count($sheetFiles)),
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                . '</Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                . '<sheets>' . implode('', $sheetEntries) . '</sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . implode('', $relationships) . '</Relationships>',
            'xl/styles.xml' => $this->styles(),
        ] + $sheetFiles;

        return $this->zipStore($files);
    }

    /** @param list<array<int, scalar|null>> $rows */
    private function worksheet(array $rows): string
    {
        $body = [];
        foreach (array_values($rows) as $rowIndex => $row) {
            if (! is_array($row)) {
                continue;
            }
            $cells = [];
            foreach (array_values($row) as $value) {
                $text = $this->cellText($value);
                $style = $rowIndex === 0 ? ' s="1"' : '';
                $cells[] = '<c t="inlineStr"' . $style . '><is><t xml:space="preserve">' . $this->xml($text) . '</t></is></c>';
            }
            $body[] = '<row>' . implode('', $cells) . '</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<sheetData>' . implode('', $body) . '</sheetData></worksheet>';
    }

    private function cellText(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (! is_scalar($value)) {
            throw new InvalidArgumentException('SafeContracts XLSX cells must be scalar or null.');
        }
        $text = trim(strip_tags((string) $value));
        $text = preg_replace('/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $text) ?? '';
        if ($text !== '' && in_array($text[0], ['=', '+', '-', '@'], true)) {
            $text = "'" . $text;
        }
        return $text;
    }

    private function sheetName(string $name, int $index): string
    {
        $name = preg_replace('~[\\\\/?*\[\]:]~', ' ', trim($name)) ?? '';
        $name = preg_replace('/\s+/', ' ', $name) ?? '';
        if ($name === '') {
            $name = 'Sheet ' . $index;
        }
        return substr($name, 0, 31);
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_COMPAT | ENT_XML1, 'UTF-8');
    }

    private function contentTypes(int $sheetCount): string
    {
        $overrides = [];
        for ($i = 1; $i <= $sheetCount; $i++) {
            $overrides[] = '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . implode('', $overrides) . '</Types>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>'
            . '</styleSheet>';
    }

    /** @param array<string,string> $files */
    private function zipStore(array $files): string
    {
        $local = '';
        $central = '';
        $offset = 0;
        $entries = 0;
        foreach ($files as $name => $data) {
            $name = str_replace('\\', '/', $name);
            $crc = crc32($data);
            $size = strlen($data);
            $nameLength = strlen($name);
            $header = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, 0, 33, $crc, $size, $size, $nameLength, 0);
            $localEntry = $header . $name . $data;
            $local .= $localEntry;

            $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 0, 0, 33, $crc, $size, $size, $nameLength, 0, 0, 0, 0, 0, $offset) . $name;
            $offset += strlen($localEntry);
            $entries++;
        }

        $centralOffset = strlen($local);
        $centralSize = strlen($central);
        $end = pack('VvvvvVVv', 0x06054b50, 0, 0, $entries, $entries, $centralSize, $centralOffset, 0);
        return $local . $central . $end;
    }
}
