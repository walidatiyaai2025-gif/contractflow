<?php

declare(strict_types=1);

namespace SafeContracts\Obligations;

use RuntimeException;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

final class ContractObligationRepository
{
    private const SELECT_COLUMNS = 'id, uuid, contract_id, obligation_code, title, description, due_date, status, completed_at, completed_by, cancelled_at, cancelled_by, created_by, updated_by, created_at, updated_at';

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
    public function find(int $obligationId): ?array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $obligations = $wpdb->prefix . 'safecontracts_contract_obligations';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT " . self::SELECT_COLUMNS . " FROM {$obligations} WHERE id = %d AND tenant_id = %d LIMIT 1",
            $obligationId,
            $tenantId
        ), ARRAY_A);
        return is_array($rows) && $rows !== [] && is_array($rows[0]) ? $rows[0] : null;
    }

    /** @return list<array<string,mixed>> */
    public function listForContract(int $contractId, int $limit = 50, int $offset = 0): array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $obligations = $wpdb->prefix . 'safecontracts_contract_obligations';
        $limit = max(1, min(100, $limit));
        $offset = max(0, min(100000, $offset));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT " . self::SELECT_COLUMNS . " FROM {$obligations}
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
        $obligations = $wpdb->prefix . 'safecontracts_contract_obligations';

        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Contract Obligation creation transaction.');
        }
        try {
            $contractRows = $wpdb->get_results($wpdb->prepare(
                "SELECT id FROM {$contracts} WHERE id = %d AND tenant_id = %d AND is_archived = 0 LIMIT 1 FOR UPDATE",
                $contractId,
                $tenantId
            ), ARRAY_A);
            if (! is_array($contractRows) || count($contractRows) !== 1 || ! is_array($contractRows[0])) {
                throw new RuntimeException('Enterprise contract changed concurrently or is no longer obligation-editable.');
            }

            $existingRows = $wpdb->get_results($wpdb->prepare(
                "SELECT id FROM {$obligations} WHERE tenant_id = %d AND contract_id = %d AND obligation_code = %s LIMIT 2 FOR UPDATE",
                $tenantId,
                $contractId,
                $code
            ), ARRAY_A);
            if (is_array($existingRows) && $existingRows !== []) {
                throw new RuntimeException('Obligation code already exists for this contract.');
            }

            $descriptionSql = $this->nullableSql($wpdb, $description);
            $dueDateSql = $this->nullableSql($wpdb, $dueDate);
            $sql = $wpdb->prepare(
                "INSERT INTO {$obligations} (
                    tenant_id, uuid, contract_id, obligation_code, title, description, due_date, status,
                    completed_at, completed_by, cancelled_at, cancelled_by,
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
                ContractObligationPolicy::STATUS_OPEN,
                $actorId,
                $actorId
            );
            if ($wpdb->query($sql) !== 1) {
                throw new RuntimeException('Unable to create Enterprise Contract Obligation.');
            }
            $obligationId = (int) ($wpdb->insert_id ?? 0);
            if ($obligationId <= 0) {
                throw new RuntimeException('Contract Obligation insert returned no identifier.');
            }
            if ($wpdb->query('COMMIT') === false) {
                throw new RuntimeException('Unable to commit Contract Obligation creation transaction.');
            }
            return $obligationId;
        } catch (\Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    public function updateMetadata(
        int $obligationId,
        string $title,
        ?string $description,
        ?string $dueDate,
        int $actorId
    ): void {
        global $wpdb;
        $tenantId = $this->tenantId();
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $obligations = $wpdb->prefix . 'safecontracts_contract_obligations';

        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Contract Obligation update transaction.');
        }
        try {
            $locked = $this->lockObligationWithContract($wpdb, $obligations, $contracts, $tenantId, $obligationId);
            if ((int) ($locked['is_archived'] ?? 0) === 1) {
                throw new RuntimeException('Archived contracts cannot mutate Contract Obligations.');
            }
            if ((string) ($locked['status'] ?? '') !== ContractObligationPolicy::STATUS_OPEN) {
                throw new RuntimeException('Terminal Contract Obligations are immutable.');
            }

            $descriptionSql = $this->nullableSql($wpdb, $description);
            $dueDateSql = $this->nullableSql($wpdb, $dueDate);
            $updated = $wpdb->query($wpdb->prepare(
                "UPDATE {$obligations}
                 SET title = %s, description = {$descriptionSql}, due_date = {$dueDateSql}, updated_by = %d, updated_at = UTC_TIMESTAMP()
                 WHERE id = %d AND tenant_id = %d AND status = %s",
                $title,
                $actorId,
                $obligationId,
                $tenantId,
                ContractObligationPolicy::STATUS_OPEN
            ));
            if ($updated !== 1) {
                throw new RuntimeException('Contract Obligation changed concurrently and was not updated.');
            }
            if ($wpdb->query('COMMIT') === false) {
                throw new RuntimeException('Unable to commit Contract Obligation update transaction.');
            }
        } catch (\Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    /** @return array<string,mixed> */
    public function transitionTerminal(int $obligationId, string $targetStatus, int $actorId): array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $obligations = $wpdb->prefix . 'safecontracts_contract_obligations';
        $targetStatus = ContractObligationPolicy::normalizeTerminalStatus($targetStatus);

        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Contract Obligation lifecycle transaction.');
        }
        try {
            $locked = $this->lockObligationWithContract($wpdb, $obligations, $contracts, $tenantId, $obligationId);
            $currentStatus = (string) ($locked['status'] ?? '');
            if ($currentStatus === $targetStatus) {
                if ($wpdb->query('COMMIT') === false) {
                    throw new RuntimeException('Unable to commit idempotent Contract Obligation lifecycle retry.');
                }
                $existing = $this->find($obligationId);
                if ($existing === null) {
                    throw new RuntimeException('Contract Obligation disappeared after idempotent lifecycle retry.');
                }
                return $existing;
            }
            if ($currentStatus !== ContractObligationPolicy::STATUS_OPEN) {
                throw new RuntimeException('Contract Obligation is already terminal with a different status.');
            }
            if ((int) ($locked['is_archived'] ?? 0) === 1) {
                throw new RuntimeException('Archived contracts cannot mutate Contract Obligations.');
            }

            if ($targetStatus === ContractObligationPolicy::STATUS_COMPLETED) {
                $updated = $wpdb->query($wpdb->prepare(
                    "UPDATE {$obligations}
                     SET status = %s, completed_at = UTC_TIMESTAMP(), completed_by = %d, updated_by = %d, updated_at = UTC_TIMESTAMP()
                     WHERE id = %d AND tenant_id = %d AND status = %s",
                    $targetStatus,
                    $actorId,
                    $actorId,
                    $obligationId,
                    $tenantId,
                    ContractObligationPolicy::STATUS_OPEN
                ));
            } else {
                $updated = $wpdb->query($wpdb->prepare(
                    "UPDATE {$obligations}
                     SET status = %s, cancelled_at = UTC_TIMESTAMP(), cancelled_by = %d, updated_by = %d, updated_at = UTC_TIMESTAMP()
                     WHERE id = %d AND tenant_id = %d AND status = %s",
                    $targetStatus,
                    $actorId,
                    $actorId,
                    $obligationId,
                    $tenantId,
                    ContractObligationPolicy::STATUS_OPEN
                ));
            }
            if ($updated !== 1) {
                throw new RuntimeException('Contract Obligation lifecycle changed concurrently.');
            }
            if ($wpdb->query('COMMIT') === false) {
                throw new RuntimeException('Unable to commit Contract Obligation lifecycle transaction.');
            }
            $result = $this->find($obligationId);
            if ($result === null) {
                throw new RuntimeException('Contract Obligation disappeared after lifecycle transition.');
            }
            return $result;
        } catch (\Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    /** @return array<string,mixed> */
    private function lockObligationWithContract(
        object $wpdb,
        string $obligations,
        string $contracts,
        int $tenantId,
        int $obligationId
    ): array {
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT o.id, o.contract_id, o.status, c.is_archived
             FROM {$obligations} o
             INNER JOIN {$contracts} c ON c.id = o.contract_id AND c.tenant_id = o.tenant_id
             WHERE o.id = %d AND o.tenant_id = %d
             LIMIT 2 FOR UPDATE",
            $obligationId,
            $tenantId
        ), ARRAY_A);
        if (! is_array($rows) || count($rows) !== 1 || ! is_array($rows[0])) {
            throw new RuntimeException('Contract Obligation was not found in the current Enterprise tenant.');
        }
        return $rows[0];
    }

    private function tenantId(): int
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise Contract Obligation access requires core tenant enforcement.');
        }
        return TenantContextStore::context()->requireTenantId();
    }

    private function nullableSql(object $wpdb, ?string $value): string
    {
        return $value === null ? 'NULL' : $wpdb->prepare('%s', $value);
    }
}
