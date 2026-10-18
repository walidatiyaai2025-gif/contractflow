<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use RuntimeException;
use SafeContracts\Database\ProductionMigration;

/**
 * Adds generic resource context to notification delivery evidence so contract
 * activity notifications can deep-link to contracts/payments without abusing
 * the legacy payment_id column.
 */
final class Migration0024NotificationActivityContext implements ProductionMigration
{
    /** @var list<string> */
    private array $addedColumns = [];

    public function preflight(object $wpdb): void
    {
        $table = $wpdb->prefix . 'safecontracts_notification_deliveries';
        $rows = $wpdb->get_results(
            "SELECT id, payment_id, user_id, template_code, created_at FROM {$table} ORDER BY id ASC LIMIT 1",
            ARRAY_A
        );
        if (! is_array($rows)) {
            throw new RuntimeException('SafeContracts notification delivery table is unavailable for activity-context migration.');
        }
        $this->assertNoDatabaseError($wpdb, 'SafeContracts notification activity-context preflight failed.');
    }

    public function up(object $wpdb): void
    {
        $table = $wpdb->prefix . 'safecontracts_notification_deliveries';
        $columns = $this->columnNames($wpdb, $table);

        $definitions = [
            'resource_type' => "varchar(20) NULL AFTER payment_id",
            'resource_id' => "bigint(20) unsigned NULL AFTER resource_type",
            'contract_id' => "bigint(20) unsigned NULL AFTER resource_id",
        ];

        foreach ($definitions as $column => $definition) {
            if (isset($columns[$column])) {
                continue;
            }
            $result = $wpdb->query("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
            if ($result === false) {
                throw new RuntimeException("SafeContracts could not add notification {$column} context.");
            }
            $this->assertNoDatabaseError($wpdb, "SafeContracts could not add notification {$column} context.");
            $this->addedColumns[] = $column;
        }

        $columns = $this->columnNames($wpdb, $table);
        if (! isset($columns['resource_lookup'])) {
            $result = $wpdb->query("ALTER TABLE {$table} ADD KEY resource_lookup (resource_type, resource_id, created_at, id)");
            if ($result === false) {
                throw new RuntimeException('SafeContracts could not add notification resource lookup index.');
            }
            $this->assertNoDatabaseError($wpdb, 'SafeContracts could not add notification resource lookup index.');
            $this->addedColumns[] = '__index_resource_lookup';
        }
    }

    public function verify(object $wpdb): void
    {
        $table = $wpdb->prefix . 'safecontracts_notification_deliveries';
        $rows = $wpdb->get_results(
            "SELECT id, payment_id, resource_type, resource_id, contract_id, user_id, channel, template_code, created_at FROM {$table} ORDER BY id ASC LIMIT 1",
            ARRAY_A
        );
        if (! is_array($rows)) {
            throw new RuntimeException('SafeContracts notification activity-context verification failed.');
        }
        $this->assertNoDatabaseError($wpdb, 'SafeContracts notification activity-context verification failed.');
    }

    public function rollback(object $wpdb): void
    {
        $table = $wpdb->prefix . 'safecontracts_notification_deliveries';
        foreach (array_reverse($this->addedColumns) as $column) {
            if ($column === '__index_resource_lookup') {
                $wpdb->query("ALTER TABLE {$table} DROP INDEX resource_lookup");
                $this->assertNoDatabaseError($wpdb, 'SafeContracts could not roll back notification resource lookup index.');
                continue;
            }
            $wpdb->query("ALTER TABLE {$table} DROP COLUMN {$column}");
            $this->assertNoDatabaseError($wpdb, "SafeContracts could not roll back notification {$column} context.");
        }
    }

    /** @return array<string,true> */
    private function columnNames(object $wpdb, string $table): array
    {
        $rows = $wpdb->get_results("SHOW COLUMNS FROM {$table}", ARRAY_A);
        if (! is_array($rows)) {
            throw new RuntimeException('SafeContracts could not inspect notification delivery columns.');
        }
        $this->assertNoDatabaseError($wpdb, 'SafeContracts could not inspect notification delivery columns.');

        $columns = [];
        foreach ($rows as $row) {
            $name = (string) ($row['Field'] ?? $row['Key_name'] ?? '');
            if ($name !== '') {
                $columns[$name] = true;
            }
        }

        // SHOW COLUMNS does not expose index names, so inspect indexes as well.
        $indexes = $wpdb->get_results("SHOW INDEX FROM {$table}", ARRAY_A);
        if (is_array($indexes)) {
            foreach ($indexes as $row) {
                $name = (string) ($row['Key_name'] ?? '');
                if ($name !== '') {
                    $columns[$name] = true;
                }
            }
        }
        $this->assertNoDatabaseError($wpdb, 'SafeContracts could not inspect notification delivery indexes.');
        return $columns;
    }

    private function assertNoDatabaseError(object $wpdb, string $message): void
    {
        if (property_exists($wpdb, 'last_error') && trim((string) $wpdb->last_error) !== '') {
            throw new RuntimeException($message . ' ' . trim((string) $wpdb->last_error));
        }
    }
}
