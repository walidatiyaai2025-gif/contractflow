<?php

declare(strict_types=1);

namespace SafeContracts\Contracts;

use RuntimeException;

final class ContractCurrencyRepository
{
    public function update(int $contractId, string $currencyCode, int $actorId): void
    {
        global $wpdb;
        if (! is_object($wpdb)) {
            throw new RuntimeException('SafeContracts contract currency update requires WordPress $wpdb.');
        }
        $table = $wpdb->prefix . 'safecontracts_contracts';
        $sql = $wpdb->prepare(
            "UPDATE {$table} SET currency_code = %s, updated_by = %d, updated_at = UTC_TIMESTAMP() WHERE id = %d",
            $currencyCode,
            $actorId,
            $contractId
        );
        if ($wpdb->query($sql) === false) {
            throw new RuntimeException('Unable to update contract currency.');
        }
    }
}
