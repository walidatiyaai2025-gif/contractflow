<?php

declare(strict_types=1);

namespace SafeContracts\Finance;

use RuntimeException;
use SafeContracts\Tenancy\CoreTenantEnforcement;
use SafeContracts\Tenancy\TenantContextStore;
use UnexpectedValueException;

final class ContractFinancialCurrencyProfileRepository
{
    private const SELECT_COLUMNS = 'id, uuid, contract_id, contract_currency, tenant_base_currency_snapshot, created_by, created_at';

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
    public function findForContract(int $contractId): ?array
    {
        global $wpdb;
        $tenantId = $this->tenantId();
        $profiles = $wpdb->prefix . 'safecontracts_contract_financial_currency_profiles';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT " . self::SELECT_COLUMNS . " FROM {$profiles} WHERE tenant_id = %d AND contract_id = %d LIMIT 2",
            $tenantId,
            $contractId
        ), ARRAY_A);

        if (! is_array($rows) || count($rows) > 1) {
            throw new RuntimeException('Contract financial currency profile lookup returned an invalid cardinality.');
        }
        if ($rows === []) {
            return null;
        }
        if (! is_array($rows[0])) {
            throw new UnexpectedValueException('Contract financial currency profile row is invalid.');
        }
        return $this->normalizeProfile($rows[0], $contractId);
    }

    public function createOrGet(
        int $contractId,
        string $uuid,
        CurrencyCode $contractCurrency,
        CurrencyCode $tenantBaseCurrencySnapshot,
        int $actorId
    ): int {
        global $wpdb;
        $tenantId = $this->tenantId();
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $profiles = $wpdb->prefix . 'safecontracts_contract_financial_currency_profiles';

        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Unable to start Contract financial currency profile transaction.');
        }

        try {
            $contractRows = $wpdb->get_results($wpdb->prepare(
                "SELECT id FROM {$contracts} WHERE id = %d AND tenant_id = %d AND is_archived = 0 LIMIT 2 FOR UPDATE",
                $contractId,
                $tenantId
            ), ARRAY_A);
            if (! is_array($contractRows) || count($contractRows) !== 1 || ! is_array($contractRows[0])) {
                throw new RuntimeException('Enterprise contract changed concurrently or is not financial-profile editable.');
            }

            $existingRows = $wpdb->get_results($wpdb->prepare(
                "SELECT " . self::SELECT_COLUMNS . " FROM {$profiles} WHERE tenant_id = %d AND contract_id = %d LIMIT 2 FOR UPDATE",
                $tenantId,
                $contractId
            ), ARRAY_A);
            if (! is_array($existingRows) || count($existingRows) > 1) {
                throw new RuntimeException('Contract financial currency profile lock returned an invalid cardinality.');
            }
            if ($existingRows !== []) {
                if (! is_array($existingRows[0])) {
                    throw new UnexpectedValueException('Contract financial currency profile row is invalid.');
                }
                $existing = $this->normalizeProfile($existingRows[0], $contractId);
                $storedCurrency = CurrencyCode::from($existing['contract_currency'] ?? null);
                if (! $storedCurrency->equals($contractCurrency)) {
                    throw new RuntimeException('Contract financial currency profile already exists with a different contract currency.');
                }
                if ($wpdb->query('COMMIT') === false) {
                    throw new RuntimeException('Unable to commit idempotent Contract financial currency profile retry.');
                }
                return (int) $existing['id'];
            }

            $inserted = $wpdb->query($wpdb->prepare(
                "INSERT INTO {$profiles} (
                    tenant_id, uuid, contract_id, contract_currency, tenant_base_currency_snapshot, created_by, created_at
                 ) VALUES (%d, %s, %d, %s, %s, %d, UTC_TIMESTAMP())",
                $tenantId,
                $uuid,
                $contractId,
                $contractCurrency->value(),
                $tenantBaseCurrencySnapshot->value(),
                $actorId
            ));
            if ($inserted !== 1) {
                throw new RuntimeException('Unable to create Enterprise Contract financial currency profile.');
            }

            $profileId = (int) ($wpdb->insert_id ?? 0);
            if ($profileId <= 0) {
                throw new RuntimeException('Contract financial currency profile insert returned no identifier.');
            }
            if ($wpdb->query('COMMIT') === false) {
                throw new RuntimeException('Unable to commit Contract financial currency profile transaction.');
            }
            return $profileId;
        } catch (\Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function normalizeProfile(array $row, int $expectedContractId): array
    {
        $id = (int) ($row['id'] ?? 0);
        $contractId = (int) ($row['contract_id'] ?? 0);
        if ($id <= 0 || $contractId !== $expectedContractId) {
            throw new UnexpectedValueException('Contract financial currency profile has invalid identity.');
        }

        $row['id'] = $id;
        $row['contract_id'] = $contractId;
        $row['contract_currency'] = CurrencyCode::from($row['contract_currency'] ?? null)->value();
        $row['tenant_base_currency_snapshot'] = CurrencyCode::from($row['tenant_base_currency_snapshot'] ?? null)->value();
        return $row;
    }

    private function tenantId(): int
    {
        if (! CoreTenantEnforcement::isEnabled()) {
            throw new RuntimeException('Enterprise Contract financial currency profile access requires core tenant enforcement.');
        }
        return TenantContextStore::context()->requireTenantId();
    }
}
