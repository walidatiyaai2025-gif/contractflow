<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use RuntimeException;
use SafeContracts\Database\ProductionMigration;

/**
 * Allows Supplier contracts to coexist with the legacy customer_id column.
 *
 * Older production schemas may retain customer_id NOT NULL even though the
 * counterparty model introduced in 1.16 defines that compatibility column as
 * nullable. Supplier contracts intentionally have no customer_id, therefore
 * the legacy constraint must be relaxed without dropping the column or data.
 */
final class Migration0019NullableLegacyCustomer implements ProductionMigration
{
    private bool $changed = false;

    public function preflight(object $wpdb): void
    {
        $columns = $this->contractColumns($wpdb);
        foreach (['customer_id', 'counterparty_type', 'counterparty_id', 'financial_direction'] as $required) {
            if (! isset($columns[$required])) {
                throw new RuntimeException('SafeContracts contracts schema is missing required column: ' . $required);
            }
        }

        $type = strtolower(trim((string) ($columns['customer_id']['Type'] ?? $columns['customer_id']['type'] ?? '')));
        if (! preg_match('/^bigint(?:\(\d+\))? unsigned$/', $type)) {
            throw new RuntimeException('SafeContracts customer_id has an unexpected database type; migration stopped before mutation.');
        }
    }

    public function up(object $wpdb): void
    {
        $columns = $this->contractColumns($wpdb);
        if ($this->isNullable($columns['customer_id'] ?? [])) {
            return;
        }

        $table = $wpdb->prefix . 'safecontracts_contracts';
        $result = $wpdb->query("ALTER TABLE {$table} MODIFY customer_id bigint(20) unsigned NULL");
        if ($result === false) {
            throw new RuntimeException('SafeContracts could not relax the legacy customer_id constraint.');
        }
        $this->changed = true;
    }

    public function verify(object $wpdb): void
    {
        $columns = $this->contractColumns($wpdb);
        if (! isset($columns['customer_id']) || ! $this->isNullable($columns['customer_id'])) {
            throw new RuntimeException('SafeContracts customer_id is still NOT NULL after migration.');
        }
        foreach (['counterparty_type', 'counterparty_id', 'financial_direction'] as $required) {
            if (! isset($columns[$required])) {
                throw new RuntimeException('SafeContracts counterparty schema verification failed: ' . $required);
            }
        }
    }

    public function rollback(object $wpdb): void
    {
        if (! $this->changed) {
            return;
        }

        $table = $wpdb->prefix . 'safecontracts_contracts';
        $nullCount = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE customer_id IS NULL");
        if ($nullCount > 0) {
            throw new RuntimeException('Rollback cannot restore customer_id NOT NULL because supplier-compatible rows now contain NULL customer_id; restore the verified pre-deployment backup instead.');
        }

        $result = $wpdb->query("ALTER TABLE {$table} MODIFY customer_id bigint(20) unsigned NOT NULL");
        if ($result === false) {
            throw new RuntimeException('SafeContracts could not restore the legacy customer_id NOT NULL constraint during rollback.');
        }
    }

    /** @return array<string,array<string,mixed>> */
    private function contractColumns(object $wpdb): array
    {
        $table = $wpdb->prefix . 'safecontracts_contracts';
        $rows = $wpdb->get_results("SHOW COLUMNS FROM {$table}", ARRAY_A);
        if (! is_array($rows) || $rows === []) {
            throw new RuntimeException('SafeContracts contracts table is unavailable for migration preflight.');
        }

        $columns = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $field = trim((string) ($row['Field'] ?? $row['field'] ?? ''));
            if ($field !== '') {
                $columns[$field] = $row;
            }
        }
        return $columns;
    }

    /** @param array<string,mixed> $column */
    private function isNullable(array $column): bool
    {
        return strtoupper(trim((string) ($column['Null'] ?? $column['null'] ?? ''))) === 'YES';
    }
}
