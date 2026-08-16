<?php

declare(strict_types=1);

namespace SafeContracts\Tenancy;

use RuntimeException;

final class CoreTenantSchemaHardener
{
    public const OPTION = 'safecontracts_esc_core_tenant_schema_hardened';

    /** @var list<string> */
    private const TABLE_SUFFIXES = [
        'safecontracts_customers',
        'safecontracts_contracts',
        'safecontracts_contract_financial_items',
        'safecontracts_contract_adjustments',
        'safecontracts_contract_attachments',
        'safecontracts_contract_history',
        'safecontracts_scheduled_payments',
        'safecontracts_payment_collections',
        'safecontracts_payment_followups',
    ];

    /** @var array<string, array<string, string>> */
    private const TENANT_INDEXES = [
        'safecontracts_customers' => [
            'esc_tenant_active_name' => 'KEY esc_tenant_active_name (tenant_id, is_active, name)',
        ],
        'safecontracts_contracts' => [
            'esc_tenant_customer_status' => 'KEY esc_tenant_customer_status (tenant_id, customer_id, status, is_archived)',
            'esc_tenant_accountant_status' => 'KEY esc_tenant_accountant_status (tenant_id, accountant_user_id, status, is_archived)',
        ],
        'safecontracts_contract_financial_items' => [
            'esc_tenant_contract_order' => 'KEY esc_tenant_contract_order (tenant_id, contract_id, display_order, id)',
        ],
        'safecontracts_contract_adjustments' => [
            'esc_tenant_contract_type_order' => 'KEY esc_tenant_contract_type_order (tenant_id, contract_id, adjustment_type, display_order, id)',
        ],
        'safecontracts_contract_attachments' => [
            'esc_tenant_contract_media' => 'KEY esc_tenant_contract_media (tenant_id, contract_id, media_id)',
        ],
        'safecontracts_contract_history' => [
            'esc_tenant_contract_created' => 'KEY esc_tenant_contract_created (tenant_id, contract_id, created_at, id)',
        ],
        'safecontracts_scheduled_payments' => [
            'esc_tenant_contract_status_due' => 'KEY esc_tenant_contract_status_due (tenant_id, contract_id, status, due_date)',
            'esc_tenant_due_status' => 'KEY esc_tenant_due_status (tenant_id, due_date, status, id)',
        ],
        'safecontracts_payment_collections' => [
            'esc_tenant_payment_date' => 'KEY esc_tenant_payment_date (tenant_id, payment_id, collection_date, id)',
        ],
        'safecontracts_payment_followups' => [
            'esc_tenant_payment_timeline' => 'KEY esc_tenant_payment_timeline (tenant_id, payment_id, created_at, id)',
        ],
    ];

    public function isHardened(): bool
    {
        return get_option(self::OPTION, '0') === '1';
    }

    /**
     * @return array{
     *   ready:bool,
     *   ownership:array<string,mixed>,
     *   enforcement_enabled:bool,
     *   duplicate_customer_codes:int,
     *   duplicate_contract_numbers:int,
     *   hardened:bool
     * }
     */
    public function preflight(): array
    {
        global $wpdb;
        $this->assertWpdb($wpdb);

        $ownership = (new CoreTenantOwnershipBackfill())->report();
        $enforcementEnabled = CoreTenantEnforcement::isEnabled();
        $customers = $wpdb->prefix . 'safecontracts_customers';
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $duplicateCustomerCodes = $this->duplicateGroupCount(
            $wpdb,
            "SELECT COUNT(*) AS total FROM (
                SELECT tenant_id, internal_code
                FROM {$customers}
                WHERE internal_code IS NOT NULL AND internal_code <> ''
                GROUP BY tenant_id, internal_code
                HAVING COUNT(*) > 1
            ) esc_duplicate_customer_codes"
        );
        $duplicateContractNumbers = $this->duplicateGroupCount(
            $wpdb,
            "SELECT COUNT(*) AS total FROM (
                SELECT tenant_id, contract_number
                FROM {$contracts}
                GROUP BY tenant_id, contract_number
                HAVING COUNT(*) > 1
            ) esc_duplicate_contract_numbers"
        );

        return [
            'ready' => (bool) ($ownership['ready'] ?? false)
                && $enforcementEnabled
                && $duplicateCustomerCodes === 0
                && $duplicateContractNumbers === 0,
            'ownership' => $ownership,
            'enforcement_enabled' => $enforcementEnabled,
            'duplicate_customer_codes' => $duplicateCustomerCodes,
            'duplicate_contract_numbers' => $duplicateContractNumbers,
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
            throw new RuntimeException(
                'Enterprise core tenant schema is not ready to harden. Ownership, runtime enforcement and tenant-scoped uniqueness preflight must all be green.'
            );
        }

        foreach (self::TABLE_SUFFIXES as $suffix) {
            $table = $wpdb->prefix . $suffix;
            $this->execute(
                $wpdb,
                "ALTER TABLE {$table} MODIFY COLUMN tenant_id bigint(20) unsigned NOT NULL",
                "Unable to enforce non-null tenant ownership for {$suffix}."
            );
        }

        $customers = $wpdb->prefix . 'safecontracts_customers';
        $contracts = $wpdb->prefix . 'safecontracts_contracts';

        $this->ensureIndex(
            $wpdb,
            $customers,
            'esc_tenant_internal_code',
            'UNIQUE KEY esc_tenant_internal_code (tenant_id, internal_code)'
        );
        $this->ensureIndex(
            $wpdb,
            $contracts,
            'esc_tenant_contract_number',
            'UNIQUE KEY esc_tenant_contract_number (tenant_id, contract_number)'
        );

        if ($this->indexExists($wpdb, $customers, 'internal_code')) {
            $this->execute($wpdb, "ALTER TABLE {$customers} DROP INDEX internal_code", 'Unable to remove global customer-code uniqueness.');
        }
        if ($this->indexExists($wpdb, $contracts, 'contract_number')) {
            $this->execute($wpdb, "ALTER TABLE {$contracts} DROP INDEX contract_number", 'Unable to remove global contract-number uniqueness.');
        }

        foreach (self::TENANT_INDEXES as $suffix => $indexes) {
            $table = $wpdb->prefix . $suffix;
            foreach ($indexes as $name => $definition) {
                $this->ensureIndex($wpdb, $table, $name, $definition);
            }
        }

        $verification = $this->verify($wpdb);
        if (! $verification['ready']) {
            throw new RuntimeException('Enterprise core tenant schema hardening verification failed after DDL execution.');
        }

        update_option(self::OPTION, '1', false);
        return [
            ...$preflight,
            ...$verification,
            'ready' => true,
            'hardened' => true,
        ];
    }

    /** @return array{ready:bool,nullable_tables:list<string>,missing_indexes:list<string>,legacy_global_unique_indexes:list<string>} */
    public function verify(?object $database = null): array
    {
        global $wpdb;
        $database ??= $wpdb;
        $this->assertWpdb($database);

        $nullableTables = [];
        $missingIndexes = [];
        foreach (self::TABLE_SUFFIXES as $suffix) {
            $table = $database->prefix . $suffix;
            if ($this->tenantColumnIsNullable($database, $table)) {
                $nullableTables[] = $suffix;
            }
            foreach (array_keys(self::TENANT_INDEXES[$suffix] ?? []) as $index) {
                if (! $this->indexExists($database, $table, $index)) {
                    $missingIndexes[] = $suffix . ':' . $index;
                }
            }
        }

        $customers = $database->prefix . 'safecontracts_customers';
        $contracts = $database->prefix . 'safecontracts_contracts';
        foreach ([
            [$customers, 'esc_tenant_internal_code', 'safecontracts_customers'],
            [$contracts, 'esc_tenant_contract_number', 'safecontracts_contracts'],
        ] as [$table, $index, $suffix]) {
            if (! $this->indexExists($database, $table, $index)) {
                $missingIndexes[] = $suffix . ':' . $index;
            }
        }

        $legacyGlobalUnique = [];
        if ($this->indexExists($database, $customers, 'internal_code')) {
            $legacyGlobalUnique[] = 'safecontracts_customers:internal_code';
        }
        if ($this->indexExists($database, $contracts, 'contract_number')) {
            $legacyGlobalUnique[] = 'safecontracts_contracts:contract_number';
        }

        return [
            'ready' => $nullableTables === [] && $missingIndexes === [] && $legacyGlobalUnique === [],
            'nullable_tables' => $nullableTables,
            'missing_indexes' => $missingIndexes,
            'legacy_global_unique_indexes' => $legacyGlobalUnique,
        ];
    }

    private function duplicateGroupCount(object $wpdb, string $sql): int
    {
        $rows = $wpdb->get_results($sql, ARRAY_A);
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
        $this->execute($wpdb, "ALTER TABLE {$table} ADD {$definition}", "Unable to add Enterprise tenant index {$name}.");
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
            throw new RuntimeException('Enterprise core tenant schema hardening requires WordPress $wpdb.');
        }
    }
}
