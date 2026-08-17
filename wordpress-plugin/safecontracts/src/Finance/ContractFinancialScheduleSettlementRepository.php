<?php

declare(strict_types=1);

namespace SafeContracts\Finance;

use InvalidArgumentException;
use RuntimeException;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;
use Throwable;
use UnexpectedValueException;

final class ContractFinancialScheduleSettlementRepository
{
    /**
     * @param callable(array<string,mixed>):void $authorizeLockedContract
     * @return array{entries:list<array<string,mixed>>,summary:array<string,string>}
     */
    public function reconcileContract(int $contractId, callable $authorizeLockedContract): array
    {
        if ($contractId <= 0) {
            throw new InvalidArgumentException('Contract ID must be positive.');
        }

        global $wpdb;
        $tenantId = $this->tenantId();
        $schedules = $wpdb->prefix . 'safecontracts_contract_financial_payment_schedule_entry_revisions';
        $receipts = $wpdb->prefix . 'safecontracts_contract_financial_collection_receipt_revisions';
        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Enterprise Contract schedule settlement read transaction.');
        }

        try {
            $this->lockContract($contractId, $authorizeLockedContract);
            $profile = $this->lockProfile($contractId);

            $scheduleLimit = ContractFinancialPaymentSchedulePolicy::MAX_ENTRIES + 1;
            $scheduleRows = $wpdb->get_results($wpdb->prepare(
                "SELECT id, contract_id, financial_currency_profile_id, schedule_entry_uuid, revision_number, sequence_no, reference, due_date, amount, currency_code, schedule_entry_state, created_by, created_at
                 FROM {$schedules} s
                 WHERE s.tenant_id = %d AND s.contract_id = %d
                   AND NOT EXISTS (
                        SELECT 1 FROM {$schedules} newer
                        WHERE newer.tenant_id = s.tenant_id
                          AND newer.contract_id = s.contract_id
                          AND newer.schedule_entry_uuid = s.schedule_entry_uuid
                          AND (
                               newer.revision_number > s.revision_number
                               OR (newer.revision_number = s.revision_number AND newer.id > s.id)
                          )
                   )
                 ORDER BY s.sequence_no ASC, s.schedule_entry_uuid ASC
                 LIMIT %d",
                $tenantId,
                $contractId,
                $scheduleLimit
            ), ARRAY_A);
            if (! is_array($scheduleRows)) {
                throw new RuntimeException('Enterprise Contract schedule settlement schedules could not be read.');
            }
            if (count($scheduleRows) > ContractFinancialPaymentSchedulePolicy::MAX_ENTRIES) {
                throw new RuntimeException('Enterprise Contract schedule settlement schedule limit was exceeded.');
            }

            $receiptLimit = ContractFinancialCollectionReceiptPolicy::MAX_RECEIPTS + 1;
            $receiptRows = $wpdb->get_results($wpdb->prepare(
                "SELECT id, contract_id, financial_currency_profile_id, receipt_uuid, revision_number, schedule_entry_uuid, schedule_sequence_no, external_reference, received_date, amount, currency_code, receipt_state, created_by, created_at
                 FROM {$receipts} r
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
                 ORDER BY r.schedule_entry_uuid ASC, r.received_date ASC, r.receipt_uuid ASC
                 LIMIT %d",
                $tenantId,
                $contractId,
                $receiptLimit
            ), ARRAY_A);
            if (! is_array($receiptRows)) {
                throw new RuntimeException('Enterprise Contract schedule settlement receipts could not be read.');
            }
            if (count($receiptRows) > ContractFinancialCollectionReceiptPolicy::MAX_RECEIPTS) {
                throw new RuntimeException('Enterprise Contract schedule settlement receipt limit was exceeded.');
            }

            $currency = $profile['currency'];
            $zero = Money::of('0', $currency);
            $schedulesByUuid = [];
            $scheduleOrder = [];
            $seenSequences = [];
            foreach ($scheduleRows as $row) {
                if (! is_array($row)) {
                    throw new UnexpectedValueException('Enterprise Contract schedule settlement schedule row is invalid.');
                }
                $schedule = $this->normalizeSchedule($row, $contractId, $profile);
                $uuid = (string) $schedule['schedule_entry_uuid'];
                $sequence = (int) $schedule['sequence_no'];
                if (isset($schedulesByUuid[$uuid])) {
                    throw new UnexpectedValueException('Enterprise Contract schedule settlement contains duplicate latest schedule identities.');
                }
                if (isset($seenSequences[$sequence])) {
                    throw new UnexpectedValueException('Enterprise Contract schedule settlement contains duplicate latest schedule sequence numbers.');
                }
                $schedulesByUuid[$uuid] = $schedule;
                $scheduleOrder[] = $uuid;
                $seenSequences[$sequence] = true;
            }

            $collectedBySchedule = [];
            $seenReceipts = [];
            foreach ($receiptRows as $row) {
                if (! is_array($row)) {
                    throw new UnexpectedValueException('Enterprise Contract schedule settlement receipt row is invalid.');
                }
                $receipt = $this->normalizeReceipt($row, $contractId, $profile);
                $receiptUuid = (string) $receipt['receipt_uuid'];
                if (isset($seenReceipts[$receiptUuid])) {
                    throw new UnexpectedValueException('Enterprise Contract schedule settlement contains duplicate latest receipt identities.');
                }
                $seenReceipts[$receiptUuid] = true;

                $scheduleUuid = (string) $receipt['schedule_entry_uuid'];
                if (! isset($schedulesByUuid[$scheduleUuid])) {
                    throw new UnexpectedValueException('Enterprise Contract schedule settlement receipt references a missing current schedule identity.');
                }
                $schedule = $schedulesByUuid[$scheduleUuid];
                if ((int) $receipt['schedule_sequence_no'] !== (int) $schedule['sequence_no']) {
                    throw new UnexpectedValueException('Enterprise Contract schedule settlement receipt sequence snapshot differs from the current schedule.');
                }
                if ((string) $receipt['receipt_state'] !== ContractFinancialCollectionReceiptPolicy::STATE_RECORDED) {
                    continue;
                }

                $receiptMoney = Money::of((string) $receipt['amount'], $currency);
                $collectedBySchedule[$scheduleUuid] = ($collectedBySchedule[$scheduleUuid] ?? $zero)->add($receiptMoney);
            }

            $scheduledTotal = $zero;
            $collectedTotal = $zero;
            $remainingTotal = $zero;
            $overCollectedTotal = $zero;
            $voidedScheduleCollectedTotal = $zero;
            $entries = [];

            foreach ($scheduleOrder as $scheduleUuid) {
                $schedule = $schedulesByUuid[$scheduleUuid];
                $scheduledMoney = Money::of((string) $schedule['amount'], $currency);
                $collectedMoney = $collectedBySchedule[$scheduleUuid] ?? $zero;
                $state = ContractFinancialScheduleSettlementPolicy::derive(
                    (string) $schedule['schedule_entry_state'],
                    $scheduledMoney,
                    $collectedMoney
                );

                $remaining = $zero;
                $overCollected = $zero;
                if ((string) $schedule['schedule_entry_state'] === ContractFinancialPaymentSchedulePolicy::STATE_VOIDED) {
                    $voidedScheduleCollectedTotal = $voidedScheduleCollectedTotal->add($collectedMoney);
                } else {
                    $scheduledTotal = $scheduledTotal->add($scheduledMoney);
                    $collectedTotal = $collectedTotal->add($collectedMoney);
                    $comparison = $collectedMoney->compare($scheduledMoney);
                    if ($comparison <= 0) {
                        $remaining = $scheduledMoney->subtract($collectedMoney);
                        $remainingTotal = $remainingTotal->add($remaining);
                    } else {
                        $overCollected = $collectedMoney->subtract($scheduledMoney);
                        $overCollectedTotal = $overCollectedTotal->add($overCollected);
                    }
                }

                $entries[] = [
                    'schedule_entry_uuid' => (string) $schedule['schedule_entry_uuid'],
                    'sequence_no' => (int) $schedule['sequence_no'],
                    'reference' => $schedule['reference'],
                    'due_date' => (string) $schedule['due_date'],
                    'schedule_state' => (string) $schedule['schedule_entry_state'],
                    'settlement_state' => $state,
                    'scheduled_amount' => $scheduledMoney->amount(),
                    'collected_amount' => $collectedMoney->amount(),
                    'remaining_amount' => $remaining->amount(),
                    'over_collected_amount' => $overCollected->amount(),
                    'currency_code' => $currency->value(),
                ];
            }

            $this->commit('Enterprise Contract schedule settlement read');
            return [
                'entries' => $entries,
                'summary' => [
                    'currency_code' => $currency->value(),
                    'scheduled_total' => $scheduledTotal->amount(),
                    'collected_total' => $collectedTotal->amount(),
                    'remaining_total' => $remainingTotal->amount(),
                    'over_collected_total' => $overCollectedTotal->amount(),
                    'voided_schedule_collected_total' => $voidedScheduleCollectedTotal->amount(),
                ],
            ];
        } catch (Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    /** @param callable(array<string,mixed>):void $authorizeLockedContract */
    private function lockContract(int $contractId, callable $authorizeLockedContract): void
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

    /** @param array<string,mixed> $row @param array{id:int,currency:CurrencyCode} $profile @return array<string,mixed> */
    private function normalizeSchedule(array $row, int $expectedContractId, array $profile): array
    {
        $id = (int) ($row['id'] ?? 0);
        $contractId = (int) ($row['contract_id'] ?? 0);
        $profileId = (int) ($row['financial_currency_profile_id'] ?? 0);
        $revision = (int) ($row['revision_number'] ?? 0);
        $createdBy = (int) ($row['created_by'] ?? 0);
        if ($id <= 0 || $contractId !== $expectedContractId || $profileId !== (int) $profile['id'] || $revision <= 0 || $createdBy <= 0) {
            throw new UnexpectedValueException('Enterprise Contract schedule settlement schedule metadata is invalid.');
        }
        try {
            $uuid = ContractFinancialPaymentSchedulePolicy::normalizeUuid($row['schedule_entry_uuid'] ?? null, 'schedule entry UUID');
            $sequence = ContractFinancialPaymentSchedulePolicy::normalizeSequence($row['sequence_no'] ?? null);
            $reference = ContractFinancialPaymentSchedulePolicy::normalizeReference($row['reference'] ?? null);
            $dueDate = ContractFinancialPaymentSchedulePolicy::normalizeDueDate($row['due_date'] ?? null);
            $state = ContractFinancialPaymentSchedulePolicy::normalizeState($row['schedule_entry_state'] ?? null);
            $currency = $this->currencyFromStorage($row['currency_code'] ?? null);
            $money = Money::of($row['amount'] ?? null, $currency);
        } catch (Throwable $error) {
            throw new UnexpectedValueException('Enterprise Contract schedule settlement schedule data is invalid.', 0, $error);
        }
        if (! $currency->equals($profile['currency']) || $money->compare(Money::of('0', $currency)) <= 0) {
            throw new UnexpectedValueException('Enterprise Contract schedule settlement schedule currency or amount is invalid.');
        }
        $row['schedule_entry_uuid'] = $uuid;
        $row['sequence_no'] = $sequence;
        $row['reference'] = $reference;
        $row['due_date'] = $dueDate;
        $row['amount'] = $money->amount();
        $row['currency_code'] = $currency->value();
        $row['schedule_entry_state'] = $state;
        return $row;
    }

    /** @param array<string,mixed> $row @param array{id:int,currency:CurrencyCode} $profile @return array<string,mixed> */
    private function normalizeReceipt(array $row, int $expectedContractId, array $profile): array
    {
        $id = (int) ($row['id'] ?? 0);
        $contractId = (int) ($row['contract_id'] ?? 0);
        $profileId = (int) ($row['financial_currency_profile_id'] ?? 0);
        $revision = (int) ($row['revision_number'] ?? 0);
        $createdBy = (int) ($row['created_by'] ?? 0);
        if ($id <= 0 || $contractId !== $expectedContractId || $profileId !== (int) $profile['id'] || $revision <= 0 || $createdBy <= 0) {
            throw new UnexpectedValueException('Enterprise Contract schedule settlement receipt metadata is invalid.');
        }
        try {
            $receiptUuid = ContractFinancialCollectionReceiptPolicy::normalizeUuid($row['receipt_uuid'] ?? null, 'receipt UUID');
            $scheduleUuid = ContractFinancialCollectionReceiptPolicy::normalizeUuid($row['schedule_entry_uuid'] ?? null, 'schedule entry UUID');
            $sequence = ContractFinancialPaymentSchedulePolicy::normalizeSequence($row['schedule_sequence_no'] ?? null);
            $reference = ContractFinancialCollectionReceiptPolicy::normalizeReference($row['external_reference'] ?? null);
            $receivedDate = ContractFinancialCollectionReceiptPolicy::normalizeReceivedDate($row['received_date'] ?? null);
            $state = ContractFinancialCollectionReceiptPolicy::normalizeState($row['receipt_state'] ?? null);
            $currency = $this->currencyFromStorage($row['currency_code'] ?? null);
            $money = Money::of($row['amount'] ?? null, $currency);
        } catch (Throwable $error) {
            throw new UnexpectedValueException('Enterprise Contract schedule settlement receipt data is invalid.', 0, $error);
        }
        if (! $currency->equals($profile['currency']) || $money->compare(Money::of('0', $currency)) <= 0) {
            throw new UnexpectedValueException('Enterprise Contract schedule settlement receipt currency or amount is invalid.');
        }
        $row['receipt_uuid'] = $receiptUuid;
        $row['schedule_entry_uuid'] = $scheduleUuid;
        $row['schedule_sequence_no'] = $sequence;
        $row['external_reference'] = $reference;
        $row['received_date'] = $receivedDate;
        $row['amount'] = $money->amount();
        $row['currency_code'] = $currency->value();
        $row['receipt_state'] = $state;
        return $row;
    }

    private function currencyFromStorage(mixed $value): CurrencyCode
    {
        try {
            return CurrencyCode::from($value);
        } catch (Throwable $error) {
            throw new UnexpectedValueException('Enterprise Contract schedule settlement currency is invalid.', 0, $error);
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
            throw new RuntimeException('Enterprise Contract schedule settlement access requires core tenant enforcement.');
        }
        return TenantContextStore::context()->requireTenantId();
    }
}
