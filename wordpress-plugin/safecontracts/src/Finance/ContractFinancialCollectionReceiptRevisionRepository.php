<?php

declare(strict_types=1);

namespace SafeContracts\Finance;

use InvalidArgumentException;
use RuntimeException;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;
use Throwable;
use UnexpectedValueException;

final class ContractFinancialCollectionReceiptRevisionRepository
{
    private const RECEIPT_COLUMNS = 'r.id, r.revision_uuid, r.contract_id, r.financial_currency_profile_id, r.receipt_uuid, r.revision_number, r.schedule_entry_uuid, r.schedule_sequence_no, r.external_reference, r.received_date, r.amount, r.currency_code, r.receipt_state, r.created_by, r.created_at';
    private const RECEIPT_COLUMNS_UNALIASED = 'id, revision_uuid, contract_id, financial_currency_profile_id, receipt_uuid, revision_number, schedule_entry_uuid, schedule_sequence_no, external_reference, received_date, amount, currency_code, receipt_state, created_by, created_at';
    private const SCHEDULE_COLUMNS = 's.financial_currency_profile_id AS authoritative_schedule_profile_id, s.sequence_no AS authoritative_schedule_sequence_no, s.schedule_entry_state AS authoritative_schedule_state';

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
        $receipts = $wpdb->prefix . 'safecontracts_contract_financial_collection_receipt_revisions';
        $schedules = $wpdb->prefix . 'safecontracts_contract_financial_payment_schedule_entry_revisions';
        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Enterprise Contract collection receipt read transaction.');
        }

        try {
            $this->lockContract($contractId, $authorizeLockedContract, false);
            $profile = $this->lockProfile($contractId);
            $limit = ContractFinancialCollectionReceiptPolicy::MAX_RECEIPTS + 1;
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT " . self::RECEIPT_COLUMNS . ", " . self::SCHEDULE_COLUMNS . "
                 FROM {$receipts} r
                 LEFT JOIN {$schedules} s
                   ON s.tenant_id = r.tenant_id
                  AND s.contract_id = r.contract_id
                  AND s.schedule_entry_uuid = r.schedule_entry_uuid
                  AND NOT EXISTS (
                       SELECT 1 FROM {$schedules} s_newer
                       WHERE s_newer.tenant_id = s.tenant_id
                         AND s_newer.contract_id = s.contract_id
                         AND s_newer.schedule_entry_uuid = s.schedule_entry_uuid
                         AND (
                              s_newer.revision_number > s.revision_number
                              OR (s_newer.revision_number = s.revision_number AND s_newer.id > s.id)
                         )
                  )
                 WHERE r.tenant_id = %d AND r.contract_id = %d
                   AND NOT EXISTS (
                        SELECT 1 FROM {$receipts} newer
                        WHERE newer.tenant_id = r.tenant_id
                          AND newer.contract_id = r.contract_id
                          AND newer.receipt_uuid = r.receipt_uuid
                          AND (
                               newer.revision_number > r.revision_number
                               OR (newer.revision_number = r.revision_number AND newer.id > r.id)
                          )
                   )
                 ORDER BY r.received_date ASC, r.receipt_uuid ASC
                 LIMIT %d",
                $tenantId,
                $contractId,
                $limit
            ), ARRAY_A);
            if (! is_array($rows)) {
                throw new RuntimeException('Enterprise Contract collection receipts could not be read.');
            }
            if (count($rows) > ContractFinancialCollectionReceiptPolicy::MAX_RECEIPTS) {
                throw new RuntimeException('Enterprise Contract collection receipt limit was exceeded.');
            }

            $normalized = [];
            $seen = [];
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    throw new UnexpectedValueException('Enterprise Contract collection receipt row is invalid.');
                }
                $receipt = $this->normalizeReceipt($row, $contractId);
                $receiptUuid = (string) $receipt['receipt_uuid'];
                if (isset($seen[$receiptUuid])) {
                    throw new UnexpectedValueException('Enterprise Contract collection receipt list contains duplicate latest identities.');
                }
                $seen[$receiptUuid] = true;
                $this->assertReceiptProfile($receipt, $profile);
                $this->assertJoinedSchedule($receipt, $row, $profile);
                $normalized[] = $receipt;
            }

            $this->commit('Enterprise Contract collection receipt read');
            return $normalized;
        } catch (Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    /** @param callable(array<string,mixed>):void $authorizeLockedContract */
    public function createReceipt(
        int $contractId,
        string $scheduleEntryUuid,
        string $receiptUuid,
        string $revisionUuid,
        mixed $externalReference,
        mixed $receivedDate,
        mixed $amount,
        int $actorId,
        callable $authorizeLockedContract
    ): int {
        $scheduleEntryUuid = ContractFinancialCollectionReceiptPolicy::normalizeUuid($scheduleEntryUuid, 'schedule entry UUID');
        $receiptUuid = ContractFinancialCollectionReceiptPolicy::normalizeUuid($receiptUuid, 'receipt UUID');
        $revisionUuid = ContractFinancialCollectionReceiptPolicy::normalizeUuid($revisionUuid, 'revision UUID');
        $reference = ContractFinancialCollectionReceiptPolicy::normalizeReference($externalReference);
        $date = ContractFinancialCollectionReceiptPolicy::normalizeReceivedDate($receivedDate);
        $this->assertActor($actorId);

        global $wpdb;
        $tenantId = $this->tenantId();
        $receipts = $wpdb->prefix . 'safecontracts_contract_financial_collection_receipt_revisions';
        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Enterprise Contract collection receipt creation transaction.');
        }

        try {
            $this->lockContract($contractId, $authorizeLockedContract, true);
            $profile = $this->lockProfile($contractId);
            $schedule = $this->lockLatestSchedule($contractId, $scheduleEntryUuid, $profile, true);
            $money = $this->receiptMoney($amount, $profile['currency']);

            $countRows = $wpdb->get_results($wpdb->prepare(
                "SELECT COUNT(DISTINCT receipt_uuid) AS total FROM {$receipts} WHERE tenant_id = %d AND contract_id = %d",
                $tenantId,
                $contractId
            ), ARRAY_A);
            if (! is_array($countRows) || count($countRows) !== 1 || ! is_array($countRows[0])) {
                throw new RuntimeException('Enterprise Contract collection receipt count returned an invalid cardinality.');
            }
            $count = (int) ($countRows[0]['total'] ?? -1);
            if ($count < 0 || $count >= ContractFinancialCollectionReceiptPolicy::MAX_RECEIPTS) {
                throw new RuntimeException('Enterprise Contract collection receipt limit has been reached.');
            }

            $collisionRows = $wpdb->get_results($wpdb->prepare(
                "SELECT id FROM {$receipts} WHERE tenant_id = %d AND contract_id = %d AND receipt_uuid = %s LIMIT 2 FOR UPDATE",
                $tenantId,
                $contractId,
                $receiptUuid
            ), ARRAY_A);
            if (! is_array($collisionRows) || $collisionRows !== []) {
                throw new RuntimeException('Enterprise Contract collection receipt identity already exists or could not be verified.');
            }

            $this->assertCollectionCapacity(
                $contractId,
                $scheduleEntryUuid,
                (int) $schedule['sequence_no'],
                $profile,
                (string) $schedule['amount'],
                $money,
                null
            );

            $id = $this->insertRevision(
                $contractId,
                (int) $profile['id'],
                $receiptUuid,
                $revisionUuid,
                1,
                $scheduleEntryUuid,
                (int) $schedule['sequence_no'],
                $reference,
                $date,
                $money,
                ContractFinancialCollectionReceiptPolicy::STATE_RECORDED,
                $actorId
            );
            $this->commit('Enterprise Contract collection receipt creation');
            return $id;
        } catch (Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    /** @param callable(array<string,mixed>):void $authorizeLockedContract */
    public function reviseReceipt(
        int $contractId,
        string $scheduleEntryUuid,
        string $receiptUuid,
        string $revisionUuid,
        mixed $externalReference,
        mixed $receivedDate,
        mixed $amount,
        int $actorId,
        callable $authorizeLockedContract
    ): int {
        $scheduleEntryUuid = ContractFinancialCollectionReceiptPolicy::normalizeUuid($scheduleEntryUuid, 'schedule entry UUID');
        $receiptUuid = ContractFinancialCollectionReceiptPolicy::normalizeUuid($receiptUuid, 'receipt UUID');
        $revisionUuid = ContractFinancialCollectionReceiptPolicy::normalizeUuid($revisionUuid, 'revision UUID');
        $reference = ContractFinancialCollectionReceiptPolicy::normalizeReference($externalReference);
        $date = ContractFinancialCollectionReceiptPolicy::normalizeReceivedDate($receivedDate);
        $this->assertActor($actorId);

        global $wpdb;
        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Enterprise Contract collection receipt revision transaction.');
        }

        try {
            $this->lockContract($contractId, $authorizeLockedContract, true);
            $profile = $this->lockProfile($contractId);
            $schedule = $this->lockLatestSchedule($contractId, $scheduleEntryUuid, $profile, true);
            $latest = $this->lockLatestReceipt($contractId, $receiptUuid);
            $this->assertReceiptProfile($latest, $profile);
            $this->assertReceiptSchedule($latest, $scheduleEntryUuid, (int) $schedule['sequence_no']);
            if ((string) $latest['receipt_state'] !== ContractFinancialCollectionReceiptPolicy::STATE_RECORDED) {
                throw new RuntimeException('Voided Enterprise Contract collection receipts cannot be revised or reactivated.');
            }

            $money = $this->receiptMoney($amount, $profile['currency']);
            if ($latest['external_reference'] === $reference
                && (string) $latest['received_date'] === $date
                && (string) $latest['amount'] === $money->amount()
                && (string) $latest['currency_code'] === $money->currencyCode()) {
                $this->commit('idempotent Enterprise Contract collection receipt revision');
                return (int) $latest['id'];
            }

            $this->assertCollectionCapacity(
                $contractId,
                $scheduleEntryUuid,
                (int) $schedule['sequence_no'],
                $profile,
                (string) $schedule['amount'],
                $money,
                $receiptUuid
            );

            $id = $this->insertRevision(
                $contractId,
                (int) $profile['id'],
                $receiptUuid,
                $revisionUuid,
                $this->nextRevisionNumber((int) $latest['revision_number']),
                $scheduleEntryUuid,
                (int) $schedule['sequence_no'],
                $reference,
                $date,
                $money,
                ContractFinancialCollectionReceiptPolicy::STATE_RECORDED,
                $actorId
            );
            $this->commit('Enterprise Contract collection receipt revision');
            return $id;
        } catch (Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    /** @param callable(array<string,mixed>):void $authorizeLockedContract */
    public function voidReceipt(
        int $contractId,
        string $scheduleEntryUuid,
        string $receiptUuid,
        string $revisionUuid,
        int $actorId,
        callable $authorizeLockedContract
    ): int {
        $scheduleEntryUuid = ContractFinancialCollectionReceiptPolicy::normalizeUuid($scheduleEntryUuid, 'schedule entry UUID');
        $receiptUuid = ContractFinancialCollectionReceiptPolicy::normalizeUuid($receiptUuid, 'receipt UUID');
        $revisionUuid = ContractFinancialCollectionReceiptPolicy::normalizeUuid($revisionUuid, 'revision UUID');
        $this->assertActor($actorId);

        global $wpdb;
        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Enterprise Contract collection receipt void transaction.');
        }

        try {
            $this->lockContract($contractId, $authorizeLockedContract, true);
            $profile = $this->lockProfile($contractId);
            $schedule = $this->lockLatestSchedule($contractId, $scheduleEntryUuid, $profile, true);
            $latest = $this->lockLatestReceipt($contractId, $receiptUuid);
            $this->assertReceiptProfile($latest, $profile);
            $this->assertReceiptSchedule($latest, $scheduleEntryUuid, (int) $schedule['sequence_no']);
            if ((string) $latest['receipt_state'] === ContractFinancialCollectionReceiptPolicy::STATE_VOIDED) {
                $this->commit('idempotent Enterprise Contract collection receipt void');
                return (int) $latest['id'];
            }

            $money = $this->receiptMoney((string) $latest['amount'], $profile['currency']);
            $id = $this->insertRevision(
                $contractId,
                (int) $profile['id'],
                $receiptUuid,
                $revisionUuid,
                $this->nextRevisionNumber((int) $latest['revision_number']),
                $scheduleEntryUuid,
                (int) $schedule['sequence_no'],
                $latest['external_reference'],
                (string) $latest['received_date'],
                $money,
                ContractFinancialCollectionReceiptPolicy::STATE_VOIDED,
                $actorId
            );
            $this->commit('Enterprise Contract collection receipt void');
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
            throw new RuntimeException('Enterprise Contract collection receipts may only change while the Contract is unarchived and active.');
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

    /** @param array{id:int,currency:CurrencyCode} $profile @return array<string,mixed> */
    private function lockLatestSchedule(int $contractId, string $scheduleEntryUuid, array $profile, bool $requireScheduled): array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $schedules = $wpdb->prefix . 'safecontracts_contract_financial_payment_schedule_entry_revisions';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, contract_id, financial_currency_profile_id, schedule_entry_uuid, revision_number, sequence_no, amount, currency_code, schedule_entry_state
             FROM {$schedules}
             WHERE tenant_id = %d AND contract_id = %d AND schedule_entry_uuid = %s
             ORDER BY revision_number DESC, id DESC LIMIT 1 FOR UPDATE",
            $tenantId,
            $contractId,
            $scheduleEntryUuid
        ), ARRAY_A);
        if (! is_array($rows) || count($rows) !== 1 || ! is_array($rows[0])) {
            throw new RuntimeException('Enterprise Contract collection receipt requires exactly one linked payment schedule entry.');
        }
        $row = $rows[0];
        try {
            $sequence = ContractFinancialPaymentSchedulePolicy::normalizeSequence($row['sequence_no'] ?? null);
            $state = ContractFinancialPaymentSchedulePolicy::normalizeState($row['schedule_entry_state'] ?? null);
            $currency = $this->currencyFromStorage($row['currency_code'] ?? null);
            $scheduledMoney = Money::of($row['amount'] ?? null, $currency);
        } catch (Throwable $error) {
            throw new UnexpectedValueException('Enterprise Contract collection receipt linked schedule contains invalid financial data.', 0, $error);
        }
        if ((int) ($row['contract_id'] ?? 0) !== $contractId
            || (int) ($row['financial_currency_profile_id'] ?? 0) !== (int) $profile['id']
            || (string) ($row['schedule_entry_uuid'] ?? '') !== $scheduleEntryUuid
            || ! $currency->equals($profile['currency'])
            || $scheduledMoney->compare(Money::of('0', $currency)) <= 0) {
            throw new UnexpectedValueException('Enterprise Contract collection receipt linked schedule identity/profile/currency/amount is invalid.');
        }
        if ($requireScheduled && $state !== ContractFinancialPaymentSchedulePolicy::STATE_SCHEDULED) {
            throw new RuntimeException('Enterprise Contract collection receipts may only mutate against a scheduled payment entry.');
        }
        $row['sequence_no'] = $sequence;
        $row['amount'] = $scheduledMoney->amount();
        $row['schedule_entry_state'] = $state;
        $row['currency_code'] = $currency->value();
        return $row;
    }

    /** @return array<string,mixed> */
    private function lockLatestReceipt(int $contractId, string $receiptUuid): array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $receipts = $wpdb->prefix . 'safecontracts_contract_financial_collection_receipt_revisions';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT " . self::RECEIPT_COLUMNS_UNALIASED . " FROM {$receipts}
             WHERE tenant_id = %d AND contract_id = %d AND receipt_uuid = %s
             ORDER BY revision_number DESC, id DESC LIMIT 1 FOR UPDATE",
            $tenantId,
            $contractId,
            $receiptUuid
        ), ARRAY_A);
        if (! is_array($rows) || count($rows) !== 1 || ! is_array($rows[0])) {
            throw new RuntimeException('Enterprise Contract collection receipt was not found or has invalid cardinality.');
        }
        return $this->normalizeReceipt($rows[0], $contractId, $receiptUuid);
    }

    /**
     * @param array{id:int,currency:CurrencyCode} $profile
     */
    private function assertCollectionCapacity(
        int $contractId,
        string $scheduleEntryUuid,
        int $scheduleSequence,
        array $profile,
        string $scheduledAmount,
        Money $proposedAmount,
        ?string $excludeReceiptUuid
    ): void {
        global $wpdb;
        $tenantId = $this->tenantId();
        $receipts = $wpdb->prefix . 'safecontracts_contract_financial_collection_receipt_revisions';
        $limit = ContractFinancialCollectionReceiptPolicy::MAX_RECEIPTS + 1;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT " . self::RECEIPT_COLUMNS_UNALIASED . " FROM {$receipts} r
             WHERE r.tenant_id = %d AND r.contract_id = %d AND r.schedule_entry_uuid = %s
               AND NOT EXISTS (
                    SELECT 1 FROM {$receipts} newer
                    WHERE newer.tenant_id = r.tenant_id
                      AND newer.contract_id = r.contract_id
                      AND newer.receipt_uuid = r.receipt_uuid
                      AND (
                           newer.revision_number > r.revision_number
                           OR (newer.revision_number = r.revision_number AND newer.id > r.id)
                      )
               )
             ORDER BY r.receipt_uuid ASC
             LIMIT %d FOR UPDATE",
            $tenantId,
            $contractId,
            $scheduleEntryUuid,
            $limit
        ), ARRAY_A);
        if (! is_array($rows)) {
            throw new RuntimeException('Enterprise Contract collection capacity could not read current receipts.');
        }
        if (count($rows) > ContractFinancialCollectionReceiptPolicy::MAX_RECEIPTS) {
            throw new RuntimeException('Enterprise Contract collection capacity receipt limit was exceeded.');
        }

        $scheduledMoney = Money::of($scheduledAmount, $profile['currency']);
        if ($scheduledMoney->compare(Money::of('0', $profile['currency'])) <= 0) {
            throw new UnexpectedValueException('Enterprise Contract collection capacity requires a positive scheduled amount.');
        }

        $used = Money::of('0', $profile['currency']);
        $seen = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                throw new UnexpectedValueException('Enterprise Contract collection capacity receipt row is invalid.');
            }
            $receipt = $this->normalizeReceipt($row, $contractId);
            $receiptUuid = (string) $receipt['receipt_uuid'];
            if (isset($seen[$receiptUuid])) {
                throw new UnexpectedValueException('Enterprise Contract collection capacity contains duplicate latest receipt identities.');
            }
            $seen[$receiptUuid] = true;
            $this->assertReceiptProfile($receipt, $profile);
            $this->assertReceiptSchedule($receipt, $scheduleEntryUuid, $scheduleSequence);

            if ($excludeReceiptUuid !== null && $receiptUuid === $excludeReceiptUuid) {
                continue;
            }
            if ((string) $receipt['receipt_state'] !== ContractFinancialCollectionReceiptPolicy::STATE_RECORDED) {
                continue;
            }
            $used = $used->add(Money::of((string) $receipt['amount'], $profile['currency']));
        }

        if ($used->add($proposedAmount)->compare($scheduledMoney) > 0) {
            throw new RuntimeException('Enterprise Contract collection receipt would exceed the linked payment schedule amount.');
        }
    }

    /** @param array<string,mixed> $receipt @param array{id:int,currency:CurrencyCode} $profile */
    private function assertReceiptProfile(array $receipt, array $profile): void
    {
        if ((int) $receipt['financial_currency_profile_id'] !== (int) $profile['id']) {
            throw new UnexpectedValueException('Enterprise Contract collection receipt references a different financial profile.');
        }
        if ((string) $receipt['currency_code'] !== $profile['currency']->value()) {
            throw new UnexpectedValueException('Enterprise Contract collection receipt currency differs from the financial profile.');
        }
    }

    /** @param array<string,mixed> $receipt @param array<string,mixed> $joined @param array{id:int,currency:CurrencyCode} $profile */
    private function assertJoinedSchedule(array $receipt, array $joined, array $profile): void
    {
        $scheduleProfile = (int) ($joined['authoritative_schedule_profile_id'] ?? 0);
        $scheduleSequenceRaw = $joined['authoritative_schedule_sequence_no'] ?? null;
        $scheduleStateRaw = $joined['authoritative_schedule_state'] ?? null;
        if ($scheduleProfile <= 0 || $scheduleSequenceRaw === null || $scheduleStateRaw === null) {
            throw new UnexpectedValueException('Enterprise Contract collection receipt linked schedule is missing.');
        }
        try {
            $sequence = ContractFinancialPaymentSchedulePolicy::normalizeSequence($scheduleSequenceRaw);
            ContractFinancialPaymentSchedulePolicy::normalizeState($scheduleStateRaw);
        } catch (Throwable $error) {
            throw new UnexpectedValueException('Enterprise Contract collection receipt linked schedule is invalid.', 0, $error);
        }
        if ($scheduleProfile !== (int) $profile['id'] || $sequence !== (int) $receipt['schedule_sequence_no']) {
            throw new UnexpectedValueException('Enterprise Contract collection receipt linked schedule profile/sequence differs from receipt evidence.');
        }
    }

    /** @param array<string,mixed> $receipt */
    private function assertReceiptSchedule(array $receipt, string $scheduleEntryUuid, int $sequence): void
    {
        if ((string) $receipt['schedule_entry_uuid'] !== $scheduleEntryUuid || (int) $receipt['schedule_sequence_no'] !== $sequence) {
            throw new UnexpectedValueException('Enterprise Contract collection receipt cannot be relinked to another payment schedule entry.');
        }
    }

    private function receiptMoney(mixed $amount, CurrencyCode $currency): Money
    {
        $money = Money::of($amount, $currency);
        if ($money->compare(Money::of('0', $currency)) <= 0) {
            throw new InvalidArgumentException('Enterprise Contract collection receipt amount must be greater than zero.');
        }
        return $money;
    }

    private function insertRevision(
        int $contractId,
        int $profileId,
        string $receiptUuid,
        string $revisionUuid,
        int $revisionNumber,
        string $scheduleEntryUuid,
        int $scheduleSequence,
        ?string $reference,
        string $receivedDate,
        Money $money,
        string $state,
        int $actorId
    ): int {
        global $wpdb;
        $tenantId = $this->tenantId();
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $profiles = $wpdb->prefix . 'safecontracts_contract_financial_currency_profiles';
        $schedules = $wpdb->prefix . 'safecontracts_contract_financial_payment_schedule_entry_revisions';
        $receipts = $wpdb->prefix . 'safecontracts_contract_financial_collection_receipt_revisions';
        $state = ContractFinancialCollectionReceiptPolicy::normalizeState($state);
        $reference = ContractFinancialCollectionReceiptPolicy::normalizeReference($reference);
        $receivedDate = ContractFinancialCollectionReceiptPolicy::normalizeReceivedDate($receivedDate);
        $scheduleSequence = ContractFinancialPaymentSchedulePolicy::normalizeSequence($scheduleSequence);

        $referenceSql = $reference === null ? 'NULL' : '%s';
        $query = "INSERT INTO {$receipts} (
                tenant_id, revision_uuid, contract_id, financial_currency_profile_id, receipt_uuid,
                revision_number, schedule_entry_uuid, schedule_sequence_no, external_reference, received_date,
                amount, currency_code, receipt_state, created_by, created_at
             ) SELECT %d, %s, c.id, p.id, %s, %d, s.schedule_entry_uuid, s.sequence_no, {$referenceSql}, %s,
                      %s, p.contract_currency, %s, %d, UTC_TIMESTAMP()
             FROM {$contracts} c
             INNER JOIN {$profiles} p ON p.tenant_id = c.tenant_id AND p.contract_id = c.id
             INNER JOIN {$schedules} s
               ON s.tenant_id = c.tenant_id
              AND s.contract_id = c.id
              AND s.schedule_entry_uuid = %s
              AND s.financial_currency_profile_id = p.id
             WHERE c.id = %d AND c.tenant_id = %d AND c.status = 'active' AND c.is_archived = 0
               AND p.id = %d AND p.contract_currency = %s
               AND s.sequence_no = %d AND s.schedule_entry_state = 'scheduled'
               AND NOT EXISTS (
                    SELECT 1 FROM {$schedules} s_newer
                    WHERE s_newer.tenant_id = s.tenant_id
                      AND s_newer.contract_id = s.contract_id
                      AND s_newer.schedule_entry_uuid = s.schedule_entry_uuid
                      AND (
                           s_newer.revision_number > s.revision_number
                           OR (s_newer.revision_number = s.revision_number AND s_newer.id > s.id)
                      )
               )
             LIMIT 1";
        $args = [$tenantId, $revisionUuid, $receiptUuid, $revisionNumber];
        if ($reference !== null) {
            $args[] = $reference;
        }
        array_push(
            $args,
            $receivedDate,
            $money->amount(),
            $state,
            $actorId,
            $scheduleEntryUuid,
            $contractId,
            $tenantId,
            $profileId,
            $money->currencyCode(),
            $scheduleSequence
        );

        $inserted = $wpdb->query($wpdb->prepare($query, ...$args));
        if ($inserted !== 1) {
            throw new RuntimeException('Unable to append Enterprise Contract collection receipt revision.');
        }
        $id = (int) ($wpdb->insert_id ?? 0);
        if ($id <= 0) {
            throw new RuntimeException('Enterprise Contract collection receipt revision insert returned no identifier.');
        }
        return $id;
    }

    private function nextRevisionNumber(int $current): int
    {
        if ($current <= 0 || $current >= ContractFinancialCollectionReceiptPolicy::MAX_REVISION) {
            throw new RuntimeException('Enterprise Contract collection receipt revision limit has been reached.');
        }
        return $current + 1;
    }

    private function assertActor(int $actorId): void
    {
        if ($actorId <= 0) {
            throw new InvalidArgumentException('Enterprise Contract collection receipt mutation requires an authenticated actor.');
        }
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function normalizeReceipt(array $row, int $expectedContractId, ?string $expectedReceiptUuid = null): array
    {
        $id = (int) ($row['id'] ?? 0);
        $contractId = (int) ($row['contract_id'] ?? 0);
        $profileId = (int) ($row['financial_currency_profile_id'] ?? 0);
        $revisionNumber = (int) ($row['revision_number'] ?? 0);
        $createdBy = (int) ($row['created_by'] ?? 0);
        if ($id <= 0 || $contractId !== $expectedContractId || $profileId <= 0 || $revisionNumber <= 0 || $revisionNumber > ContractFinancialCollectionReceiptPolicy::MAX_REVISION || $createdBy <= 0) {
            throw new UnexpectedValueException('Enterprise Contract collection receipt revision has invalid identity or revision metadata.');
        }

        try {
            $revisionUuid = ContractFinancialCollectionReceiptPolicy::normalizeUuid($row['revision_uuid'] ?? null, 'revision UUID');
            $receiptUuid = ContractFinancialCollectionReceiptPolicy::normalizeUuid($row['receipt_uuid'] ?? null, 'receipt UUID');
            $scheduleEntryUuid = ContractFinancialCollectionReceiptPolicy::normalizeUuid($row['schedule_entry_uuid'] ?? null, 'schedule entry UUID');
            $sequence = ContractFinancialPaymentSchedulePolicy::normalizeSequence($row['schedule_sequence_no'] ?? null);
            $reference = ContractFinancialCollectionReceiptPolicy::normalizeReference($row['external_reference'] ?? null);
            $receivedDate = ContractFinancialCollectionReceiptPolicy::normalizeReceivedDate($row['received_date'] ?? null);
            $currency = $this->currencyFromStorage($row['currency_code'] ?? null);
            $money = $this->receiptMoney($row['amount'] ?? null, $currency);
            $state = ContractFinancialCollectionReceiptPolicy::normalizeState($row['receipt_state'] ?? null);
        } catch (Throwable $error) {
            throw new UnexpectedValueException('Enterprise Contract collection receipt revision contains invalid persisted data.', 0, $error);
        }
        if ($expectedReceiptUuid !== null && $receiptUuid !== $expectedReceiptUuid) {
            throw new UnexpectedValueException('Enterprise Contract collection receipt lookup returned a different receipt identity.');
        }

        $row['id'] = $id;
        $row['revision_uuid'] = $revisionUuid;
        $row['contract_id'] = $contractId;
        $row['financial_currency_profile_id'] = $profileId;
        $row['receipt_uuid'] = $receiptUuid;
        $row['revision_number'] = $revisionNumber;
        $row['schedule_entry_uuid'] = $scheduleEntryUuid;
        $row['schedule_sequence_no'] = $sequence;
        $row['external_reference'] = $reference;
        $row['received_date'] = $receivedDate;
        $row['amount'] = $money->amount();
        $row['currency_code'] = $money->currencyCode();
        $row['receipt_state'] = $state;
        $row['created_by'] = $createdBy;
        unset($row['authoritative_schedule_profile_id'], $row['authoritative_schedule_sequence_no'], $row['authoritative_schedule_state']);
        return $row;
    }

    private function currencyFromStorage(mixed $value): CurrencyCode
    {
        try {
            return CurrencyCode::from($value);
        } catch (Throwable $error) {
            throw new UnexpectedValueException('Enterprise Contract collection receipt currency is invalid.', 0, $error);
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
            throw new RuntimeException('Enterprise Contract collection receipt access requires core tenant enforcement.');
        }
        return TenantContextStore::context()->requireTenantId();
    }
}
