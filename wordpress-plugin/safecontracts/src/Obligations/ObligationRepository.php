<?php

declare(strict_types=1);

namespace SafeContracts\Obligations;

use RuntimeException;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

final class ObligationRepository
{
    private const COLUMNS = 'id, uuid, contract_id, obligation_code, title, description, due_date, status, completed_at, completed_by, cancelled_at, cancelled_by, created_by, updated_by, created_at, updated_at';

    /** @return array<string,mixed>|null */
    public function findContract(int $contractId): ?array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, accountant_user_id, status, is_archived FROM {$contracts} WHERE tenant_id = %d AND id = %d LIMIT 1",
            $tenantId,
            $contractId
        ), ARRAY_A);
        return is_array($rows) && count($rows) === 1 && is_array($rows[0]) ? $rows[0] : null;
    }

    /** @return array<string,mixed>|null */
    public function find(int $contractId, int $obligationId): ?array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_contract_obligations';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT " . self::COLUMNS . " FROM {$table} WHERE tenant_id = %d AND contract_id = %d AND id = %d LIMIT 1",
            $tenantId,
            $contractId,
            $obligationId
        ), ARRAY_A);
        return is_array($rows) && count($rows) === 1 && is_array($rows[0]) ? $rows[0] : null;
    }

    /** @return list<array<string,mixed>> */
    public function search(int $contractId, array $filters, int $limit = 50, int $offset = 0): array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_contract_obligations';
        $limit = max(1, min(ObligationPolicy::MAX_SEARCH_LIMIT, $limit));
        $offset = max(0, min(100000, $offset));
        $where = ['tenant_id = %d', 'contract_id = %d'];
        $args = [$tenantId, $contractId];
        if (($filters['status'] ?? null) !== null) {
            $where[] = 'status = %s';
            $args[] = $filters['status'];
        }
        if (($filters['due_from'] ?? null) !== null) {
            $where[] = 'due_date >= %s';
            $args[] = $filters['due_from'];
        }
        if (($filters['due_to'] ?? null) !== null) {
            $where[] = 'due_date <= %s';
            $args[] = $filters['due_to'];
        }
        if (($filters['obligation_code'] ?? null) !== null) {
            $where[] = 'obligation_code = %s';
            $args[] = $filters['obligation_code'];
        }
        $args[] = $limit;
        $args[] = $offset;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT " . self::COLUMNS . " FROM {$table} WHERE " . implode(' AND ', $where) . " ORDER BY due_date IS NULL ASC, due_date ASC, id ASC LIMIT %d OFFSET %d",
            ...$args
        ), ARRAY_A);
        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    /** @return array<string,mixed> */
    public function create(int $contractId, string $uuid, array $data, int $actorId): array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_contract_obligations';
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $sql = $wpdb->prepare(
            "INSERT INTO {$table} (tenant_id, uuid, contract_id, obligation_code, title, description, due_date, status, created_by, updated_by, created_at, updated_at)
             SELECT %d, %s, c.id, %s, %s, NULLIF(%s, ''), NULLIF(%s, ''), %s, %d, %d, UTC_TIMESTAMP(), UTC_TIMESTAMP()
             FROM {$contracts} c WHERE c.tenant_id = %d AND c.id = %d AND c.is_archived = 0",
            $tenantId,
            $uuid,
            $data['obligation_code'],
            $data['title'],
            $data['description'] ?? '',
            $data['due_date'] ?? '',
            ObligationPolicy::STATUS_OPEN,
            $actorId,
            $actorId,
            $tenantId,
            $contractId
        );
        $result = $wpdb->query($sql);
        if ($result === false) {
            throw new RuntimeException('Unable to create Contract Obligation; code/UUID uniqueness or persistence failed.');
        }
        if ($result !== 1 || (int) $wpdb->insert_id <= 0) {
            throw new RuntimeException('Contract Obligation contract changed concurrently or is no longer mutable.');
        }
        return [
            'id' => (int) $wpdb->insert_id,
            'uuid' => $uuid,
            'contract_id' => $contractId,
            'obligation_code' => $data['obligation_code'],
            'title' => $data['title'],
            'description' => $data['description'],
            'due_date' => $data['due_date'],
            'status' => ObligationPolicy::STATUS_OPEN,
            'completed_at' => null,
            'completed_by' => null,
            'cancelled_at' => null,
            'cancelled_by' => null,
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ];
    }

    /** @return array<string,mixed> */
    public function updateMetadata(int $contractId, int $obligationId, array $data, int $actorId): array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_contract_obligations';
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $result = $wpdb->query($wpdb->prepare(
            "UPDATE {$table} o INNER JOIN {$contracts} c ON c.id = o.contract_id AND c.tenant_id = o.tenant_id
             SET o.title = %s, o.description = NULLIF(%s, ''), o.due_date = NULLIF(%s, ''), o.updated_by = %d, o.updated_at = UTC_TIMESTAMP()
             WHERE o.tenant_id = %d AND o.contract_id = %d AND o.id = %d AND o.status = %s AND c.is_archived = 0",
            $data['title'],
            $data['description'] ?? '',
            $data['due_date'] ?? '',
            $actorId,
            $tenantId,
            $contractId,
            $obligationId,
            ObligationPolicy::STATUS_OPEN
        ));
        if ($result === false) {
            throw new RuntimeException('Unable to update Contract Obligation metadata.');
        }
        if ($result !== 1) {
            throw new RuntimeException('Contract Obligation metadata update lost its open/current-tenant compare-and-set predicate.');
        }
        $row = $this->find($contractId, $obligationId);
        if ($row === null) {
            throw new RuntimeException('Updated Contract Obligation is no longer visible in the current tenant.');
        }
        return $row;
    }

    /** @return array{obligation:array<string,mixed>,idempotent:bool} */
    public function transition(int $contractId, int $obligationId, string $targetStatus, int $actorId): array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_contract_obligations';
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $targetStatus = ObligationPolicy::normalizeTerminalTarget($targetStatus);
        $completedAt = $targetStatus === ObligationPolicy::STATUS_COMPLETED ? 'UTC_TIMESTAMP()' : 'NULL';
        $completedBy = $targetStatus === ObligationPolicy::STATUS_COMPLETED ? (string) $actorId : 'NULL';
        $cancelledAt = $targetStatus === ObligationPolicy::STATUS_CANCELLED ? 'UTC_TIMESTAMP()' : 'NULL';
        $cancelledBy = $targetStatus === ObligationPolicy::STATUS_CANCELLED ? (string) $actorId : 'NULL';
        $sql = $wpdb->prepare(
            "UPDATE {$table} o INNER JOIN {$contracts} c ON c.id = o.contract_id AND c.tenant_id = o.tenant_id
             SET o.status = %s, o.completed_at = {$completedAt}, o.completed_by = {$completedBy},
                 o.cancelled_at = {$cancelledAt}, o.cancelled_by = {$cancelledBy},
                 o.updated_by = %d, o.updated_at = UTC_TIMESTAMP()
             WHERE o.tenant_id = %d AND o.contract_id = %d AND o.id = %d AND o.status = %s AND c.is_archived = 0",
            $targetStatus,
            $actorId,
            $tenantId,
            $contractId,
            $obligationId,
            ObligationPolicy::STATUS_OPEN
        );
        $result = $wpdb->query($sql);
        if ($result === false) {
            throw new RuntimeException('Unable to transition Contract Obligation.');
        }
        $row = $this->find($contractId, $obligationId);
        if ($row !== null && (string) ($row['status'] ?? '') === $targetStatus) {
            return ['obligation' => $row, 'idempotent' => $result !== 1];
        }
        if ($result !== 1) {
            throw new RuntimeException('Contract Obligation terminal compare-and-set failed because state, tenant or Contract mutability changed.');
        }
        throw new RuntimeException('Contract Obligation terminal transition did not produce the requested terminal state.');
    }

    private function tenantId(): int
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise Contract Obligation access requires core tenant enforcement.');
        }
        return TenantContextStore::context()->requireTenantId();
    }
}
