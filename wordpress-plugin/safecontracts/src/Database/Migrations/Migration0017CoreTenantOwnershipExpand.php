<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use SafeContracts\Database\Migration;

final class Migration0017CoreTenantOwnershipExpand implements Migration
{
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

    public function up(object $wpdb): void
    {
        foreach (self::TABLE_SUFFIXES as $suffix) {
            $table = $wpdb->prefix . $suffix;
            if (! $this->columnExists($wpdb, $table, 'tenant_id')) {
                $wpdb->query("ALTER TABLE {$table} ADD COLUMN tenant_id bigint(20) unsigned NULL AFTER id");
            }
            if (! $this->indexExists($wpdb, $table, 'esc_tenant_record')) {
                $wpdb->query("ALTER TABLE {$table} ADD KEY esc_tenant_record (tenant_id, id)");
            }
        }
    }

    private function columnExists(object $wpdb, string $table, string $column): bool
    {
        $rows = $wpdb->get_results("SHOW COLUMNS FROM {$table} LIKE '" . addslashes($column) . "'", ARRAY_A);
        return is_array($rows) && $rows !== [];
    }

    private function indexExists(object $wpdb, string $table, string $index): bool
    {
        $rows = $wpdb->get_results("SHOW INDEX FROM {$table} WHERE Key_name = '" . addslashes($index) . "'", ARRAY_A);
        return is_array($rows) && $rows !== [];
    }
}
