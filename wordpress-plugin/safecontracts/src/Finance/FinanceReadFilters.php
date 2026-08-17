<?php

declare(strict_types=1);

namespace SafeContracts\Finance;

use DateTimeImmutable;
use SafeContracts\Contracts\Counterparty;
use SafeContracts\Payments\CurrencyCode;
use SafeContracts\Payments\FinancialDirection;
use SafeContracts\Payments\PaymentStatus;

final class FinanceReadFilters
{
    /** @return array{direction:string,currency_code:string,counterparty_type:string,customer_id:int,supplier_id:int,contract_id:int,counterparty_id:int,accountant_user_id:int,status:string,due_from:?string,due_to:?string,aging_bucket:string,limit:int} */
    public static function normalize(array $input): array
    {
        $directionInput = array_key_exists('direction', $input) ? $input['direction'] : ($input['financial_direction'] ?? '');
        $direction = self::enum($directionInput, ['', FinancialDirection::RECEIVABLE, FinancialDirection::PAYABLE]);
        $counterpartyType = self::enum($input['counterparty_type'] ?? '', ['', Counterparty::CUSTOMER, Counterparty::SUPPLIER]);
        $currency = strtoupper(trim(self::scalarString($input['currency_code'] ?? '')));
        if ($currency !== '' && ! preg_match('/^[A-Z]{3}$/', $currency)) {
            $currency = '';
        }
        if ($currency === CurrencyCode::UNKNOWN) {
            $currency = CurrencyCode::UNKNOWN;
        }
        $status = self::enum($input['status'] ?? '', array_merge([''], PaymentStatus::all()));
        $aging = self::enum($input['aging_bucket'] ?? '', array_merge([''], AgingBucket::all()));
        $dueFrom = self::date($input['due_from'] ?? null);
        $dueTo = self::date($input['due_to'] ?? null);
        if ($dueFrom !== null && $dueTo !== null && $dueTo < $dueFrom) {
            [$dueFrom, $dueTo] = [$dueTo, $dueFrom];
        }

        $customerId = self::id($input['customer_id'] ?? null);
        $supplierId = self::id($input['supplier_id'] ?? null);
        $counterpartyId = self::id($input['counterparty_id'] ?? null);
        if ($customerId > 0 && $counterpartyType === '' && $counterpartyId === 0) {
            $counterpartyType = Counterparty::CUSTOMER;
            $counterpartyId = $customerId;
        }
        if ($supplierId > 0 && $counterpartyType === '' && $counterpartyId === 0) {
            $counterpartyType = Counterparty::SUPPLIER;
            $counterpartyId = $supplierId;
        }

        return [
            'direction' => $direction,
            'currency_code' => $currency,
            'counterparty_type' => $counterpartyType,
            'customer_id' => $customerId,
            'supplier_id' => $supplierId,
            'contract_id' => self::id($input['contract_id'] ?? null),
            'counterparty_id' => $counterpartyId,
            'accountant_user_id' => self::id($input['accountant_user_id'] ?? null),
            'status' => $status,
            'due_from' => $dueFrom,
            'due_to' => $dueTo,
            'aging_bucket' => $aging,
            'limit' => self::limit($input['limit'] ?? 100),
        ];
    }

    /** @param list<string> $allowed */
    private static function enum(mixed $value, array $allowed): string
    {
        $normalized = strtolower(trim(self::scalarString($value)));
        return in_array($normalized, $allowed, true) ? $normalized : '';
    }

    private static function id(mixed $value): int
    {
        if (! is_scalar($value) || is_bool($value)) {
            return 0;
        }
        $raw = trim((string) $value);
        return preg_match('/^[1-9]\d*$/', $raw) ? (int) $raw : 0;
    }

    private static function limit(mixed $value): int
    {
        if (! is_scalar($value) || is_bool($value)) {
            return 100;
        }
        return max(1, min(500, (int) $value));
    }

    private static function date(mixed $value): ?string
    {
        $raw = trim(self::scalarString($value));
        if ($raw === '') {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
        return $date && $date->format('Y-m-d') === $raw ? $raw : null;
    }

    private static function scalarString(mixed $value): string
    {
        if (! is_scalar($value) || is_bool($value)) {
            return '';
        }
        return (string) $value;
    }
}
