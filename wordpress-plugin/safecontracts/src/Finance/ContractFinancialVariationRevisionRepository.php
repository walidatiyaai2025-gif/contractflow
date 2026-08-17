<?php

declare(strict_types=1);

namespace SafeContracts\Finance;

use InvalidArgumentException;
use RuntimeException;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;
use Throwable;
use UnexpectedValueException;

final class ContractFinancialVariationRevisionRepository
{
    private const SELECT_COLUMNS = 'id, revision_uuid, contract_id, financial_currency_profile_id, variation_uuid, revision_number, variation_direction, description, amount, currency_code, variation_state, created_by, created_at';

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
        $revisions = $wpdb->prefix . 'safecontracts_contract_financial_variation_revisions';
        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Enterprise financial variation read transaction.');
        }

        try {
            $this->lockContract($contractId, $authorizeLockedContract, false);
            $profile = $this->lockProfile($contractId);
            $limit = ContractFinancialVariationPolicy::MAX_VARIATIONS + 1;
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT " . self::SELECT_COLUMNS . " FROM {$revisions} r
                 WHERE r.tenant_id = %d AND r.contract_id = %d
                   AND NOT EXISTS (
                        SELECT 1 FROM {$revisions} newer
                        WHERE newer.tenant_id = r.tenant_id
                          AND newer.contract_id = r.contract_id
                          AND newer.variation_uuid = r.variation_uuid
                          AND (
                               newer.revision_number > r.revision_number
                               OR (newer.revision_number = r.revision_number AND newer.id > r.id)
                          )
                   )
                 ORDER BY r.variation_uuid ASC
                 LIMIT %d",
                $tenantId,
                $contractId,
                $limit
            ), ARRAY_A);
            if (! is_array($rows)) {
                throw new RuntimeException('Enterprise Contract financial variation list could not be read.');
            }
            if (count($rows) > ContractFinancialVariationPolicy::MAX_VARIATIONS) {
                throw new RuntimeException('Enterprise Contract financial variation limit was exceeded.');
            }

            $normalized = [];
            $seen = [];
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    throw new UnexpectedValueException('Enterprise Contract financial variation row is invalid.');
                }
                $revision = $this->normalizeRevision($row, $contractId);
                $variationUuid = (string) $revision['variation_uuid'];
                if (isset($seen[$variationUuid])) {
                    throw new UnexpectedValueException('Enterprise Contract financial variation list contains duplicate latest identities.');
                }
                $seen[$variationUuid] = true;
                $this->assertRevisionProfile($revision, $profile);
                $normalized[] = $revision;
            }

            $this->commit('Enterprise financial variation read');
            return $normalized;
        } catch (Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    /** @param callable(array<string,mixed>):void $authorizeLockedContract */
    public function createVariation(
        int $contractId,
        string $variationUuid,
        string $revisionUuid,
        string $direction,
        string $description,
        mixed $amount,
        int $actorId,
        callable $authorizeLockedContract
    ): int {
        $variationUuid = ContractFinancialVariationPolicy::normalizeUuid($variationUuid, 'variation UUID');
        $revisionUuid = ContractFinancialVariationPolicy::normalizeUuid($revisionUuid, 'revision UUID');
        $direction = ContractFinancialVariationPolicy::normalizeDirection($direction);
        $description = ContractFinancialVariationPolicy::normalizeDescription($description);
        $this->assertActor($actorId);

        global $wpdb;
        $tenantId = $this->tenantId();
        $revisions = $wpdb->prefix . 'safecontracts_contract_financial_variation_revisions';
        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Enterprise financial variation creation transaction.');
        }

        try {
            $this->lockContract($contractId, $authorizeLockedContract, true);
            $profile = $this->lockProfile($contractId);
            $money = $this->moneyForProfile($amount, $profile['currency']);

            $countRows = $wpdb->get_results($wpdb->prepare(
                "SELECT COUNT(DISTINCT variation_uuid) AS total FROM {$revisions} WHERE tenant_id = %d AND contract_id = %d",
                $tenantId,
                $contractId
            ), ARRAY_A);
            if (! is_array($countRows) || count($countRows) !== 1 || ! is_array($countRows[0])) {
                throw new RuntimeException('Enterprise financial variation count returned an invalid cardinality.');
            }
            $count = (int) ($countRows[0]['total'] ?? -1);
            if ($count < 0 || $count >= ContractFinancialVariationPolicy::MAX_VARIATIONS) {
                throw new RuntimeException('Enterprise Contract financial variation limit has been reached.');
            }

            $collisionRows = $wpdb->get_results($wpdb->prepare(
                "SELECT id FROM {$revisions} WHERE tenant_id = %d AND contract_id = %d AND variation_uuid = %s LIMIT 2 FOR UPDATE",
                $tenantId,
                $contractId,
                $variationUuid
            ), ARRAY_A);
            if (! is_array($collisionRows) || $collisionRows !== []) {
                throw new RuntimeException('Enterprise financial variation identity already exists or could not be verified.');
            }

            $id = $this->insertRevision(
                $contractId,
                (int) $profile['id'],
                $variationUuid,
                $revisionUuid,
                1,
                $direction,
                $description,
                $money,
                ContractFinancialVariationPolicy::STATE_PROPOSED,
                $actorId
            );
            $this->commit('Enterprise financial variation creation');
            return $id;
        } catch (Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    /** @param callable(array<string,mixed>):void $authorizeLockedContract */
    public function reviseVariation(
        int $contractId,
        string $variationUuid,
        string $revisionUuid,
        string $direction,
        string $description,
        mixed $amount,
        int $actorId,
        callable $authorizeLockedContract
    ): int {
        $variationUuid = ContractFinancialVariationPolicy::normalizeUuid($variationUuid, 'variation UUID');
        $revisionUuid = ContractFinancialVariationPolicy::normalizeUuid($revisionUuid, 'revision UUID');
        $direction = ContractFinancialVariationPolicy::normalizeDirection($direction);
        $description = ContractFinancialVariationPolicy::normalizeDescription($description);
        $this->assertActor($actorId);

        global $wpdb;
        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Enterprise financial variation revision transaction.');
        }

        try {
            $this->lockContract($contractId, $authorizeLockedContract, true);
            $profile = $this->lockProfile($contractId);
            $latest = $this->lockLatestVariation($contractId, $variationUuid);
            $this->assertRevisionProfile($latest, $profile);
            if ((string) $latest['variation_state'] !== ContractFinancialVariationPolicy::STATE_PROPOSED) {
                throw new RuntimeException('Voided Enterprise financial variations cannot be revised or reactivated.');
            }
            $money = $this->moneyForProfile($amount, $profile['currency']);
            $storedMoney = Money::of((string) $latest['amount'], (string) $latest['currency_code']);
            if ((string) $latest['variation_direction'] === $direction
                && (string) $latest['description'] === $description
                && $storedMoney->equals($money)) {
                $this->commit('idempotent Enterprise financial variation revision');
                return (int) $latest['id'];
            }

            $id = $this->insertRevision(
                $contractId,
                (int) $profile['id'],
                $variationUuid,
                $revisionUuid,
                $this->nextRevisionNumber((int) $latest['revision_number']),
                $direction,
                $description,
                $money,
                ContractFinancialVariationPolicy::STATE_PROPOSED,
                $actorId
            );
            $this->commit('Enterprise financial variation revision');
            return $id;
        } catch (Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    /** @param callable(array<string,mixed>):void $authorizeLockedContract */
    public function voidVariation(
        int $contractId,
        string $variationUuid,
        string $revisionUuid,
        int $actorId,
        callable $authorizeLockedContract
    ): int {
        $variationUuid = ContractFinancialVariationPolicy::normalizeUuid($variationUuid, 'variation UUID');
        $revisionUuid = ContractFinancialVariationPolicy::normalizeUuid($revisionUuid, 'revision UUID');
        $this->assertActor($actorId);

        global $wpdb;
        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Enterprise financial variation void transaction.');
        }

        try {
            $this->lockContract($contractId, $authorizeLockedContract, true);
            $profile = $this->lockProfile($contractId);
            $latest = $this->lockLatestVariation($contractId, $variationUuid);
            $this->assertRevisionProfile($latest, $profile);
            if ((string) $latest['variation_state'] === ContractFinancialVariationPolicy::STATE_VOIDED) {
                $this->commit('idempotent Enterprise financial variation void');
                return (int) $latest['id'];
            }

            $money = Money::of((string) $latest['amount'], (string) $latest['currency_code']);
            $id = $this->insertRevision(
                $contractId,
                (int) $profile['id'],
                $variationUuid,
                $revisionUuid,
                $this->nextRevisionNumber((int) $latest['revision_number']),
                (string) $latest['variation_direction'],
                (string) $latest['description'],
                $money,
                ContractFinancialVariationPolicy::STATE_VOIDED,
                $actorId
            );
            $this->commit('Enterprise financial variation void');
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

        // Authorization deliberately occurs against the exact row protected by the Contract lock,
        // before profile or variation financial state is observed.
        $authorizeLockedContract($contract);

        if ($requireActive
            && ((int) ($contract['is_archived'] ?? 0) !== 0 || (string) ($contract['status'] ?? '') !== 'active')) {
            throw new RuntimeException('Enterprise financial variations may only change while the Contract is unarchived and active.');
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
        $currency = $this->currencyFromStorage($rows[0]['contract_currency'] ?? null, 'financial profile currency');
        if ($profileId <= 0 || $profileContractId !== $contractId) {
            throw new UnexpectedValueException('Enterprise Contract financial profile identity is invalid.');
        }
        return ['id' => $profileId, 'currency' => $currency];
    }

    /** @return array<string,mixed> */
    private function lockLatestVariation(int $contractId, string $variationUuid): array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $revisions = $wpdb->prefix . 'safecontracts_contract_financial_variation_revisions';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT " . self::SELECT_COLUMNS . " FROM {$revisions}
             WHERE tenant_id = %d AND contract_id = %d AND variation_uuid = %s
             ORDER BY revision_number DESC, id DESC LIMIT 1 FOR UPDATE",
            $tenantId,
            $contractId,
            $variationUuid
        ), ARRAY_A);
        if (! is_array($rows) || count($rows) !== 1 || ! is_array($rows[0])) {
            throw new RuntimeException('Enterprise financial variation was not found in the current Contract or has invalid cardinality.');
        }
        return $this->normalizeRevision($rows[0], $contractId, $variationUuid);
    }

    /** @param array<string,mixed> $revision @param array{id:int,currency:CurrencyCode} $profile */
    private function assertRevisionProfile(array $revision, array $profile): void
    {
        if ((int) $revision['financial_currency_profile_id'] !== (int) $profile['id']) {
            throw new UnexpectedValueException('Enterprise financial variation revision references a different financial profile.');
        }
        $currency = CurrencyCode::from((string) $revision['currency_code']);
        if (! $currency->equals($profile['currency'])) {
            throw new UnexpectedValueException('Enterprise financial variation revision currency differs from the financial profile.');
        }
    }

    private function moneyForProfile(mixed $amount, CurrencyCode $currency): Money
    {
        try {
            $money = Money::of($amount, $currency);
        } catch (Throwable $error) {
            throw new InvalidArgumentException('Enterprise financial variation amount is invalid.', 0, $error);
        }
        if ($money->compare(Money::of('0', $currency)) < 0) {
            throw new InvalidArgumentException('Enterprise financial variation amount cannot be negative.');
        }
        return $money;
    }

    private function insertRevision(
        int $contractId,
        int $profileId,
        string $variationUuid,
        string $revisionUuid,
        int $revisionNumber,
        string $direction,
        string $description,
        Money $money,
        string $state,
        int $actorId
    ): int {
        global $wpdb;
        $tenantId = $this->tenantId();
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $profiles = $wpdb->prefix . 'safecontracts_contract_financial_currency_profiles';
        $revisions = $wpdb->prefix . 'safecontracts_contract_financial_variation_revisions';
        $state = ContractFinancialVariationPolicy::normalizeState($state);

        $inserted = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$revisions} (
                tenant_id, revision_uuid, contract_id, financial_currency_profile_id, variation_uuid,
                revision_number, variation_direction, description, amount, currency_code, variation_state,
                created_by, created_at
             ) SELECT %d, %s, c.id, p.id, %s, %d, %s, %s, %s, p.contract_currency, %s, %d, UTC_TIMESTAMP()
             FROM {$contracts} c
             INNER JOIN {$profiles} p ON p.tenant_id = c.tenant_id AND p.contract_id = c.id
             WHERE c.id = %d AND c.tenant_id = %d AND c.status = 'active' AND c.is_archived = 0
               AND p.id = %d AND p.contract_currency = %s
             LIMIT 1",
            $tenantId,
            $revisionUuid,
            $variationUuid,
            $revisionNumber,
            $direction,
            $description,
            $money->amount(),
            $state,
            $actorId,
            $contractId,
            $tenantId,
            $profileId,
            $money->currencyCode()
        ));
        if ($inserted !== 1) {
            throw new RuntimeException('Unable to append Enterprise Contract financial variation revision.');
        }
        $id = (int) ($wpdb->insert_id ?? 0);
        if ($id <= 0) {
            throw new RuntimeException('Enterprise financial variation revision insert returned no identifier.');
        }
        return $id;
    }

    private function nextRevisionNumber(int $current): int
    {
        if ($current <= 0 || $current >= ContractFinancialVariationPolicy::MAX_REVISION) {
            throw new RuntimeException('Enterprise financial variation revision limit has been reached.');
        }
        return $current + 1;
    }

    private function assertActor(int $actorId): void
    {
        if ($actorId <= 0) {
            throw new InvalidArgumentException('Enterprise financial variation mutation requires an authenticated actor.');
        }
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function normalizeRevision(array $row, int $expectedContractId, ?string $expectedVariationUuid = null): array
    {
        $id = (int) ($row['id'] ?? 0);
        $contractId = (int) ($row['contract_id'] ?? 0);
        $profileId = (int) ($row['financial_currency_profile_id'] ?? 0);
        $revisionNumber = (int) ($row['revision_number'] ?? 0);
        $createdBy = (int) ($row['created_by'] ?? 0);
        if ($id <= 0 || $contractId !== $expectedContractId || $profileId <= 0
            || $revisionNumber <= 0 || $revisionNumber > ContractFinancialVariationPolicy::MAX_REVISION
            || $createdBy <= 0) {
            throw new UnexpectedValueException('Enterprise financial variation revision has invalid identity or revision metadata.');
        }

        try {
            $revisionUuid = ContractFinancialVariationPolicy::normalizeUuid($row['revision_uuid'] ?? null, 'revision UUID');
            $variationUuid = ContractFinancialVariationPolicy::normalizeUuid($row['variation_uuid'] ?? null, 'variation UUID');
            $direction = ContractFinancialVariationPolicy::normalizeDirection($row['variation_direction'] ?? null);
            $description = ContractFinancialVariationPolicy::normalizeDescription($row['description'] ?? null);
            $state = ContractFinancialVariationPolicy::normalizeState($row['variation_state'] ?? null);
            $money = Money::of($row['amount'] ?? null, $this->currencyFromStorage($row['currency_code'] ?? null, 'revision currency'));
        } catch (Throwable $error) {
            throw new UnexpectedValueException('Enterprise financial variation revision contains invalid persisted data.', 0, $error);
        }
        if ($expectedVariationUuid !== null && $variationUuid !== $expectedVariationUuid) {
            throw new UnexpectedValueException('Enterprise financial variation lookup returned a different variation identity.');
        }
        if ($money->compare(Money::of('0', $money->currency())) < 0) {
            throw new UnexpectedValueException('Stored Enterprise financial variation amount cannot be negative.');
        }

        $row['id'] = $id;
        $row['revision_uuid'] = $revisionUuid;
        $row['contract_id'] = $contractId;
        $row['financial_currency_profile_id'] = $profileId;
        $row['variation_uuid'] = $variationUuid;
        $row['revision_number'] = $revisionNumber;
        $row['variation_direction'] = $direction;
        $row['description'] = $description;
        $row['amount'] = $money->amount();
        $row['currency_code'] = $money->currencyCode();
        $row['variation_state'] = $state;
        $row['created_by'] = $createdBy;
        return $row;
    }

    private function currencyFromStorage(mixed $value, string $field): CurrencyCode
    {
        try {
            return CurrencyCode::from($value);
        } catch (Throwable $error) {
            throw new UnexpectedValueException("Enterprise financial variation {$field} is invalid.", 0, $error);
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
            throw new RuntimeException('Enterprise financial variation access requires core tenant enforcement.');
        }
        return TenantContextStore::context()->requireTenantId();
    }
}
