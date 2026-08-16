<?php

declare(strict_types=1);

namespace SafeContracts\Contracts;

use RuntimeException;
use SafeContracts\Tenancy\CoreTenantScope;

final class ContractArchiveRepository
{
    /** @return array{id:int,accountant_user_id:?int,is_archived:bool}|null */
    public function findState(int $contractId): ?array
    {
        global $wpdb;
        if (! is_object($wpdb)) {
            throw new RuntimeException('SafeContracts contract archive requires WordPress $wpdb.');
        }

        $table = $wpdb->prefix . 'safecontracts_contracts';
        $tenant = $this->tenantCondition();
        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT id, accountant_user_id, is_archived FROM {$table} WHERE id = %d{$tenant} LIMIT 1", $contractId),
            ARRAY_A
        );
        if (! is_array($rows) || ! isset($rows[0]) || ! is_array($rows[0])) {
            return null;
        }

        $row = $rows[0];
        return [
            'id' => (int) ($row['id'] ?? 0),
            'accountant_user_id' => isset($row['accountant_user_id']) && $row['accountant_user_id'] !== null ? (int) $row['accountant_user_id'] : null,
            'is_archived' => (bool) ($row['is_archived'] ?? false),
        ];
    }

    public function archive(int $contractId, int $actorId): void
    {
        global $wpdb;
        if (! is_object($wpdb)) {
            throw new RuntimeException('SafeContracts contract archive requires WordPress $wpdb.');
        }

        $table = $wpdb->prefix . 'safecontracts_contracts';
        $tenant = $this->tenantCondition();
        $sql = $wpdb->prepare(
            "UPDATE {$table} SET is_archived = 1, updated_by = %d, updated_at = UTC_TIMESTAMP() WHERE id = %d AND is_archived = 0{$tenant}",
            $actorId,
            $contractId
        );
        if ($wpdb->query($sql) === false) {
            throw new RuntimeException('Unable to archive contract.');
        }
    }

    private function tenantCondition(string $column = 'tenant_id'): string
    {
        $tenantId = CoreTenantScope::tenantId();
        return $tenantId === null ? '' : ' AND ' . $column . ' = ' . $tenantId;
    }
}
