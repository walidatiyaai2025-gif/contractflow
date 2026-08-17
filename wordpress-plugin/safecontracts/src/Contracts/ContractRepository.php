<?php

declare(strict_types=1);

namespace SafeContracts\Contracts;

use RuntimeException;
use SafeContracts\Payments\CurrencyCode;
use SafeContracts\Payments\FinancialDirection;

final class ContractRepository
{
    public function customerIsActive(int $customerId): bool
    {
        return $this->counterpartyIsActive(Counterparty::CUSTOMER, $customerId);
    }

    public function counterpartyIsActive(string $type, int $counterpartyId): bool
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $type = Counterparty::normalize($type);
        $table = $type === Counterparty::SUPPLIER
            ? $wpdb->prefix . 'safecontracts_suppliers'
            : $wpdb->prefix . 'safecontracts_customers';
        $archive = $type === Counterparty::SUPPLIER ? ' AND is_archived = 0' : '';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM {$table} WHERE id = %d AND is_active = 1{$archive} LIMIT 1",
            $counterpartyId
        ), ARRAY_A);
        return is_array($rows) && $rows !== [];
    }

    /** @return array{id:int, contract_number:string, customer_id:?int, counterparty_type:string, counterparty_id:int, financial_direction:string, currency_code:string, accountant_user_id:?int, status:string, start_date:?string, end_date:?string, base_value:string, notes:string, is_archived:bool}|null */
    public function find(int $contractId): ?array
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_contracts';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, contract_number, customer_id, counterparty_type, counterparty_id, financial_direction, currency_code,
                    accountant_user_id, status, start_date, end_date, base_value, notes, is_archived
             FROM {$table} WHERE id = %d LIMIT 1",
            $contractId
        ), ARRAY_A);
        if (! is_array($rows) || $rows === []) {
            return null;
        }
        $row = $rows[0];
        $customerId = isset($row['customer_id']) && $row['customer_id'] !== null ? (int) $row['customer_id'] : null;
        $type = trim((string) ($row['counterparty_type'] ?? ''));
        if ($type === '') {
            $type = $customerId !== null && $customerId > 0 ? Counterparty::CUSTOMER : '';
        }
        $type = Counterparty::normalize($type);
        $counterpartyId = (int) ($row['counterparty_id'] ?? 0);
        if ($counterpartyId <= 0 && $type === Counterparty::CUSTOMER && $customerId !== null) {
            $counterpartyId = $customerId;
        }
        $direction = trim((string) ($row['financial_direction'] ?? ''));
        if ($direction === '') {
            $direction = Counterparty::defaultFinancialDirection($type);
        }
        $currency = strtoupper(trim((string) ($row['currency_code'] ?? '')));
        if ($currency === '') {
            $currency = CurrencyCode::UNKNOWN;
        }
        return [
            'id' => (int) ($row['id'] ?? 0),
            'contract_number' => (string) ($row['contract_number'] ?? ''),
            'customer_id' => $customerId,
            'counterparty_type' => $type,
            'counterparty_id' => $counterpartyId,
            'financial_direction' => FinancialDirection::normalize($direction),
            'currency_code' => CurrencyCode::normalize($currency),
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
        return $this->createForCounterparty(
            $contractNumber,
            Counterparty::CUSTOMER,
            $customerId,
            FinancialDirection::RECEIVABLE,
            CurrencyCode::fromInputOrSettings(null),
            $accountantUserId,
            $notes,
            $actorId
        );
    }

    public function createForCounterparty(
        string $contractNumber,
        string $counterpartyType,
        int $counterpartyId,
        string $financialDirection,
        string $currencyCode,
        ?int $accountantUserId,
        string $notes,
        int $actorId
    ): int {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_contracts';
        $counterpartyType = Counterparty::normalize($counterpartyType);
        $financialDirection = FinancialDirection::normalize($financialDirection);
        $currencyCode = CurrencyCode::normalize($currencyCode);
        $customerId = $counterpartyType === Counterparty::CUSTOMER ? $counterpartyId : null;
        $customerSql = $customerId === null ? 'NULL' : '%d';
        $accountantSql = $accountantUserId === null ? 'NULL' : '%d';
        $query = "INSERT INTO {$table}
            (contract_number, customer_id, accountant_user_id, counterparty_type, counterparty_id, financial_direction, currency_code, status, notes, created_by, updated_by, created_at, updated_at)
            VALUES (%s, {$customerSql}, {$accountantSql}, %s, %d, %s, %s, %s, %s, %d, %d, UTC_TIMESTAMP(), UTC_TIMESTAMP())";
        $args = [$contractNumber];
        if ($customerId !== null) {
            $args[] = $customerId;
        }
        if ($accountantUserId !== null) {
            $args[] = $accountantUserId;
        }
        $args[] = $counterpartyType;
        $args[] = $counterpartyId;
        $args[] = $financialDirection;
        $args[] = $currencyCode;
        $args[] = ContractStatus::DRAFT;
        $args[] = $notes;
        $args[] = $actorId;
        $args[] = $actorId;
        if ($wpdb->query($wpdb->prepare($query, ...$args)) === false) {
            throw new RuntimeException('Unable to create contract.');
        }
        return (int) $wpdb->insert_id;
    }

    public function updateDetails(int $contractId, string $contractNumber, string $notes, int $actorId): void
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_contracts';
        $this->executeMutation($wpdb, $wpdb->prepare("UPDATE {$table} SET contract_number = %s, notes = %s, updated_by = %d, updated_at = UTC_TIMESTAMP() WHERE id = %d", $contractNumber, $notes, $actorId, $contractId), 'Unable to edit contract.');
    }

    public function updateDates(int $contractId, ?string $startDate, ?string $endDate, int $actorId): void
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_contracts';
        $startSql = $startDate === null ? 'NULL' : "'" . addslashes($startDate) . "'";
        $endSql = $endDate === null ? 'NULL' : "'" . addslashes($endDate) . "'";
        $sql = $wpdb->prepare("UPDATE {$table} SET start_date = {$startSql}, end_date = {$endSql}, updated_by = %d, updated_at = UTC_TIMESTAMP() WHERE id = %d", $actorId, $contractId);
        $this->executeMutation($wpdb, $sql, 'Unable to update contract dates.');
    }

    public function updateBaseValue(int $contractId, string $baseValue, int $actorId): void
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_contracts';
        $this->executeMutation($wpdb, $wpdb->prepare("UPDATE {$table} SET base_value = %s, updated_by = %d, updated_at = UTC_TIMESTAMP() WHERE id = %d", $baseValue, $actorId, $contractId), 'Unable to update contract base value.');
    }

    public function addFinancialItem(int $contractId, string $description, string $amount, int $displayOrder, int $actorId): int
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_contract_financial_items';
        $sql = $wpdb->prepare("INSERT INTO {$table} (contract_id, description, amount, display_order, created_by, updated_by, created_at, updated_at) VALUES (%d, %s, %s, %d, %d, %d, UTC_TIMESTAMP(), UTC_TIMESTAMP())", $contractId, $description, $amount, $displayOrder, $actorId, $actorId);
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
        $sql = $wpdb->prepare("INSERT INTO {$table} (contract_id, adjustment_type, description, amount, display_order, created_by, updated_by, created_at, updated_at) VALUES (%d, %s, %s, %s, %d, %d, %d, UTC_TIMESTAMP(), UTC_TIMESTAMP())", $contractId, $type, $description, $amount, $displayOrder, $actorId, $actorId);
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
        $itemRows = $wpdb->get_results($wpdb->prepare("SELECT COALESCE(SUM(amount), 0) AS total FROM {$itemsTable} WHERE contract_id = %d", $contractId), ARRAY_A);
        $adjustmentRows = $wpdb->get_results($wpdb->prepare("SELECT adjustment_type, COALESCE(SUM(amount), 0) AS total FROM {$adjustmentsTable} WHERE contract_id = %d GROUP BY adjustment_type", $contractId), ARRAY_A);
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
        $sql = $wpdb->prepare("INSERT INTO {$table} (contract_id, media_id, label, created_by, created_at) VALUES (%d, %d, %s, %d, UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE label = VALUES(label)", $contractId, $mediaId, $label, $actorId);
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
        $this->executeMutation($wpdb, $wpdb->prepare("DELETE FROM {$table} WHERE contract_id = %d AND media_id = %d", $contractId, $mediaId), 'Unable to detach contract document.');
    }

    public function assignCustomer(int $contractId, int $customerId, int $actorId): void
    {
        $this->assignCounterparty($contractId, Counterparty::CUSTOMER, $customerId, $actorId);
    }

    public function assignCounterparty(int $contractId, string $type, int $counterpartyId, int $actorId): void
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_contracts';
        $type = Counterparty::normalize($type);
        $direction = Counterparty::defaultFinancialDirection($type);
        if ($type === Counterparty::CUSTOMER) {
            $sql = $wpdb->prepare(
                "UPDATE {$table} SET customer_id = %d, counterparty_type = %s, counterparty_id = %d, financial_direction = %s, updated_by = %d, updated_at = UTC_TIMESTAMP() WHERE id = %d",
                $counterpartyId, $type, $counterpartyId, $direction, $actorId, $contractId
            );
        } else {
            $sql = $wpdb->prepare(
                "UPDATE {$table} SET customer_id = NULL, counterparty_type = %s, counterparty_id = %d, financial_direction = %s, updated_by = %d, updated_at = UTC_TIMESTAMP() WHERE id = %d",
                $type, $counterpartyId, $direction, $actorId, $contractId
            );
        }
        $this->executeMutation($wpdb, $sql, 'Unable to assign contract counterparty.');
    }

    public function assignAccountant(int $contractId, ?int $accountantUserId, int $actorId): void
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_contracts';
        if ($accountantUserId === null) {
            $sql = $wpdb->prepare("UPDATE {$table} SET accountant_user_id = NULL, updated_by = %d, updated_at = UTC_TIMESTAMP() WHERE id = %d", $actorId, $contractId);
        } else {
            $sql = $wpdb->prepare("UPDATE {$table} SET accountant_user_id = %d, updated_by = %d, updated_at = UTC_TIMESTAMP() WHERE id = %d", $accountantUserId, $actorId, $contractId);
        }
        $this->executeMutation($wpdb, $sql, 'Unable to assign contract accountant.');
    }

    public function updateStatus(int $contractId, string $status, int $actorId): void
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_contracts';
        $this->executeMutation($wpdb, $wpdb->prepare("UPDATE {$table} SET status = %s, updated_by = %d, updated_at = UTC_TIMESTAMP() WHERE id = %d", $status, $actorId, $contractId), 'Unable to update contract status.');
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
