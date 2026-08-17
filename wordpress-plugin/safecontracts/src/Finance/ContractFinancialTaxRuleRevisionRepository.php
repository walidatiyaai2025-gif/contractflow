<?php

declare(strict_types=1);

namespace SafeContracts\Finance;

use InvalidArgumentException;
use RuntimeException;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;
use Throwable;
use UnexpectedValueException;

final class ContractFinancialTaxRuleRevisionRepository
{
    private const SELECT_COLUMNS = 'id, revision_uuid, contract_id, financial_currency_profile_id, tax_rule_uuid, revision_number, tax_kind, label, rate_percent, tax_rule_state, created_by, created_at';

    /**
     * @param callable(array<string,mixed>):void $authorizeLockedContract
     * @return list<array<string,mixed>>
     */
    public function listCurrentForContract(int $contractId, callable $authorizeLockedContract): array
    {
        if ($contractId <= 0) {
            throw new InvalidArgumentException('Contract ID must be positive.');
        }

        global $wpdb;
        $tenantId = $this->tenantId();
        $revisions = $wpdb->prefix . 'safecontracts_contract_financial_tax_rule_revisions';
        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Enterprise Contract tax rule read transaction.');
        }

        try {
            $this->lockContract($contractId, $authorizeLockedContract, false);
            $profile = $this->lockProfile($contractId);
            $limit = ContractFinancialTaxRulePolicy::MAX_RULES + 1;
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT " . self::SELECT_COLUMNS . " FROM {$revisions} r
                 WHERE r.tenant_id = %d AND r.contract_id = %d
                   AND NOT EXISTS (
                        SELECT 1 FROM {$revisions} newer
                        WHERE newer.tenant_id = r.tenant_id
                          AND newer.contract_id = r.contract_id
                          AND newer.tax_rule_uuid = r.tax_rule_uuid
                          AND (
                               newer.revision_number > r.revision_number
                               OR (newer.revision_number = r.revision_number AND newer.id > r.id)
                          )
                   )
                 ORDER BY r.tax_rule_uuid ASC
                 LIMIT %d",
                $tenantId,
                $contractId,
                $limit
            ), ARRAY_A);
            if (! is_array($rows)) {
                throw new RuntimeException('Enterprise Contract tax rule list could not be read.');
            }
            if (count($rows) > ContractFinancialTaxRulePolicy::MAX_RULES) {
                throw new RuntimeException('Enterprise Contract tax rule limit was exceeded.');
            }

            $normalized = [];
            $seen = [];
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    throw new UnexpectedValueException('Enterprise Contract tax rule row is invalid.');
                }
                $revision = $this->normalizeRevision($row, $contractId);
                $ruleUuid = (string) $revision['tax_rule_uuid'];
                if (isset($seen[$ruleUuid])) {
                    throw new UnexpectedValueException('Enterprise Contract tax rule list contains duplicate latest identities.');
                }
                $seen[$ruleUuid] = true;
                $this->assertRevisionProfile($revision, $profile);
                $normalized[] = $revision;
            }

            $this->commit('Enterprise Contract tax rule read');
            return $normalized;
        } catch (Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    /** @param callable(array<string,mixed>):void $authorizeLockedContract */
    public function createRule(
        int $contractId,
        string $ruleUuid,
        string $revisionUuid,
        string $kind,
        string $label,
        PercentageRate $rate,
        int $actorId,
        callable $authorizeLockedContract
    ): int {
        $ruleUuid = ContractFinancialTaxRulePolicy::normalizeUuid($ruleUuid, 'tax rule UUID');
        $revisionUuid = ContractFinancialTaxRulePolicy::normalizeUuid($revisionUuid, 'revision UUID');
        $kind = ContractFinancialTaxRulePolicy::normalizeKind($kind);
        $label = ContractFinancialTaxRulePolicy::normalizeLabel($label);
        $this->assertActor($actorId);

        global $wpdb;
        $tenantId = $this->tenantId();
        $revisions = $wpdb->prefix . 'safecontracts_contract_financial_tax_rule_revisions';
        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Enterprise Contract tax rule creation transaction.');
        }

        try {
            $this->lockContract($contractId, $authorizeLockedContract, true);
            $profile = $this->lockProfile($contractId);

            $countRows = $wpdb->get_results($wpdb->prepare(
                "SELECT COUNT(DISTINCT tax_rule_uuid) AS total FROM {$revisions} WHERE tenant_id = %d AND contract_id = %d",
                $tenantId,
                $contractId
            ), ARRAY_A);
            if (! is_array($countRows) || count($countRows) !== 1 || ! is_array($countRows[0])) {
                throw new RuntimeException('Enterprise Contract tax rule count returned an invalid cardinality.');
            }
            $count = (int) ($countRows[0]['total'] ?? -1);
            if ($count < 0 || $count >= ContractFinancialTaxRulePolicy::MAX_RULES) {
                throw new RuntimeException('Enterprise Contract tax rule limit has been reached.');
            }

            $collisionRows = $wpdb->get_results($wpdb->prepare(
                "SELECT id FROM {$revisions} WHERE tenant_id = %d AND contract_id = %d AND tax_rule_uuid = %s LIMIT 2 FOR UPDATE",
                $tenantId,
                $contractId,
                $ruleUuid
            ), ARRAY_A);
            if (! is_array($collisionRows) || $collisionRows !== []) {
                throw new RuntimeException('Enterprise Contract tax rule identity already exists or could not be verified.');
            }

            $id = $this->insertRevision(
                $contractId,
                (int) $profile['id'],
                $ruleUuid,
                $revisionUuid,
                1,
                $kind,
                $label,
                $rate,
                ContractFinancialTaxRulePolicy::STATE_CONFIGURED,
                $actorId
            );
            $this->commit('Enterprise Contract tax rule creation');
            return $id;
        } catch (Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    /** @param callable(array<string,mixed>):void $authorizeLockedContract */
    public function reviseRule(
        int $contractId,
        string $ruleUuid,
        string $revisionUuid,
        string $kind,
        string $label,
        PercentageRate $rate,
        int $actorId,
        callable $authorizeLockedContract
    ): int {
        $ruleUuid = ContractFinancialTaxRulePolicy::normalizeUuid($ruleUuid, 'tax rule UUID');
        $revisionUuid = ContractFinancialTaxRulePolicy::normalizeUuid($revisionUuid, 'revision UUID');
        $kind = ContractFinancialTaxRulePolicy::normalizeKind($kind);
        $label = ContractFinancialTaxRulePolicy::normalizeLabel($label);
        $this->assertActor($actorId);

        global $wpdb;
        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Enterprise Contract tax rule revision transaction.');
        }

        try {
            $this->lockContract($contractId, $authorizeLockedContract, true);
            $profile = $this->lockProfile($contractId);
            $latest = $this->lockLatestRule($contractId, $ruleUuid);
            $this->assertRevisionProfile($latest, $profile);
            if ((string) $latest['tax_rule_state'] !== ContractFinancialTaxRulePolicy::STATE_CONFIGURED) {
                throw new RuntimeException('Voided Enterprise Contract tax rules cannot be revised or reactivated.');
            }

            $storedRate = PercentageRate::of((string) $latest['rate_percent']);
            if ((string) $latest['tax_kind'] === $kind
                && (string) $latest['label'] === $label
                && $storedRate->equals($rate)) {
                $this->commit('idempotent Enterprise Contract tax rule revision');
                return (int) $latest['id'];
            }

            $id = $this->insertRevision(
                $contractId,
                (int) $profile['id'],
                $ruleUuid,
                $revisionUuid,
                $this->nextRevisionNumber((int) $latest['revision_number']),
                $kind,
                $label,
                $rate,
                ContractFinancialTaxRulePolicy::STATE_CONFIGURED,
                $actorId
            );
            $this->commit('Enterprise Contract tax rule revision');
            return $id;
        } catch (Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    /** @param callable(array<string,mixed>):void $authorizeLockedContract */
    public function voidRule(
        int $contractId,
        string $ruleUuid,
        string $revisionUuid,
        int $actorId,
        callable $authorizeLockedContract
    ): int {
        $ruleUuid = ContractFinancialTaxRulePolicy::normalizeUuid($ruleUuid, 'tax rule UUID');
        $revisionUuid = ContractFinancialTaxRulePolicy::normalizeUuid($revisionUuid, 'revision UUID');
        $this->assertActor($actorId);

        global $wpdb;
        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Enterprise Contract tax rule void transaction.');
        }

        try {
            $this->lockContract($contractId, $authorizeLockedContract, true);
            $profile = $this->lockProfile($contractId);
            $latest = $this->lockLatestRule($contractId, $ruleUuid);
            $this->assertRevisionProfile($latest, $profile);
            if ((string) $latest['tax_rule_state'] === ContractFinancialTaxRulePolicy::STATE_VOIDED) {
                $this->commit('idempotent Enterprise Contract tax rule void');
                return (int) $latest['id'];
            }

            $id = $this->insertRevision(
                $contractId,
                (int) $profile['id'],
                $ruleUuid,
                $revisionUuid,
                $this->nextRevisionNumber((int) $latest['revision_number']),
                (string) $latest['tax_kind'],
                (string) $latest['label'],
                PercentageRate::of((string) $latest['rate_percent']),
                ContractFinancialTaxRulePolicy::STATE_VOIDED,
                $actorId
            );
            $this->commit('Enterprise Contract tax rule void');
            return $id;
        } catch (Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    /**
     * @param callable(array<string,mixed>):void $authorizeLockedContract
     * @return array<string,mixed>
     */
    private function lockContract(int $contractId, callable $authorizeLockedContract, bool $requireMutable): array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, accountant_user_id, status, is_archived FROM {$contracts} WHERE id = %d AND tenant_id = %d LIMIT 2 FOR UPDATE",
            $contractId,
            $tenantId
        ), ARRAY_A);
        if (! is_array($rows) || count($rows) !== 1 || ! is_array($rows[0])) {
            throw new RuntimeException('Enterprise Contract changed concurrently or is outside the current tenant.');
        }
        $contract = $rows[0];
        if ((int) ($contract['id'] ?? 0) !== $contractId) {
            throw new UnexpectedValueException('Locked Enterprise Contract identity is invalid.');
        }

        // Authorization occurs against the exact row protected by the Contract lock,
        // before profile or tax-rule financial state is observed.
        $authorizeLockedContract($contract);

        $status = (string) ($contract['status'] ?? '');
        if ($requireMutable
            && ((int) ($contract['is_archived'] ?? 0) !== 0 || ! in_array($status, ['draft', 'active'], true))) {
            throw new RuntimeException('Enterprise Contract tax rules may only change while the Contract is unarchived and draft or active.');
        }
        return $contract;
    }

    /** @return array{id:int,currency:CurrencyCode} */
    private function lockProfile(int $contractId): array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $profiles = $wpdb->prefix . 'safecontracts_contract_financial_currency_profiles';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, contract_id, contract_currency FROM {$profiles} WHERE tenant_id = %d AND contract_id = %d LIMIT 2 FOR UPDATE",
            $tenantId,
            $contractId
        ), ARRAY_A);
        if (! is_array($rows) || count($rows) !== 1 || ! is_array($rows[0])) {
            throw new RuntimeException('Enterprise Contract requires exactly one current-tenant financial currency profile.');
        }
        $profileId = (int) ($rows[0]['id'] ?? 0);
        $profileContractId = (int) ($rows[0]['contract_id'] ?? 0);
        $currency = $this->currencyFromStorage($rows[0]['contract_currency'] ?? null);
        if ($profileId <= 0 || $profileContractId !== $contractId) {
            throw new UnexpectedValueException('Enterprise Contract financial profile identity is invalid.');
        }
        return ['id' => $profileId, 'currency' => $currency];
    }

    /** @return array<string,mixed> */
    private function lockLatestRule(int $contractId, string $ruleUuid): array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $revisions = $wpdb->prefix . 'safecontracts_contract_financial_tax_rule_revisions';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT " . self::SELECT_COLUMNS . " FROM {$revisions}
             WHERE tenant_id = %d AND contract_id = %d AND tax_rule_uuid = %s
             ORDER BY revision_number DESC, id DESC LIMIT 1 FOR UPDATE",
            $tenantId,
            $contractId,
            $ruleUuid
        ), ARRAY_A);
        if (! is_array($rows) || count($rows) !== 1 || ! is_array($rows[0])) {
            throw new RuntimeException('Enterprise Contract tax rule was not found or has invalid cardinality.');
        }
        return $this->normalizeRevision($rows[0], $contractId, $ruleUuid);
    }

    /** @param array<string,mixed> $revision @param array{id:int,currency:CurrencyCode} $profile */
    private function assertRevisionProfile(array $revision, array $profile): void
    {
        if ((int) $revision['financial_currency_profile_id'] !== (int) $profile['id']) {
            throw new UnexpectedValueException('Enterprise Contract tax rule revision references a different financial profile.');
        }
    }

    private function insertRevision(
        int $contractId,
        int $profileId,
        string $ruleUuid,
        string $revisionUuid,
        int $revisionNumber,
        string $kind,
        string $label,
        PercentageRate $rate,
        string $state,
        int $actorId
    ): int {
        global $wpdb;
        $tenantId = $this->tenantId();
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $profiles = $wpdb->prefix . 'safecontracts_contract_financial_currency_profiles';
        $revisions = $wpdb->prefix . 'safecontracts_contract_financial_tax_rule_revisions';
        $state = ContractFinancialTaxRulePolicy::normalizeState($state);

        $inserted = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$revisions} (
                tenant_id, revision_uuid, contract_id, financial_currency_profile_id, tax_rule_uuid,
                revision_number, tax_kind, label, rate_percent, tax_rule_state, created_by, created_at
             ) SELECT %d, %s, c.id, p.id, %s, %d, %s, %s, %s, %s, %d, UTC_TIMESTAMP()
             FROM {$contracts} c
             INNER JOIN {$profiles} p ON p.tenant_id = c.tenant_id AND p.contract_id = c.id
             WHERE c.id = %d AND c.tenant_id = %d AND c.status IN ('draft', 'active') AND c.is_archived = 0
               AND p.id = %d
             LIMIT 1",
            $tenantId,
            $revisionUuid,
            $ruleUuid,
            $revisionNumber,
            $kind,
            $label,
            $rate->value(),
            $state,
            $actorId,
            $contractId,
            $tenantId,
            $profileId
        ));
        if ($inserted !== 1) {
            throw new RuntimeException('Unable to append Enterprise Contract tax rule revision.');
        }
        $id = (int) ($wpdb->insert_id ?? 0);
        if ($id <= 0) {
            throw new RuntimeException('Enterprise Contract tax rule revision insert returned no identifier.');
        }
        return $id;
    }

    private function nextRevisionNumber(int $current): int
    {
        if ($current <= 0 || $current >= ContractFinancialTaxRulePolicy::MAX_REVISION) {
            throw new RuntimeException('Enterprise Contract tax rule revision limit has been reached.');
        }
        return $current + 1;
    }

    private function assertActor(int $actorId): void
    {
        if ($actorId <= 0) {
            throw new InvalidArgumentException('Enterprise Contract tax rule mutation requires an authenticated actor.');
        }
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function normalizeRevision(array $row, int $expectedContractId, ?string $expectedRuleUuid = null): array
    {
        $id = (int) ($row['id'] ?? 0);
        $contractId = (int) ($row['contract_id'] ?? 0);
        $profileId = (int) ($row['financial_currency_profile_id'] ?? 0);
        $revisionNumber = (int) ($row['revision_number'] ?? 0);
        $createdBy = (int) ($row['created_by'] ?? 0);
        if ($id <= 0 || $contractId !== $expectedContractId || $profileId <= 0
            || $revisionNumber <= 0 || $revisionNumber > ContractFinancialTaxRulePolicy::MAX_REVISION
            || $createdBy <= 0) {
            throw new UnexpectedValueException('Enterprise Contract tax rule revision has invalid identity or revision metadata.');
        }

        try {
            $revisionUuid = ContractFinancialTaxRulePolicy::normalizeUuid($row['revision_uuid'] ?? null, 'revision UUID');
            $ruleUuid = ContractFinancialTaxRulePolicy::normalizeUuid($row['tax_rule_uuid'] ?? null, 'tax rule UUID');
            $kind = ContractFinancialTaxRulePolicy::normalizeKind($row['tax_kind'] ?? null);
            $label = ContractFinancialTaxRulePolicy::normalizeLabel($row['label'] ?? null);
            $rate = PercentageRate::of($row['rate_percent'] ?? null);
            $state = ContractFinancialTaxRulePolicy::normalizeState($row['tax_rule_state'] ?? null);
        } catch (Throwable $error) {
            throw new UnexpectedValueException('Enterprise Contract tax rule revision contains invalid persisted data.', 0, $error);
        }
        if ($expectedRuleUuid !== null && $ruleUuid !== $expectedRuleUuid) {
            throw new UnexpectedValueException('Enterprise Contract tax rule lookup returned a different rule identity.');
        }

        $row['id'] = $id;
        $row['revision_uuid'] = $revisionUuid;
        $row['contract_id'] = $contractId;
        $row['financial_currency_profile_id'] = $profileId;
        $row['tax_rule_uuid'] = $ruleUuid;
        $row['revision_number'] = $revisionNumber;
        $row['tax_kind'] = $kind;
        $row['label'] = $label;
        $row['rate_percent'] = $rate->value();
        $row['tax_rule_state'] = $state;
        $row['created_by'] = $createdBy;
        return $row;
    }

    private function currencyFromStorage(mixed $value): CurrencyCode
    {
        try {
            return CurrencyCode::from($value);
        } catch (Throwable $error) {
            throw new UnexpectedValueException('Enterprise Contract tax rule financial profile currency is invalid.', 0, $error);
        }
    }

    private function commit(string $operation): void
    {
        global $wpdb;
        if ($wpdb->query('COMMIT') === false) {
            throw new RuntimeException("Unable to commit {$operation}.");
        }
    }

    private function tenantId(): int
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise Contract tax rule access requires core tenant enforcement.');
        }
        return TenantContextStore::context()->requireTenantId();
    }
}
