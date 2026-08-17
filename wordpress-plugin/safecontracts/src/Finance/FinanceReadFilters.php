<?php

declare(strict_types=1);

namespace SafeContracts\Finance;

use DateTimeImmutable;
use InvalidArgumentException;
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
        if (
            $customerId > 0
            && $counterpartyId === 0
            && ($counterpartyType === '' || $counterpartyType === Counterparty::CUSTOMER)
        ) {
            $counterpartyType = Counterparty::CUSTOMER;
            $counterpartyId = $customerId;
        }
        if (
            $supplierId > 0
            && $counterpartyId === 0
            && ($counterpartyType === '' || $counterpartyType === Counterparty::SUPPLIER)
        ) {
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

    /**
     * Strict normalization for public REST reads. Invalid or contradictory
     * filters must never degrade into a wider unfiltered finance query.
     *
     * @return array{direction:string,currency_code:string,counterparty_type:string,customer_id:int,supplier_id:int,contract_id:int,counterparty_id:int,accountant_user_id:int,status:string,due_from:?string,due_to:?string,aging_bucket:string,limit:int}
     */
    public static function strict(array $input): array
    {
        $direction = self::strictEnumValue($input['direction'] ?? '', [FinancialDirection::RECEIVABLE, FinancialDirection::PAYABLE], 'financial direction');
        $legacyDirection = self::strictEnumValue($input['financial_direction'] ?? '', [FinancialDirection::RECEIVABLE, FinancialDirection::PAYABLE], 'financial direction');
        if ($direction !== '' && $legacyDirection !== '' && $direction !== $legacyDirection) {
            throw new InvalidArgumentException('Conflicting financial direction filters are not allowed.');
        }

        self::strictEnumValue($input['counterparty_type'] ?? '', [Counterparty::CUSTOMER, Counterparty::SUPPLIER], 'counterparty type');
        self::strictEnumValue($input['status'] ?? '', PaymentStatus::all(), 'payment status');
        self::strictEnumValue($input['aging_bucket'] ?? '', AgingBucket::all(), 'aging bucket');

        $currency = strtoupper(trim(self::strictScalar($input['currency_code'] ?? '', 'currency code')));
        if ($currency !== '' && ! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException('Currency code must be exactly three letters.');
        }

        foreach (['customer_id', 'supplier_id', 'contract_id', 'counterparty_id', 'accountant_user_id'] as $field) {
            if (! array_key_exists($field, $input) || $input[$field] === null || trim(self::strictScalar($input[$field], $field)) === '') {
                continue;
            }
            if (! preg_match('/^[1-9]\d*$/', trim((string) $input[$field]))) {
                throw new InvalidArgumentException(str_replace('_', ' ', ucfirst($field)) . ' must be a positive integer.');
            }
        }

        foreach (['due_from', 'due_to'] as $field) {
            if (! array_key_exists($field, $input) || $input[$field] === null || trim(self::strictScalar($input[$field], $field)) === '') {
                continue;
            }
            $raw = trim((string) $input[$field]);
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
            if (! $date || $date->format('Y-m-d') !== $raw) {
                throw new InvalidArgumentException(str_replace('_', ' ', ucfirst($field)) . ' must use YYYY-MM-DD and be a valid date.');
            }
        }

        if (array_key_exists('limit', $input) && $input['limit'] !== null && trim(self::strictScalar($input['limit'], 'limit')) !== '') {
            $rawLimit = trim((string) $input['limit']);
            if (! preg_match('/^[1-9]\d*$/', $rawLimit) || (int) $rawLimit > 500) {
                throw new InvalidArgumentException('Finance result limit must be between 1 and 500.');
            }
        }

        $normalized = self::normalize($input);
        if ($normalized['due_from'] !== null && $normalized['due_to'] !== null && $normalized['due_to'] < $normalized['due_from']) {
            throw new InvalidArgumentException('Finance due-to date cannot be earlier than due-from date.');
        }
        if ($normalized['customer_id'] > 0 && $normalized['supplier_id'] > 0) {
            throw new InvalidArgumentException('Customer and Supplier selectors cannot be combined.');
        }
        if ($normalized['customer_id'] > 0 && $normalized['counterparty_type'] === Counterparty::SUPPLIER) {
            throw new InvalidArgumentException('Customer selector conflicts with Supplier counterparty type.');
        }
        if ($normalized['supplier_id'] > 0 && $normalized['counterparty_type'] === Counterparty::CUSTOMER) {
            throw new InvalidArgumentException('Supplier selector conflicts with Customer counterparty type.');
        }
        return $normalized;
    }

    /** @param list<string> $allowed */
    private static function enum(mixed $value, array $allowed): string
    {
        $normalized = strtolower(trim(self::scalarString($value)));
        return in_array($normalized, $allowed, true) ? $normalized : '';
    }

    /** @param list<string> $allowed */
    private static function strictEnumValue(mixed $value, array $allowed, string $field): string
    {
        $raw = strtolower(trim(self::strictScalar($value, $field)));
        if ($raw !== '' && ! in_array($raw, $allowed, true)) {
            throw new InvalidArgumentException('Unsupported ' . $field . ' filter.');
        }
        return $raw;
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

    private static function strictScalar(mixed $value, string $field): string
    {
        if (! is_scalar($value) || is_bool($value)) {
            throw new InvalidArgumentException(ucfirst($field) . ' must be a scalar value.');
        }
        return (string) $value;
    }

    private static function scalarString(mixed $value): string
    {
        if (! is_scalar($value) || is_bool($value)) {
            return '';
        }
        return (string) $value;
    }
}
