<?php

declare(strict_types=1);

namespace SafeContracts\Contracts;

use RuntimeException;

final class ContractRepository
{
    public function customerIsActive(int $customerId): bool
    {
        global $wpdb;
        $this->assertWpdb($wpdb);

        $table = $wpdb->prefix . 'safecontracts_customers';
        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT id FROM {$table} WHERE id = %d AND is_active = 1 LIMIT 1", $customerId),
            ARRAY_A
        );

        return is_array($rows) && $rows !== [];
    }

    /** @return array{id:int, contract_number:string, customer_id:int, accountant_user_id:?int, status:string, start_date:?string, end_date:?string, base_value:string, notes:string, is_archived:bool}|null */
    public function find(int $contractId): ?array
    {
        global $wpdb;
        $this->assertWpdb($wpdb);

        $table = $wpdb->prefix . 'safecontracts_contracts';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, contract_number, customer_id, accountant_user_id, status, start_date, end_date, base_value, notes, is_archived
                 FROM {$table} WHERE id = %d LIMIT 1",
                $contractId
            ),
            ARRAY_A
        );

        if (! is_array($rows) || $rows === []) {
            return null;
        }

        $row = $rows[0];
        return [
            'id' => (int) ($row['id'] ?? 0),
            'contract_number' => (string) ($row['contract_number'] ?? ''),
            'customer_id' => (int) ($row['customer_id'] ?? 0),
            'accountant_user_id' => isset($row['accountant_user_id']) && $row['accountant_user_id'] !== null
                ? (int) $row['accountant_user_id']
                : null,
            'status' => (string) ($row['status'] ?? ContractStatus::DRAFT),
            'start_date' => isset($row['start_date']) && $row['start_date'] !== '' ? (string) $row['start_date'] : null,
            'end_date' => isset($row['end_date']) && $row['end_date'] !== '' ? (string) $row['end_date'] : null,
            'base_value' => DecimalAmount::normalize($row['base_value'] ?? '0'),
            'notes' => (string) ($row['notes'] ?? ''),
            'is_archived' => (bool) ($row['is_archived'] ?? false),
        ];
    }

    public function create(string $contractNumber, int $customerId, ?int $accountantUserId, string $notes, int $actorId): int
    {
        global $wpdb;
        $this->assertWpdb($wpdb);

        $table = $wpdb->prefix . 'safecontracts_contracts';
        if ($accountantUserId === null) {
            $sql = $wpdb->prepare(
                "INSERT INTO {$table}
                    (contract_number, customer_id, accountant_user_id, status, notes, created_by, updated_by, created_at, updated_at)
                 VALUES (%s, %d, NULL, %s, %s, %d, %d, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
                $contractNumber,
                $customerId,
                ContractStatus::DRAFT,
                $notes,
                $actorId,
                $actorId
            );
        } else {
            $sql = $wpdb->prepare(
                "INSERT INTO {$table}
                    (contract_number, customer_id, accountant_user_id, status, notes, created_by, updated_by, created_at, updated_at)
                 VALUES (%s, %d, %d, %s, %s, %d, %d, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
                $contractNumber,
                $customerId,
                $accountantUserId,
                ContractStatus::DRAFT,
                $notes,
                $actorId,
                $actorId
            );
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
        $sql = $wpdb->prepare(
            "UPDATE {$table}
             SET contract_number = %s, notes = %s, updated_by = %d, updated_at = UTC_TIMESTAMP()
             WHERE id = %d",
            $contractNumber,
            $notes,
            $actorId,
            $contractId
        );
        $this->executeMutation($wpdb, $sql, 'Unable to edit contract.');
    }

    public function updateDates(int $contractId, ?string $startDate, ?string $endDate, int $actorId): void
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_contracts';
        $startSql = $startDate === null ? 'NULL' : '%s';
        $endSql = $endDate === null ? 'NULL' : '%s';
        $args = [];
        if ($startDate !== null) {
            $args[] = $startDate;
        }
        if ($endDate !== null) {
            $args[] = $endDate;
        }
        $args[] = $actorId;
        $args[] = $contractId;
        $sql = $wpdb->prepare(
            "UPDATE {$table}
             SET start_date = {$startSql}, end_date = {$endSql}, updated_by = %d, updated_at = UTC_TIMESTAMP()
             WHERE id = %d",
            ...$args
        );
        $this->executeMutation($wpdb, $sql, 'Unable to update contract dates.');
    }

    public function updateBaseValue(int $contractId, string $baseValue, int $actorId): void
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_contracts';
        $sql = $wpdb->prepare(
            "UPDATE {$table}
             SET base_value = %s, updated_by = %d, updated_at = UTC_TIMESTAMP()
             WHERE id = %d",
            $baseValue,
            $actorId,
            $contractId
        );
        $this->executeMutation($wpdb, $sql, 'Unable to update contract base value.');
    }

    public function assignCustomer(int $contractId, int $customerId, int $actorId): void
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_contracts';
        $sql = $wpdb->prepare(
            "UPDATE {$table}
             SET customer_id = %d, updated_by = %d, updated_at = UTC_TIMESTAMP()
             WHERE id = %d",
            $customerId,
            $actorId,
            $contractId
        );
        $this->executeMutation($wpdb, $sql, 'Unable to assign contract customer.');
    }

    public function assignAccountant(int $contractId, ?int $accountantUserId, int $actorId): void
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_contracts';

        if ($accountantUserId === null) {
            $sql = $wpdb->prepare(
                "UPDATE {$table}
                 SET accountant_user_id = NULL, updated_by = %d, updated_at = UTC_TIMESTAMP()
                 WHERE id = %d",
                $actorId,
                $contractId
            );
        } else {
            $sql = $wpdb->prepare(
                "UPDATE {$table}
                 SET accountant_user_id = %d, updated_by = %d, updated_at = UTC_TIMESTAMP()
                 WHERE id = %d",
                $accountantUserId,
                $actorId,
                $contractId
            );
        }

        $this->executeMutation($wpdb, $sql, 'Unable to assign contract accountant.');
    }

    public function updateStatus(int $contractId, string $status, int $actorId): void
    {
        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_contracts';
        $sql = $wpdb->prepare(
            "UPDATE {$table}
             SET status = %s, updated_by = %d, updated_at = UTC_TIMESTAMP()
             WHERE id = %d",
            $status,
            $actorId,
            $contractId
        );
        $this->executeMutation($wpdb, $sql, 'Unable to update contract status.');
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
