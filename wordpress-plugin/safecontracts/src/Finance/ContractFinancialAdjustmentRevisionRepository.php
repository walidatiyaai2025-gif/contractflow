<?php

declare(strict_types=1);

namespace SafeContracts\Finance;

use InvalidArgumentException;
use RuntimeException;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;
use Throwable;
use UnexpectedValueException;

final class ContractFinancialAdjustmentRevisionRepository
{
    private const SELECT_COLUMNS = 'id, revision_uuid, contract_id, financial_currency_profile_id, line_uuid, revision_number, adjustment_kind, description, amount, currency_code, line_state, created_by, created_at';

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
            throw new RuntimeException('Enterprise Contract lookup returned an invalid cardinality.');
        }
        return $rows === [] ? null : (is_array($rows[0]) ? $rows[0] : null);
    }

    /** @return list<array<string,mixed>> */
    public function listCurrentForContract(int $contractId): array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $profiles = $wpdb->prefix . 'safecontracts_contract_financial_currency_profiles';
        $revisions = $wpdb->prefix . 'safecontracts_contract_financial_adjustment_revisions';
        $limit = ContractFinancialAdjustmentPolicy::MAX_LINES + 1;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT r.id, r.revision_uuid, r.contract_id, r.financial_currency_profile_id, r.line_uuid,
                    r.revision_number, r.adjustment_kind, r.description, r.amount, r.currency_code,
                    r.line_state, r.created_by, r.created_at,
                    p.id AS profile_match_id, p.contract_currency AS profile_currency
             FROM {$revisions} r
             LEFT JOIN {$profiles} p
               ON p.tenant_id = r.tenant_id
              AND p.id = r.financial_currency_profile_id
              AND p.contract_id = r.contract_id
             WHERE r.tenant_id = %d AND r.contract_id = %d
               AND NOT EXISTS (
                    SELECT 1 FROM {$revisions} newer
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

        if (! is_array($rows)) {
            throw new RuntimeException('Enterprise Contract financial adjustment list could not be read.');
        }
        if (count($rows) > ContractFinancialAdjustmentPolicy::MAX_LINES) {
            throw new RuntimeException('Enterprise Contract financial adjustment line limit was exceeded.');
        }

        $normalized = [];
        $seenLines = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                throw new UnexpectedValueException('Enterprise Contract financial adjustment row is invalid.');
            }
            $revision = $this->normalizeRevision($row, $contractId);
            $lineUuid = (string) $revision['line_uuid'];
            if (isset($seenLines[$lineUuid])) {
                throw new UnexpectedValueException('Enterprise Contract financial adjustment list contains duplicate latest line identities.');
            }
            $seenLines[$lineUuid] = true;
            $profileId = (int) ($row['profile_match_id'] ?? 0);
            if ($profileId !== (int) $revision['financial_currency_profile_id']) {
                throw new UnexpectedValueException('Enterprise Contract financial adjustment has no matching current-tenant currency profile.');
            }
            $profileCurrency = $this->currencyFromStorage($row['profile_currency'] ?? null, 'financial profile currency');
            if (! $profileCurrency->equals(CurrencyCode::from((string) $revision['currency_code']))) {
                throw new UnexpectedValueException('Enterprise Contract financial adjustment currency differs from its financial profile.');
            }
            $normalized[] = $revision;
        }

        return $normalized;
    }

    public function createLine(
        int $contractId,
        string $lineUuid,
        string $revisionUuid,
        string $kind,
        string $description,
        Money $money,
        int $actorId
    ): int {
        $lineUuid = ContractFinancialAdjustmentPolicy::normalizeUuid($lineUuid, 'line UUID');
        $revisionUuid = ContractFinancialAdjustmentPolicy::normalizeUuid($revisionUuid, 'revision UUID');
        $kind = ContractFinancialAdjustmentPolicy::normalizeKind($kind);
        $description = ContractFinancialAdjustmentPolicy::normalizeDescription($description);
        $this->assertMoneyAndActor($money, $actorId);

        global $wpdb;
        $tenantId = $this->tenantId();
        $revisions = $wpdb->prefix . 'safecontracts_contract_financial_adjustment_revisions';
        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Enterprise financial adjustment creation transaction.');
        }

        try {
            $profile = $this->lockContractAndProfile($contractId, $money);

            $countRows = $wpdb->get_results($wpdb->prepare(
                "SELECT COUNT(DISTINCT line_uuid) AS total FROM {$revisions} WHERE tenant_id = %d AND contract_id = %d",
                $tenantId,
                $contractId
            ), ARRAY_A);
            if (! is_array($countRows) || count($countRows) !== 1 || ! is_array($countRows[0])) {
                throw new RuntimeException('Enterprise financial adjustment line count returned an invalid cardinality.');
            }
            $lineCount = (int) ($countRows[0]['total'] ?? -1);
            if ($lineCount < 0 || $lineCount >= ContractFinancialAdjustmentPolicy::MAX_LINES) {
                throw new RuntimeException('Enterprise Contract financial adjustment line limit has been reached.');
            }

            $collisionRows = $wpdb->get_results($wpdb->prepare(
                "SELECT id FROM {$revisions} WHERE tenant_id = %d AND contract_id = %d AND line_uuid = %s LIMIT 2 FOR UPDATE",
                $tenantId,
                $contractId,
                $lineUuid
            ), ARRAY_A);
            if (! is_array($collisionRows) || $collisionRows !== []) {
                throw new RuntimeException('Enterprise financial adjustment line identity already exists or could not be verified.');
            }

            $revisionId = $this->insertRevision(
                $contractId,
                (int) $profile['id'],
                $lineUuid,
                $revisionUuid,
                1,
                $kind,
                $description,
                $money,
                ContractFinancialAdjustmentPolicy::STATE_ACTIVE,
                $actorId
            );
            $this->commit('Enterprise financial adjustment creation');
            return $revisionId;
        } catch (Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    public function reviseLine(
        int $contractId,
        string $lineUuid,
        string $revisionUuid,
        string $kind,
        string $description,
        Money $money,
        int $actorId
    ): int {
        $lineUuid = ContractFinancialAdjustmentPolicy::normalizeUuid($lineUuid, 'line UUID');
        $revisionUuid = ContractFinancialAdjustmentPolicy::normalizeUuid($revisionUuid, 'revision UUID');
        $kind = ContractFinancialAdjustmentPolicy::normalizeKind($kind);
        $description = ContractFinancialAdjustmentPolicy::normalizeDescription($description);
        $this->assertMoneyAndActor($money, $actorId);

        global $wpdb;
        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Enterprise financial adjustment revision transaction.');
        }

        try {
            $profile = $this->lockContractAndProfile($contractId, $money);
            $latest = $this->lockLatestLine($contractId, $lineUuid);
            $this->assertRevisionProfile($latest, $profile);
            if ((string) $latest['line_state'] !== ContractFinancialAdjustmentPolicy::STATE_ACTIVE) {
                throw new RuntimeException('Voided Enterprise financial adjustment lines cannot be revised or reactivated.');
            }

            $storedMoney = Money::of((string) $latest['amount'], (string) $latest['currency_code']);
            if ((string) $latest['adjustment_kind'] === $kind
                && (string) $latest['description'] === $description
                && $storedMoney->equals($money)) {
                $this->commit('idempotent Enterprise financial adjustment revision');
                return (int) $latest['id'];
            }

            $nextRevision = $this->nextRevisionNumber((int) $latest['revision_number']);
            $revisionId = $this->insertRevision(
                $contractId,
                (int) $profile['id'],
                $lineUuid,
                $revisionUuid,
                $nextRevision,
                $kind,
                $description,
                $money,
                ContractFinancialAdjustmentPolicy::STATE_ACTIVE,
                $actorId
            );
            $this->commit('Enterprise financial adjustment revision');
            return $revisionId;
        } catch (Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    public function voidLine(int $contractId, string $lineUuid, string $revisionUuid, int $actorId): int
    {
        $lineUuid = ContractFinancialAdjustmentPolicy::normalizeUuid($lineUuid, 'line UUID');
        $revisionUuid = ContractFinancialAdjustmentPolicy::normalizeUuid($revisionUuid, 'revision UUID');
        if ($actorId <= 0) {
            throw new InvalidArgumentException('Enterprise financial adjustment mutation requires an authenticated actor.');
        }

        global $wpdb;
        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Enterprise financial adjustment void transaction.');
        }

        try {
            $this->lockDraftContract($contractId);
            $profile = $this->lockProfile($contractId);
            $latest = $this->lockLatestLine($contractId, $lineUuid);
            $this->assertRevisionProfile($latest, $profile);
            $storedMoney = Money::of((string) $latest['amount'], (string) $latest['currency_code']);

            if ((string) $latest['line_state'] === ContractFinancialAdjustmentPolicy::STATE_VOIDED) {
                $this->commit('idempotent Enterprise financial adjustment void');
                return (int) $latest['id'];
            }

            $nextRevision = $this->nextRevisionNumber((int) $latest['revision_number']);
            $revisionId = $this->insertRevision(
                $contractId,
                (int) $profile['id'],
                $lineUuid,
                $revisionUuid,
                $nextRevision,
                (string) $latest['adjustment_kind'],
                (string) $latest['description'],
                $storedMoney,
                ContractFinancialAdjustmentPolicy::STATE_VOIDED,
                $actorId
            );
            $this->commit('Enterprise financial adjustment void');
            return $revisionId;
        } catch (Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    /** @return array{id:int,currency:CurrencyCode} */
    private function lockContractAndProfile(int $contractId, Money $money): array
    {
        $this->lockDraftContract($contractId);
        return $this->lockProfileForMoney($contractId, $money);
    }

    private function lockDraftContract(int $contractId): void
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, status, is_archived FROM {$contracts} WHERE id = %d AND tenant_id = %d LIMIT 2 FOR UPDATE",
            $contractId,
            $tenantId
        ), ARRAY_A);
        if (! is_array($rows) || count($rows) !== 1 || ! is_array($rows[0])) {
            throw new RuntimeException('Enterprise Contract changed concurrently or is outside the current tenant.');
        }
        if ((int) ($rows[0]['is_archived'] ?? 0) !== 0 || (string) ($rows[0]['status'] ?? '') !== 'draft') {
            throw new RuntimeException('Enterprise financial adjustments may only change while the Contract is an unarchived draft.');
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
        $profileContractId = (int) ($rows[0]['contract_id'] ?? 0);
        $profileCurrency = $this->currencyFromStorage($rows[0]['contract_currency'] ?? null, 'financial profile currency');
        if ($profileId <= 0 || $profileContractId !== $contractId) {
            throw new UnexpectedValueException('Enterprise Contract financial profile identity is invalid.');
        }
        return ['id' => $profileId, 'currency' => $profileCurrency];
    }

    /** @return array{id:int,currency:CurrencyCode} */
    private function lockProfileForMoney(int $contractId, Money $money): array
    {
        $profile = $this->lockProfile($contractId);
        if (! $profile['currency']->equals($money->currency())) {
            throw new UnexpectedValueException('Enterprise Contract financial profile is inconsistent with the adjustment currency.');
        }
        return $profile;
    }

    /** @return array<string,mixed> */
    private function lockLatestLine(int $contractId, string $lineUuid): array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $revisions = $wpdb->prefix . 'safecontracts_contract_financial_adjustment_revisions';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT " . self::SELECT_COLUMNS . " FROM {$revisions}
             WHERE tenant_id = %d AND contract_id = %d AND line_uuid = %s
             ORDER BY revision_number DESC, id DESC LIMIT 1 FOR UPDATE",
            $tenantId,
            $contractId,
            $lineUuid
        ), ARRAY_A);
        if (! is_array($rows) || count($rows) !== 1 || ! is_array($rows[0])) {
            throw new RuntimeException('Enterprise financial adjustment line was not found in the current Contract or has invalid cardinality.');
        }
        return $this->normalizeRevision($rows[0], $contractId, $lineUuid);
    }

    /** @param array<string,mixed> $revision @param array{id:int,currency:CurrencyCode} $profile */
    private function assertRevisionProfile(array $revision, array $profile): void
    {
        if ((int) $revision['financial_currency_profile_id'] !== (int) $profile['id']) {
            throw new UnexpectedValueException('Enterprise financial adjustment revision references a different financial profile.');
        }
        $currency = CurrencyCode::from((string) $revision['currency_code']);
        if (! $currency->equals($profile['currency'])) {
            throw new UnexpectedValueException('Enterprise financial adjustment revision currency differs from the financial profile.');
        }
    }

    private function insertRevision(
        int $contractId,
        int $profileId,
        string $lineUuid,
        string $revisionUuid,
        int $revisionNumber,
        string $kind,
        string $description,
        Money $money,
        string $state,
        int $actorId
    ): int {
        global $wpdb;
        $tenantId = $this->tenantId();
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $profiles = $wpdb->prefix . 'safecontracts_contract_financial_currency_profiles';
        $revisions = $wpdb->prefix . 'safecontracts_contract_financial_adjustment_revisions';
        $state = ContractFinancialAdjustmentPolicy::normalizeState($state);

        $inserted = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$revisions} (
                tenant_id, revision_uuid, contract_id, financial_currency_profile_id, line_uuid,
                revision_number, adjustment_kind, description, amount, currency_code, line_state,
                created_by, created_at
             ) SELECT %d, %s, c.id, p.id, %s, %d, %s, %s, %s, p.contract_currency, %s, %d, UTC_TIMESTAMP()
             FROM {$contracts} c
             INNER JOIN {$profiles} p ON p.tenant_id = c.tenant_id AND p.contract_id = c.id
             WHERE c.id = %d AND c.tenant_id = %d AND c.status = 'draft' AND c.is_archived = 0
               AND p.id = %d AND p.contract_currency = %s
             LIMIT 1",
            $tenantId,
            $revisionUuid,
            $lineUuid,
            $revisionNumber,
            $kind,
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
            throw new RuntimeException('Unable to append Enterprise Contract financial adjustment revision.');
        }
        $revisionId = (int) ($wpdb->insert_id ?? 0);
        if ($revisionId <= 0) {
            throw new RuntimeException('Enterprise financial adjustment revision insert returned no identifier.');
        }
        return $revisionId;
    }

    private function nextRevisionNumber(int $current): int
    {
        if ($current <= 0 || $current >= ContractFinancialAdjustmentPolicy::MAX_REVISION) {
            throw new RuntimeException('Enterprise financial adjustment revision limit has been reached.');
        }
        return $current + 1;
    }

    private function assertMoneyAndActor(Money $money, int $actorId): void
    {
        if ($money->compare(Money::of('0', $money->currency())) < 0) {
            throw new InvalidArgumentException('Enterprise financial adjustment amount cannot be negative.');
        }
        if ($actorId <= 0) {
            throw new InvalidArgumentException('Enterprise financial adjustment mutation requires an authenticated actor.');
        }
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function normalizeRevision(array $row, int $expectedContractId, ?string $expectedLineUuid = null): array
    {
        $id = (int) ($row['id'] ?? 0);
        $contractId = (int) ($row['contract_id'] ?? 0);
        $profileId = (int) ($row['financial_currency_profile_id'] ?? 0);
        $revisionNumber = (int) ($row['revision_number'] ?? 0);
        $createdBy = (int) ($row['created_by'] ?? 0);
        if ($id <= 0 || $contractId !== $expectedContractId || $profileId <= 0
            || $revisionNumber <= 0 || $revisionNumber > ContractFinancialAdjustmentPolicy::MAX_REVISION
            || $createdBy <= 0) {
            throw new UnexpectedValueException('Enterprise financial adjustment revision has invalid identity or revision metadata.');
        }

        try {
            $revisionUuid = ContractFinancialAdjustmentPolicy::normalizeUuid($row['revision_uuid'] ?? null, 'revision UUID');
            $lineUuid = ContractFinancialAdjustmentPolicy::normalizeUuid($row['line_uuid'] ?? null, 'line UUID');
            $kind = ContractFinancialAdjustmentPolicy::normalizeKind($row['adjustment_kind'] ?? null);
            $description = ContractFinancialAdjustmentPolicy::normalizeDescription($row['description'] ?? null);
            $state = ContractFinancialAdjustmentPolicy::normalizeState($row['line_state'] ?? null);
            $money = Money::of($row['amount'] ?? null, $this->currencyFromStorage($row['currency_code'] ?? null, 'revision currency'));
        } catch (Throwable $error) {
            throw new UnexpectedValueException('Enterprise financial adjustment revision contains invalid persisted data.', 0, $error);
        }
        if ($expectedLineUuid !== null && $lineUuid !== $expectedLineUuid) {
            throw new UnexpectedValueException('Enterprise financial adjustment line lookup returned a different line identity.');
        }
        if ($money->compare(Money::of('0', $money->currency())) < 0) {
            throw new UnexpectedValueException('Stored Enterprise financial adjustment amount cannot be negative.');
        }

        $row['id'] = $id;
        $row['revision_uuid'] = $revisionUuid;
        $row['contract_id'] = $contractId;
        $row['financial_currency_profile_id'] = $profileId;
        $row['line_uuid'] = $lineUuid;
        $row['revision_number'] = $revisionNumber;
        $row['adjustment_kind'] = $kind;
        $row['description'] = $description;
        $row['amount'] = $money->amount();
        $row['currency_code'] = $money->currencyCode();
        $row['line_state'] = $state;
        $row['created_by'] = $createdBy;
        return $row;
    }

    private function currencyFromStorage(mixed $value, string $field): CurrencyCode
    {
        try {
            return CurrencyCode::from($value);
        } catch (Throwable $error) {
            throw new UnexpectedValueException("Enterprise financial adjustment {$field} is invalid.", 0, $error);
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
            throw new RuntimeException('Enterprise financial adjustment access requires core tenant enforcement.');
        }
        return TenantContextStore::context()->requireTenantId();
    }
}
