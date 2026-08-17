<?php

declare(strict_types=1);

namespace SafeContracts\Renewals;

use RuntimeException;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;

final class ContractRenewalTermsRepository
{
    private const SELECT_COLUMNS = 'id, uuid, contract_id, renewal_mode, interval_value, interval_unit, max_occurrences, notes, revision, created_by, updated_by, created_at, updated_at';

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
    public function find(int $termsId): ?array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_contract_renewal_terms';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT " . self::SELECT_COLUMNS . " FROM {$table} WHERE id = %d AND tenant_id = %d LIMIT 1",
            $termsId,
            $tenantId
        ), ARRAY_A);
        return is_array($rows) && $rows !== [] && is_array($rows[0]) ? $rows[0] : null;
    }

    /** @return array<string,mixed>|null */
    public function findForContract(int $contractId): ?array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $table = $wpdb->prefix . 'safecontracts_contract_renewal_terms';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT " . self::SELECT_COLUMNS . " FROM {$table} WHERE contract_id = %d AND tenant_id = %d LIMIT 1",
            $contractId,
            $tenantId
        ), ARRAY_A);
        return is_array($rows) && $rows !== [] && is_array($rows[0]) ? $rows[0] : null;
    }

    public function create(
        int $contractId,
        string $uuid,
        string $mode,
        ?int $intervalValue,
        ?string $intervalUnit,
        ?int $maxOccurrences,
        ?string $notes,
        int $actorId
    ): int {
        global $wpdb;
        $tenantId = $this->tenantId();
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $terms = $wpdb->prefix . 'safecontracts_contract_renewal_terms';

        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Contract Renewal Terms creation transaction.');
        }
        try {
            $contractRows = $wpdb->get_results($wpdb->prepare(
                "SELECT id FROM {$contracts} WHERE id = %d AND tenant_id = %d AND is_archived = 0 LIMIT 1 FOR UPDATE",
                $contractId,
                $tenantId
            ), ARRAY_A);
            if (! is_array($contractRows) || count($contractRows) !== 1 || ! is_array($contractRows[0])) {
                throw new RuntimeException('Enterprise contract changed concurrently or is no longer renewal-editable.');
            }

            $existingRows = $wpdb->get_results($wpdb->prepare(
                "SELECT id FROM {$terms} WHERE tenant_id = %d AND contract_id = %d LIMIT 2 FOR UPDATE",
                $tenantId,
                $contractId
            ), ARRAY_A);
            if (is_array($existingRows) && $existingRows !== []) {
                throw new RuntimeException('Contract Renewal Terms already exist for this contract.');
            }

            $intervalValueSql = $this->nullableIntSql($intervalValue);
            $intervalUnitSql = $this->nullableStringSql($wpdb, $intervalUnit);
            $maxOccurrencesSql = $this->nullableIntSql($maxOccurrences);
            $notesSql = $this->nullableStringSql($wpdb, $notes);
            $sql = $wpdb->prepare(
                "INSERT INTO {$terms} (
                    tenant_id, uuid, contract_id, renewal_mode, interval_value, interval_unit, max_occurrences, notes,
                    revision, created_by, updated_by, created_at, updated_at
                 ) VALUES (
                    %d, %s, %d, %s, {$intervalValueSql}, {$intervalUnitSql}, {$maxOccurrencesSql}, {$notesSql},
                    1, %d, %d, UTC_TIMESTAMP(), UTC_TIMESTAMP()
                 )",
                $tenantId,
                $uuid,
                $contractId,
                $mode,
                $actorId,
                $actorId
            );
            if ($wpdb->query($sql) !== 1) {
                throw new RuntimeException('Unable to create Enterprise Contract Renewal Terms.');
            }
            $termsId = (int) ($wpdb->insert_id ?? 0);
            if ($termsId <= 0) {
                throw new RuntimeException('Contract Renewal Terms insert returned no identifier.');
            }
            if ($wpdb->query('COMMIT') === false) {
                throw new RuntimeException('Unable to commit Contract Renewal Terms creation transaction.');
            }
            return $termsId;
        } catch (\Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    /** @return array<string,mixed> */
    public function update(
        int $termsId,
        int $expectedRevision,
        string $mode,
        ?int $intervalValue,
        ?string $intervalUnit,
        ?int $maxOccurrences,
        ?string $notes,
        int $actorId
    ): array {
        global $wpdb;
        $tenantId = $this->tenantId();
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $terms = $wpdb->prefix . 'safecontracts_contract_renewal_terms';

        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Contract Renewal Terms update transaction.');
        }
        try {
            $locked = $this->lockTermsWithContract($wpdb, $terms, $contracts, $tenantId, $termsId);
            if ((int) ($locked['is_archived'] ?? 0) === 1) {
                throw new RuntimeException('Archived contracts cannot mutate Contract Renewal Terms.');
            }
            if ((int) ($locked['revision'] ?? 0) !== $expectedRevision) {
                throw new RuntimeException('Contract Renewal Terms changed concurrently.');
            }

            $intervalValueSql = $this->nullableIntSql($intervalValue);
            $intervalUnitSql = $this->nullableStringSql($wpdb, $intervalUnit);
            $maxOccurrencesSql = $this->nullableIntSql($maxOccurrences);
            $notesSql = $this->nullableStringSql($wpdb, $notes);
            $updated = $wpdb->query($wpdb->prepare(
                "UPDATE {$terms}
                 SET renewal_mode = %s,
                     interval_value = {$intervalValueSql},
                     interval_unit = {$intervalUnitSql},
                     max_occurrences = {$maxOccurrencesSql},
                     notes = {$notesSql},
                     revision = revision + 1,
                     updated_by = %d,
                     updated_at = UTC_TIMESTAMP()
                 WHERE id = %d AND tenant_id = %d AND revision = %d",
                $mode,
                $actorId,
                $termsId,
                $tenantId,
                $expectedRevision
            ));
            if ($updated !== 1) {
                throw new RuntimeException('Contract Renewal Terms changed concurrently and were not updated.');
            }
            if ($wpdb->query('COMMIT') === false) {
                throw new RuntimeException('Unable to commit Contract Renewal Terms update transaction.');
            }
            $result = $this->find($termsId);
            if ($result === null) {
                throw new RuntimeException('Contract Renewal Terms disappeared after update.');
            }
            return $result;
        } catch (\Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    /** @return array<string,mixed> */
    private function lockTermsWithContract(
        object $wpdb,
        string $terms,
        string $contracts,
        int $tenantId,
        int $termsId
    ): array {
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT r.id, r.contract_id, r.revision, c.is_archived
             FROM {$terms} r
             INNER JOIN {$contracts} c ON c.id = r.contract_id AND c.tenant_id = r.tenant_id
             WHERE r.id = %d AND r.tenant_id = %d
             LIMIT 2 FOR UPDATE",
            $termsId,
            $tenantId
        ), ARRAY_A);
        if (! is_array($rows) || count($rows) !== 1 || ! is_array($rows[0])) {
            throw new RuntimeException('Contract Renewal Terms were not found in the current Enterprise tenant.');
        }
        return $rows[0];
    }

    private function tenantId(): int
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise Contract Renewal Terms access requires core tenant enforcement.');
        }
        return TenantContextStore::context()->requireTenantId();
    }

    private function nullableIntSql(?int $value): string
    {
        return $value === null ? 'NULL' : (string) $value;
    }

    private function nullableStringSql(object $wpdb, ?string $value): string
    {
        return $value === null ? 'NULL' : $wpdb->prepare('%s', $value);
    }
}
