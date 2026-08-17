<?php

declare(strict_types=1);

namespace SafeContracts\Milestones;

use RuntimeException;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

final class ContractMilestoneRepository
{
    private const SELECT_COLUMNS = 'id, uuid, contract_id, milestone_code, title, description, target_date, status, achieved_at, achieved_by, cancelled_at, cancelled_by, created_by, updated_by, created_at, updated_at';

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
    public function find(int $milestoneId): ?array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $milestones = $wpdb->prefix . 'safecontracts_contract_milestones';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT " . self::SELECT_COLUMNS . " FROM {$milestones} WHERE id = %d AND tenant_id = %d LIMIT 1",
            $milestoneId,
            $tenantId
        ), ARRAY_A);
        return is_array($rows) && $rows !== [] && is_array($rows[0]) ? $rows[0] : null;
    }

    /** @return list<array<string,mixed>> */
    public function listForContract(int $contractId, int $limit = 50, int $offset = 0): array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $milestones = $wpdb->prefix . 'safecontracts_contract_milestones';
        $limit = max(1, min(100, $limit));
        $offset = max(0, min(100000, $offset));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT " . self::SELECT_COLUMNS . " FROM {$milestones}
             WHERE tenant_id = %d AND contract_id = %d
             ORDER BY CASE WHEN target_date IS NULL THEN 1 ELSE 0 END ASC, target_date ASC, id ASC
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
        ?string $targetDate,
        int $actorId
    ): int {
        global $wpdb;
        $tenantId = $this->tenantId();
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $milestones = $wpdb->prefix . 'safecontracts_contract_milestones';

        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Contract Milestone creation transaction.');
        }
        try {
            $contractRows = $wpdb->get_results($wpdb->prepare(
                "SELECT id FROM {$contracts} WHERE id = %d AND tenant_id = %d AND is_archived = 0 LIMIT 1 FOR UPDATE",
                $contractId,
                $tenantId
            ), ARRAY_A);
            if (! is_array($contractRows) || count($contractRows) !== 1 || ! is_array($contractRows[0])) {
                throw new RuntimeException('Enterprise contract changed concurrently or is no longer milestone-editable.');
            }

            $existingRows = $wpdb->get_results($wpdb->prepare(
                "SELECT id FROM {$milestones} WHERE tenant_id = %d AND contract_id = %d AND milestone_code = %s LIMIT 2 FOR UPDATE",
                $tenantId,
                $contractId,
                $code
            ), ARRAY_A);
            if (is_array($existingRows) && $existingRows !== []) {
                throw new RuntimeException('Milestone code already exists for this contract.');
            }

            $descriptionSql = $this->nullableSql($wpdb, $description);
            $targetDateSql = $this->nullableSql($wpdb, $targetDate);
            $sql = $wpdb->prepare(
                "INSERT INTO {$milestones} (
                    tenant_id, uuid, contract_id, milestone_code, title, description, target_date, status,
                    achieved_at, achieved_by, cancelled_at, cancelled_by,
                    created_by, updated_by, created_at, updated_at
                 ) VALUES (
                    %d, %s, %d, %s, %s, {$descriptionSql}, {$targetDateSql}, %s,
                    NULL, NULL, NULL, NULL,
                    %d, %d, UTC_TIMESTAMP(), UTC_TIMESTAMP()
                 )",
                $tenantId,
                $uuid,
                $contractId,
                $code,
                $title,
                ContractMilestonePolicy::STATUS_PLANNED,
                $actorId,
                $actorId
            );
            if ($wpdb->query($sql) !== 1) {
                throw new RuntimeException('Unable to create Enterprise Contract Milestone.');
            }
            $milestoneId = (int) ($wpdb->insert_id ?? 0);
            if ($milestoneId <= 0) {
                throw new RuntimeException('Contract Milestone insert returned no identifier.');
            }
            if ($wpdb->query('COMMIT') === false) {
                throw new RuntimeException('Unable to commit Contract Milestone creation transaction.');
            }
            return $milestoneId;
        } catch (\Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    public function updateMetadata(
        int $milestoneId,
        string $title,
        ?string $description,
        ?string $targetDate,
        int $actorId
    ): void {
        global $wpdb;
        $tenantId = $this->tenantId();
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $milestones = $wpdb->prefix . 'safecontracts_contract_milestones';

        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Contract Milestone update transaction.');
        }
        try {
            $locked = $this->lockMilestoneWithContract($wpdb, $milestones, $contracts, $tenantId, $milestoneId);
            if ((int) ($locked['is_archived'] ?? 0) === 1) {
                throw new RuntimeException('Archived contracts cannot mutate Contract Milestones.');
            }
            if ((string) ($locked['status'] ?? '') !== ContractMilestonePolicy::STATUS_PLANNED) {
                throw new RuntimeException('Terminal Contract Milestones are immutable.');
            }

            $descriptionSql = $this->nullableSql($wpdb, $description);
            $targetDateSql = $this->nullableSql($wpdb, $targetDate);
            $updated = $wpdb->query($wpdb->prepare(
                "UPDATE {$milestones}
                 SET title = %s, description = {$descriptionSql}, target_date = {$targetDateSql}, updated_by = %d, updated_at = UTC_TIMESTAMP()
                 WHERE id = %d AND tenant_id = %d AND status = %s",
                $title,
                $actorId,
                $milestoneId,
                $tenantId,
                ContractMilestonePolicy::STATUS_PLANNED
            ));
            if ($updated === false || ($updated !== 0 && $updated !== 1)) {
                throw new RuntimeException('Contract Milestone changed concurrently and was not updated.');
            }
            if ($wpdb->query('COMMIT') === false) {
                throw new RuntimeException('Unable to commit Contract Milestone update transaction.');
            }
        } catch (\Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    /** @return array<string,mixed> */
    public function transitionTerminal(int $milestoneId, string $targetStatus, int $actorId): array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $milestones = $wpdb->prefix . 'safecontracts_contract_milestones';
        $targetStatus = ContractMilestonePolicy::normalizeTerminalStatus($targetStatus);

        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Contract Milestone lifecycle transaction.');
        }
        try {
            $locked = $this->lockMilestoneWithContract($wpdb, $milestones, $contracts, $tenantId, $milestoneId);
            $currentStatus = (string) ($locked['status'] ?? '');
            if ($currentStatus === $targetStatus) {
                if ($wpdb->query('COMMIT') === false) {
                    throw new RuntimeException('Unable to commit idempotent Contract Milestone lifecycle retry.');
                }
                $existing = $this->find($milestoneId);
                if ($existing === null) {
                    throw new RuntimeException('Contract Milestone disappeared after idempotent lifecycle retry.');
                }
                return $existing;
            }
            if ($currentStatus !== ContractMilestonePolicy::STATUS_PLANNED) {
                throw new RuntimeException('Contract Milestone is already terminal with a different status.');
            }
            if ((int) ($locked['is_archived'] ?? 0) === 1) {
                throw new RuntimeException('Archived contracts cannot mutate Contract Milestones.');
            }

            if ($targetStatus === ContractMilestonePolicy::STATUS_ACHIEVED) {
                $updated = $wpdb->query($wpdb->prepare(
                    "UPDATE {$milestones}
                     SET status = %s, achieved_at = UTC_TIMESTAMP(), achieved_by = %d, updated_by = %d, updated_at = UTC_TIMESTAMP()
                     WHERE id = %d AND tenant_id = %d AND status = %s",
                    $targetStatus,
                    $actorId,
                    $actorId,
                    $milestoneId,
                    $tenantId,
                    ContractMilestonePolicy::STATUS_PLANNED
                ));
            } else {
                $updated = $wpdb->query($wpdb->prepare(
                    "UPDATE {$milestones}
                     SET status = %s, cancelled_at = UTC_TIMESTAMP(), cancelled_by = %d, updated_by = %d, updated_at = UTC_TIMESTAMP()
                     WHERE id = %d AND tenant_id = %d AND status = %s",
                    $targetStatus,
                    $actorId,
                    $actorId,
                    $milestoneId,
                    $tenantId,
                    ContractMilestonePolicy::STATUS_PLANNED
                ));
            }
            if ($updated !== 1) {
                throw new RuntimeException('Contract Milestone lifecycle changed concurrently.');
            }
            if ($wpdb->query('COMMIT') === false) {
                throw new RuntimeException('Unable to commit Contract Milestone lifecycle transaction.');
            }
            $result = $this->find($milestoneId);
            if ($result === null) {
                throw new RuntimeException('Contract Milestone disappeared after lifecycle transition.');
            }
            return $result;
        } catch (\Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    /** @return array<string,mixed> */
    private function lockMilestoneWithContract(
        object $wpdb,
        string $milestones,
        string $contracts,
        int $tenantId,
        int $milestoneId
    ): array {
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT m.id, m.contract_id, m.status, c.is_archived
             FROM {$milestones} m
             INNER JOIN {$contracts} c ON c.id = m.contract_id AND c.tenant_id = m.tenant_id
             WHERE m.id = %d AND m.tenant_id = %d
             LIMIT 2 FOR UPDATE",
            $milestoneId,
            $tenantId
        ), ARRAY_A);
        if (! is_array($rows) || count($rows) !== 1 || ! is_array($rows[0])) {
            throw new RuntimeException('Contract Milestone was not found in the current Enterprise tenant.');
        }
        return $rows[0];
    }

    private function tenantId(): int
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise Contract Milestone access requires core tenant enforcement.');
        }
        return TenantContextStore::context()->requireTenantId();
    }

    private function nullableSql(object $wpdb, ?string $value): string
    {
        return $value === null ? 'NULL' : $wpdb->prepare('%s', $value);
    }
}
