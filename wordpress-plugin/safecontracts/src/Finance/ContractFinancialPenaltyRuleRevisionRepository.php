<?php

declare(strict_types=1);

namespace SafeContracts\Finance;

use InvalidArgumentException;
use RuntimeException;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;
use Throwable;
use UnexpectedValueException;

final class ContractFinancialPenaltyRuleRevisionRepository
{
    private const SELECT_COLUMNS = 'id, revision_uuid, contract_id, financial_currency_profile_id, penalty_rule_uuid, revision_number, label, calculation_mode, configured_value, currency_code, penalty_rule_state, created_by, created_at';

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
        $revisions = $wpdb->prefix . 'safecontracts_contract_financial_penalty_rule_revisions';
        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Enterprise Contract penalty rule read transaction.');
        }

        try {
            $this->lockContract($contractId, $authorizeLockedContract, false);
            $profile = $this->lockProfile($contractId);
            $limit = ContractFinancialPenaltyRulePolicy::MAX_RULES + 1;
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT " . self::SELECT_COLUMNS . " FROM {$revisions} r
                 WHERE r.tenant_id = %d AND r.contract_id = %d
                   AND NOT EXISTS (
                        SELECT 1 FROM {$revisions} newer
                        WHERE newer.tenant_id = r.tenant_id
                          AND newer.contract_id = r.contract_id
                          AND newer.penalty_rule_uuid = r.penalty_rule_uuid
                          AND (
                               newer.revision_number > r.revision_number
                               OR (newer.revision_number = r.revision_number AND newer.id > r.id)
                          )
                   )
                 ORDER BY r.penalty_rule_uuid ASC
                 LIMIT %d",
                $tenantId,
                $contractId,
                $limit
            ), ARRAY_A);
            if (! is_array($rows)) {
                throw new RuntimeException('Enterprise Contract penalty rule list could not be read.');
            }
            if (count($rows) > ContractFinancialPenaltyRulePolicy::MAX_RULES) {
                throw new RuntimeException('Enterprise Contract penalty rule limit was exceeded.');
            }

            $normalized = [];
            $seen = [];
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    throw new UnexpectedValueException('Enterprise Contract penalty rule row is invalid.');
                }
                $revision = $this->normalizeRevision($row, $contractId);
                $ruleUuid = (string) $revision['penalty_rule_uuid'];
                if (isset($seen[$ruleUuid])) {
                    throw new UnexpectedValueException('Enterprise Contract penalty rule list contains duplicate latest identities.');
                }
                $seen[$ruleUuid] = true;
                $this->assertRevisionProfile($revision, $profile);
                $normalized[] = $revision;
            }

            $this->commit('Enterprise Contract penalty rule read');
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
        string $label,
        string $mode,
        mixed $configuredValue,
        int $actorId,
        callable $authorizeLockedContract
    ): int {
        $ruleUuid = ContractFinancialPenaltyRulePolicy::normalizeUuid($ruleUuid, 'penalty rule UUID');
        $revisionUuid = ContractFinancialPenaltyRulePolicy::normalizeUuid($revisionUuid, 'revision UUID');
        $label = ContractFinancialPenaltyRulePolicy::normalizeLabel($label);
        $mode = ContractFinancialPenaltyRulePolicy::normalizeMode($mode);
        $this->assertActor($actorId);

        global $wpdb;
        $tenantId = $this->tenantId();
        $revisions = $wpdb->prefix . 'safecontracts_contract_financial_penalty_rule_revisions';
        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Enterprise Contract penalty rule creation transaction.');
        }

        try {
            $this->lockContract($contractId, $authorizeLockedContract, true);
            $profile = $this->lockProfile($contractId);
            $value = $this->canonicalValue($configuredValue, $mode, $profile['currency']);

            $countRows = $wpdb->get_results($wpdb->prepare(
                "SELECT COUNT(DISTINCT penalty_rule_uuid) AS total FROM {$revisions} WHERE tenant_id = %d AND contract_id = %d",
                $tenantId,
                $contractId
            ), ARRAY_A);
            if (! is_array($countRows) || count($countRows) !== 1 || ! is_array($countRows[0])) {
                throw new RuntimeException('Enterprise Contract penalty rule count returned an invalid cardinality.');
            }
            $count = (int) ($countRows[0]['total'] ?? -1);
            if ($count < 0 || $count >= ContractFinancialPenaltyRulePolicy::MAX_RULES) {
                throw new RuntimeException('Enterprise Contract penalty rule limit has been reached.');
            }

            $collisionRows = $wpdb->get_results($wpdb->prepare(
                "SELECT id FROM {$revisions} WHERE tenant_id = %d AND contract_id = %d AND penalty_rule_uuid = %s LIMIT 2 FOR UPDATE",
                $tenantId,
                $contractId,
                $ruleUuid
            ), ARRAY_A);
            if (! is_array($collisionRows) || $collisionRows !== []) {
                throw new RuntimeException('Enterprise Contract penalty rule identity already exists or could not be verified.');
            }

            $id = $this->insertRevision(
                $contractId,
                (int) $profile['id'],
                $ruleUuid,
                $revisionUuid,
                1,
                $label,
                $mode,
                $value,
                $profile['currency'],
                ContractFinancialPenaltyRulePolicy::STATE_CONFIGURED,
                $actorId
            );
            $this->commit('Enterprise Contract penalty rule creation');
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
        string $label,
        string $mode,
        mixed $configuredValue,
        int $actorId,
        callable $authorizeLockedContract
    ): int {
        $ruleUuid = ContractFinancialPenaltyRulePolicy::normalizeUuid($ruleUuid, 'penalty rule UUID');
        $revisionUuid = ContractFinancialPenaltyRulePolicy::normalizeUuid($revisionUuid, 'revision UUID');
        $label = ContractFinancialPenaltyRulePolicy::normalizeLabel($label);
        $mode = ContractFinancialPenaltyRulePolicy::normalizeMode($mode);
        $this->assertActor($actorId);

        global $wpdb;
        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Enterprise Contract penalty rule revision transaction.');
        }

        try {
            $this->lockContract($contractId, $authorizeLockedContract, true);
            $profile = $this->lockProfile($contractId);
            $latest = $this->lockLatestRule($contractId, $ruleUuid);
            $this->assertRevisionProfile($latest, $profile);
            if ((string) $latest['penalty_rule_state'] !== ContractFinancialPenaltyRulePolicy::STATE_CONFIGURED) {
                throw new RuntimeException('Voided Enterprise Contract penalty rules cannot be revised or reactivated.');
            }

            $value = $this->canonicalValue($configuredValue, $mode, $profile['currency']);
            if ((string) $latest['label'] === $label
                && (string) $latest['calculation_mode'] === $mode
                && (string) $latest['configured_value'] === $value
                && (string) $latest['currency_code'] === $profile['currency']->value()) {
                $this->commit('idempotent Enterprise Contract penalty rule revision');
                return (int) $latest['id'];
            }

            $id = $this->insertRevision(
                $contractId,
                (int) $profile['id'],
                $ruleUuid,
                $revisionUuid,
                $this->nextRevisionNumber((int) $latest['revision_number']),
                $label,
                $mode,
                $value,
                $profile['currency'],
                ContractFinancialPenaltyRulePolicy::STATE_CONFIGURED,
                $actorId
            );
            $this->commit('Enterprise Contract penalty rule revision');
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
        $ruleUuid = ContractFinancialPenaltyRulePolicy::normalizeUuid($ruleUuid, 'penalty rule UUID');
        $revisionUuid = ContractFinancialPenaltyRulePolicy::normalizeUuid($revisionUuid, 'revision UUID');
        $this->assertActor($actorId);

        global $wpdb;
        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Enterprise Contract penalty rule void transaction.');
        }

        try {
            $this->lockContract($contractId, $authorizeLockedContract, true);
            $profile = $this->lockProfile($contractId);
            $latest = $this->lockLatestRule($contractId, $ruleUuid);
            $this->assertRevisionProfile($latest, $profile);
            if ((string) $latest['penalty_rule_state'] === ContractFinancialPenaltyRulePolicy::STATE_VOIDED) {
                $this->commit('idempotent Enterprise Contract penalty rule void');
                return (int) $latest['id'];
            }

            $id = $this->insertRevision(
                $contractId,
                (int) $profile['id'],
                $ruleUuid,
                $revisionUuid,
                $this->nextRevisionNumber((int) $latest['revision_number']),
                (string) $latest['label'],
                (string) $latest['calculation_mode'],
                (string) $latest['configured_value'],
                $profile['currency'],
                ContractFinancialPenaltyRulePolicy::STATE_VOIDED,
                $actorId
            );
            $this->commit('Enterprise Contract penalty rule void');
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

        $authorizeLockedContract($contract);

        $status = (string) ($contract['status'] ?? '');
        if ($requireMutable
            && ((int) ($contract['is_archived'] ?? 0) !== 0 || ! in_array($status, ['draft', 'active'], true))) {
            throw new RuntimeException('Enterprise Contract penalty rules may only change while the Contract is unarchived and draft or active.');
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
        $revisions = $wpdb->prefix . 'safecontracts_contract_financial_penalty_rule_revisions';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT " . self::SELECT_COLUMNS . " FROM {$revisions}
             WHERE tenant_id = %d AND contract_id = %d AND penalty_rule_uuid = %s
             ORDER BY revision_number DESC, id DESC LIMIT 1 FOR UPDATE",
            $tenantId,
            $contractId,
            $ruleUuid
        ), ARRAY_A);
        if (! is_array($rows) || count($rows) !== 1 || ! is_array($rows[0])) {
            throw new RuntimeException('Enterprise Contract penalty rule was not found or has invalid cardinality.');
        }
        return $this->normalizeRevision($rows[0], $contractId, $ruleUuid);
    }

    /** @param array<string,mixed> $revision @param array{id:int,currency:CurrencyCode} $profile */
    private function assertRevisionProfile(array $revision, array $profile): void
    {
        if ((int) $revision['financial_currency_profile_id'] !== (int) $profile['id']) {
            throw new UnexpectedValueException('Enterprise Contract penalty rule revision references a different financial profile.');
        }
        if ((string) $revision['currency_code'] !== $profile['currency']->value()) {
            throw new UnexpectedValueException('Enterprise Contract penalty rule revision currency differs from the financial profile.');
        }
    }

    private function canonicalValue(mixed $value, string $mode, CurrencyCode $currency): string
    {
        if ($mode === ContractFinancialPenaltyRulePolicy::MODE_PERCENTAGE) {
            return PercentageRate::of($value)->value();
        }
        if ($mode !== ContractFinancialPenaltyRulePolicy::MODE_FIXED_AMOUNT) {
            throw new InvalidArgumentException('Unsupported Enterprise Contract penalty mode.');
        }

        $money = Money::of($value, $currency);
        if ($money->compare(Money::of('0', $currency)) < 0) {
            throw new InvalidArgumentException('Enterprise Contract fixed penalty amount cannot be negative.');
        }
        return $money->amount();
    }

    private function insertRevision(
        int $contractId,
        int $profileId,
        string $ruleUuid,
        string $revisionUuid,
        int $revisionNumber,
        string $label,
        string $mode,
        string $configuredValue,
        CurrencyCode $currency,
        string $state,
        int $actorId
    ): int {
        global $wpdb;
        $tenantId = $this->tenantId();
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $profiles = $wpdb->prefix . 'safecontracts_contract_financial_currency_profiles';
        $revisions = $wpdb->prefix . 'safecontracts_contract_financial_penalty_rule_revisions';
        $state = ContractFinancialPenaltyRulePolicy::normalizeState($state);
        $mode = ContractFinancialPenaltyRulePolicy::normalizeMode($mode);

        $inserted = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$revisions} (
                tenant_id, revision_uuid, contract_id, financial_currency_profile_id, penalty_rule_uuid,
                revision_number, label, calculation_mode, configured_value, currency_code,
                penalty_rule_state, created_by, created_at
             ) SELECT %d, %s, c.id, p.id, %s, %d, %s, %s, %s, p.contract_currency, %s, %d, UTC_TIMESTAMP()
             FROM {$contracts} c
             INNER JOIN {$profiles} p ON p.tenant_id = c.tenant_id AND p.contract_id = c.id
             WHERE c.id = %d AND c.tenant_id = %d AND c.status IN ('draft', 'active') AND c.is_archived = 0
               AND p.id = %d AND p.contract_currency = %s
             LIMIT 1",
            $tenantId,
            $revisionUuid,
            $ruleUuid,
            $revisionNumber,
            $label,
            $mode,
            $configuredValue,
            $state,
            $actorId,
            $contractId,
            $tenantId,
            $profileId,
            $currency->value()
        ));
        if ($inserted !== 1) {
            throw new RuntimeException('Unable to append Enterprise Contract penalty rule revision.');
        }
        $id = (int) ($wpdb->insert_id ?? 0);
        if ($id <= 0) {
            throw new RuntimeException('Enterprise Contract penalty rule revision insert returned no identifier.');
        }
        return $id;
    }

    private function nextRevisionNumber(int $current): int
    {
        if ($current <= 0 || $current >= ContractFinancialPenaltyRulePolicy::MAX_REVISION) {
            throw new RuntimeException('Enterprise Contract penalty rule revision limit has been reached.');
        }
        return $current + 1;
    }

    private function assertActor(int $actorId): void
    {
        if ($actorId <= 0) {
            throw new InvalidArgumentException('Enterprise Contract penalty rule mutation requires an authenticated actor.');
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
            || $revisionNumber <= 0 || $revisionNumber > ContractFinancialPenaltyRulePolicy::MAX_REVISION
            || $createdBy <= 0) {
            throw new UnexpectedValueException('Enterprise Contract penalty rule revision has invalid identity or revision metadata.');
        }

        try {
            $revisionUuid = ContractFinancialPenaltyRulePolicy::normalizeUuid($row['revision_uuid'] ?? null, 'revision UUID');
            $ruleUuid = ContractFinancialPenaltyRulePolicy::normalizeUuid($row['penalty_rule_uuid'] ?? null, 'penalty rule UUID');
            $label = ContractFinancialPenaltyRulePolicy::normalizeLabel($row['label'] ?? null);
            $mode = ContractFinancialPenaltyRulePolicy::normalizeMode($row['calculation_mode'] ?? null);
            $currency = $this->currencyFromStorage($row['currency_code'] ?? null);
            $value = $this->canonicalValue($row['configured_value'] ?? null, $mode, $currency);
            $state = ContractFinancialPenaltyRulePolicy::normalizeState($row['penalty_rule_state'] ?? null);
        } catch (Throwable $error) {
            throw new UnexpectedValueException('Enterprise Contract penalty rule revision contains invalid persisted data.', 0, $error);
        }
        if ($expectedRuleUuid !== null && $ruleUuid !== $expectedRuleUuid) {
            throw new UnexpectedValueException('Enterprise Contract penalty rule lookup returned a different rule identity.');
        }

        $row['id'] = $id;
        $row['revision_uuid'] = $revisionUuid;
        $row['contract_id'] = $contractId;
        $row['financial_currency_profile_id'] = $profileId;
        $row['penalty_rule_uuid'] = $ruleUuid;
        $row['revision_number'] = $revisionNumber;
        $row['label'] = $label;
        $row['calculation_mode'] = $mode;
        $row['configured_value'] = $value;
        $row['currency_code'] = $currency->value();
        $row['penalty_rule_state'] = $state;
        $row['created_by'] = $createdBy;
        return $row;
    }

    private function currencyFromStorage(mixed $value): CurrencyCode
    {
        try {
            return CurrencyCode::from($value);
        } catch (Throwable $error) {
            throw new UnexpectedValueException('Enterprise Contract penalty rule currency is invalid.', 0, $error);
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
            throw new RuntimeException('Enterprise Contract penalty rule access requires core tenant enforcement.');
        }
        return TenantContextStore::context()->requireTenantId();
    }
}
