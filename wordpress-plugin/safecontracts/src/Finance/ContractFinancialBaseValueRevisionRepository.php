<?php

declare(strict_types=1);

namespace SafeContracts\Finance;

use RuntimeException;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;
use UnexpectedValueException;

final class ContractFinancialBaseValueRevisionRepository
{
    private const SELECT_COLUMNS = 'id, uuid, contract_id, financial_currency_profile_id, revision_number, amount, currency_code, created_by, created_at';
    private const MAX_REVISION = 2147483647;

    /** @return array<string,mixed>|null */
    public function findContract(int $contractId): ?array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, accountant_user_id, status, is_archived FROM {$contracts} WHERE id = %d AND tenant_id = %d LIMIT 1",
            $contractId,
            $tenantId
        ), ARRAY_A);
        if (! is_array($rows) || count($rows) > 1) {
            throw new RuntimeException('Enterprise contract lookup returned an invalid cardinality.');
        }
        return $rows === [] ? null : (is_array($rows[0]) ? $rows[0] : null);
    }

    /** @return array<string,mixed>|null */
    public function findLatestForContract(int $contractId): ?array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $revisions = $wpdb->prefix . 'safecontracts_contract_financial_base_value_revisions';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT " . self::SELECT_COLUMNS . " FROM {$revisions} WHERE tenant_id = %d AND contract_id = %d ORDER BY revision_number DESC, id DESC LIMIT 1",
            $tenantId,
            $contractId
        ), ARRAY_A);
        if (! is_array($rows) || count($rows) > 1) {
            throw new RuntimeException('Contract base-value revision lookup returned an invalid cardinality.');
        }
        if ($rows === []) {
            return null;
        }
        if (! is_array($rows[0])) {
            throw new UnexpectedValueException('Contract base-value revision row is invalid.');
        }
        return $this->normalizeRevision($rows[0], $contractId);
    }

    public function appendOrGetLatest(int $contractId, string $uuid, Money $money, int $actorId): int
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $profiles = $wpdb->prefix . 'safecontracts_contract_financial_currency_profiles';
        $revisions = $wpdb->prefix . 'safecontracts_contract_financial_base_value_revisions';

        $zero = Money::of('0', $money->currency());
        if ($money->compare($zero) < 0) {
            throw new UnexpectedValueException('Enterprise Contract base value cannot be negative.');
        }
        if ($actorId <= 0) {
            throw new UnexpectedValueException('Enterprise Contract base-value revision requires a positive actor.');
        }
        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Contract base-value revision transaction.');
        }

        try {
            $contractRows = $wpdb->get_results($wpdb->prepare(
                "SELECT id, status, is_archived FROM {$contracts} WHERE id = %d AND tenant_id = %d LIMIT 2 FOR UPDATE",
                $contractId,
                $tenantId
            ), ARRAY_A);
            if (! is_array($contractRows) || count($contractRows) !== 1 || ! is_array($contractRows[0])) {
                throw new RuntimeException('Enterprise Contract changed concurrently or is outside the current tenant.');
            }
            $contract = $contractRows[0];
            if ((int) ($contract['is_archived'] ?? 0) !== 0 || (string) ($contract['status'] ?? '') !== 'draft') {
                throw new RuntimeException('Enterprise Contract base value may only be revised while the Contract is an unarchived draft.');
            }

            $profileRows = $wpdb->get_results($wpdb->prepare(
                "SELECT id, contract_id, contract_currency FROM {$profiles} WHERE tenant_id = %d AND contract_id = %d LIMIT 2 FOR UPDATE",
                $tenantId,
                $contractId
            ), ARRAY_A);
            if (! is_array($profileRows) || count($profileRows) !== 1 || ! is_array($profileRows[0])) {
                throw new RuntimeException('Enterprise Contract requires exactly one current-tenant financial currency profile before setting base value.');
            }
            $profileId = (int) ($profileRows[0]['id'] ?? 0);
            $profileContractId = (int) ($profileRows[0]['contract_id'] ?? 0);
            $profileCurrency = CurrencyCode::from($profileRows[0]['contract_currency'] ?? null);
            if ($profileId <= 0 || $profileContractId !== $contractId || ! $profileCurrency->equals($money->currency())) {
                throw new UnexpectedValueException('Enterprise Contract financial currency profile is inconsistent with the base-value command.');
            }

            $latestRows = $wpdb->get_results($wpdb->prepare(
                "SELECT " . self::SELECT_COLUMNS . " FROM {$revisions} WHERE tenant_id = %d AND contract_id = %d ORDER BY revision_number DESC, id DESC LIMIT 1 FOR UPDATE",
                $tenantId,
                $contractId
            ), ARRAY_A);
            if (! is_array($latestRows) || count($latestRows) > 1) {
                throw new RuntimeException('Contract base-value revision lock returned an invalid cardinality.');
            }

            $nextRevision = 1;
            if ($latestRows !== []) {
                if (! is_array($latestRows[0])) {
                    throw new UnexpectedValueException('Contract base-value revision row is invalid.');
                }
                $latest = $this->normalizeRevision($latestRows[0], $contractId);
                if ((int) $latest['financial_currency_profile_id'] !== $profileId) {
                    throw new UnexpectedValueException('Stored Contract base-value revision references a different financial currency profile.');
                }
                $storedMoney = Money::of($latest['amount'], $latest['currency_code']);
                if (! $storedMoney->currency()->equals($profileCurrency)) {
                    throw new UnexpectedValueException('Stored Contract base-value revision currency differs from the financial currency profile.');
                }
                if ($storedMoney->equals($money)) {
                    if ($wpdb->query('COMMIT') === false) {
                        throw new RuntimeException('Unable to commit idempotent Contract base-value retry.');
                    }
                    return (int) $latest['id'];
                }
                $currentRevision = (int) $latest['revision_number'];
                if ($currentRevision >= self::MAX_REVISION) {
                    throw new RuntimeException('Contract base-value revision limit has been reached.');
                }
                $nextRevision = $currentRevision + 1;
            }

            $inserted = $wpdb->query($wpdb->prepare(
                "INSERT INTO {$revisions} (
                    tenant_id, uuid, contract_id, financial_currency_profile_id, revision_number, amount, currency_code, created_by, created_at
                 ) SELECT %d, %s, c.id, p.id, %d, %s, p.contract_currency, %d, UTC_TIMESTAMP()
                 FROM {$contracts} c
                 INNER JOIN {$profiles} p ON p.tenant_id = c.tenant_id AND p.contract_id = c.id
                 WHERE c.id = %d AND c.tenant_id = %d AND c.status = 'draft' AND c.is_archived = 0
                   AND p.id = %d AND p.contract_currency = %s
                 LIMIT 1",
                $tenantId,
                $uuid,
                $nextRevision,
                $money->amount(),
                $actorId,
                $contractId,
                $tenantId,
                $profileId,
                $profileCurrency->value()
            ));
            if ($inserted !== 1) {
                throw new RuntimeException('Unable to append Enterprise Contract base-value revision.');
            }
            $revisionId = (int) ($wpdb->insert_id ?? 0);
            if ($revisionId <= 0) {
                throw new RuntimeException('Contract base-value revision insert returned no identifier.');
            }
            if ($wpdb->query('COMMIT') === false) {
                throw new RuntimeException('Unable to commit Contract base-value revision transaction.');
            }
            return $revisionId;
        } catch (\Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function normalizeRevision(array $row, int $expectedContractId): array
    {
        $id = (int) ($row['id'] ?? 0);
        $contractId = (int) ($row['contract_id'] ?? 0);
        $profileId = (int) ($row['financial_currency_profile_id'] ?? 0);
        $revisionNumber = (int) ($row['revision_number'] ?? 0);
        if ($id <= 0 || $contractId !== $expectedContractId || $profileId <= 0 || $revisionNumber <= 0 || $revisionNumber > self::MAX_REVISION) {
            throw new UnexpectedValueException('Contract base-value revision has invalid identity or revision number.');
        }
        $money = Money::of($row['amount'] ?? null, CurrencyCode::from($row['currency_code'] ?? null));
        if ($money->compare(Money::of('0', $money->currency())) < 0) {
            throw new UnexpectedValueException('Stored Contract base value cannot be negative.');
        }
        $row['id'] = $id;
        $row['contract_id'] = $contractId;
        $row['financial_currency_profile_id'] = $profileId;
        $row['revision_number'] = $revisionNumber;
        $row['amount'] = $money->amount();
        $row['currency_code'] = $money->currencyCode();
        return $row;
    }

    private function tenantId(): int
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise Contract base-value access requires core tenant enforcement.');
        }
        return TenantContextStore::context()->requireTenantId();
    }
}
