<?php

declare(strict_types=1);

namespace SafeContracts\Import;

final class ImportPreviewService
{
    public function __construct(private ?WorkbookReader $reader = null)
    {
        $this->reader ??= new WorkbookReader();
    }

    /** @param array<string,string> $mapping @return list<array{row_number:int,data:array<string,string>}> */
    public function preview(string $path, string $sheetName, int $headerRow, array $mapping, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        $rows = $this->reader->rows($path, $sheetName, $headerRow, $limit);
        $preview = [];
        foreach ($rows as $row) {
            $mapped = [];
            foreach ($mapping as $target => $sourceColumn) {
                $value = $row['cells'][$sourceColumn] ?? '';
                $mapped[$target] = $this->safeText($value);
            }
            $preview[] = ['row_number' => (int) $row['row_number'], 'data' => $mapped];
            if (count($preview) >= $limit) {
                break;
            }
        }
        return $preview;
    }

    private function safeText(mixed $value): string
    {
        if (! is_scalar($value) && $value !== null) {
            return '';
        }
        $text = trim(strip_tags((string) ($value ?? '')));
        return substr($text, 0, 5000);
    }
}
