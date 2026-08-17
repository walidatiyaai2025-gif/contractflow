<?php

declare(strict_types=1);

namespace SafeContracts\Contracts;

use RuntimeException;

final class ContractCurrencyRepository
{
    public function hasScheduledObligations(int $contractId): bool
    {
        return (new CounterpartyAssignmentRepository())->hasScheduledObligations($contractId);
    }

    public function update(int $contractId, string $currencyCode, int $actorId): void
    {
        global $wpdb;
        if (! is_object($wpdb) || ! method_exists($wpdb, 'prepare') || ! method_exists($wpdb, 'query')) {
            throw new RuntimeException('SafeContracts contract currency updates require WordPress $wpdb.');
        }
        $table = $wpdb->prefix . 'safecontracts_contracts';
        $sql = $wpdb->prepare(
            "UPDATE {$table}
             SET currency_code = %s, updated_by = %d, updated_at = UTC_TIMESTAMP()
             WHERE id = %d AND is_archived = 0",
            $currencyCode,
            $actorId,
            $contractId
        );
        if ($wpdb->query($sql) === false) {
            throw new RuntimeException('Unable to update contract currency.');
        }
    }
}
