<?php

declare(strict_types=1);

namespace SafeContracts\Contracts;

use RuntimeException;
use SafeContracts\Tenancy\CoreTenantScope;

final class ContractRepository
{
    public function customerIsActive(int $customerId): bool
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_customers';
        $tenantId = CoreTenantScope::tenantId();
        $tenant = $tenantId === null ? '' : ' AND tenant_id = ' . $tenantId;
        $rows = $wpdb->get_results($wpdb->prepare("SELECT id FROM {$table} WHERE id = %d AND is_active = 1{$tenant} LIMIT 1", $customerId), ARRAY_A);
        return is_array($rows) && $rows !== [];
    }

    /** @return array{id:int, contract_number:string, customer_id:int, accountant_user_id:?int, status:string, start_date:?string, end_date:?string, base_value:string, notes:string, is_archived:bool}|null */
    public function find(int $contractId): ?array
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_contracts';
        $tenantId = CoreTenantScope::tenantId();
        $tenant = $tenantId === null ? '' : ' AND tenant_id = ' . $tenantId;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, contract_number, customer_id, accountant_user_id, status, start_date, end_date, base_value, notes, is_archived FROM {$table} WHERE id = %d{$tenant} LIMIT 1",
            $contractId
        ), ARRAY_A);
        if (! is_array($rows) || $rows === []) {
            return null;
        }
        $row = $rows[0];
        return [
            'id' => (int) ($row['id'] ?? 0),
            'contract_number' => (string) ($row['contract_number'] ?? ''),
            'customer_id' => (int) ($row['customer_id'] ?? 0),
            'accountant_user_id' => isset($row['accountant_user_id']) && $row['accountant_user_id'] !== null ? (int) $row['accountant_user_id'] : null,
            'status' => (string) ($row['status'] ?? ContractStatus::DRAFT),
            'start_date' => isset($row['start_date']) && $row['start_date'] !== null ? (string) $row['start_date'] : null,
            'end_date' => isset($row['end_date']) && $row['end_date'] !== null ? (string) $row['end_date'] : null,
            'base_value' => ContractMoney::normalizeNonNegative((string) ($row['base_value'] ?? '0')),
            'notes' => (string) ($row['notes'] ?? ''),
            'is_archived' => (bool) ($row['is_archived'] ?? false),
        ];
    }

    public function create(string $contractNumber, int $customerId, ?int $accountantUserId, string $notes, int $actorId): int
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_contracts';
        $tenantId = CoreTenantScope::tenantId();
        if ($tenantId !== null && ! $this->customerIsActive($customerId)) {
            throw new RuntimeException('Contract customer is outside the current Enterprise tenant.');
        }

        if ($tenantId === null) {
            if ($accountantUserId === null) {
                $sql = $wpdb->prepare("INSERT INTO {$table} (contract_number, customer_id, accountant_user_id, status, notes, created_by, updated_by, created_at, updated_at) VALUES (%s, %d, NULL, %s, %s, %d, %d, UTC_TIMESTAMP(), UTC_TIMESTAMP())", $contractNumber, $customerId, ContractStatus::DRAFT, $notes, $actorId, $actorId);
            } else {
                $sql = $wpdb->prepare("INSERT INTO {$table} (contract_number, customer_id, accountant_user_id, status, notes, created_by, updated_by, created_at, updated_at) VALUES (%s, %d, %d, %s, %s, %d, %d, UTC_TIMESTAMP(), UTC_TIMESTAMP())", $contractNumber, $customerId, $accountantUserId, ContractStatus::DRAFT, $notes, $actorId, $actorId);
            }
        } elseif ($accountantUserId === null) {
            $sql = $wpdb->prepare("INSERT INTO {$table} (tenant_id, contract_number, customer_id, accountant_user_id, status, notes, created_by, updated_by, created_at, updated_at) VALUES (%d, %s, %d, NULL, %s, %s, %d, %d, UTC_TIMESTAMP(), UTC_TIMESTAMP())", $tenantId, $contractNumber, $customerId, ContractStatus::DRAFT, $notes, $actorId, $actorId);
        } else {
            $sql = $wpdb->prepare("INSERT INTO {$table} (tenant_id, contract_number, customer_id, accountant_user_id, status, notes, created_by, updated_by, created_at, updated_at) VALUES (%d, %s, %d, %d, %s, %s, %d, %d, UTC_TIMESTAMP(), UTC_TIMESTAMP())", $tenantId, $contractNumber, $customerId, $accountantUserId, ContractStatus::DRAFT, $notes, $actorId, $actorId);
        }
        if ($wpdb->query($sql) === false) {
            throw new RuntimeException('Unable to create contract.');
        }
        return (int) $wpdb->insert_id;
    }

    public function updateDetails(int $contractId, string $contractNumber, string $notes, int $actorId): void
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_contracts';
        $tenant = $this->tenantCondition();
        $this->executeMutation($wpdb, $wpdb->prepare("UPDATE {$table} SET contract_number = %s, notes = %s, updated_by = %d, updated_at = UTC_TIMESTAMP() WHERE id = %d{$tenant}", $contractNumber, $notes, $actorId, $contractId), 'Unable to edit contract.');
    }

    public function updateDates(int $contractId, ?string $startDate, ?string $endDate, int $actorId): void
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_contracts';
        $startSql = $startDate === null ? 'NULL' : "'" . addslashes($startDate) . "'";
        $endSql = $endDate === null ? 'NULL' : "'" . addslashes($endDate) . "'";
        $tenant = $this->tenantCondition();
        $sql = $wpdb->prepare("UPDATE {$table} SET start_date = {$startSql}, end_date = {$endSql}, updated_by = %d, updated_at = UTC_TIMESTAMP() WHERE id = %d{$tenant}", $actorId, $contractId);
        $this->executeMutation($wpdb, $sql, 'Unable to update contract dates.');
    }

    public function updateBaseValue(int $contractId, string $baseValue, int $actorId): void
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_contracts';
        $tenant = $this->tenantCondition();
        $this->executeMutation($wpdb, $wpdb->prepare("UPDATE {$table} SET base_value = %s, updated_by = %d, updated_at = UTC_TIMESTAMP() WHERE id = %d{$tenant}", $baseValue, $actorId, $contractId), 'Unable to update contract base value.');
    }

    public function addFinancialItem(int $contractId, string $description, string $amount, int $displayOrder, int $actorId): int
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_contract_financial_items';
        $tenantId = CoreTenantScope::tenantId();
        $this->assertOwnedContract($wpdb, $contractId, $tenantId);
        if ($tenantId === null) {
            $sql = $wpdb->prepare("INSERT INTO {$table} (contract_id, description, amount, display_order, created_by, updated_by, created_at, updated_at) VALUES (%d, %s, %s, %d, %d, %d, UTC_TIMESTAMP(), UTC_TIMESTAMP())", $contractId, $description, $amount, $displayOrder, $actorId, $actorId);
        } else {
            $sql = $wpdb->prepare("INSERT INTO {$table} (tenant_id, contract_id, description, amount, display_order, created_by, updated_by, created_at, updated_at) VALUES (%d, %d, %s, %s, %d, %d, %d, UTC_TIMESTAMP(), UTC_TIMESTAMP())", $tenantId, $contractId, $description, $amount, $displayOrder, $actorId, $actorId);
        }
        if ($wpdb->query($sql) === false) {
            throw new RuntimeException('Unable to add contract financial item.');
        }
        return (int) $wpdb->insert_id;
    }

    public function addAdjustment(int $contractId, string $type, string $description, string $amount, int $displayOrder, int $actorId): int
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_contract_adjustments';
        $tenantId = CoreTenantScope::tenantId();
        $this->assertOwnedContract($wpdb, $contractId, $tenantId);
        if ($tenantId === null) {
            $sql = $wpdb->prepare("INSERT INTO {$table} (contract_id, adjustment_type, description, amount, display_order, created_by, updated_by, created_at, updated_at) VALUES (%d, %s, %s, %s, %d, %d, %d, UTC_TIMESTAMP(), UTC_TIMESTAMP())", $contractId, $type, $description, $amount, $displayOrder, $actorId, $actorId);
        } else {
            $sql = $wpdb->prepare("INSERT INTO {$table} (tenant_id, contract_id, adjustment_type, description, amount, display_order, created_by, updated_by, created_at, updated_at) VALUES (%d, %d, %s, %s, %s, %d, %d, %d, UTC_TIMESTAMP(), UTC_TIMESTAMP())", $tenantId, $contractId, $type, $description, $amount, $displayOrder, $actorId, $actorId);
        }
        if ($wpdb->query($sql) === false) {
            throw new RuntimeException('Unable to add contract adjustment.');
        }
        return (int) $wpdb->insert_id;
    }

    /** @return array{items:string, additions:string, discounts:string} */
    public function financialTotals(int $contractId): array
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $itemsTable = $wpdb->prefix . 'safecontracts_contract_financial_items';
        $adjustmentsTable = $wpdb->prefix . 'safecontracts_contract_adjustments';
        $tenant = $this->tenantCondition();
        $itemRows = $wpdb->get_results($wpdb->prepare("SELECT COALESCE(SUM(amount), 0) AS total FROM {$itemsTable} WHERE contract_id = %d{$tenant}", $contractId), ARRAY_A);
        $adjustmentRows = $wpdb->get_results($wpdb->prepare("SELECT adjustment_type, COALESCE(SUM(amount), 0) AS total FROM {$adjustmentsTable} WHERE contract_id = %d{$tenant} GROUP BY adjustment_type", $contractId), ARRAY_A);
        $additions = '0';
        $discounts = '0';
        foreach ($adjustmentRows as $row) {
            if (($row['adjustment_type'] ?? '') === 'addition') {
                $additions = (string) ($row['total'] ?? '0');
            } elseif (($row['adjustment_type'] ?? '') === 'discount') {
                $discounts = (string) ($row['total'] ?? '0');
            }
        }
        return [
            'items' => (string) ($itemRows[0]['total'] ?? '0'),
            'additions' => $additions,
            'discounts' => $discounts,
        ];
    }

    public function attachMedia(int $contractId, int $mediaId, string $label, int $actorId): int
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_contract_attachments';
        $tenantId = CoreTenantScope::tenantId();
        $this->assertOwnedContract($wpdb, $contractId, $tenantId);
        if ($tenantId === null) {
            $sql = $wpdb->prepare("INSERT INTO {$table} (contract_id, media_id, label, created_by, created_at) VALUES (%d, %d, %s, %d, UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE label = VALUES(label)", $contractId, $mediaId, $label, $actorId);
        } else {
            $sql = $wpdb->prepare("INSERT INTO {$table} (tenant_id, contract_id, media_id, label, created_by, created_at) VALUES (%d, %d, %d, %s, %d, UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE label = VALUES(label)", $tenantId, $contractId, $mediaId, $label, $actorId);
        }
        if ($wpdb->query($sql) === false) {
            throw new RuntimeException('Unable to attach contract document.');
        }
        return (int) $wpdb->insert_id;
    }

    public function detachMedia(int $contractId, int $mediaId): void
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_contract_attachments';
        $tenant = $this->tenantCondition();
        $this->executeMutation($wpdb, $wpdb->prepare("DELETE FROM {$table} WHERE contract_id = %d AND media_id = %d{$tenant}", $contractId, $mediaId), 'Unable to detach contract document.');
    }

    public function assignCustomer(int $contractId, int $customerId, int $actorId): void
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_contracts';
        $tenant = $this->tenantCondition();
        $this->executeMutation($wpdb, $wpdb->prepare("UPDATE {$table} SET customer_id = %d, updated_by = %d, updated_at = UTC_TIMESTAMP() WHERE id = %d{$tenant}", $customerId, $actorId, $contractId), 'Unable to assign contract customer.');
    }

    public function assignAccountant(int $contractId, ?int $accountantUserId, int $actorId): void
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_contracts';
        $tenant = $this->tenantCondition();
        if ($accountantUserId === null) {
            $sql = $wpdb->prepare("UPDATE {$table} SET accountant_user_id = NULL, updated_by = %d, updated_at = UTC_TIMESTAMP() WHERE id = %d{$tenant}", $actorId, $contractId);
        } else {
            $sql = $wpdb->prepare("UPDATE {$table} SET accountant_user_id = %d, updated_by = %d, updated_at = UTC_TIMESTAMP() WHERE id = %d{$tenant}", $accountantUserId, $actorId, $contractId);
        }
        $this->executeMutation($wpdb, $sql, 'Unable to assign contract accountant.');
    }

    public function updateStatus(int $contractId, string $status, int $actorId): void
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_contracts';
        $tenant = $this->tenantCondition();
        $this->executeMutation($wpdb, $wpdb->prepare("UPDATE {$table} SET status = %s, updated_by = %d, updated_at = UTC_TIMESTAMP() WHERE id = %d{$tenant}", $status, $actorId, $contractId), 'Unable to update contract status.');
    }

    private function tenantCondition(string $column = 'tenant_id'): string
    {
        $tenantId = CoreTenantScope::tenantId();
        return $tenantId === null ? '' : ' AND ' . $column . ' = ' . $tenantId;
    }

    private function assertOwnedContract(object $wpdb, int $contractId, ?int $tenantId): void
    {
        if ($tenantId === null) {
            return;
        }
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM {$contracts} WHERE id = %d AND tenant_id = %d LIMIT 1",
            $contractId,
            $tenantId
        ), ARRAY_A);
        if (! is_array($rows) || $rows === []) {
            throw new RuntimeException('Contract is outside the current Enterprise tenant.');
        }
    }

    private function assertWpdb(mixed $wpdb): void
    {
        if (! is_object($wpdb)) {
            throw new RuntimeException('SafeContracts contracts require WordPress $wpdb.');
        }
    }

    private function executeMutation(object $wpdb, string $sql, string $message): void
    {
        if ($wpdb->query($sql) === false) {
            throw new RuntimeException($message);
        }
    }
}
