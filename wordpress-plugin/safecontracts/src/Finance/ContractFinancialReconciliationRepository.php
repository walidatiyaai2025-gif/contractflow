<?php

declare(strict_types=1);

namespace SafeContracts\Finance;

use RuntimeException;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;
use Throwable;
use UnexpectedValueException;

final class ContractFinancialReconciliationRepository
{
    private const MAX_BASE_REVISION = 2147483647;

    /**
     * @param callable(array<string,mixed>):void $authorizeLockedContract
     * @return array{profile:array{id:int,currency:string},base:array{amount:string,currency:string,profile_id:int,revision_number:int},adjustments:list<array{line_uuid:string,revision_number:int,kind:string,amount:string,currency:string,state:string,profile_id:int}>}
     */
    public function snapshot(int $contractId, callable $authorizeLockedContract): array
    {
        if ($contractId <= 0) {
            throw new RuntimeException('Enterprise financial reconciliation requires a positive Contract identifier.');
        }

        global $wpdb;
        $tenantId = $this->tenantId();
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $profiles = $wpdb->prefix . 'safecontracts_contract_financial_currency_profiles';
        $baseRevisions = $wpdb->prefix . 'safecontracts_contract_financial_base_value_revisions';
        $adjustmentRevisions = $wpdb->prefix . 'safecontracts_contract_financial_adjustment_revisions';

        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Enterprise financial reconciliation snapshot transaction.');
        }

        try {
            $contractRows = $wpdb->get_results($wpdb->prepare(
                "SELECT id, accountant_user_id, status, is_archived
                 FROM {$contracts}
                 WHERE id = %d AND tenant_id = %d
                 LIMIT 2 FOR UPDATE",
                $contractId,
                $tenantId
            ), ARRAY_A);
            if (! is_array($contractRows) || count($contractRows) !== 1 || ! is_array($contractRows[0])) {
                throw new RuntimeException('Enterprise Contract was not found in the current tenant or has invalid cardinality.');
            }
            $contract = $contractRows[0];
            if ((int) ($contract['id'] ?? 0) !== $contractId) {
                throw new UnexpectedValueException('Locked Enterprise Contract identity is invalid.');
            }

            // Scope authorization must use the exact row protected by the Contract lock and
            // must complete before any profile, base-value or adjustment state is observed.
            $authorizeLockedContract($contract);

            $profileRows = $wpdb->get_results($wpdb->prepare(
                "SELECT id, contract_id, contract_currency
                 FROM {$profiles}
                 WHERE tenant_id = %d AND contract_id = %d
                 LIMIT 2",
                $tenantId,
                $contractId
            ), ARRAY_A);
            if (! is_array($profileRows) || count($profileRows) !== 1 || ! is_array($profileRows[0])) {
                throw new RuntimeException('Enterprise financial reconciliation requires exactly one Contract financial currency profile.');
            }
            $profileId = (int) ($profileRows[0]['id'] ?? 0);
            $profileContractId = (int) ($profileRows[0]['contract_id'] ?? 0);
            $profileCurrency = $this->currency($profileRows[0]['contract_currency'] ?? null, 'Contract financial currency profile');
            if ($profileId <= 0 || $profileContractId !== $contractId) {
                throw new UnexpectedValueException('Enterprise Contract financial currency profile identity is invalid.');
            }

            $baseRows = $wpdb->get_results($wpdb->prepare(
                "SELECT id, uuid, contract_id, financial_currency_profile_id, revision_number,
                        amount, currency_code, created_by
                 FROM {$baseRevisions}
                 WHERE tenant_id = %d AND contract_id = %d
                 ORDER BY revision_number DESC, id DESC
                 LIMIT 1",
                $tenantId,
                $contractId
            ), ARRAY_A);
            if (! is_array($baseRows) || count($baseRows) !== 1 || ! is_array($baseRows[0])) {
                throw new RuntimeException('Enterprise financial reconciliation requires an explicit Contract base-value revision.');
            }
            $base = $baseRows[0];
            $baseId = (int) ($base['id'] ?? 0);
            $baseContractId = (int) ($base['contract_id'] ?? 0);
            $baseProfileId = (int) ($base['financial_currency_profile_id'] ?? 0);
            $baseRevisionNumber = (int) ($base['revision_number'] ?? 0);
            $baseCreatedBy = (int) ($base['created_by'] ?? 0);
            $this->uuid($base['uuid'] ?? null, 'Contract base-value revision UUID');
            if ($baseId <= 0 || $baseContractId !== $contractId || $baseProfileId !== $profileId
                || $baseRevisionNumber <= 0 || $baseRevisionNumber > self::MAX_BASE_REVISION || $baseCreatedBy <= 0) {
                throw new UnexpectedValueException('Enterprise Contract base-value revision has invalid identity, profile or revision metadata.');
            }
            $baseMoney = $this->money($base['amount'] ?? null, $base['currency_code'] ?? null, 'Contract base value');
            if (! $baseMoney->currency()->equals($profileCurrency)) {
                throw new UnexpectedValueException('Enterprise Contract base-value currency differs from its financial currency profile.');
            }
            if ($baseMoney->compare(Money::of('0', $profileCurrency)) < 0) {
                throw new UnexpectedValueException('Stored Enterprise Contract base value cannot be negative.');
            }

            $limit = ContractFinancialAdjustmentPolicy::MAX_LINES + 1;
            $adjustmentRows = $wpdb->get_results($wpdb->prepare(
                "SELECT r.id, r.revision_uuid, r.contract_id, r.financial_currency_profile_id, r.line_uuid,
                        r.revision_number, r.adjustment_kind, r.description, r.amount,
                        r.currency_code, r.line_state, r.created_by
                 FROM {$adjustmentRevisions} r
                 WHERE r.tenant_id = %d AND r.contract_id = %d
                   AND NOT EXISTS (
                        SELECT 1 FROM {$adjustmentRevisions} newer
                        WHERE newer.tenant_id = r.tenant_id
                          AND newer.contract_id = r.contract_id
                          AND newer.line_uuid = r.line_uuid
                          AND (
                               newer.revision_number > r.revision_number
                               OR (newer.revision_number = r.revision_number AND newer.id > r.id)
                          )
                   )
                 ORDER BY r.line_uuid ASC
                 LIMIT %d",
                $tenantId,
                $contractId,
                $limit
            ), ARRAY_A);
            if (! is_array($adjustmentRows)) {
                throw new RuntimeException('Enterprise financial adjustment reconciliation snapshot could not be read.');
            }
            if (count($adjustmentRows) > ContractFinancialAdjustmentPolicy::MAX_LINES) {
                throw new RuntimeException('Enterprise financial adjustment line limit was exceeded during reconciliation.');
            }

            $currentAdjustments = [];
            $seenLines = [];
            foreach ($adjustmentRows as $row) {
                if (! is_array($row)) {
                    throw new UnexpectedValueException('Enterprise financial adjustment reconciliation row is invalid.');
                }
                try {
                    ContractFinancialAdjustmentPolicy::normalizeUuid($row['revision_uuid'] ?? null, 'revision UUID');
                    $lineUuid = ContractFinancialAdjustmentPolicy::normalizeUuid($row['line_uuid'] ?? null, 'line UUID');
                    $kind = ContractFinancialAdjustmentPolicy::normalizeKind($row['adjustment_kind'] ?? null);
                    ContractFinancialAdjustmentPolicy::normalizeDescription($row['description'] ?? null);
                    $state = ContractFinancialAdjustmentPolicy::normalizeState($row['line_state'] ?? null);
                } catch (Throwable $error) {
                    throw new UnexpectedValueException('Enterprise financial adjustment reconciliation contains invalid persisted metadata.', 0, $error);
                }

                $rowId = (int) ($row['id'] ?? 0);
                $rowContractId = (int) ($row['contract_id'] ?? 0);
                $rowProfileId = (int) ($row['financial_currency_profile_id'] ?? 0);
                $revisionNumber = (int) ($row['revision_number'] ?? 0);
                $createdBy = (int) ($row['created_by'] ?? 0);
                if ($rowId <= 0 || $rowContractId !== $contractId || $rowProfileId !== $profileId
                    || $revisionNumber <= 0 || $revisionNumber > ContractFinancialAdjustmentPolicy::MAX_REVISION || $createdBy <= 0) {
                    throw new UnexpectedValueException('Enterprise financial adjustment reconciliation has invalid identity, profile or revision metadata.');
                }
                if (isset($seenLines[$lineUuid])) {
                    throw new UnexpectedValueException('Enterprise financial reconciliation contains duplicate latest adjustment line identities.');
                }
                $seenLines[$lineUuid] = true;

                $money = $this->money($row['amount'] ?? null, $row['currency_code'] ?? null, 'financial adjustment');
                if (! $money->currency()->equals($profileCurrency)) {
                    throw new UnexpectedValueException('Enterprise financial adjustment currency differs from the Contract financial currency profile.');
                }
                if ($money->compare(Money::of('0', $profileCurrency)) < 0) {
                    throw new UnexpectedValueException('Stored Enterprise financial adjustment amount cannot be negative.');
                }

                $currentAdjustments[] = [
                    'line_uuid' => $lineUuid,
                    'revision_number' => $revisionNumber,
                    'kind' => $kind,
                    'amount' => $money->amount(),
                    'currency' => $money->currencyCode(),
                    'state' => $state,
                    'profile_id' => $rowProfileId,
                ];
            }

            if ($wpdb->query('COMMIT') === false) {
                throw new RuntimeException('Unable to commit Enterprise financial reconciliation snapshot transaction.');
            }

            return [
                'profile' => ['id' => $profileId, 'currency' => $profileCurrency->value()],
                'base' => [
                    'amount' => $baseMoney->amount(),
                    'currency' => $baseMoney->currencyCode(),
                    'profile_id' => $baseProfileId,
                    'revision_number' => $baseRevisionNumber,
                ],
                'adjustments' => $currentAdjustments,
            ];
        } catch (Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    private function money(mixed $amount, mixed $currency, string $field): Money
    {
        try {
            return Money::of($amount, $this->currency($currency, $field . ' currency'));
        } catch (Throwable $error) {
            throw new UnexpectedValueException("Enterprise {$field} is invalid.", 0, $error);
        }
    }

    private function currency(mixed $value, string $field): CurrencyCode
    {
        try {
            return CurrencyCode::from($value);
        } catch (Throwable $error) {
            throw new UnexpectedValueException("Enterprise {$field} is invalid.", 0, $error);
        }
    }

    private function uuid(mixed $value, string $field): string
    {
        if (! is_string($value)) {
            throw new UnexpectedValueException("Enterprise {$field} is invalid.");
        }
        $uuid = strtolower(trim($value));
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $uuid) !== 1) {
            throw new UnexpectedValueException("Enterprise {$field} is invalid.");
        }
        return $uuid;
    }

    private function tenantId(): int
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise financial reconciliation requires core tenant enforcement.');
        }
        return TenantContextStore::context()->requireTenantId();
    }
}
