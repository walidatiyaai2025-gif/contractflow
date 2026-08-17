<?php

declare(strict_types=1);

namespace SafeContracts\Notices;

use RuntimeException;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

final class ContractNoticePeriodRuleRepository
{
    private const SELECT_COLUMNS = 'id, uuid, contract_id, notice_code, purpose, direction, period_value, period_unit, is_active, notes, revision, created_by, updated_by, created_at, updated_at';

    /** @return array<string,mixed>|null */
    public function findContract(int $contractId): ?array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, accountant_user_id, status, start_date, end_date, is_archived FROM {$contracts} WHERE id = %d AND tenant_id = %d LIMIT 1",
            $contractId,
            $tenantId
        ), ARRAY_A);
        return is_array($rows) && $rows !== [] && is_array($rows[0]) ? $rows[0] : null;
    }

    /** @return array<string,mixed>|null */
    public function find(int $ruleId): ?array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $rules = $wpdb->prefix . 'safecontracts_contract_notice_period_rules';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT " . self::SELECT_COLUMNS . " FROM {$rules} WHERE id = %d AND tenant_id = %d LIMIT 1",
            $ruleId,
            $tenantId
        ), ARRAY_A);
        return is_array($rows) && $rows !== [] && is_array($rows[0]) ? $rows[0] : null;
    }

    /** @return list<array<string,mixed>> */
    public function listForContract(int $contractId, int $limit = 50, int $offset = 0): array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $rules = $wpdb->prefix . 'safecontracts_contract_notice_period_rules';
        $limit = max(1, min(100, $limit));
        $offset = max(0, min(100000, $offset));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT " . self::SELECT_COLUMNS . " FROM {$rules}
             WHERE tenant_id = %d AND contract_id = %d
             ORDER BY is_active DESC, purpose ASC, notice_code ASC, id ASC
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
        string $purpose,
        string $direction,
        int $periodValue,
        string $periodUnit,
        int $isActive,
        ?string $notes,
        int $actorId
    ): int {
        global $wpdb;
        $tenantId = $this->tenantId();
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $rules = $wpdb->prefix . 'safecontracts_contract_notice_period_rules';

        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Contract Notice Period Rule creation transaction.');
        }
        try {
            $contractRows = $wpdb->get_results($wpdb->prepare(
                "SELECT id FROM {$contracts} WHERE id = %d AND tenant_id = %d AND is_archived = 0 LIMIT 1 FOR UPDATE",
                $contractId,
                $tenantId
            ), ARRAY_A);
            if (! is_array($contractRows) || count($contractRows) !== 1 || ! is_array($contractRows[0])) {
                throw new RuntimeException('Enterprise contract changed concurrently or is no longer notice-rule-editable.');
            }

            $existingRows = $wpdb->get_results($wpdb->prepare(
                "SELECT id FROM {$rules} WHERE tenant_id = %d AND contract_id = %d AND notice_code = %s LIMIT 2 FOR UPDATE",
                $tenantId,
                $contractId,
                $code
            ), ARRAY_A);
            if (is_array($existingRows) && $existingRows !== []) {
                throw new RuntimeException('Notice period code already exists for this contract.');
            }

            $notesSql = $this->nullableStringSql($wpdb, $notes);
            $sql = $wpdb->prepare(
                "INSERT INTO {$rules} (
                    tenant_id, uuid, contract_id, notice_code, purpose, direction,
                    period_value, period_unit, is_active, notes, revision,
                    created_by, updated_by, created_at, updated_at
                 ) VALUES (
                    %d, %s, %d, %s, %s, %s,
                    %d, %s, %d, {$notesSql}, 1,
                    %d, %d, UTC_TIMESTAMP(), UTC_TIMESTAMP()
                 )",
                $tenantId,
                $uuid,
                $contractId,
                $code,
                $purpose,
                $direction,
                $periodValue,
                $periodUnit,
                $isActive,
                $actorId,
                $actorId
            );
            if ($wpdb->query($sql) !== 1) {
                throw new RuntimeException('Unable to create Enterprise Contract Notice Period Rule.');
            }
            $ruleId = (int) ($wpdb->insert_id ?? 0);
            if ($ruleId <= 0) {
                throw new RuntimeException('Contract Notice Period Rule insert returned no identifier.');
            }
            if ($wpdb->query('COMMIT') === false) {
                throw new RuntimeException('Unable to commit Contract Notice Period Rule creation transaction.');
            }
            return $ruleId;
        } catch (\Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    /** @return array<string,mixed> */
    public function update(
        int $ruleId,
        int $expectedRevision,
        string $purpose,
        string $direction,
        int $periodValue,
        string $periodUnit,
        int $isActive,
        ?string $notes,
        int $actorId
    ): array {
        global $wpdb;
        $tenantId = $this->tenantId();
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $rules = $wpdb->prefix . 'safecontracts_contract_notice_period_rules';

        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Contract Notice Period Rule update transaction.');
        }
        try {
            $locked = $this->lockRuleWithContract($wpdb, $rules, $contracts, $tenantId, $ruleId);
            if ((int) ($locked['is_archived'] ?? 0) === 1) {
                throw new RuntimeException('Archived contracts cannot mutate Contract Notice Period Rules.');
            }
            if ((int) ($locked['revision'] ?? 0) !== $expectedRevision) {
                throw new RuntimeException('Contract Notice Period Rule changed concurrently.');
            }

            $notesSql = $this->nullableStringSql($wpdb, $notes);
            $updated = $wpdb->query($wpdb->prepare(
                "UPDATE {$rules}
                 SET purpose = %s,
                     direction = %s,
                     period_value = %d,
                     period_unit = %s,
                     is_active = %d,
                     notes = {$notesSql},
                     revision = revision + 1,
                     updated_by = %d,
                     updated_at = UTC_TIMESTAMP()
                 WHERE id = %d AND tenant_id = %d AND revision = %d",
                $purpose,
                $direction,
                $periodValue,
                $periodUnit,
                $isActive,
                $actorId,
                $ruleId,
                $tenantId,
                $expectedRevision
            ));
            if ($updated !== 1) {
                throw new RuntimeException('Contract Notice Period Rule changed concurrently and was not updated.');
            }
            if ($wpdb->query('COMMIT') === false) {
                throw new RuntimeException('Unable to commit Contract Notice Period Rule update transaction.');
            }
            $result = $this->find($ruleId);
            if ($result === null) {
                throw new RuntimeException('Contract Notice Period Rule disappeared after update.');
            }
            return $result;
        } catch (\Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    /** @return array<string,mixed> */
    private function lockRuleWithContract(
        object $wpdb,
        string $rules,
        string $contracts,
        int $tenantId,
        int $ruleId
    ): array {
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT n.id, n.contract_id, n.revision, c.is_archived
             FROM {$rules} n
             INNER JOIN {$contracts} c ON c.id = n.contract_id AND c.tenant_id = n.tenant_id
             WHERE n.id = %d AND n.tenant_id = %d
             LIMIT 2 FOR UPDATE",
            $ruleId,
            $tenantId
        ), ARRAY_A);
        if (! is_array($rows) || count($rows) !== 1 || ! is_array($rows[0])) {
            throw new RuntimeException('Contract Notice Period Rule was not found in the current Enterprise tenant.');
        }
        return $rows[0];
    }

    private function tenantId(): int
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise Contract Notice Period Rule access requires core tenant enforcement.');
        }
        return TenantContextStore::context()->requireTenantId();
    }

    private function nullableStringSql(object $wpdb, ?string $value): string
    {
        return $value === null ? 'NULL' : $wpdb->prepare('%s', $value);
    }
}
