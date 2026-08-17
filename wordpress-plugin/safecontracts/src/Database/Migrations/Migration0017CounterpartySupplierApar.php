<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use SafeContracts\Database\Migration;

final class Migration0017CounterpartySupplierApar implements Migration
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

        dbDelta("CREATE TABLE {$suppliers} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            internal_code varchar(100) NULL,
            name varchar(191) NOT NULL,
            contact_name varchar(191) NULL,
            email varchar(191) NULL,
            phone varchar(64) NULL,
            notes text NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            is_archived tinyint(1) NOT NULL DEFAULT 0,
            archived_by bigint(20) unsigned NULL,
            archived_at datetime NULL,
            created_by bigint(20) unsigned NULL,
            updated_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY internal_code (internal_code),
            KEY active_name (is_active, is_archived, name),
            KEY archived_name (is_archived, name)
        ) {$charset};");

        dbDelta("CREATE TABLE {$contracts} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            contract_number varchar(100) NOT NULL,
            customer_id bigint(20) unsigned NULL,
            counterparty_type varchar(16) NULL,
            counterparty_id bigint(20) unsigned NULL,
            financial_direction varchar(16) NULL,
            currency_code char(3) NULL,
            accountant_user_id bigint(20) unsigned NULL,
            status varchar(32) NOT NULL DEFAULT 'draft',
            start_date date NULL,
            end_date date NULL,
            base_value decimal(20,4) NOT NULL DEFAULT 0.0000,
            notes longtext NULL,
            is_archived tinyint(1) NOT NULL DEFAULT 0,
            created_by bigint(20) unsigned NULL,
            updated_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY contract_number (contract_number),
            KEY customer_status (customer_id, status, is_archived),
            KEY counterparty_status (counterparty_type, counterparty_id, status, is_archived),
            KEY direction_currency (financial_direction, currency_code, is_archived),
            KEY accountant_status (accountant_user_id, status, is_archived),
            KEY contract_dates (start_date, end_date)
        ) {$charset};");

        dbDelta("CREATE TABLE {$payments} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            contract_id bigint(20) unsigned NOT NULL,
            financial_direction varchar(16) NULL,
            currency_code char(3) NULL,
            sequence_no int(11) unsigned NOT NULL,
            reference varchar(100) NULL,
            due_date date NOT NULL,
            expected_payment_date date NULL,
            original_amount decimal(20,4) NOT NULL DEFAULT 0.0000,
            paid_amount decimal(20,4) NOT NULL DEFAULT 0.0000,
            remaining_amount decimal(20,4) NOT NULL DEFAULT 0.0000,
            status varchar(32) NOT NULL DEFAULT 'upcoming',
            is_archived tinyint(1) NOT NULL DEFAULT 0,
            archived_by bigint(20) unsigned NULL,
            archived_at datetime NULL,
            followup_notes longtext NULL,
            created_by bigint(20) unsigned NULL,
            updated_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY contract_sequence (contract_id, sequence_no),
            KEY contract_status_due (contract_id, status, due_date),
            KEY direction_currency_due (financial_direction, currency_code, due_date, status),
            KEY due_status (due_date, status),
            KEY expected_date (expected_payment_date),
            KEY archived_due (is_archived, due_date, id)
        ) {$charset};");

        dbDelta("CREATE TABLE {$collections} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            payment_id bigint(20) unsigned NOT NULL,
            financial_direction varchar(16) NULL,
            currency_code char(3) NULL,
            amount decimal(20,4) NOT NULL,
            collection_date date NOT NULL,
            payment_method_id bigint(20) unsigned NOT NULL,
            reference varchar(191) NULL,
            details text NULL,
            proof_media_id bigint(20) unsigned NULL,
            is_archived tinyint(1) NOT NULL DEFAULT 0,
            archived_by bigint(20) unsigned NULL,
            archived_at datetime NULL,
            created_by bigint(20) unsigned NULL,
            updated_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY payment_date (payment_id, collection_date, id),
            KEY direction_currency_date (financial_direction, currency_code, collection_date, id),
            KEY method_date (payment_method_id, collection_date, id),
            KEY collection_date (collection_date, id),
            KEY proof_media (proof_media_id),
            KEY archived_payment_date (is_archived, payment_id, collection_date, id)
        ) {$charset};");

        $currency = $this->configuredCurrency();
        $wpdb->query("UPDATE {$contracts}
            SET counterparty_type = 'customer', counterparty_id = customer_id
            WHERE customer_id IS NOT NULL AND customer_id > 0
              AND (counterparty_type IS NULL OR counterparty_type = '' OR counterparty_id IS NULL OR counterparty_id = 0)");
        $wpdb->query("UPDATE {$contracts}
            SET financial_direction = 'receivable'
            WHERE counterparty_type = 'customer'
              AND (financial_direction IS NULL OR financial_direction = '')");
        $wpdb->query($wpdb->prepare(
            "UPDATE {$contracts} SET currency_code = %s WHERE currency_code IS NULL OR currency_code = ''",
            $currency
        ));
        $wpdb->query("UPDATE {$payments} p
            INNER JOIN {$contracts} c ON c.id = p.contract_id
            SET p.financial_direction = COALESCE(NULLIF(p.financial_direction, ''), c.financial_direction, 'receivable'),
                p.currency_code = COALESCE(NULLIF(p.currency_code, ''), NULLIF(c.currency_code, ''), 'XXX')
            WHERE p.financial_direction IS NULL OR p.financial_direction = '' OR p.currency_code IS NULL OR p.currency_code = ''");
        $wpdb->query("UPDATE {$collections} cl
            INNER JOIN {$payments} p ON p.id = cl.payment_id
            SET cl.financial_direction = COALESCE(NULLIF(cl.financial_direction, ''), p.financial_direction, 'receivable'),
                cl.currency_code = COALESCE(NULLIF(cl.currency_code, ''), NULLIF(p.currency_code, ''), 'XXX')
            WHERE cl.financial_direction IS NULL OR cl.financial_direction = '' OR cl.currency_code IS NULL OR cl.currency_code = ''");
    }

    private function configuredCurrency(): string
    {
        if (! function_exists('get_option')) {
            return 'XXX';
        }
        $settings = get_option('safecontracts_general_settings', []);
        $currency = is_array($settings) ? strtoupper(trim((string) ($settings['currency_code'] ?? ''))) : '';
        return preg_match('/^[A-Z]{3}$/', $currency) ? $currency : 'XXX';
    }
}
