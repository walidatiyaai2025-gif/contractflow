<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use SafeContracts\Database\Migration;

final class Migration0056EnterpriseContractFinancialPaymentScheduleEntryRevisions implements Migration
{
    public function up(object $wpdb): void
    {
        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $revisions = $wpdb->prefix . 'safecontracts_contract_financial_payment_schedule_entry_revisions';

        dbDelta("CREATE TABLE {$revisions} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tenant_id bigint(20) unsigned NOT NULL,
            revision_uuid char(36) NOT NULL,
            contract_id bigint(20) unsigned NOT NULL,
            financial_currency_profile_id bigint(20) unsigned NOT NULL,
            schedule_entry_uuid char(36) NOT NULL,
            revision_number bigint(20) unsigned NOT NULL,
            sequence_no int(11) unsigned NOT NULL,
            reference varchar(100) NULL,
            due_date date NOT NULL,
            amount decimal(20,4) NOT NULL,
            currency_code char(3) NOT NULL,
            schedule_entry_state varchar(16) NOT NULL DEFAULT 'scheduled',
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY financial_payment_schedule_revision_uuid (revision_uuid),
            UNIQUE KEY tenant_contract_payment_schedule_revision (tenant_id, contract_id, schedule_entry_uuid, revision_number),
            KEY tenant_contract_payment_schedule_latest (tenant_id, contract_id, schedule_entry_uuid, revision_number, id),
            KEY tenant_contract_payment_schedule_sequence (tenant_id, contract_id, sequence_no, schedule_entry_uuid),
            KEY tenant_contract_payment_schedule_due_state (tenant_id, contract_id, due_date, schedule_entry_state, id),
            KEY tenant_contract_payment_schedule_profile (tenant_id, contract_id, financial_currency_profile_id)
        ) {$charset};");
    }
}
