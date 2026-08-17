<?php

declare(strict_types=1);

namespace SafeContracts\Finance;

use InvalidArgumentException;
use RuntimeException;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;
use Throwable;
use UnexpectedValueException;

final class ContractFinancialCreditRevisionRepository
{
    private const SELECT_COLUMNS = 'id, revision_uuid, contract_id, financial_currency_profile_id, credit_uuid, revision_number, reason, amount, currency_code, credit_state, created_by, created_at';

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
        $revisions = $wpdb->prefix . 'safecontracts_contract_financial_credit_revisions';
        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Enterprise Contract credit read transaction.');
        }

        try {
            $this->lockContract($contractId, $authorizeLockedContract, false);
            $profile = $this->lockProfile($contractId);
            $limit = ContractFinancialCreditPolicy::MAX_CREDITS + 1;
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT " . self::SELECT_COLUMNS . " FROM {$revisions} r
                 WHERE r.tenant_id = %d AND r.contract_id = %d
                   AND NOT EXISTS (
                        SELECT 1 FROM {$revisions} newer
                        WHERE newer.tenant_id = r.tenant_id
                          AND newer.contract_id = r.contract_id
                          AND newer.credit_uuid = r.credit_uuid
                          AND (
                               newer.revision_number > r.revision_number
                               OR (newer.revision_number = r.revision_number AND newer.id > r.id)
                          )
                   )
                 ORDER BY r.credit_uuid ASC
                 LIMIT %d",
                $tenantId,
                $contractId,
                $limit
            ), ARRAY_A);
            if (! is_array($rows)) {
                throw new RuntimeException('Enterprise Contract credit list could not be read.');
            }
            if (count($rows) > ContractFinancialCreditPolicy::MAX_CREDITS) {
                throw new RuntimeException('Enterprise Contract credit limit was exceeded.');
            }

            $normalized = [];
            $seen = [];
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    throw new UnexpectedValueException('Enterprise Contract credit row is invalid.');
                }
                $revision = $this->normalizeRevision($row, $contractId);
                $creditUuid = (string) $revision['credit_uuid'];
                if (isset($seen[$creditUuid])) {
                    throw new UnexpectedValueException('Enterprise Contract credit list contains duplicate latest identities.');
                }
                $seen[$creditUuid] = true;
                $this->assertRevisionProfile($revision, $profile);
                $normalized[] = $revision;
            }

            $this->commit('Enterprise Contract credit read');
            return $normalized;
        } catch (Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    /** @param callable(array<string,mixed>):void $authorizeLockedContract */
    public function createCredit(
        int $contractId,
        string $creditUuid,
        string $revisionUuid,
        string $reason,
        mixed $amount,
        int $actorId,
        callable $authorizeLockedContract
    ): int {
        $creditUuid = ContractFinancialCreditPolicy::normalizeUuid($creditUuid, 'credit UUID');
        $revisionUuid = ContractFinancialCreditPolicy::normalizeUuid($revisionUuid, 'revision UUID');
        $reason = ContractFinancialCreditPolicy::normalizeReason($reason);
        $this->assertActor($actorId);

        global $wpdb;
        $tenantId = $this->tenantId();
        $revisions = $wpdb->prefix . 'safecontracts_contract_financial_credit_revisions';
        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Enterprise Contract credit creation transaction.');
        }

        try {
            $this->lockContract($contractId, $authorizeLockedContract, true);
            $profile = $this->lockProfile($contractId);
            $money = $this->creditMoney($amount, $profile['currency']);

            $countRows = $wpdb->get_results($wpdb->prepare(
                "SELECT COUNT(DISTINCT credit_uuid) AS total FROM {$revisions} WHERE tenant_id = %d AND contract_id = %d",
                $tenantId,
                $contractId
            ), ARRAY_A);
            if (! is_array($countRows) || count($countRows) !== 1 || ! is_array($countRows[0])) {
                throw new RuntimeException('Enterprise Contract credit count returned an invalid cardinality.');
            }
            $count = (int) ($countRows[0]['total'] ?? -1);
            if ($count < 0 || $count >= ContractFinancialCreditPolicy::MAX_CREDITS) {
                throw new RuntimeException('Enterprise Contract credit limit has been reached.');
            }

            $collisionRows = $wpdb->get_results($wpdb->prepare(
                "SELECT id FROM {$revisions} WHERE tenant_id = %d AND contract_id = %d AND credit_uuid = %s LIMIT 2 FOR UPDATE",
                $tenantId,
                $contractId,
                $creditUuid
            ), ARRAY_A);
            if (! is_array($collisionRows) || $collisionRows !== []) {
                throw new RuntimeException('Enterprise Contract credit identity already exists or could not be verified.');
            }

            $id = $this->insertRevision($contractId, (int) $profile['id'], $creditUuid, $revisionUuid, 1, $reason, $money, ContractFinancialCreditPolicy::STATE_PROPOSED, $actorId);
            $this->commit('Enterprise Contract credit creation');
            return $id;
        } catch (Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    /** @param callable(array<string,mixed>):void $authorizeLockedContract */
    public function reviseCredit(
        int $contractId,
        string $creditUuid,
        string $revisionUuid,
        string $reason,
        mixed $amount,
        int $actorId,
        callable $authorizeLockedContract
    ): int {
        $creditUuid = ContractFinancialCreditPolicy::normalizeUuid($creditUuid, 'credit UUID');
        $revisionUuid = ContractFinancialCreditPolicy::normalizeUuid($revisionUuid, 'revision UUID');
        $reason = ContractFinancialCreditPolicy::normalizeReason($reason);
        $this->assertActor($actorId);

        global $wpdb;
        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Enterprise Contract credit revision transaction.');
        }

        try {
            $this->lockContract($contractId, $authorizeLockedContract, true);
            $profile = $this->lockProfile($contractId);
            $latest = $this->lockLatestCredit($contractId, $creditUuid);
            $this->assertRevisionProfile($latest, $profile);
            if ((string) $latest['credit_state'] !== ContractFinancialCreditPolicy::STATE_PROPOSED) {
                throw new RuntimeException('Voided Enterprise Contract credits cannot be revised or reactivated.');
            }

            $money = $this->creditMoney($amount, $profile['currency']);
            if ((string) $latest['reason'] === $reason
                && (string) $latest['amount'] === $money->amount()
                && (string) $latest['currency_code'] === $money->currencyCode()) {
                $this->commit('idempotent Enterprise Contract credit revision');
                return (int) $latest['id'];
            }

            $id = $this->insertRevision(
                $contractId,
                (int) $profile['id'],
                $creditUuid,
                $revisionUuid,
                $this->nextRevisionNumber((int) $latest['revision_number']),
                $reason,
                $money,
                ContractFinancialCreditPolicy::STATE_PROPOSED,
                $actorId
            );
            $this->commit('Enterprise Contract credit revision');
            return $id;
        } catch (Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    /** @param callable(array<string,mixed>):void $authorizeLockedContract */
    public function voidCredit(int $contractId, string $creditUuid, string $revisionUuid, int $actorId, callable $authorizeLockedContract): int
    {
        $creditUuid = ContractFinancialCreditPolicy::normalizeUuid($creditUuid, 'credit UUID');
        $revisionUuid = ContractFinancialCreditPolicy::normalizeUuid($revisionUuid, 'revision UUID');
        $this->assertActor($actorId);

        global $wpdb;
        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Enterprise Contract credit void transaction.');
        }

        try {
            $this->lockContract($contractId, $authorizeLockedContract, true);
            $profile = $this->lockProfile($contractId);
            $latest = $this->lockLatestCredit($contractId, $creditUuid);
            $this->assertRevisionProfile($latest, $profile);
            if ((string) $latest['credit_state'] === ContractFinancialCreditPolicy::STATE_VOIDED) {
                $this->commit('idempotent Enterprise Contract credit void');
                return (int) $latest['id'];
            }

            $money = Money::of((string) $latest['amount'], $profile['currency']);
            $id = $this->insertRevision(
                $contractId,
                (int) $profile['id'],
                $creditUuid,
                $revisionUuid,
                $this->nextRevisionNumber((int) $latest['revision_number']),
                (string) $latest['reason'],
                $money,
                ContractFinancialCreditPolicy::STATE_VOIDED,
                $actorId
            );
            $this->commit('Enterprise Contract credit void');
            return $id;
        } catch (Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    /** @param callable(array<string,mixed>):void $authorizeLockedContract @return array<string,mixed> */
    private function lockContract(int $contractId, callable $authorizeLockedContract, bool $requireActive): array
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
        if ($requireActive && ((int) ($contract['is_archived'] ?? 0) !== 0 || (string) ($contract['status'] ?? '') !== 'active')) {
            throw new RuntimeException('Enterprise Contract credits may only change while the Contract is unarchived and active.');
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
        $currency = $this->currencyFromStorage($rows[0]['contract_currency'] ?? null);
        if ($profileId <= 0 || (int) ($rows[0]['contract_id'] ?? 0) !== $contractId) {
            throw new UnexpectedValueException('Enterprise Contract financial profile identity is invalid.');
        }
        return ['id' => $profileId, 'currency' => $currency];
    }

    /** @return array<string,mixed> */
    private function lockLatestCredit(int $contractId, string $creditUuid): array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $revisions = $wpdb->prefix . 'safecontracts_contract_financial_credit_revisions';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT " . self::SELECT_COLUMNS . " FROM {$revisions}
             WHERE tenant_id = %d AND contract_id = %d AND credit_uuid = %s
             ORDER BY revision_number DESC, id DESC LIMIT 1 FOR UPDATE",
            $tenantId,
            $contractId,
            $creditUuid
        ), ARRAY_A);
        if (! is_array($rows) || count($rows) !== 1 || ! is_array($rows[0])) {
            throw new RuntimeException('Enterprise Contract credit was not found or has invalid cardinality.');
        }
        return $this->normalizeRevision($rows[0], $contractId, $creditUuid);
    }

    /** @param array<string,mixed> $revision @param array{id:int,currency:CurrencyCode} $profile */
    private function assertRevisionProfile(array $revision, array $profile): void
    {
        if ((int) $revision['financial_currency_profile_id'] !== (int) $profile['id']) {
            throw new UnexpectedValueException('Enterprise Contract credit revision references a different financial profile.');
        }
        if ((string) $revision['currency_code'] !== $profile['currency']->value()) {
            throw new UnexpectedValueException('Enterprise Contract credit revision currency differs from the financial profile.');
        }
    }

    private function creditMoney(mixed $amount, CurrencyCode $currency): Money
    {
        $money = Money::of($amount, $currency);
        if ($money->compare(Money::of('0', $currency)) < 0) {
            throw new InvalidArgumentException('Enterprise Contract credit amount cannot be negative.');
        }
        return $money;
    }

    private function insertRevision(int $contractId, int $profileId, string $creditUuid, string $revisionUuid, int $revisionNumber, string $reason, Money $money, string $state, int $actorId): int
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $profiles = $wpdb->prefix . 'safecontracts_contract_financial_currency_profiles';
        $revisions = $wpdb->prefix . 'safecontracts_contract_financial_credit_revisions';
        $state = ContractFinancialCreditPolicy::normalizeState($state);

        $inserted = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$revisions} (
                tenant_id, revision_uuid, contract_id, financial_currency_profile_id, credit_uuid,
                revision_number, reason, amount, currency_code, credit_state, created_by, created_at
             ) SELECT %d, %s, c.id, p.id, %s, %d, %s, %s, p.contract_currency, %s, %d, UTC_TIMESTAMP()
             FROM {$contracts} c
             INNER JOIN {$profiles} p ON p.tenant_id = c.tenant_id AND p.contract_id = c.id
             WHERE c.id = %d AND c.tenant_id = %d AND c.status = 'active' AND c.is_archived = 0
               AND p.id = %d AND p.contract_currency = %s
             LIMIT 1",
            $tenantId, $revisionUuid, $creditUuid, $revisionNumber, $reason, $money->amount(), $state, $actorId,
            $contractId, $tenantId, $profileId, $money->currencyCode()
        ));
        if ($inserted !== 1) {
            throw new RuntimeException('Unable to append Enterprise Contract credit revision.');
        }
        $id = (int) ($wpdb->insert_id ?? 0);
        if ($id <= 0) {
            throw new RuntimeException('Enterprise Contract credit revision insert returned no identifier.');
        }
        return $id;
    }

    private function nextRevisionNumber(int $current): int
    {
        if ($current <= 0 || $current >= ContractFinancialCreditPolicy::MAX_REVISION) {
            throw new RuntimeException('Enterprise Contract credit revision limit has been reached.');
        }
        return $current + 1;
    }

    private function assertActor(int $actorId): void
    {
        if ($actorId <= 0) {
            throw new InvalidArgumentException('Enterprise Contract credit mutation requires an authenticated actor.');
        }
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function normalizeRevision(array $row, int $expectedContractId, ?string $expectedCreditUuid = null): array
    {
        $id = (int) ($row['id'] ?? 0);
        $contractId = (int) ($row['contract_id'] ?? 0);
        $profileId = (int) ($row['financial_currency_profile_id'] ?? 0);
        $revisionNumber = (int) ($row['revision_number'] ?? 0);
        $createdBy = (int) ($row['created_by'] ?? 0);
        if ($id <= 0 || $contractId !== $expectedContractId || $profileId <= 0 || $revisionNumber <= 0 || $revisionNumber > ContractFinancialCreditPolicy::MAX_REVISION || $createdBy <= 0) {
            throw new UnexpectedValueException('Enterprise Contract credit revision has invalid identity or revision metadata.');
        }

        try {
            $revisionUuid = ContractFinancialCreditPolicy::normalizeUuid($row['revision_uuid'] ?? null, 'revision UUID');
            $creditUuid = ContractFinancialCreditPolicy::normalizeUuid($row['credit_uuid'] ?? null, 'credit UUID');
            $reason = ContractFinancialCreditPolicy::normalizeReason($row['reason'] ?? null);
            $currency = $this->currencyFromStorage($row['currency_code'] ?? null);
            $money = $this->creditMoney($row['amount'] ?? null, $currency);
            $state = ContractFinancialCreditPolicy::normalizeState($row['credit_state'] ?? null);
        } catch (Throwable $error) {
            throw new UnexpectedValueException('Enterprise Contract credit revision contains invalid persisted data.', 0, $error);
        }
        if ($expectedCreditUuid !== null && $creditUuid !== $expectedCreditUuid) {
            throw new UnexpectedValueException('Enterprise Contract credit lookup returned a different credit identity.');
        }

        $row['id'] = $id;
        $row['revision_uuid'] = $revisionUuid;
        $row['contract_id'] = $contractId;
        $row['financial_currency_profile_id'] = $profileId;
        $row['credit_uuid'] = $creditUuid;
        $row['revision_number'] = $revisionNumber;
        $row['reason'] = $reason;
        $row['amount'] = $money->amount();
        $row['currency_code'] = $money->currencyCode();
        $row['credit_state'] = $state;
        $row['created_by'] = $createdBy;
        return $row;
    }

    private function currencyFromStorage(mixed $value): CurrencyCode
    {
        try {
            return CurrencyCode::from($value);
        } catch (Throwable $error) {
            throw new UnexpectedValueException('Enterprise Contract credit currency is invalid.', 0, $error);
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
            throw new RuntimeException('Enterprise Contract credit access requires core tenant enforcement.');
        }
        return TenantContextStore::context()->requireTenantId();
    }
}
