<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use SafeContracts\Database\Migration;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Roles\RoleRegistrar;
use RuntimeException;

final class Migration0017CounterpartyFinanceFoundation implements Migration
{
    public function up(object $wpdb): void
    {
        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $suppliers = $wpdb->prefix . 'safecontracts_suppliers';
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $payments = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $collections = $wpdb->prefix . 'safecontracts_payment_collections';
        $transactions = $wpdb->prefix . 'safecontracts_financial_transactions';

        dbDelta("CREATE TABLE {$suppliers} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            internal_code varchar(100) NULL,
            legal_name varchar(191) NOT NULL,
            trading_name varchar(191) NULL,
            contact_name varchar(191) NULL,
            phone varchar(64) NULL,
            email varchar(191) NULL,
            address text NULL,
            country_code char(2) NULL,
            registration_number varchar(100) NULL,
            tax_number varchar(100) NULL,
            default_currency char(3) NULL,
            payment_terms varchar(191) NULL,
            status varchar(16) NOT NULL DEFAULT 'active',
            notes longtext NULL,
            is_archived tinyint(1) NOT NULL DEFAULT 0,
            created_by bigint(20) unsigned NULL,
            updated_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY internal_code (internal_code),
            KEY status_name (status, is_archived, legal_name),
            KEY registration_number (registration_number),
            KEY tax_number (tax_number)
        ) {$charset};");

        // Legacy contracts proved their counterparty through customer_id. Make that
        // compatibility column nullable before supplier contracts are introduced.
        $this->execute($wpdb, "ALTER TABLE {$contracts} MODIFY customer_id bigint(20) unsigned NULL");
        $this->addColumn($wpdb, $contracts, 'counterparty_type', "varchar(32) NULL AFTER customer_id");
        $this->addColumn($wpdb, $contracts, 'counterparty_id', "bigint(20) unsigned NULL AFTER counterparty_type");
        $this->addColumn($wpdb, $contracts, 'financial_direction', "varchar(16) NULL AFTER counterparty_id");
        $this->addColumn($wpdb, $contracts, 'currency_code', "char(3) NULL AFTER financial_direction");
        $this->addIndex($wpdb, $contracts, 'counterparty_status', '(counterparty_type, counterparty_id, status, is_archived)');
        $this->addIndex($wpdb, $contracts, 'direction_status', '(financial_direction, status, is_archived)');

        // This is a deterministic migration, not a guess: every legacy contract
        // was structurally required to reference a customer.
        $this->execute(
            $wpdb,
            "UPDATE {$contracts}
             SET counterparty_type = 'customer',
                 counterparty_id = customer_id,
                 financial_direction = 'receivable'
             WHERE customer_id IS NOT NULL
               AND customer_id > 0
               AND (counterparty_type IS NULL OR counterparty_id IS NULL OR financial_direction IS NULL)"
        );

        $this->addColumn($wpdb, $payments, 'financial_direction', "varchar(16) NULL AFTER contract_id");
        $this->addColumn($wpdb, $payments, 'currency_code', "char(3) NULL AFTER financial_direction");
        $this->addIndex($wpdb, $payments, 'direction_due_status', '(financial_direction, due_date, status)');
        $this->addIndex($wpdb, $payments, 'currency_direction_due', '(currency_code, financial_direction, due_date)');
        $this->execute(
            $wpdb,
            "UPDATE {$payments} p
             INNER JOIN {$contracts} c ON c.id = p.contract_id
             SET p.financial_direction = c.financial_direction,
                 p.currency_code = c.currency_code
             WHERE p.financial_direction IS NULL OR p.currency_code IS NULL"
        );

        // Existing collection rows are historical receipts. Direction/currency are
        // made explicit without deleting or rewriting their monetary history.
        $this->addColumn($wpdb, $collections, 'financial_direction', "varchar(16) NULL AFTER payment_id");
        $this->addColumn($wpdb, $collections, 'currency_code', "char(3) NULL AFTER financial_direction");
        $this->addIndex($wpdb, $collections, 'direction_date', '(financial_direction, collection_date, id)');
        $this->execute(
            $wpdb,
            "UPDATE {$collections} pc
             INNER JOIN {$payments} p ON p.id = pc.payment_id
             SET pc.financial_direction = COALESCE(p.financial_direction, 'receivable'),
                 pc.currency_code = p.currency_code
             WHERE pc.financial_direction IS NULL OR pc.currency_code IS NULL"
        );

        dbDelta("CREATE TABLE {$transactions} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            payment_id bigint(20) unsigned NOT NULL,
            contract_id bigint(20) unsigned NOT NULL,
            financial_direction varchar(16) NOT NULL,
            transaction_kind varchar(16) NOT NULL,
            amount decimal(20,4) NOT NULL,
            currency_code char(3) NOT NULL,
            transaction_date date NOT NULL,
            payment_method_id bigint(20) unsigned NULL,
            reference varchar(191) NULL,
            details text NULL,
            proof_media_id bigint(20) unsigned NULL,
            idempotency_key varchar(128) NULL,
            reversal_of_transaction_id bigint(20) unsigned NULL,
            created_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY idempotency_key (idempotency_key),
            KEY payment_date (payment_id, transaction_date, id),
            KEY contract_direction_date (contract_id, financial_direction, transaction_date, id),
            KEY currency_direction_date (currency_code, financial_direction, transaction_date, id),
            KEY reversal_of (reversal_of_transaction_id)
        ) {$charset};");

        $this->grantCapabilities();
    }

    private function addColumn(object $wpdb, string $table, string $column, string $definition): void
    {
        $exists = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $column));
        if ($exists !== null) {
            return;
        }
        $this->execute($wpdb, "ALTER TABLE {$table} ADD {$column} {$definition}");
    }

    private function addIndex(object $wpdb, string $table, string $index, string $columns): void
    {
        $exists = $wpdb->get_var($wpdb->prepare("SHOW INDEX FROM {$table} WHERE Key_name = %s", $index));
        if ($exists !== null) {
            return;
        }
        $this->execute($wpdb, "ALTER TABLE {$table} ADD KEY {$index} {$columns}");
    }

    private function execute(object $wpdb, string $sql): void
    {
        if ($wpdb->query($sql) === false) {
            throw new RuntimeException('SafeContracts P11 finance migration failed.');
        }
    }

    private function grantCapabilities(): void
    {
        foreach (['administrator', RoleRegistrar::SYSTEM_ADMIN] as $roleSlug) {
            $role = get_role($roleSlug);
            if (! $role) {
                continue;
            }
            foreach (Capabilities::all() as $capability) {
                $role->add_cap($capability);
            }
        }

        $manager = get_role(RoleRegistrar::MANAGER);
        if ($manager) {
            foreach ([
                Capabilities::VIEW_SUPPLIERS,
                Capabilities::CREATE_SUPPLIERS,
                Capabilities::EDIT_SUPPLIERS,
                Capabilities::ARCHIVE_SUPPLIERS,
                Capabilities::VIEW_PAYABLES,
                Capabilities::VIEW_RECEIVABLES,
                Capabilities::RECORD_PAYMENT,
                Capabilities::RECORD_RECEIPT,
                Capabilities::MODIFY_FINANCE,
            ] as $capability) {
                $manager->add_cap($capability);
            }
        }

        $accountant = get_role(RoleRegistrar::ACCOUNTANT);
        if ($accountant) {
            foreach ([
                Capabilities::VIEW_SUPPLIERS,
                Capabilities::VIEW_PAYABLES,
                Capabilities::VIEW_RECEIVABLES,
                Capabilities::RECORD_PAYMENT,
                Capabilities::RECORD_RECEIPT,
            ] as $capability) {
                $accountant->add_cap($capability);
            }
        }
    }
}
