<?php

declare(strict_types=1);

namespace SafeContracts\Import;

use RuntimeException;
use SafeContracts\Admin\AdminPeriodFilter;
use SafeContracts\Admin\ImportsPage;

final class ImportRunRepository
{
    public function create(string $filename, string $storageKey, string $sha256, int $size, int $actorId): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'safecontracts_import_runs';
        $sql = $wpdb->prepare(
            "INSERT INTO {$table} (original_filename, storage_key, file_sha256, file_size, status, duplicate_strategy, created_by, created_at, updated_at)
             VALUES (%s, %s, %s, %d, %s, %s, %d, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
            $filename,
            $storageKey,
            $sha256,
            $size,
            'uploaded',
            'fail',
            $actorId
        );
        if ($wpdb->query($sql) === false) {
            throw new RuntimeException('Unable to create SafeContracts import run.');
        }
        return (int) $wpdb->insert_id;
    }

    public function saveDiscovery(int $runId, array $discovery): void
    {
        $this->updateJson($runId, 'discovery_json', $discovery, 'discovered');
    }

    public function saveMapping(int $runId, string $sheet, array $mapping): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'safecontracts_import_runs';
        $json = $this->json($mapping);
        $sql = $wpdb->prepare(
            "UPDATE {$table} SET selected_sheet = %s, mapping_json = %s, status = %s, updated_at = UTC_TIMESTAMP() WHERE id = %d",
            $sheet,
            $json,
            'mapped',
            $runId
        );
        if ($wpdb->query($sql) === false) {
            throw new RuntimeException('Unable to save SafeContracts import mapping.');
        }
    }

    public function updateStatus(int $runId, string $status, array $counts = [], ?string $duplicateStrategy = null): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'safecontracts_import_runs';
        $allowed = ['uploaded','discovered','mapped','validated','running','completed','completed_with_errors','failed'];
        if (! in_array($status, $allowed, true)) {
            throw new RuntimeException('Invalid SafeContracts import status.');
        }
        $values = [
            'total_rows' => max(0, (int) ($counts['total_rows'] ?? 0)),
            'valid_rows' => max(0, (int) ($counts['valid_rows'] ?? 0)),
            'imported_rows' => max(0, (int) ($counts['imported_rows'] ?? 0)),
            'skipped_rows' => max(0, (int) ($counts['skipped_rows'] ?? 0)),
            'error_rows' => max(0, (int) ($counts['error_rows'] ?? 0)),
        ];
        $strategy = $duplicateStrategy ?? 'fail';
        $sql = $wpdb->prepare(
            "UPDATE {$table} SET status = %s, duplicate_strategy = %s, total_rows = %d, valid_rows = %d, imported_rows = %d, skipped_rows = %d, error_rows = %d, updated_at = UTC_TIMESTAMP() WHERE id = %d",
            $status,
            $strategy,
            $values['total_rows'],
            $values['valid_rows'],
            $values['imported_rows'],
            $values['skipped_rows'],
            $values['error_rows'],
            $runId
        );
        if ($wpdb->query($sql) === false) {
            throw new RuntimeException('Unable to update SafeContracts import run.');
        }
    }

    public function find(int $runId): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'safecontracts_import_runs';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, original_filename, storage_key, file_sha256, file_size, status, selected_sheet, discovery_json, mapping_json, duplicate_strategy, total_rows, valid_rows, imported_rows, skipped_rows, error_rows, created_by, created_at, updated_at FROM {$table} WHERE id = %d LIMIT 1",
            $runId
        ), ARRAY_A);
        if (! is_array($rows) || $rows === []) {
            return null;
        }
        return $this->normalizeRun($rows[0]);
    }

    /** @return list<array<string,mixed>> */
    public function recent(int $limit = 20, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        global $wpdb;
        $limit = max(1, min(100, $limit));

        // ImportsPage predates the shared admin-read repository. Keep its
        // existing call surface backwards compatible while allowing the admin
        // page to use the same strict period query contract as other data pages.
        if ($dateFrom === null && $dateTo === null && (string) ($_GET['page'] ?? '') === ImportsPage::SLUG) {
            $period = AdminPeriodFilter::normalize($_GET);
            if (! empty($period['date_range_error'])) {
                return [];
            }
            $dateFrom = $period['date_from'];
            $dateTo = $period['date_to'];
        }

        $table = $wpdb->prefix . 'safecontracts_import_runs';
        $where = ['1 = 1'];
        $args = [];
        if ($dateFrom !== null) {
            $where[] = 'DATE(created_at) >= %s';
            $args[] = $dateFrom;
        }
        if ($dateTo !== null) {
            $where[] = 'DATE(created_at) <= %s';
            $args[] = $dateTo;
        }
        $args[] = $limit;
        $sql = "SELECT id, original_filename, storage_key, file_sha256, file_size, status, selected_sheet, discovery_json, mapping_json, duplicate_strategy, total_rows, valid_rows, imported_rows, skipped_rows, error_rows, created_by, created_at, updated_at
                FROM {$table}
                WHERE " . implode(' AND ', $where) . '
                ORDER BY id DESC LIMIT %d';
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A);
        return array_map(fn (array $row): array => $this->normalizeRun($row), is_array($rows) ? $rows : []);
    }

    public function clearErrors(int $runId): void
    {
        global $wpdb;
        if ($runId <= 0) {
            throw new RuntimeException('Import run ID must be positive when clearing row errors.');
        }
        $table = $wpdb->prefix . 'safecontracts_import_errors';
        if ($wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE import_run_id = %d", $runId)) === false) {
            throw new RuntimeException('Unable to clear SafeContracts import row errors.');
        }
    }

    public function addError(int $runId, int $rowNumber, ?string $field, string $code, string $message): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'safecontracts_import_errors';
        $fieldName = $field === null ? null : substr(trim(strip_tags($field)), 0, 100);
        if ($fieldName === '') {
            $fieldName = null;
        }
        $code = substr(preg_replace('/[^a-z0-9_.-]/', '_', strtolower($code)) ?? 'invalid', 0, 80);
        $message = substr(trim(strip_tags($message)), 0, 1000);

        if ($fieldName === null) {
            $sql = $wpdb->prepare(
                "INSERT INTO {$table} (import_run_id, `row_number`, field_name, error_code, message, created_at) VALUES (%d, %d, NULL, %s, %s, UTC_TIMESTAMP())",
                $runId,
                max(0, $rowNumber),
                $code,
                $message
            );
        } else {
            $sql = $wpdb->prepare(
                "INSERT INTO {$table} (import_run_id, `row_number`, field_name, error_code, message, created_at) VALUES (%d, %d, %s, %s, %s, UTC_TIMESTAMP())",
                $runId,
                max(0, $rowNumber),
                $fieldName,
                $code,
                $message
            );
        }
        if ($wpdb->query($sql) === false) {
            throw new RuntimeException('Unable to record SafeContracts import error.');
        }
    }

    public function errorCount(int $runId): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'safecontracts_import_errors';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT COUNT(*) AS error_count FROM {$table} WHERE import_run_id = %d",
            $runId
        ), ARRAY_A);
        if (! is_array($rows) || ! isset($rows[0]) || ! is_array($rows[0])) {
            return 0;
        }
        return max(0, (int) ($rows[0]['error_count'] ?? 0));
    }

    /** @return list<array<string,mixed>> */
    public function errors(int $runId, int $limit = 500): array
    {
        global $wpdb;
        $limit = max(1, min(1000, $limit));
        $table = $wpdb->prefix . 'safecontracts_import_errors';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, import_run_id, `row_number`, field_name, error_code, message, created_at FROM {$table} WHERE import_run_id = %d ORDER BY `row_number` ASC, id ASC LIMIT {$limit}",
            $runId
        ), ARRAY_A);
        return is_array($rows) ? array_values($rows) : [];
    }

    private function updateJson(int $runId, string $column, array $value, string $status): void
    {
        global $wpdb;
        if (! in_array($column, ['discovery_json', 'mapping_json'], true)) {
            throw new RuntimeException('Invalid import JSON column.');
        }
        $table = $wpdb->prefix . 'safecontracts_import_runs';
        $json = $this->json($value);
        $sql = $wpdb->prepare("UPDATE {$table} SET {$column} = %s, status = %s, updated_at = UTC_TIMESTAMP() WHERE id = %d", $json, $status, $runId);
        if ($wpdb->query($sql) === false) {
            throw new RuntimeException('Unable to update SafeContracts import metadata.');
        }
    }

    private function json(array $value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (! is_string($json)) {
            throw new RuntimeException('Unable to encode SafeContracts import metadata.');
        }
        return $json;
    }

    private function normalizeRun(array $row): array
    {
        $row['id'] = (int) ($row['id'] ?? 0);
        $row['file_size'] = (int) ($row['file_size'] ?? 0);
        foreach (['total_rows','valid_rows','imported_rows','skipped_rows','error_rows','created_by'] as $key) {
            $row[$key] = (int) ($row[$key] ?? 0);
        }
        foreach (['discovery_json' => 'discovery', 'mapping_json' => 'mapping'] as $jsonKey => $target) {
            $decoded = json_decode((string) ($row[$jsonKey] ?? ''), true);
            $row[$target] = is_array($decoded) ? $decoded : [];
            unset($row[$jsonKey]);
        }
        return $row;
    }
}
