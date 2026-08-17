<?php

declare(strict_types=1);

namespace SafeContracts\Finance;

use DateTimeImmutable;
use SafeContracts\Payments\PaymentStatus;

final class FinanceReadFilters
{
    /** @return array{direction:string,currency_code:string,contract_id:int,counterparty_id:int,accountant_user_id:int,status:string,due_from:?string,due_to:?string,aging_bucket:string,limit:int} */
    public static function normalize(array $input): array
    {
        $direction = self::enum($input['direction'] ?? '', FinancialDirection::all());
        $currency = strtoupper(trim(self::scalarString($input['currency_code'] ?? '')));
        if ($currency !== '' && $currency !== 'UNSET' && ! preg_match('/^[A-Z]{3}$/', $currency)) {
            $currency = '';
        }
        $status = self::enum($input['status'] ?? '', array_merge([''], PaymentStatus::all()));
        $aging = self::enum($input['aging_bucket'] ?? '', array_merge([''], AgingBucket::all()));
        $dueFrom = self::date($input['due_from'] ?? null);
        $dueTo = self::date($input['due_to'] ?? null);
        if ($dueFrom !== null && $dueTo !== null && $dueTo < $dueFrom) {
            [$dueFrom, $dueTo] = [$dueTo, $dueFrom];
        }

        return [
            'direction' => $direction,
            'currency_code' => $currency,
            'contract_id' => self::id($input['contract_id'] ?? null),
            'counterparty_id' => self::id($input['counterparty_id'] ?? null),
            'accountant_user_id' => self::id($input['accountant_user_id'] ?? null),
            'status' => $status,
            'due_from' => $dueFrom,
            'due_to' => $dueTo,
            'aging_bucket' => $aging,
            'limit' => self::limit($input['limit'] ?? 100),
        ];
    }

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
