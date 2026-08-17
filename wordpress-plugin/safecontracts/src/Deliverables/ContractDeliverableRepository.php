<?php

declare(strict_types=1);

namespace SafeContracts\Deliverables;

use RuntimeException;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

final class ContractDeliverableRepository
{
    private const SELECT_COLUMNS = 'id, uuid, contract_id, deliverable_code, title, description, due_date, status, delivered_at, delivered_by, cancelled_at, cancelled_by, created_by, updated_by, created_at, updated_at';

    /** @return array<string,mixed>|null */
    public function findContract(int $contractId): ?array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, accountant_user_id, status, is_archived FROM {$contracts} WHERE id = %d AND tenant_id = %d LIMIT 1",
            $contractId,
            $tenantId
        ), ARRAY_A);
        return is_array($rows) && $rows !== [] && is_array($rows[0]) ? $rows[0] : null;
    }

    /** @return array<string,mixed>|null */
    public function find(int $deliverableId): ?array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $deliverables = $wpdb->prefix . 'safecontracts_contract_deliverables';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT " . self::SELECT_COLUMNS . " FROM {$deliverables} WHERE id = %d AND tenant_id = %d LIMIT 1",
            $deliverableId,
            $tenantId
        ), ARRAY_A);
        return is_array($rows) && $rows !== [] && is_array($rows[0]) ? $rows[0] : null;
    }

    /** @return list<array<string,mixed>> */
    public function listForContract(int $contractId, int $limit = 50, int $offset = 0): array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $deliverables = $wpdb->prefix . 'safecontracts_contract_deliverables';
        $limit = max(1, min(100, $limit));
        $offset = max(0, min(100000, $offset));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT " . self::SELECT_COLUMNS . " FROM {$deliverables}
             WHERE tenant_id = %d AND contract_id = %d
             ORDER BY CASE WHEN due_date IS NULL THEN 1 ELSE 0 END ASC, due_date ASC, id ASC
             LIMIT %d OFFSET %d",
            $tenantId,
            $contractId,
            $limit,
            $offset
        ), ARRAY_A);
        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    public function create(
        int $contractId,
        string $uuid,
        string $code,
        string $title,
        ?string $description,
        ?string $dueDate,
        int $actorId
    ): int {
        global $wpdb;
        $tenantId = $this->tenantId();
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $deliverables = $wpdb->prefix . 'safecontracts_contract_deliverables';

        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Contract Deliverable creation transaction.');
        }
        try {
            $contractRows = $wpdb->get_results($wpdb->prepare(
                "SELECT id FROM {$contracts} WHERE id = %d AND tenant_id = %d AND is_archived = 0 LIMIT 1 FOR UPDATE",
                $contractId,
                $tenantId
            ), ARRAY_A);
            if (! is_array($contractRows) || count($contractRows) !== 1 || ! is_array($contractRows[0])) {
                throw new RuntimeException('Enterprise contract changed concurrently or is no longer deliverable-editable.');
            }

            $existingRows = $wpdb->get_results($wpdb->prepare(
                "SELECT id FROM {$deliverables} WHERE tenant_id = %d AND contract_id = %d AND deliverable_code = %s LIMIT 2 FOR UPDATE",
                $tenantId,
                $contractId,
                $code
            ), ARRAY_A);
            if (is_array($existingRows) && $existingRows !== []) {
                throw new RuntimeException('Deliverable code already exists for this contract.');
            }

            $descriptionSql = $this->nullableSql($wpdb, $description);
            $dueDateSql = $this->nullableSql($wpdb, $dueDate);
            $sql = $wpdb->prepare(
                "INSERT INTO {$deliverables} (
                    tenant_id, uuid, contract_id, deliverable_code, title, description, due_date, status,
                    delivered_at, delivered_by, cancelled_at, cancelled_by,
                    created_by, updated_by, created_at, updated_at
                 ) VALUES (
                    %d, %s, %d, %s, %s, {$descriptionSql}, {$dueDateSql}, %s,
                    NULL, NULL, NULL, NULL,
                    %d, %d, UTC_TIMESTAMP(), UTC_TIMESTAMP()
                 )",
                $tenantId,
                $uuid,
                $contractId,
                $code,
                $title,
                ContractDeliverablePolicy::STATUS_PENDING,
                $actorId,
                $actorId
            );
            if ($wpdb->query($sql) !== 1) {
                throw new RuntimeException('Unable to create Enterprise Contract Deliverable.');
            }
            $deliverableId = (int) ($wpdb->insert_id ?? 0);
            if ($deliverableId <= 0) {
                throw new RuntimeException('Contract Deliverable insert returned no identifier.');
            }
            if ($wpdb->query('COMMIT') === false) {
                throw new RuntimeException('Unable to commit Contract Deliverable creation transaction.');
            }
            return $deliverableId;
        } catch (\Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    public function updateMetadata(
        int $deliverableId,
        string $title,
        ?string $description,
        ?string $dueDate,
        int $actorId
    ): void {
        global $wpdb;
        $tenantId = $this->tenantId();
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $deliverables = $wpdb->prefix . 'safecontracts_contract_deliverables';

        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Contract Deliverable update transaction.');
        }
        try {
            $locked = $this->lockDeliverableWithContract($wpdb, $deliverables, $contracts, $tenantId, $deliverableId);
            if ((int) ($locked['is_archived'] ?? 0) === 1) {
                throw new RuntimeException('Archived contracts cannot mutate Contract Deliverables.');
            }
            if ((string) ($locked['status'] ?? '') !== ContractDeliverablePolicy::STATUS_PENDING) {
                throw new RuntimeException('Terminal Contract Deliverables are immutable.');
            }

            $descriptionSql = $this->nullableSql($wpdb, $description);
            $dueDateSql = $this->nullableSql($wpdb, $dueDate);
            $updated = $wpdb->query($wpdb->prepare(
                "UPDATE {$deliverables}
                 SET title = %s, description = {$descriptionSql}, due_date = {$dueDateSql}, updated_by = %d, updated_at = UTC_TIMESTAMP()
                 WHERE id = %d AND tenant_id = %d AND status = %s",
                $title,
                $actorId,
                $deliverableId,
                $tenantId,
                ContractDeliverablePolicy::STATUS_PENDING
            ));
            if ($updated === false || ($updated !== 0 && $updated !== 1)) {
                throw new RuntimeException('Contract Deliverable changed concurrently and was not updated.');
            }
            if ($wpdb->query('COMMIT') === false) {
                throw new RuntimeException('Unable to commit Contract Deliverable update transaction.');
            }
        } catch (\Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    /** @return array<string,mixed> */
    public function transitionTerminal(int $deliverableId, string $targetStatus, int $actorId): array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $deliverables = $wpdb->prefix . 'safecontracts_contract_deliverables';
        $targetStatus = ContractDeliverablePolicy::normalizeTerminalStatus($targetStatus);

        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Contract Deliverable lifecycle transaction.');
        }
        try {
            $locked = $this->lockDeliverableWithContract($wpdb, $deliverables, $contracts, $tenantId, $deliverableId);
            $currentStatus = (string) ($locked['status'] ?? '');
            if ($currentStatus === $targetStatus) {
                if ($wpdb->query('COMMIT') === false) {
                    throw new RuntimeException('Unable to commit idempotent Contract Deliverable lifecycle retry.');
                }
                $existing = $this->find($deliverableId);
                if ($existing === null) {
                    throw new RuntimeException('Contract Deliverable disappeared after idempotent lifecycle retry.');
                }
                return $existing;
            }
            if ($currentStatus !== ContractDeliverablePolicy::STATUS_PENDING) {
                throw new RuntimeException('Contract Deliverable is already terminal with a different status.');
            }
            if ((int) ($locked['is_archived'] ?? 0) === 1) {
                throw new RuntimeException('Archived contracts cannot mutate Contract Deliverables.');
            }

            if ($targetStatus === ContractDeliverablePolicy::STATUS_DELIVERED) {
                $updated = $wpdb->query($wpdb->prepare(
                    "UPDATE {$deliverables}
                     SET status = %s, delivered_at = UTC_TIMESTAMP(), delivered_by = %d, updated_by = %d, updated_at = UTC_TIMESTAMP()
                     WHERE id = %d AND tenant_id = %d AND status = %s",
                    $targetStatus,
                    $actorId,
                    $actorId,
                    $deliverableId,
                    $tenantId,
                    ContractDeliverablePolicy::STATUS_PENDING
                ));
            } else {
                $updated = $wpdb->query($wpdb->prepare(
                    "UPDATE {$deliverables}
                     SET status = %s, cancelled_at = UTC_TIMESTAMP(), cancelled_by = %d, updated_by = %d, updated_at = UTC_TIMESTAMP()
                     WHERE id = %d AND tenant_id = %d AND status = %s",
                    $targetStatus,
                    $actorId,
                    $actorId,
                    $deliverableId,
                    $tenantId,
                    ContractDeliverablePolicy::STATUS_PENDING
                ));
            }
            if ($updated !== 1) {
                throw new RuntimeException('Contract Deliverable lifecycle changed concurrently.');
            }
            if ($wpdb->query('COMMIT') === false) {
                throw new RuntimeException('Unable to commit Contract Deliverable lifecycle transaction.');
            }
            $result = $this->find($deliverableId);
            if ($result === null) {
                throw new RuntimeException('Contract Deliverable disappeared after lifecycle transition.');
            }
            return $result;
        } catch (\Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    /** @return array<string,mixed> */
    private function lockDeliverableWithContract(
        object $wpdb,
        string $deliverables,
        string $contracts,
        int $tenantId,
        int $deliverableId
    ): array {
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT d.id, d.contract_id, d.status, c.is_archived
             FROM {$deliverables} d
             INNER JOIN {$contracts} c ON c.id = d.contract_id AND c.tenant_id = d.tenant_id
             WHERE d.id = %d AND d.tenant_id = %d
             LIMIT 2 FOR UPDATE",
            $deliverableId,
            $tenantId
        ), ARRAY_A);
        if (! is_array($rows) || count($rows) !== 1 || ! is_array($rows[0])) {
            throw new RuntimeException('Contract Deliverable was not found in the current Enterprise tenant.');
        }
        return $rows[0];
    }

    private function tenantId(): int
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise Contract Deliverable access requires core tenant enforcement.');
        }
        return TenantContextStore::context()->requireTenantId();
    }

    private function nullableSql(object $wpdb, ?string $value): string
    {
        return $value === null ? 'NULL' : $wpdb->prepare('%s', $value);
    }
}
