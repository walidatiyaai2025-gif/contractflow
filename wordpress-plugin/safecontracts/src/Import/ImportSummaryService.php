<?php

declare(strict_types=1);

namespace SafeContracts\Import;

use DomainException;
use InvalidArgumentException;
use SafeContracts\Roles\Capabilities;

final class ImportSummaryService
{
    public function __construct(private ?ImportRunRepository $runs = null)
    {
        $this->runs ??= new ImportRunRepository();
    }

    /**
     * @return array{
     *   run_id:int,status:string,selected_sheet:string,duplicate_strategy:string,
     *   workbook:array{original_filename:string,file_size:int,sheet_count:int,mapped_fields:int},
     *   counts:array{total_rows:int,valid_rows:int,imported_rows:int,skipped_rows:int,error_rows:int,error_entries:int},
     *   actor_id:int,created_at:string,updated_at:string
     * }
     */
    public function get(int $runId): array
    {
        if (! current_user_can(Capabilities::RUN_IMPORTS)) {
            throw new DomainException('You do not have permission to read SafeContracts import summaries.');
        }
        if ($runId <= 0) {
            throw new InvalidArgumentException('Import run ID must be positive.');
        }
        $run = $this->runs->find($runId);
        if ($run === null) {
            throw new InvalidArgumentException('Import run was not found.');
        }

        $sheets = is_array($run['discovery']['sheets'] ?? null) ? $run['discovery']['sheets'] : [];
        $mapping = is_array($run['mapping'] ?? null) ? $run['mapping'] : [];
        return [
            'run_id' => (int) $run['id'],
            'status' => (string) ($run['status'] ?? ''),
            'selected_sheet' => (string) ($run['selected_sheet'] ?? ''),
            'duplicate_strategy' => (string) ($run['duplicate_strategy'] ?? 'fail'),
            'workbook' => [
                'original_filename' => (string) ($run['original_filename'] ?? ''),
                'file_size' => max(0, (int) ($run['file_size'] ?? 0)),
                'sheet_count' => count($sheets),
                'mapped_fields' => count($mapping),
            ],
            'counts' => [
                'total_rows' => max(0, (int) ($run['total_rows'] ?? 0)),
                'valid_rows' => max(0, (int) ($run['valid_rows'] ?? 0)),
                'imported_rows' => max(0, (int) ($run['imported_rows'] ?? 0)),
                'skipped_rows' => max(0, (int) ($run['skipped_rows'] ?? 0)),
                'error_rows' => max(0, (int) ($run['error_rows'] ?? 0)),
                'error_entries' => $this->runs->errorCount($runId),
            ],
            'actor_id' => max(0, (int) ($run['created_by'] ?? 0)),
            'created_at' => (string) ($run['created_at'] ?? ''),
            'updated_at' => (string) ($run['updated_at'] ?? ''),
        ];
    }

    /** @param array<string,mixed> $summary @return array<string,mixed> */
    public static function auditContext(array $summary): array
    {
        $workbook = is_array($summary['workbook'] ?? null) ? $summary['workbook'] : [];
        return [
            'run_id' => max(0, (int) ($summary['run_id'] ?? 0)),
            'status' => (string) ($summary['status'] ?? ''),
            'selected_sheet' => (string) ($summary['selected_sheet'] ?? ''),
            'duplicate_strategy' => (string) ($summary['duplicate_strategy'] ?? 'fail'),
            'sheet_count' => max(0, (int) ($workbook['sheet_count'] ?? 0)),
            'mapped_fields' => max(0, (int) ($workbook['mapped_fields'] ?? 0)),
            'counts' => is_array($summary['counts'] ?? null) ? $summary['counts'] : [],
            'actor_id' => max(0, (int) ($summary['actor_id'] ?? 0)),
            'created_at' => (string) ($summary['created_at'] ?? ''),
            'updated_at' => (string) ($summary['updated_at'] ?? ''),
        ];
    }
}
