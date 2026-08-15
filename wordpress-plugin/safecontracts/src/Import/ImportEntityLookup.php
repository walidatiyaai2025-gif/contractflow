<?php

declare(strict_types=1);

namespace SafeContracts\Import;

final class ImportEntityLookup
{
    public function customer(string $internalCode, string $name): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'safecontracts_customers';
        if ($internalCode !== '') {
            $sql = $wpdb->prepare("SELECT id, internal_code, name, is_active FROM {$table} WHERE internal_code = %s LIMIT 1", $internalCode);
        } else {
            $sql = $wpdb->prepare("SELECT id, internal_code, name, is_active FROM {$table} WHERE name = %s LIMIT 1", $name);
        }
        $rows = $wpdb->get_results($sql, ARRAY_A);
        return is_array($rows) && $rows !== [] ? $rows[0] : null;
    }

    public function contract(string $contractNumber): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'safecontracts_contracts';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, contract_number, customer_id, accountant_user_id, status, start_date, end_date, base_value, is_archived FROM {$table} WHERE contract_number = %s LIMIT 1",
            $contractNumber
        ), ARRAY_A);
        return is_array($rows) && $rows !== [] ? $rows[0] : null;
    }

    public function payment(int $contractId, int $sequenceNo): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'safecontracts_payments';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, contract_id, sequence_no, reference, due_date, expected_payment_date, original_amount, paid_amount, remaining_amount, status FROM {$table} WHERE contract_id = %d AND sequence_no = %d LIMIT 1",
            $contractId,
            $sequenceNo
        ), ARRAY_A);
        return is_array($rows) && $rows !== [] ? $rows[0] : null;
    }
}
