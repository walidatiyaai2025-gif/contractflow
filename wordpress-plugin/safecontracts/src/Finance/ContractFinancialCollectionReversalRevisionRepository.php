<?php

declare(strict_types=1);

namespace SafeContracts\Finance;

use InvalidArgumentException;
use RuntimeException;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;
use Throwable;
use UnexpectedValueException;

final class ContractFinancialCollectionReversalRevisionRepository
{
    private const REVERSAL_COLUMNS = 'id, revision_uuid, contract_id, financial_currency_profile_id, reversal_uuid, revision_number, receipt_uuid, schedule_entry_uuid, schedule_sequence_no, external_reference, reversal_date, amount, currency_code, reversal_state, created_by, created_at';

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
        $reversals = $wpdb->prefix . 'safecontracts_contract_financial_collection_reversal_revisions';
        $receipts = $wpdb->prefix . 'safecontracts_contract_financial_collection_receipt_revisions';
        $schedules = $wpdb->prefix . 'safecontracts_contract_financial_payment_schedule_entry_revisions';
        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Enterprise Contract collection reversal read transaction.');
        }

        try {
            $this->lockContract($contractId, $authorizeLockedContract, false);
            $profile = $this->lockProfile($contractId);
            $limit = ContractFinancialCollectionReversalPolicy::MAX_REVERSALS + 1;
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT r.*,\n"
                . " rr.financial_currency_profile_id AS authoritative_receipt_profile_id,\n"
                . " rr.schedule_entry_uuid AS authoritative_receipt_schedule_uuid,\n"
                . " rr.schedule_sequence_no AS authoritative_receipt_schedule_sequence_no,\n"
                . " rr.amount AS authoritative_receipt_amount,\n"
                . " rr.currency_code AS authoritative_receipt_currency,\n"
                . " rr.receipt_state AS authoritative_receipt_state,\n"
                . " s.financial_currency_profile_id AS authoritative_schedule_profile_id,\n"
                . " s.sequence_no AS authoritative_schedule_sequence_no,\n"
                . " s.currency_code AS authoritative_schedule_currency,\n"
                . " s.schedule_entry_state AS authoritative_schedule_state\n"
                . "FROM {$reversals} r\n"
                . "LEFT JOIN {$receipts} rr ON rr.tenant_id = r.tenant_id AND rr.contract_id = r.contract_id AND rr.receipt_uuid = r.receipt_uuid\n"
                . " AND NOT EXISTS (SELECT 1 FROM {$receipts} rr_newer WHERE rr_newer.tenant_id = rr.tenant_id AND rr_newer.contract_id = rr.contract_id AND rr_newer.receipt_uuid = rr.receipt_uuid AND (rr_newer.revision_number > rr.revision_number OR (rr_newer.revision_number = rr.revision_number AND rr_newer.id > rr.id)))\n"
                . "LEFT JOIN {$schedules} s ON s.tenant_id = r.tenant_id AND s.contract_id = r.contract_id AND s.schedule_entry_uuid = r.schedule_entry_uuid\n"
                . " AND NOT EXISTS (SELECT 1 FROM {$schedules} s_newer WHERE s_newer.tenant_id = s.tenant_id AND s_newer.contract_id = s.contract_id AND s_newer.schedule_entry_uuid = s.schedule_entry_uuid AND (s_newer.revision_number > s.revision_number OR (s_newer.revision_number = s.revision_number AND s_newer.id > s.id)))\n"
                . "WHERE r.tenant_id = %d AND r.contract_id = %d\n"
                . " AND NOT EXISTS (SELECT 1 FROM {$reversals} newer WHERE newer.tenant_id = r.tenant_id AND newer.contract_id = r.contract_id AND newer.reversal_uuid = r.reversal_uuid AND (newer.revision_number > r.revision_number OR (newer.revision_number = r.revision_number AND newer.id > r.id)))\n"
                . "ORDER BY r.reversal_date ASC, r.reversal_uuid ASC LIMIT %d",
                $tenantId,
                $contractId,
                $limit
            ), ARRAY_A);
            if (! is_array($rows)) {
                throw new RuntimeException('Enterprise Contract collection reversals could not be read.');
            }
            if (count($rows) > ContractFinancialCollectionReversalPolicy::MAX_REVERSALS) {
                throw new RuntimeException('Enterprise Contract collection reversal limit was exceeded.');
            }

            $normalized = [];
            $seen = [];
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    throw new UnexpectedValueException('Enterprise Contract collection reversal row is invalid.');
                }
                $reversal = $this->normalizeReversal($row, $contractId);
                $uuid = (string) $reversal['reversal_uuid'];
                if (isset($seen[$uuid])) {
                    throw new UnexpectedValueException('Enterprise Contract collection reversal list contains duplicate latest identities.');
                }
                $seen[$uuid] = true;
                $this->assertReversalProfile($reversal, $profile);
                $this->assertJoinedReceiptAndSchedule($reversal, $row, $profile);
                $normalized[] = $reversal;
            }

            $this->commit('Enterprise Contract collection reversal read');
            return $normalized;
        } catch (Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    /** @param callable(array<string,mixed>):void $authorizeLockedContract */
    public function createReversal(
        int $contractId,
        string $receiptUuid,
        string $reversalUuid,
        string $revisionUuid,
        mixed $externalReference,
        mixed $reversalDate,
        mixed $amount,
        int $actorId,
        callable $authorizeLockedContract
    ): int {
        $receiptUuid = ContractFinancialCollectionReversalPolicy::normalizeUuid($receiptUuid, 'receipt UUID');
        $reversalUuid = ContractFinancialCollectionReversalPolicy::normalizeUuid($reversalUuid, 'reversal UUID');
        $revisionUuid = ContractFinancialCollectionReversalPolicy::normalizeUuid($revisionUuid, 'revision UUID');
        $reference = ContractFinancialCollectionReversalPolicy::normalizeReference($externalReference);
        $date = ContractFinancialCollectionReversalPolicy::normalizeReversalDate($reversalDate);
        $this->assertActor($actorId);

        global $wpdb;
        $tenantId = $this->tenantId();
        $reversals = $wpdb->prefix . 'safecontracts_contract_financial_collection_reversal_revisions';
        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Enterprise Contract collection reversal creation transaction.');
        }

        try {
            $this->lockContract($contractId, $authorizeLockedContract, true);
            $profile = $this->lockProfile($contractId);
            $receipt = $this->lockLatestReceipt($contractId, $receiptUuid, $profile, true);
            $schedule = $this->lockLatestSchedule($contractId, (string) $receipt['schedule_entry_uuid'], $profile, true);
            $this->assertReceiptSchedule($receipt, $schedule);
            $money = $this->reversalMoney($amount, $profile['currency']);

            $countRows = $wpdb->get_results($wpdb->prepare(
                "SELECT COUNT(DISTINCT reversal_uuid) AS total FROM {$reversals} WHERE tenant_id = %d AND contract_id = %d",
                $tenantId,
                $contractId
            ), ARRAY_A);
            if (! is_array($countRows) || count($countRows) !== 1 || ! is_array($countRows[0])) {
                throw new RuntimeException('Enterprise Contract collection reversal count returned an invalid cardinality.');
            }
            $count = (int) ($countRows[0]['total'] ?? -1);
            if ($count < 0 || $count >= ContractFinancialCollectionReversalPolicy::MAX_REVERSALS) {
                throw new RuntimeException('Enterprise Contract collection reversal limit has been reached.');
            }

            $collisionRows = $wpdb->get_results($wpdb->prepare(
                "SELECT id FROM {$reversals} WHERE tenant_id = %d AND contract_id = %d AND reversal_uuid = %s LIMIT 2 FOR UPDATE",
                $tenantId,
                $contractId,
                $reversalUuid
            ), ARRAY_A);
            if (! is_array($collisionRows) || $collisionRows !== []) {
                throw new RuntimeException('Enterprise Contract collection reversal identity already exists or could not be verified.');
            }

            $this->assertReversalCapacity($contractId, $receipt, $profile, $money, null);
            $id = $this->insertRevision(
                $contractId,
                (int) $profile['id'],
                $reversalUuid,
                $revisionUuid,
                1,
                $receiptUuid,
                (string) $receipt['schedule_entry_uuid'],
                (int) $receipt['schedule_sequence_no'],
                $reference,
                $date,
                $money,
                ContractFinancialCollectionReversalPolicy::STATE_RECORDED,
                $actorId
            );
            $this->commit('Enterprise Contract collection reversal creation');
            return $id;
        } catch (Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    /** @param callable(array<string,mixed>):void $authorizeLockedContract */
    public function reviseReversal(
        int $contractId,
        string $receiptUuid,
        string $reversalUuid,
        string $revisionUuid,
        mixed $externalReference,
        mixed $reversalDate,
        mixed $amount,
        int $actorId,
        callable $authorizeLockedContract
    ): int {
        $receiptUuid = ContractFinancialCollectionReversalPolicy::normalizeUuid($receiptUuid, 'receipt UUID');
        $reversalUuid = ContractFinancialCollectionReversalPolicy::normalizeUuid($reversalUuid, 'reversal UUID');
        $revisionUuid = ContractFinancialCollectionReversalPolicy::normalizeUuid($revisionUuid, 'revision UUID');
        $reference = ContractFinancialCollectionReversalPolicy::normalizeReference($externalReference);
        $date = ContractFinancialCollectionReversalPolicy::normalizeReversalDate($reversalDate);
        $this->assertActor($actorId);

        global $wpdb;
        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Enterprise Contract collection reversal revision transaction.');
        }

        try {
            $this->lockContract($contractId, $authorizeLockedContract, true);
            $profile = $this->lockProfile($contractId);
            $receipt = $this->lockLatestReceipt($contractId, $receiptUuid, $profile, true);
            $schedule = $this->lockLatestSchedule($contractId, (string) $receipt['schedule_entry_uuid'], $profile, true);
            $this->assertReceiptSchedule($receipt, $schedule);
            $latest = $this->lockLatestReversal($contractId, $reversalUuid);
            $this->assertReversalProfile($latest, $profile);
            $this->assertReversalLink($latest, $receipt);
            if ((string) $latest['reversal_state'] !== ContractFinancialCollectionReversalPolicy::STATE_RECORDED) {
                throw new RuntimeException('Voided Enterprise Contract collection reversals cannot be revised or reactivated.');
            }

            $money = $this->reversalMoney($amount, $profile['currency']);
            if ($latest['external_reference'] === $reference
                && (string) $latest['reversal_date'] === $date
                && (string) $latest['amount'] === $money->amount()
                && (string) $latest['currency_code'] === $money->currencyCode()) {
                $this->commit('idempotent Enterprise Contract collection reversal revision');
                return (int) $latest['id'];
            }

            $this->assertReversalCapacity($contractId, $receipt, $profile, $money, $reversalUuid);
            $id = $this->insertRevision(
                $contractId,
                (int) $profile['id'],
                $reversalUuid,
                $revisionUuid,
                $this->nextRevisionNumber((int) $latest['revision_number']),
                $receiptUuid,
                (string) $receipt['schedule_entry_uuid'],
                (int) $receipt['schedule_sequence_no'],
                $reference,
                $date,
                $money,
                ContractFinancialCollectionReversalPolicy::STATE_RECORDED,
                $actorId
            );
            $this->commit('Enterprise Contract collection reversal revision');
            return $id;
        } catch (Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    /** @param callable(array<string,mixed>):void $authorizeLockedContract */
    public function voidReversal(
        int $contractId,
        string $receiptUuid,
        string $reversalUuid,
        string $revisionUuid,
        int $actorId,
        callable $authorizeLockedContract
    ): int {
        $receiptUuid = ContractFinancialCollectionReversalPolicy::normalizeUuid($receiptUuid, 'receipt UUID');
        $reversalUuid = ContractFinancialCollectionReversalPolicy::normalizeUuid($reversalUuid, 'reversal UUID');
        $revisionUuid = ContractFinancialCollectionReversalPolicy::normalizeUuid($revisionUuid, 'revision UUID');
        $this->assertActor($actorId);

        global $wpdb;
        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Enterprise Contract collection reversal void transaction.');
        }

        try {
            $this->lockContract($contractId, $authorizeLockedContract, true);
            $profile = $this->lockProfile($contractId);
            $receipt = $this->lockLatestReceipt($contractId, $receiptUuid, $profile, true);
            $schedule = $this->lockLatestSchedule($contractId, (string) $receipt['schedule_entry_uuid'], $profile, true);
            $this->assertReceiptSchedule($receipt, $schedule);
            $latest = $this->lockLatestReversal($contractId, $reversalUuid);
            $this->assertReversalProfile($latest, $profile);
            $this->assertReversalLink($latest, $receipt);
            if ((string) $latest['reversal_state'] === ContractFinancialCollectionReversalPolicy::STATE_VOIDED) {
                $this->commit('idempotent Enterprise Contract collection reversal void');
                return (int) $latest['id'];
            }

            $money = $this->reversalMoney((string) $latest['amount'], $profile['currency']);
            $id = $this->insertRevision(
                $contractId,
                (int) $profile['id'],
                $reversalUuid,
                $revisionUuid,
                $this->nextRevisionNumber((int) $latest['revision_number']),
                $receiptUuid,
                (string) $receipt['schedule_entry_uuid'],
                (int) $receipt['schedule_sequence_no'],
                $latest['external_reference'],
                (string) $latest['reversal_date'],
                $money,
                ContractFinancialCollectionReversalPolicy::STATE_VOIDED,
                $actorId
            );
            $this->commit('Enterprise Contract collection reversal void');
            return $id;
        } catch (Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    /** @param callable(array<string,mixed>):void $authorizeLockedContract */
    private function lockContract(int $contractId, callable $authorizeLockedContract, bool $requireActive): void
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
            throw new RuntimeException('Enterprise Contract collection reversals may only change while the Contract is unarchived and active.');
        }
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
    private function lockLatestReceipt(int $contractId, string $receiptUuid, array $profile, bool $requireRecorded): array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $receipts = $wpdb->prefix . 'safecontracts_contract_financial_collection_receipt_revisions';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, contract_id, financial_currency_profile_id, receipt_uuid, revision_number, schedule_entry_uuid, schedule_sequence_no, amount, currency_code, receipt_state FROM {$receipts} WHERE tenant_id = %d AND contract_id = %d AND receipt_uuid = %s ORDER BY revision_number DESC, id DESC LIMIT 1 FOR UPDATE",
            $tenantId,
            $contractId,
            $receiptUuid
        ), ARRAY_A);
        if (! is_array($rows) || count($rows) !== 1 || ! is_array($rows[0])) {
            throw new RuntimeException('Enterprise Contract collection reversal requires exactly one linked collection receipt.');
        }
        $row = $rows[0];
        try {
            $scheduleUuid = ContractFinancialCollectionReversalPolicy::normalizeUuid($row['schedule_entry_uuid'] ?? null, 'schedule entry UUID');
            $sequence = ContractFinancialPaymentSchedulePolicy::normalizeSequence($row['schedule_sequence_no'] ?? null);
            $currency = $this->currencyFromStorage($row['currency_code'] ?? null);
            $money = Money::of($row['amount'] ?? null, $currency);
            $state = ContractFinancialCollectionReceiptPolicy::normalizeState($row['receipt_state'] ?? null);
        } catch (Throwable $error) {
            throw new UnexpectedValueException('Enterprise Contract collection reversal linked receipt contains invalid data.', 0, $error);
        }
        if ((int) ($row['contract_id'] ?? 0) !== $contractId
            || (int) ($row['financial_currency_profile_id'] ?? 0) !== (int) $profile['id']
            || (string) ($row['receipt_uuid'] ?? '') !== $receiptUuid
            || ! $currency->equals($profile['currency'])
            || $money->compare(Money::of('0', $currency)) <= 0) {
            throw new UnexpectedValueException('Enterprise Contract collection reversal linked receipt identity/profile/currency/amount is invalid.');
        }
        if ($requireRecorded && $state !== ContractFinancialCollectionReceiptPolicy::STATE_RECORDED) {
            throw new RuntimeException('Enterprise Contract collection reversals may only mutate against a recorded receipt.');
        }
        $row['schedule_entry_uuid'] = $scheduleUuid;
        $row['schedule_sequence_no'] = $sequence;
        $row['amount'] = $money->amount();
        $row['currency_code'] = $currency->value();
        $row['receipt_state'] = $state;
        return $row;
    }

    /** @param array{id:int,currency:CurrencyCode} $profile @return array<string,mixed> */
    private function lockLatestSchedule(int $contractId, string $scheduleUuid, array $profile, bool $requireScheduled): array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $schedules = $wpdb->prefix . 'safecontracts_contract_financial_payment_schedule_entry_revisions';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, contract_id, financial_currency_profile_id, schedule_entry_uuid, revision_number, sequence_no, currency_code, schedule_entry_state FROM {$schedules} WHERE tenant_id = %d AND contract_id = %d AND schedule_entry_uuid = %s ORDER BY revision_number DESC, id DESC LIMIT 1 FOR UPDATE",
            $tenantId,
            $contractId,
            $scheduleUuid
        ), ARRAY_A);
        if (! is_array($rows) || count($rows) !== 1 || ! is_array($rows[0])) {
            throw new RuntimeException('Enterprise Contract collection reversal requires exactly one linked payment schedule entry.');
        }
        $row = $rows[0];
        try {
            $sequence = ContractFinancialPaymentSchedulePolicy::normalizeSequence($row['sequence_no'] ?? null);
            $currency = $this->currencyFromStorage($row['currency_code'] ?? null);
            $state = ContractFinancialPaymentSchedulePolicy::normalizeState($row['schedule_entry_state'] ?? null);
        } catch (Throwable $error) {
            throw new UnexpectedValueException('Enterprise Contract collection reversal linked schedule contains invalid data.', 0, $error);
        }
        if ((int) ($row['contract_id'] ?? 0) !== $contractId
            || (int) ($row['financial_currency_profile_id'] ?? 0) !== (int) $profile['id']
            || (string) ($row['schedule_entry_uuid'] ?? '') !== $scheduleUuid
            || ! $currency->equals($profile['currency'])) {
            throw new UnexpectedValueException('Enterprise Contract collection reversal linked schedule identity/profile/currency is invalid.');
        }
        if ($requireScheduled && $state !== ContractFinancialPaymentSchedulePolicy::STATE_SCHEDULED) {
            throw new RuntimeException('Enterprise Contract collection reversals may only mutate against a scheduled payment entry.');
        }
        $row['sequence_no'] = $sequence;
        $row['currency_code'] = $currency->value();
        $row['schedule_entry_state'] = $state;
        return $row;
    }

    /** @return array<string,mixed> */
    private function lockLatestReversal(int $contractId, string $reversalUuid): array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $reversals = $wpdb->prefix . 'safecontracts_contract_financial_collection_reversal_revisions';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT " . self::REVERSAL_COLUMNS . " FROM {$reversals} WHERE tenant_id = %d AND contract_id = %d AND reversal_uuid = %s ORDER BY revision_number DESC, id DESC LIMIT 1 FOR UPDATE",
            $tenantId,
            $contractId,
            $reversalUuid
        ), ARRAY_A);
        if (! is_array($rows) || count($rows) !== 1 || ! is_array($rows[0])) {
            throw new RuntimeException('Enterprise Contract collection reversal was not found or has invalid cardinality.');
        }
        return $this->normalizeReversal($rows[0], $contractId, $reversalUuid);
    }

    /** @param array<string,mixed> $receipt @param array<string,mixed> $schedule */
    private function assertReceiptSchedule(array $receipt, array $schedule): void
    {
        if ((string) $receipt['schedule_entry_uuid'] !== (string) $schedule['schedule_entry_uuid']
            || (int) $receipt['schedule_sequence_no'] !== (int) $schedule['sequence_no']) {
            throw new UnexpectedValueException('Enterprise Contract collection reversal receipt/schedule linkage is invalid.');
        }
    }

    /** @param array<string,mixed> $reversal @param array<string,mixed> $receipt */
    private function assertReversalLink(array $reversal, array $receipt): void
    {
        if ((string) $reversal['receipt_uuid'] !== (string) $receipt['receipt_uuid']
            || (string) $reversal['schedule_entry_uuid'] !== (string) $receipt['schedule_entry_uuid']
            || (int) $reversal['schedule_sequence_no'] !== (int) $receipt['schedule_sequence_no']) {
            throw new UnexpectedValueException('Enterprise Contract collection reversal cannot be relinked to another receipt or schedule.');
        }
    }

    /** @param array<string,mixed> $reversal @param array{id:int,currency:CurrencyCode} $profile */
    private function assertReversalProfile(array $reversal, array $profile): void
    {
        if ((int) $reversal['financial_currency_profile_id'] !== (int) $profile['id']
            || (string) $reversal['currency_code'] !== $profile['currency']->value()) {
            throw new UnexpectedValueException('Enterprise Contract collection reversal profile/currency differs from the authoritative profile.');
        }
    }

    /** @param array<string,mixed> $reversal @param array<string,mixed> $row @param array{id:int,currency:CurrencyCode} $profile */
    private function assertJoinedReceiptAndSchedule(array $reversal, array $row, array $profile): void
    {
        $receiptProfile = (int) ($row['authoritative_receipt_profile_id'] ?? 0);
        $receiptSchedule = (string) ($row['authoritative_receipt_schedule_uuid'] ?? '');
        $receiptSequenceRaw = $row['authoritative_receipt_schedule_sequence_no'] ?? null;
        $receiptAmountRaw = $row['authoritative_receipt_amount'] ?? null;
        $receiptCurrencyRaw = $row['authoritative_receipt_currency'] ?? null;
        $receiptStateRaw = $row['authoritative_receipt_state'] ?? null;
        $scheduleProfile = (int) ($row['authoritative_schedule_profile_id'] ?? 0);
        $scheduleSequenceRaw = $row['authoritative_schedule_sequence_no'] ?? null;
        $scheduleCurrencyRaw = $row['authoritative_schedule_currency'] ?? null;
        $scheduleStateRaw = $row['authoritative_schedule_state'] ?? null;
        if ($receiptProfile <= 0 || $receiptSequenceRaw === null || $receiptAmountRaw === null || $receiptCurrencyRaw === null || $receiptStateRaw === null
            || $scheduleProfile <= 0 || $scheduleSequenceRaw === null || $scheduleCurrencyRaw === null || $scheduleStateRaw === null) {
            throw new UnexpectedValueException('Enterprise Contract collection reversal linked receipt or schedule is missing.');
        }
        try {
            $receiptSequence = ContractFinancialPaymentSchedulePolicy::normalizeSequence($receiptSequenceRaw);
            $receiptCurrency = $this->currencyFromStorage($receiptCurrencyRaw);
            $receiptMoney = Money::of($receiptAmountRaw, $receiptCurrency);
            ContractFinancialCollectionReceiptPolicy::normalizeState($receiptStateRaw);
            $scheduleSequence = ContractFinancialPaymentSchedulePolicy::normalizeSequence($scheduleSequenceRaw);
            $scheduleCurrency = $this->currencyFromStorage($scheduleCurrencyRaw);
            ContractFinancialPaymentSchedulePolicy::normalizeState($scheduleStateRaw);
        } catch (Throwable $error) {
            throw new UnexpectedValueException('Enterprise Contract collection reversal linked receipt or schedule is invalid.', 0, $error);
        }
        if ($receiptProfile !== (int) $profile['id'] || $scheduleProfile !== (int) $profile['id']
            || $receiptSchedule !== (string) $reversal['schedule_entry_uuid']
            || $receiptSequence !== (int) $reversal['schedule_sequence_no']
            || $scheduleSequence !== (int) $reversal['schedule_sequence_no']
            || ! $receiptCurrency->equals($profile['currency']) || ! $scheduleCurrency->equals($profile['currency'])
            || $receiptMoney->compare(Money::of('0', $receiptCurrency)) <= 0) {
            throw new UnexpectedValueException('Enterprise Contract collection reversal linked receipt/schedule profile, currency or sequence differs from reversal evidence.');
        }
    }

    /** @param array<string,mixed> $receipt @param array{id:int,currency:CurrencyCode} $profile */
    private function assertReversalCapacity(int $contractId, array $receipt, array $profile, Money $proposed, ?string $excludeReversalUuid): void
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $reversals = $wpdb->prefix . 'safecontracts_contract_financial_collection_reversal_revisions';
        $limit = ContractFinancialCollectionReversalPolicy::MAX_REVERSALS + 1;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT " . self::REVERSAL_COLUMNS . " FROM {$reversals} r WHERE r.tenant_id = %d AND r.contract_id = %d AND r.receipt_uuid = %s\n"
            . " AND NOT EXISTS (SELECT 1 FROM {$reversals} newer WHERE newer.tenant_id = r.tenant_id AND newer.contract_id = r.contract_id AND newer.reversal_uuid = r.reversal_uuid AND (newer.revision_number > r.revision_number OR (newer.revision_number = r.revision_number AND newer.id > r.id)))\n"
            . " ORDER BY r.reversal_uuid ASC LIMIT %d FOR UPDATE",
            $tenantId,
            $contractId,
            (string) $receipt['receipt_uuid'],
            $limit
        ), ARRAY_A);
        if (! is_array($rows)) {
            throw new RuntimeException('Enterprise Contract collection reversal capacity could not read current reversals.');
        }
        if (count($rows) > ContractFinancialCollectionReversalPolicy::MAX_REVERSALS) {
            throw new RuntimeException('Enterprise Contract collection reversal capacity limit was exceeded.');
        }

        $receiptMoney = Money::of((string) $receipt['amount'], $profile['currency']);
        $used = Money::of('0', $profile['currency']);
        $seen = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                throw new UnexpectedValueException('Enterprise Contract collection reversal capacity row is invalid.');
            }
            $reversal = $this->normalizeReversal($row, $contractId);
            $uuid = (string) $reversal['reversal_uuid'];
            if (isset($seen[$uuid])) {
                throw new UnexpectedValueException('Enterprise Contract collection reversal capacity contains duplicate latest identities.');
            }
            $seen[$uuid] = true;
            $this->assertReversalProfile($reversal, $profile);
            $this->assertReversalLink($reversal, $receipt);
            if ($excludeReversalUuid !== null && $uuid === $excludeReversalUuid) {
                continue;
            }
            if ((string) $reversal['reversal_state'] !== ContractFinancialCollectionReversalPolicy::STATE_RECORDED) {
                continue;
            }
            $used = $used->add(Money::of((string) $reversal['amount'], $profile['currency']));
        }

        if ($used->add($proposed)->compare($receiptMoney) > 0) {
            throw new RuntimeException('Enterprise Contract collection reversal would exceed the linked recorded receipt amount.');
        }
    }

    private function reversalMoney(mixed $amount, CurrencyCode $currency): Money
    {
        $money = Money::of($amount, $currency);
        if ($money->compare(Money::of('0', $currency)) <= 0) {
            throw new InvalidArgumentException('Enterprise Contract collection reversal amount must be greater than zero.');
        }
        return $money;
    }

    private function insertRevision(
        int $contractId,
        int $profileId,
        string $reversalUuid,
        string $revisionUuid,
        int $revisionNumber,
        string $receiptUuid,
        string $scheduleUuid,
        int $scheduleSequence,
        ?string $reference,
        string $date,
        Money $money,
        string $state,
        int $actorId
    ): int {
        global $wpdb;
        $tenantId = $this->tenantId();
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $profiles = $wpdb->prefix . 'safecontracts_contract_financial_currency_profiles';
        $receipts = $wpdb->prefix . 'safecontracts_contract_financial_collection_receipt_revisions';
        $schedules = $wpdb->prefix . 'safecontracts_contract_financial_payment_schedule_entry_revisions';
        $reversals = $wpdb->prefix . 'safecontracts_contract_financial_collection_reversal_revisions';
        $state = ContractFinancialCollectionReversalPolicy::normalizeState($state);
        $reference = ContractFinancialCollectionReversalPolicy::normalizeReference($reference);
        $date = ContractFinancialCollectionReversalPolicy::normalizeReversalDate($date);
        $scheduleSequence = ContractFinancialPaymentSchedulePolicy::normalizeSequence($scheduleSequence);
        $referenceSql = $reference === null ? 'NULL' : '%s';

        $query = "INSERT INTO {$reversals} (tenant_id, revision_uuid, contract_id, financial_currency_profile_id, reversal_uuid, revision_number, receipt_uuid, schedule_entry_uuid, schedule_sequence_no, external_reference, reversal_date, amount, currency_code, reversal_state, created_by, created_at)\n"
            . "SELECT %d, %s, c.id, p.id, %s, %d, rr.receipt_uuid, rr.schedule_entry_uuid, rr.schedule_sequence_no, {$referenceSql}, %s, %s, p.contract_currency, %s, %d, UTC_TIMESTAMP()\n"
            . "FROM {$contracts} c\n"
            . "INNER JOIN {$profiles} p ON p.tenant_id = c.tenant_id AND p.contract_id = c.id\n"
            . "INNER JOIN {$receipts} rr ON rr.tenant_id = c.tenant_id AND rr.contract_id = c.id AND rr.receipt_uuid = %s AND rr.financial_currency_profile_id = p.id\n"
            . "INNER JOIN {$schedules} s ON s.tenant_id = c.tenant_id AND s.contract_id = c.id AND s.schedule_entry_uuid = rr.schedule_entry_uuid AND s.financial_currency_profile_id = p.id\n"
            . "WHERE c.id = %d AND c.tenant_id = %d AND c.status = 'active' AND c.is_archived = 0 AND p.id = %d AND p.contract_currency = %s\n"
            . " AND rr.schedule_entry_uuid = %s AND rr.schedule_sequence_no = %d AND rr.receipt_state = 'recorded'\n"
            . " AND s.sequence_no = %d AND s.schedule_entry_state = 'scheduled'\n"
            . " AND NOT EXISTS (SELECT 1 FROM {$receipts} rr_newer WHERE rr_newer.tenant_id = rr.tenant_id AND rr_newer.contract_id = rr.contract_id AND rr_newer.receipt_uuid = rr.receipt_uuid AND (rr_newer.revision_number > rr.revision_number OR (rr_newer.revision_number = rr.revision_number AND rr_newer.id > rr.id)))\n"
            . " AND NOT EXISTS (SELECT 1 FROM {$schedules} s_newer WHERE s_newer.tenant_id = s.tenant_id AND s_newer.contract_id = s.contract_id AND s_newer.schedule_entry_uuid = s.schedule_entry_uuid AND (s_newer.revision_number > s.revision_number OR (s_newer.revision_number = s.revision_number AND s_newer.id > s.id))) LIMIT 1";
        $args = [$tenantId, $revisionUuid, $reversalUuid, $revisionNumber];
        if ($reference !== null) {
            $args[] = $reference;
        }
        array_push($args, $date, $money->amount(), $state, $actorId, $receiptUuid, $contractId, $tenantId, $profileId, $money->currencyCode(), $scheduleUuid, $scheduleSequence, $scheduleSequence);
        $inserted = $wpdb->query($wpdb->prepare($query, ...$args));
        if ($inserted !== 1) {
            throw new RuntimeException('Unable to append Enterprise Contract collection reversal revision.');
        }
        $id = (int) ($wpdb->insert_id ?? 0);
        if ($id <= 0) {
            throw new RuntimeException('Enterprise Contract collection reversal revision insert returned no identifier.');
        }
        return $id;
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function normalizeReversal(array $row, int $expectedContractId, ?string $expectedReversalUuid = null): array
    {
        $id = (int) ($row['id'] ?? 0);
        $contractId = (int) ($row['contract_id'] ?? 0);
        $profileId = (int) ($row['financial_currency_profile_id'] ?? 0);
        $revision = (int) ($row['revision_number'] ?? 0);
        $createdBy = (int) ($row['created_by'] ?? 0);
        if ($id <= 0 || $contractId !== $expectedContractId || $profileId <= 0 || $revision <= 0 || $revision > ContractFinancialCollectionReversalPolicy::MAX_REVISION || $createdBy <= 0) {
            throw new UnexpectedValueException('Enterprise Contract collection reversal revision metadata is invalid.');
        }
        try {
            $revisionUuid = ContractFinancialCollectionReversalPolicy::normalizeUuid($row['revision_uuid'] ?? null, 'revision UUID');
            $reversalUuid = ContractFinancialCollectionReversalPolicy::normalizeUuid($row['reversal_uuid'] ?? null, 'reversal UUID');
            $receiptUuid = ContractFinancialCollectionReversalPolicy::normalizeUuid($row['receipt_uuid'] ?? null, 'receipt UUID');
            $scheduleUuid = ContractFinancialCollectionReversalPolicy::normalizeUuid($row['schedule_entry_uuid'] ?? null, 'schedule entry UUID');
            $sequence = ContractFinancialPaymentSchedulePolicy::normalizeSequence($row['schedule_sequence_no'] ?? null);
            $reference = ContractFinancialCollectionReversalPolicy::normalizeReference($row['external_reference'] ?? null);
            $date = ContractFinancialCollectionReversalPolicy::normalizeReversalDate($row['reversal_date'] ?? null);
            $currency = $this->currencyFromStorage($row['currency_code'] ?? null);
            $money = $this->reversalMoney($row['amount'] ?? null, $currency);
            $state = ContractFinancialCollectionReversalPolicy::normalizeState($row['reversal_state'] ?? null);
        } catch (Throwable $error) {
            throw new UnexpectedValueException('Enterprise Contract collection reversal revision contains invalid persisted data.', 0, $error);
        }
        if ($expectedReversalUuid !== null && $reversalUuid !== $expectedReversalUuid) {
            throw new UnexpectedValueException('Enterprise Contract collection reversal lookup returned a different identity.');
        }
        $row['id'] = $id;
        $row['revision_uuid'] = $revisionUuid;
        $row['contract_id'] = $contractId;
        $row['financial_currency_profile_id'] = $profileId;
        $row['reversal_uuid'] = $reversalUuid;
        $row['revision_number'] = $revision;
        $row['receipt_uuid'] = $receiptUuid;
        $row['schedule_entry_uuid'] = $scheduleUuid;
        $row['schedule_sequence_no'] = $sequence;
        $row['external_reference'] = $reference;
        $row['reversal_date'] = $date;
        $row['amount'] = $money->amount();
        $row['currency_code'] = $money->currencyCode();
        $row['reversal_state'] = $state;
        $row['created_by'] = $createdBy;
        foreach (array_keys($row) as $key) {
            if (str_starts_with((string) $key, 'authoritative_')) {
                unset($row[$key]);
            }
        }
        return $row;
    }

    private function nextRevisionNumber(int $current): int
    {
        if ($current <= 0 || $current >= ContractFinancialCollectionReversalPolicy::MAX_REVISION) {
            throw new RuntimeException('Enterprise Contract collection reversal revision limit has been reached.');
        }
        return $current + 1;
    }

    private function assertActor(int $actorId): void
    {
        if ($actorId <= 0) {
            throw new InvalidArgumentException('Enterprise Contract collection reversal mutation requires an authenticated actor.');
        }
    }

    private function currencyFromStorage(mixed $value): CurrencyCode
    {
        try {
            return CurrencyCode::from($value);
        } catch (Throwable $error) {
            throw new UnexpectedValueException('Enterprise Contract collection reversal currency is invalid.', 0, $error);
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
            throw new RuntimeException('Enterprise Contract collection reversal access requires core tenant enforcement.');
        }
        return TenantContextStore::context()->requireTenantId();
    }
}
