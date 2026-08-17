<?php

declare(strict_types=1);

namespace SafeContracts\Finance;

use InvalidArgumentException;
use RuntimeException;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;
use Throwable;
use UnexpectedValueException;

final class ContractFinancialPaymentScheduleRevisionRepository
{
    private const SELECT_COLUMNS = 'id, revision_uuid, contract_id, financial_currency_profile_id, schedule_entry_uuid, revision_number, sequence_no, reference, due_date, amount, currency_code, schedule_entry_state, created_by, created_at';

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
        $revisions = $wpdb->prefix . 'safecontracts_contract_financial_payment_schedule_entry_revisions';
        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Enterprise Contract payment schedule read transaction.');
        }

        try {
            $this->lockContract($contractId, $authorizeLockedContract, false);
            $profile = $this->lockProfile($contractId);
            $limit = ContractFinancialPaymentSchedulePolicy::MAX_ENTRIES + 1;
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT " . self::SELECT_COLUMNS . " FROM {$revisions} r
                 WHERE r.tenant_id = %d AND r.contract_id = %d
                   AND NOT EXISTS (
                        SELECT 1 FROM {$revisions} newer
                        WHERE newer.tenant_id = r.tenant_id
                          AND newer.contract_id = r.contract_id
                          AND newer.schedule_entry_uuid = r.schedule_entry_uuid
                          AND (
                               newer.revision_number > r.revision_number
                               OR (newer.revision_number = r.revision_number AND newer.id > r.id)
                          )
                   )
                 ORDER BY r.sequence_no ASC, r.schedule_entry_uuid ASC
                 LIMIT %d",
                $tenantId,
                $contractId,
                $limit
            ), ARRAY_A);
            if (! is_array($rows)) {
                throw new RuntimeException('Enterprise Contract payment schedule could not be read.');
            }
            if (count($rows) > ContractFinancialPaymentSchedulePolicy::MAX_ENTRIES) {
                throw new RuntimeException('Enterprise Contract payment schedule entry limit was exceeded.');
            }

            $normalized = [];
            $seenIdentities = [];
            $seenSequences = [];
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    throw new UnexpectedValueException('Enterprise Contract payment schedule row is invalid.');
                }
                $revision = $this->normalizeRevision($row, $contractId);
                $entryUuid = (string) $revision['schedule_entry_uuid'];
                $sequence = (int) $revision['sequence_no'];
                if (isset($seenIdentities[$entryUuid])) {
                    throw new UnexpectedValueException('Enterprise Contract payment schedule contains duplicate latest identities.');
                }
                if (isset($seenSequences[$sequence])) {
                    throw new UnexpectedValueException('Enterprise Contract payment schedule contains duplicate latest sequence numbers.');
                }
                $seenIdentities[$entryUuid] = true;
                $seenSequences[$sequence] = true;
                $this->assertRevisionProfile($revision, $profile);
                $normalized[] = $revision;
            }

            $this->commit('Enterprise Contract payment schedule read');
            return $normalized;
        } catch (Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    /** @param callable(array<string,mixed>):void $authorizeLockedContract */
    public function createEntry(
        int $contractId,
        string $entryUuid,
        string $revisionUuid,
        mixed $sequenceNo,
        mixed $reference,
        mixed $dueDate,
        mixed $amount,
        int $actorId,
        callable $authorizeLockedContract
    ): int {
        $entryUuid = ContractFinancialPaymentSchedulePolicy::normalizeUuid($entryUuid, 'schedule entry UUID');
        $revisionUuid = ContractFinancialPaymentSchedulePolicy::normalizeUuid($revisionUuid, 'revision UUID');
        $sequence = ContractFinancialPaymentSchedulePolicy::normalizeSequence($sequenceNo);
        $normalizedReference = ContractFinancialPaymentSchedulePolicy::normalizeReference($reference);
        $normalizedDueDate = ContractFinancialPaymentSchedulePolicy::normalizeDueDate($dueDate);
        $this->assertActor($actorId);

        global $wpdb;
        $tenantId = $this->tenantId();
        $revisions = $wpdb->prefix . 'safecontracts_contract_financial_payment_schedule_entry_revisions';
        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Enterprise Contract payment schedule creation transaction.');
        }

        try {
            $this->lockContract($contractId, $authorizeLockedContract, true);
            $profile = $this->lockProfile($contractId);
            $money = $this->scheduleMoney($amount, $profile['currency']);

            $countRows = $wpdb->get_results($wpdb->prepare(
                "SELECT COUNT(DISTINCT schedule_entry_uuid) AS total FROM {$revisions} WHERE tenant_id = %d AND contract_id = %d",
                $tenantId,
                $contractId
            ), ARRAY_A);
            if (! is_array($countRows) || count($countRows) !== 1 || ! is_array($countRows[0])) {
                throw new RuntimeException('Enterprise Contract payment schedule count returned an invalid cardinality.');
            }
            $count = (int) ($countRows[0]['total'] ?? -1);
            if ($count < 0 || $count >= ContractFinancialPaymentSchedulePolicy::MAX_ENTRIES) {
                throw new RuntimeException('Enterprise Contract payment schedule entry limit has been reached.');
            }

            $identityRows = $wpdb->get_results($wpdb->prepare(
                "SELECT id FROM {$revisions} WHERE tenant_id = %d AND contract_id = %d AND schedule_entry_uuid = %s LIMIT 2 FOR UPDATE",
                $tenantId,
                $contractId,
                $entryUuid
            ), ARRAY_A);
            if (! is_array($identityRows) || $identityRows !== []) {
                throw new RuntimeException('Enterprise Contract payment schedule entry identity already exists or could not be verified.');
            }

            $sequenceRows = $wpdb->get_results($wpdb->prepare(
                "SELECT id, schedule_entry_uuid FROM {$revisions} WHERE tenant_id = %d AND contract_id = %d AND sequence_no = %d ORDER BY id ASC LIMIT 2 FOR UPDATE",
                $tenantId,
                $contractId,
                $sequence
            ), ARRAY_A);
            if (! is_array($sequenceRows) || $sequenceRows !== []) {
                throw new RuntimeException('Enterprise Contract payment schedule sequence has already been assigned and cannot be reused.');
            }

            $id = $this->insertRevision(
                $contractId,
                (int) $profile['id'],
                $entryUuid,
                $revisionUuid,
                1,
                $sequence,
                $normalizedReference,
                $normalizedDueDate,
                $money,
                ContractFinancialPaymentSchedulePolicy::STATE_SCHEDULED,
                $actorId
            );
            $this->commit('Enterprise Contract payment schedule creation');
            return $id;
        } catch (Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    /** @param callable(array<string,mixed>):void $authorizeLockedContract */
    public function reviseEntry(
        int $contractId,
        string $entryUuid,
        string $revisionUuid,
        mixed $reference,
        mixed $dueDate,
        mixed $amount,
        int $actorId,
        callable $authorizeLockedContract
    ): int {
        $entryUuid = ContractFinancialPaymentSchedulePolicy::normalizeUuid($entryUuid, 'schedule entry UUID');
        $revisionUuid = ContractFinancialPaymentSchedulePolicy::normalizeUuid($revisionUuid, 'revision UUID');
        $normalizedReference = ContractFinancialPaymentSchedulePolicy::normalizeReference($reference);
        $normalizedDueDate = ContractFinancialPaymentSchedulePolicy::normalizeDueDate($dueDate);
        $this->assertActor($actorId);

        global $wpdb;
        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Enterprise Contract payment schedule revision transaction.');
        }

        try {
            $this->lockContract($contractId, $authorizeLockedContract, true);
            $profile = $this->lockProfile($contractId);
            $latest = $this->lockLatestEntry($contractId, $entryUuid);
            $this->assertRevisionProfile($latest, $profile);
            if ((string) $latest['schedule_entry_state'] !== ContractFinancialPaymentSchedulePolicy::STATE_SCHEDULED) {
                throw new RuntimeException('Voided Enterprise Contract payment schedule entries cannot be revised or reactivated.');
            }

            $money = $this->scheduleMoney($amount, $profile['currency']);
            if ($latest['reference'] === $normalizedReference
                && (string) $latest['due_date'] === $normalizedDueDate
                && (string) $latest['amount'] === $money->amount()
                && (string) $latest['currency_code'] === $money->currencyCode()) {
                $this->commit('idempotent Enterprise Contract payment schedule revision');
                return (int) $latest['id'];
            }

            $id = $this->insertRevision(
                $contractId,
                (int) $profile['id'],
                $entryUuid,
                $revisionUuid,
                $this->nextRevisionNumber((int) $latest['revision_number']),
                (int) $latest['sequence_no'],
                $normalizedReference,
                $normalizedDueDate,
                $money,
                ContractFinancialPaymentSchedulePolicy::STATE_SCHEDULED,
                $actorId
            );
            $this->commit('Enterprise Contract payment schedule revision');
            return $id;
        } catch (Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    /** @param callable(array<string,mixed>):void $authorizeLockedContract */
    public function voidEntry(
        int $contractId,
        string $entryUuid,
        string $revisionUuid,
        int $actorId,
        callable $authorizeLockedContract
    ): int {
        $entryUuid = ContractFinancialPaymentSchedulePolicy::normalizeUuid($entryUuid, 'schedule entry UUID');
        $revisionUuid = ContractFinancialPaymentSchedulePolicy::normalizeUuid($revisionUuid, 'revision UUID');
        $this->assertActor($actorId);

        global $wpdb;
        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Enterprise Contract payment schedule void transaction.');
        }

        try {
            $this->lockContract($contractId, $authorizeLockedContract, true);
            $profile = $this->lockProfile($contractId);
            $latest = $this->lockLatestEntry($contractId, $entryUuid);
            $this->assertRevisionProfile($latest, $profile);
            if ((string) $latest['schedule_entry_state'] === ContractFinancialPaymentSchedulePolicy::STATE_VOIDED) {
                $this->commit('idempotent Enterprise Contract payment schedule void');
                return (int) $latest['id'];
            }

            $money = $this->scheduleMoney((string) $latest['amount'], $profile['currency']);
            $id = $this->insertRevision(
                $contractId,
                (int) $profile['id'],
                $entryUuid,
                $revisionUuid,
                $this->nextRevisionNumber((int) $latest['revision_number']),
                (int) $latest['sequence_no'],
                $latest['reference'],
                (string) $latest['due_date'],
                $money,
                ContractFinancialPaymentSchedulePolicy::STATE_VOIDED,
                $actorId
            );
            $this->commit('Enterprise Contract payment schedule void');
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
            throw new RuntimeException('Enterprise Contract payment schedule may only change while the Contract is unarchived and active.');
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
    private function lockLatestEntry(int $contractId, string $entryUuid): array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $revisions = $wpdb->prefix . 'safecontracts_contract_financial_payment_schedule_entry_revisions';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT " . self::SELECT_COLUMNS . " FROM {$revisions}
             WHERE tenant_id = %d AND contract_id = %d AND schedule_entry_uuid = %s
             ORDER BY revision_number DESC, id DESC LIMIT 1 FOR UPDATE",
            $tenantId,
            $contractId,
            $entryUuid
        ), ARRAY_A);
        if (! is_array($rows) || count($rows) !== 1 || ! is_array($rows[0])) {
            throw new RuntimeException('Enterprise Contract payment schedule entry was not found or has invalid cardinality.');
        }
        return $this->normalizeRevision($rows[0], $contractId, $entryUuid);
    }

    /** @param array<string,mixed> $revision @param array{id:int,currency:CurrencyCode} $profile */
    private function assertRevisionProfile(array $revision, array $profile): void
    {
        if ((int) $revision['financial_currency_profile_id'] !== (int) $profile['id']) {
            throw new UnexpectedValueException('Enterprise Contract payment schedule revision references a different financial profile.');
        }
        if ((string) $revision['currency_code'] !== $profile['currency']->value()) {
            throw new UnexpectedValueException('Enterprise Contract payment schedule revision currency differs from the financial profile.');
        }
    }

    private function scheduleMoney(mixed $amount, CurrencyCode $currency): Money
    {
        $money = Money::of($amount, $currency);
        if ($money->compare(Money::of('0', $currency)) <= 0) {
            throw new InvalidArgumentException('Enterprise Contract payment schedule amount must be greater than zero.');
        }
        return $money;
    }

    private function insertRevision(
        int $contractId,
        int $profileId,
        string $entryUuid,
        string $revisionUuid,
        int $revisionNumber,
        int $sequence,
        ?string $reference,
        string $dueDate,
        Money $money,
        string $state,
        int $actorId
    ): int {
        global $wpdb;
        $tenantId = $this->tenantId();
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $profiles = $wpdb->prefix . 'safecontracts_contract_financial_currency_profiles';
        $revisions = $wpdb->prefix . 'safecontracts_contract_financial_payment_schedule_entry_revisions';
        $state = ContractFinancialPaymentSchedulePolicy::normalizeState($state);
        $sequence = ContractFinancialPaymentSchedulePolicy::normalizeSequence($sequence);
        $reference = ContractFinancialPaymentSchedulePolicy::normalizeReference($reference);
        $dueDate = ContractFinancialPaymentSchedulePolicy::normalizeDueDate($dueDate);

        $referenceSql = $reference === null ? 'NULL' : '%s';
        $query = "INSERT INTO {$revisions} (
                tenant_id, revision_uuid, contract_id, financial_currency_profile_id, schedule_entry_uuid,
                revision_number, sequence_no, reference, due_date, amount, currency_code, schedule_entry_state, created_by, created_at
             ) SELECT %d, %s, c.id, p.id, %s, %d, %d, {$referenceSql}, %s, %s, p.contract_currency, %s, %d, UTC_TIMESTAMP()
             FROM {$contracts} c
             INNER JOIN {$profiles} p ON p.tenant_id = c.tenant_id AND p.contract_id = c.id
             WHERE c.id = %d AND c.tenant_id = %d AND c.status = 'active' AND c.is_archived = 0
               AND p.id = %d AND p.contract_currency = %s
             LIMIT 1";
        $args = [$tenantId, $revisionUuid, $entryUuid, $revisionNumber, $sequence];
        if ($reference !== null) {
            $args[] = $reference;
        }
        array_push(
            $args,
            $dueDate,
            $money->amount(),
            $state,
            $actorId,
            $contractId,
            $tenantId,
            $profileId,
            $money->currencyCode()
        );

        $inserted = $wpdb->query($wpdb->prepare($query, ...$args));
        if ($inserted !== 1) {
            throw new RuntimeException('Unable to append Enterprise Contract payment schedule revision.');
        }
        $id = (int) ($wpdb->insert_id ?? 0);
        if ($id <= 0) {
            throw new RuntimeException('Enterprise Contract payment schedule revision insert returned no identifier.');
        }
        return $id;
    }

    private function nextRevisionNumber(int $current): int
    {
        if ($current <= 0 || $current >= ContractFinancialPaymentSchedulePolicy::MAX_REVISION) {
            throw new RuntimeException('Enterprise Contract payment schedule revision limit has been reached.');
        }
        return $current + 1;
    }

    private function assertActor(int $actorId): void
    {
        if ($actorId <= 0) {
            throw new InvalidArgumentException('Enterprise Contract payment schedule mutation requires an authenticated actor.');
        }
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function normalizeRevision(array $row, int $expectedContractId, ?string $expectedEntryUuid = null): array
    {
        $id = (int) ($row['id'] ?? 0);
        $contractId = (int) ($row['contract_id'] ?? 0);
        $profileId = (int) ($row['financial_currency_profile_id'] ?? 0);
        $revisionNumber = (int) ($row['revision_number'] ?? 0);
        $createdBy = (int) ($row['created_by'] ?? 0);
        if ($id <= 0 || $contractId !== $expectedContractId || $profileId <= 0 || $revisionNumber <= 0 || $revisionNumber > ContractFinancialPaymentSchedulePolicy::MAX_REVISION || $createdBy <= 0) {
            throw new UnexpectedValueException('Enterprise Contract payment schedule revision has invalid identity or revision metadata.');
        }

        try {
            $revisionUuid = ContractFinancialPaymentSchedulePolicy::normalizeUuid($row['revision_uuid'] ?? null, 'revision UUID');
            $entryUuid = ContractFinancialPaymentSchedulePolicy::normalizeUuid($row['schedule_entry_uuid'] ?? null, 'schedule entry UUID');
            $sequence = ContractFinancialPaymentSchedulePolicy::normalizeSequence($row['sequence_no'] ?? null);
            $reference = ContractFinancialPaymentSchedulePolicy::normalizeReference($row['reference'] ?? null);
            $dueDate = ContractFinancialPaymentSchedulePolicy::normalizeDueDate($row['due_date'] ?? null);
            $currency = $this->currencyFromStorage($row['currency_code'] ?? null);
            $money = $this->scheduleMoney($row['amount'] ?? null, $currency);
            $state = ContractFinancialPaymentSchedulePolicy::normalizeState($row['schedule_entry_state'] ?? null);
        } catch (Throwable $error) {
            throw new UnexpectedValueException('Enterprise Contract payment schedule revision contains invalid persisted data.', 0, $error);
        }
        if ($expectedEntryUuid !== null && $entryUuid !== $expectedEntryUuid) {
            throw new UnexpectedValueException('Enterprise Contract payment schedule lookup returned a different entry identity.');
        }

        $row['id'] = $id;
        $row['revision_uuid'] = $revisionUuid;
        $row['contract_id'] = $contractId;
        $row['financial_currency_profile_id'] = $profileId;
        $row['schedule_entry_uuid'] = $entryUuid;
        $row['revision_number'] = $revisionNumber;
        $row['sequence_no'] = $sequence;
        $row['reference'] = $reference;
        $row['due_date'] = $dueDate;
        $row['amount'] = $money->amount();
        $row['currency_code'] = $money->currencyCode();
        $row['schedule_entry_state'] = $state;
        $row['created_by'] = $createdBy;
        return $row;
    }

    private function currencyFromStorage(mixed $value): CurrencyCode
    {
        try {
            return CurrencyCode::from($value);
        } catch (Throwable $error) {
            throw new UnexpectedValueException('Enterprise Contract payment schedule currency is invalid.', 0, $error);
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
            throw new RuntimeException('Enterprise Contract payment schedule access requires core tenant enforcement.');
        }
        return TenantContextStore::context()->requireTenantId();
    }
}
