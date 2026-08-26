<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use RuntimeException;
use SafeContracts\Database\ProductionMigration;

/**
 * Adds explicit counterparty/direction scope to notification rules.
 *
 * Legacy rows remain all/all so upgrading cannot silently narrow or broaden
 * deliveries. Administrators can opt into customer/supplier and
 * receivable/payable scope on rules without encoding business semantics in
 * the rule name.
 */
final class Migration0023NotificationRuleScope implements ProductionMigration
{
    private bool $addedCounterpartyType = false;
    private bool $addedFinancialDirection = false;

    public function preflight(object $wpdb): void
    {
        $table = $wpdb->prefix . 'safecontracts_notification_rules';
        $rows = $wpdb->get_results("SELECT id FROM {$table} ORDER BY id ASC LIMIT 1", ARRAY_A);
        if (! is_array($rows)) {
            throw new RuntimeException('SafeContracts notification-rule scope preflight could not read the rules table.');
        }
        $this->assertNoDatabaseError($wpdb, 'SafeContracts notification-rule scope preflight failed.');
    }

    public function up(object $wpdb): void
    {
        $table = $wpdb->prefix . 'safecontracts_notification_rules';

        if (! $this->columnExists($wpdb, $table, 'counterparty_type')) {
            $result = $wpdb->query(
                "ALTER TABLE {$table} ADD COLUMN counterparty_type varchar(16) NOT NULL DEFAULT 'all' AFTER trigger_type"
            );
            if ($result === false) {
                throw new RuntimeException('SafeContracts could not add notification counterparty scope.');
            }
            $this->addedCounterpartyType = true;
        }

        if (! $this->columnExists($wpdb, $table, 'financial_direction')) {
            $result = $wpdb->query(
                "ALTER TABLE {$table} ADD COLUMN financial_direction varchar(16) NOT NULL DEFAULT 'all' AFTER counterparty_type"
            );
            if ($result === false) {
                throw new RuntimeException('SafeContracts could not add notification financial-direction scope.');
            }
            $this->addedFinancialDirection = true;
        }

        // Keep all historical rules behavior-compatible. Scope is explicit and
        // opt-in; rule names/codes are presentation identifiers, not authority.
        $result = $wpdb->query(
            "UPDATE {$table}
             SET counterparty_type = 'all'
             WHERE counterparty_type IS NULL OR counterparty_type NOT IN ('all','customer','supplier')"
        );
        if ($result === false) {
            throw new RuntimeException('SafeContracts could not normalize notification counterparty scope.');
        }

        $result = $wpdb->query(
            "UPDATE {$table}
             SET financial_direction = 'all'
             WHERE financial_direction IS NULL OR financial_direction NOT IN ('all','receivable','payable')"
        );
        if ($result === false) {
            throw new RuntimeException('SafeContracts could not normalize notification financial-direction scope.');
        }
    }

    public function verify(object $wpdb): void
    {
        $table = $wpdb->prefix . 'safecontracts_notification_rules';
        foreach (['counterparty_type', 'financial_direction'] as $column) {
            if (! $this->columnExists($wpdb, $table, $column)) {
                throw new RuntimeException('SafeContracts notification-rule scope verification is missing column: ' . $column);
            }
        }

        $invalid = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table}
             WHERE counterparty_type NOT IN ('all','customer','supplier')
                OR financial_direction NOT IN ('all','receivable','payable')"
        );
        $this->assertNoDatabaseError($wpdb, 'SafeContracts notification-rule scope verification failed.');
        if ((int) $invalid !== 0) {
            throw new RuntimeException('SafeContracts notification-rule scope verification found invalid persisted values.');
        }
    }

    public function rollback(object $wpdb): void
    {
        $table = $wpdb->prefix . 'safecontracts_notification_rules';

        if ($this->addedFinancialDirection && $this->columnExists($wpdb, $table, 'financial_direction')) {
            if ($wpdb->query("ALTER TABLE {$table} DROP COLUMN financial_direction") === false) {
                throw new RuntimeException('SafeContracts could not rollback notification financial-direction scope.');
            }
        }
        if ($this->addedCounterpartyType && $this->columnExists($wpdb, $table, 'counterparty_type')) {
            if ($wpdb->query("ALTER TABLE {$table} DROP COLUMN counterparty_type") === false) {
                throw new RuntimeException('SafeContracts could not rollback notification counterparty scope.');
            }
        }
    }

    private function columnExists(object $wpdb, string $table, string $column): bool
    {
        $rows = $wpdb->get_results(
            $wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $column),
            ARRAY_A
        );
        $this->assertNoDatabaseError($wpdb, 'SafeContracts notification-rule scope column inspection failed.');
        return is_array($rows) && $rows !== [];
    }

    private function assertNoDatabaseError(object $wpdb, string $message): void
    {
        if (property_exists($wpdb, 'last_error') && trim((string) $wpdb->last_error) !== '') {
            throw new RuntimeException($message . ' ' . trim((string) $wpdb->last_error));
        }
    }
}
