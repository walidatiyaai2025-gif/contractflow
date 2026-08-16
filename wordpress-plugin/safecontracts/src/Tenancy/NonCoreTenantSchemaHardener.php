<?php

declare(strict_types=1);

namespace SafeContracts\Tenancy;

use RuntimeException;

final class NonCoreTenantSchemaHardener
{
    public const OPTION = 'safecontracts_esc_noncore_tenant_schema_hardened';

    /** @var list<string> */
    private const NON_NULL_TABLE_SUFFIXES = [
        'safecontracts_notification_rules',
        'safecontracts_notification_templates',
        'safecontracts_device_tokens',
        'safecontracts_notification_deliveries',
        'safecontracts_notification_schedule',
        'safecontracts_notification_suppressions',
        'safecontracts_import_runs',
        'safecontracts_import_errors',
    ];

    private const AUDIT_SUFFIX = 'safecontracts_audit_log';

    /** @var array<string, array{legacy:string,new:string,definition:string}> */
    private const SCOPED_UNIQUES = [
        'safecontracts_notification_rules' => [
            'legacy' => 'code',
            'new' => 'esc_tenant_rule_code',
            'definition' => 'UNIQUE KEY esc_tenant_rule_code (tenant_id, code)',
        ],
        'safecontracts_notification_templates' => [
            'legacy' => 'code',
            'new' => 'esc_tenant_template_code',
            'definition' => 'UNIQUE KEY esc_tenant_template_code (tenant_id, code)',
        ],
        'safecontracts_device_tokens' => [
            'legacy' => 'token_hash',
            'new' => 'esc_tenant_token_hash',
            'definition' => 'UNIQUE KEY esc_tenant_token_hash (tenant_id, token_hash)',
        ],
        'safecontracts_notification_schedule' => [
            'legacy' => 'rule_payment_attempt',
            'new' => 'esc_tenant_rule_payment_attempt',
            'definition' => 'UNIQUE KEY esc_tenant_rule_payment_attempt (tenant_id, rule_id, payment_id, attempt_no)',
        ],
        'safecontracts_notification_suppressions' => [
            'legacy' => 'scope',
            'new' => 'esc_tenant_suppression_scope',
            'definition' => 'UNIQUE KEY esc_tenant_suppression_scope (tenant_id, scope_type, scope_id)',
        ],
        'safecontracts_import_runs' => [
            'legacy' => 'storage_key',
            'new' => 'esc_tenant_storage_key',
            'definition' => 'UNIQUE KEY esc_tenant_storage_key (tenant_id, storage_key)',
        ],
    ];

    /** @var array<string,array<string,string>> */
    private const TENANT_INDEXES = [
        'safecontracts_notification_rules' => [
            'esc_tenant_rule_active' => 'KEY esc_tenant_rule_active (tenant_id, is_active, id)',
        ],
        'safecontracts_notification_templates' => [
            'esc_tenant_template_active' => 'KEY esc_tenant_template_active (tenant_id, is_active, code, id)',
        ],
        'safecontracts_device_tokens' => [
            'esc_tenant_user_active' => 'KEY esc_tenant_user_active (tenant_id, user_id, is_active, id)',
            'esc_tenant_platform_active' => 'KEY esc_tenant_platform_active (tenant_id, platform, is_active, id)',
        ],
        'safecontracts_notification_deliveries' => [
            'esc_tenant_payment_created' => 'KEY esc_tenant_payment_created (tenant_id, payment_id, created_at, id)',
            'esc_tenant_user_channel_status' => 'KEY esc_tenant_user_channel_status (tenant_id, user_id, channel, status, created_at, id)',
        ],
        'safecontracts_notification_schedule' => [
            'esc_tenant_status_due' => 'KEY esc_tenant_status_due (tenant_id, status, scheduled_for, id)',
            'esc_tenant_payment_due' => 'KEY esc_tenant_payment_due (tenant_id, payment_id, scheduled_for, id)',
        ],
        'safecontracts_notification_suppressions' => [
            'esc_tenant_scope_active' => 'KEY esc_tenant_scope_active (tenant_id, scope_type, scope_id, is_active, id)',
        ],
        'safecontracts_import_runs' => [
            'esc_tenant_run_created' => 'KEY esc_tenant_run_created (tenant_id, created_at, id)',
            'esc_tenant_run_status' => 'KEY esc_tenant_run_status (tenant_id, status, created_at, id)',
        ],
        'safecontracts_import_errors' => [
            'esc_tenant_run_row' => 'KEY esc_tenant_run_row (tenant_id, import_run_id, row_number, id)',
        ],
        'safecontracts_audit_log' => [
            'esc_tenant_audit_created' => 'KEY esc_tenant_audit_created (tenant_id, created_at, id)',
            'esc_tenant_audit_entity' => 'KEY esc_tenant_audit_entity (tenant_id, entity_type, entity_id, created_at, id)',
        ],
    ];

    public function isHardened(): bool
    {
        return get_option(self::OPTION, '0') === '1';
    }

    /** @return array<string,mixed> */
    public function preflight(): array
    {
        global $wpdb;
        $this->assertWpdb($wpdb);

        $ownership = (new NonCoreTenantOwnershipBackfill())->report();
        $duplicates = [
            'rule_codes' => $this->duplicateCount($wpdb, 'safecontracts_notification_rules', 'tenant_id, code', "code <> ''"),
            'template_codes' => $this->duplicateCount($wpdb, 'safecontracts_notification_templates', 'tenant_id, code', "code <> ''"),
            'device_tokens' => $this->duplicateCount($wpdb, 'safecontracts_device_tokens', 'tenant_id, token_hash', "token_hash <> ''"),
            'schedule_attempts' => $this->duplicateCount($wpdb, 'safecontracts_notification_schedule', 'tenant_id, rule_id, payment_id, attempt_no'),
            'suppression_scopes' => $this->duplicateCount($wpdb, 'safecontracts_notification_suppressions', 'tenant_id, scope_type, scope_id'),
            'import_storage_keys' => $this->duplicateCount($wpdb, 'safecontracts_import_runs', 'tenant_id, storage_key', "storage_key <> ''"),
        ];

        return [
            'ready' => (bool) ($ownership['ready'] ?? false) && array_sum($duplicates) === 0,
            'ownership' => $ownership,
            'duplicates' => $duplicates,
            'hardened' => $this->isHardened(),
        ];
    }

    /** @return array<string,mixed> */
    public function harden(): array
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $preflight = $this->preflight();
        if (! $preflight['ready']) {
            throw new RuntimeException('Enterprise non-core tenant schema is not ready to harden. Ownership and tenant-scoped uniqueness preflight must be green.');
        }

        foreach (self::NON_NULL_TABLE_SUFFIXES as $suffix) {
            $table = $wpdb->prefix . $suffix;
            $this->execute(
                $wpdb,
                "ALTER TABLE {$table} MODIFY COLUMN tenant_id bigint(20) unsigned NOT NULL",
                "Unable to enforce non-null non-core tenant ownership for {$suffix}."
            );
        }

        foreach (self::SCOPED_UNIQUES as $suffix => $index) {
            $table = $wpdb->prefix . $suffix;
            $this->ensureIndex($wpdb, $table, $index['new'], $index['definition']);
            if ($this->indexExists($wpdb, $table, $index['legacy'])) {
                $this->execute(
                    $wpdb,
                    "ALTER TABLE {$table} DROP INDEX {$index['legacy']}",
                    "Unable to remove legacy global non-core unique index {$index['legacy']}."
                );
            }
        }

        foreach (self::TENANT_INDEXES as $suffix => $indexes) {
            $table = $wpdb->prefix . $suffix;
            foreach ($indexes as $name => $definition) {
                $this->ensureIndex($wpdb, $table, $name, $definition);
            }
        }

        $verification = $this->verify($wpdb);
        if (! $verification['ready']) {
            throw new RuntimeException('Enterprise non-core tenant schema hardening verification failed after DDL execution.');
        }

        update_option(self::OPTION, '1', false);
        return [
            ...$preflight,
            ...$verification,
            'ready' => true,
            'hardened' => true,
        ];
    }

    /** @return array{ready:bool,nullable_tables:list<string>,missing_indexes:list<string>,legacy_global_unique_indexes:list<string>,audit_tenant_nullable:bool} */
    public function verify(?object $database = null): array
    {
        global $wpdb;
        $database ??= $wpdb;
        $this->assertWpdb($database);

        $nullableTables = [];
        foreach (self::NON_NULL_TABLE_SUFFIXES as $suffix) {
            $table = $database->prefix . $suffix;
            if ($this->tenantColumnIsNullable($database, $table)) {
                $nullableTables[] = $suffix;
            }
        }

        $missingIndexes = [];
        foreach (self::SCOPED_UNIQUES as $suffix => $index) {
            $table = $database->prefix . $suffix;
            if (! $this->indexExists($database, $table, $index['new'])) {
                $missingIndexes[] = $suffix . ':' . $index['new'];
            }
        }
        foreach (self::TENANT_INDEXES as $suffix => $indexes) {
            $table = $database->prefix . $suffix;
            foreach (array_keys($indexes) as $name) {
                if (! $this->indexExists($database, $table, $name)) {
                    $missingIndexes[] = $suffix . ':' . $name;
                }
            }
        }

        $legacy = [];
        foreach (self::SCOPED_UNIQUES as $suffix => $index) {
            $table = $database->prefix . $suffix;
            if ($this->indexExists($database, $table, $index['legacy'])) {
                $legacy[] = $suffix . ':' . $index['legacy'];
            }
        }

        $auditTable = $database->prefix . self::AUDIT_SUFFIX;
        $auditNullable = $this->tenantColumnIsNullable($database, $auditTable);

        return [
            'ready' => $nullableTables === [] && $missingIndexes === [] && $legacy === [] && $auditNullable,
            'nullable_tables' => $nullableTables,
            'missing_indexes' => $missingIndexes,
            'legacy_global_unique_indexes' => $legacy,
            'audit_tenant_nullable' => $auditNullable,
        ];
    }

    private function duplicateCount(object $wpdb, string $suffix, string $groupBy, string $where = '1 = 1'): int
    {
        $table = $wpdb->prefix . $suffix;
        $rows = $wpdb->get_results(
            "SELECT COUNT(*) AS total FROM (
                SELECT {$groupBy}
                FROM {$table}
                WHERE tenant_id IS NOT NULL AND {$where}
                GROUP BY {$groupBy}
                HAVING COUNT(*) > 1
            ) esc_noncore_duplicates",
            ARRAY_A
        );
        if (! is_array($rows) || ! isset($rows[0]) || ! is_array($rows[0])) {
            return 0;
        }
        return max(0, (int) ($rows[0]['total'] ?? 0));
    }

    private function ensureIndex(object $wpdb, string $table, string $name, string $definition): void
    {
        if ($this->indexExists($wpdb, $table, $name)) {
            return;
        }
        $this->execute($wpdb, "ALTER TABLE {$table} ADD {$definition}", "Unable to add Enterprise non-core tenant index {$name}.");
    }

    private function tenantColumnIsNullable(object $wpdb, string $table): bool
    {
        $rows = $wpdb->get_results("SHOW COLUMNS FROM {$table} LIKE 'tenant_id'", ARRAY_A);
        if (! is_array($rows) || ! isset($rows[0]) || ! is_array($rows[0])) {
            return true;
        }
        return strtoupper((string) ($rows[0]['Null'] ?? 'YES')) !== 'NO';
    }

    private function indexExists(object $wpdb, string $table, string $index): bool
    {
        $rows = $wpdb->get_results("SHOW INDEX FROM {$table} WHERE Key_name = '" . addslashes($index) . "'", ARRAY_A);
        return is_array($rows) && $rows !== [];
    }

    private function execute(object $wpdb, string $sql, string $message): void
    {
        if ($wpdb->query($sql) === false) {
            throw new RuntimeException($message);
        }
    }

    private function assertWpdb(mixed $wpdb): void
    {
        if (! is_object($wpdb) || ! isset($wpdb->prefix) || ! method_exists($wpdb, 'get_results') || ! method_exists($wpdb, 'query')) {
            throw new RuntimeException('Enterprise non-core tenant schema hardening requires WordPress $wpdb.');
        }
    }
}
