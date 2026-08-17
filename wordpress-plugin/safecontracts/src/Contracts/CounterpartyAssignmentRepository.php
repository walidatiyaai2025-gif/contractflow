<?php

declare(strict_types=1);

namespace SafeContracts\Contracts;

use RuntimeException;

final class CounterpartyAssignmentRepository
{
    public function hasScheduledObligations(int $contractId): bool
    {
        global $wpdb;
        if (! is_object($wpdb)) {
            throw new RuntimeException('SafeContracts counterparty assignment requires WordPress $wpdb.');
        }
        $table = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM {$table} WHERE contract_id = %d LIMIT 1",
            $contractId
        ), ARRAY_A);
        return is_array($rows) && $rows !== [];
    }
}
