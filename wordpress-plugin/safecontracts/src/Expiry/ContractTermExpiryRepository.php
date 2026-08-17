<?php

declare(strict_types=1);

namespace SafeContracts\Expiry;

use RuntimeException;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

final class ContractTermExpiryRepository
{
    /** @return array<string,mixed>|null */
    public function findContract(int $contractId): ?array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, contract_number, accountant_user_id, status, start_date, end_date, is_archived
             FROM {$contracts}
             WHERE id = %d AND tenant_id = %d
             LIMIT 1",
            $contractId,
            $tenantId
        ), ARRAY_A);
        return is_array($rows) && $rows !== [] && is_array($rows[0]) ? $rows[0] : null;
    }

    private function tenantId(): int
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise Contract expiry evaluation requires core tenant enforcement.');
        }
        return TenantContextStore::context()->requireTenantId();
    }
}
