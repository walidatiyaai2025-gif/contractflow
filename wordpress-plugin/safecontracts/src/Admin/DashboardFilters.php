<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use DateTimeImmutable;
use SafeContracts\Contracts\ContractStatus;
use SafeContracts\Contracts\Counterparty;
use SafeContracts\Payments\FinancialDirection;
use SafeContracts\Payments\PaymentStatus;

final class DashboardFilters
{
    /** @return array{customer_id:int,counterparty_type:string,counterparty_id:int,financial_direction:string,currency_code:string,contract_id:int,accountant_user_id:int,status:string,due_from:?string,due_to:?string,date_from:?string,date_to:?string,date_range_error:bool} */
    public static function normalize(array $input): array
    {
        $customerId = self::id($input['customer_id'] ?? null);
        $counterpartyId = self::id($input['counterparty_id'] ?? null);
        $counterpartyType = self::enum($input['counterparty_type'] ?? '', [Counterparty::CUSTOMER, Counterparty::SUPPLIER]);
        if ($customerId > 0 && $counterpartyId === 0 && $counterpartyType === '') {
            $counterpartyType = Counterparty::CUSTOMER;
            $counterpartyId = $customerId;
        }
        $direction = self::enum($input['financial_direction'] ?? '', [FinancialDirection::RECEIVABLE, FinancialDirection::PAYABLE]);
        $currencyCode = self::currency($input['currency_code'] ?? '');
        $contractId = self::id($input['contract_id'] ?? null);
        $accountantUserId = self::id($input['accountant_user_id'] ?? null);
        $statusValue = $input['status'] ?? '';
        $status = is_scalar($statusValue) && ! is_bool($statusValue)
            ? strtolower(trim((string) $statusValue))
            : '';
        $allowedStatuses = array_merge(
            ['', ContractStatus::DRAFT, ContractStatus::ACTIVE, ContractStatus::COMPLETED, ContractStatus::CANCELLED],
            [PaymentStatus::UPCOMING, PaymentStatus::DUE_SOON, PaymentStatus::DUE, PaymentStatus::OVERDUE, PaymentStatus::PARTIALLY_PAID, PaymentStatus::PAID]
        );
        if (! in_array($status, $allowedStatuses, true)) {
            $status = '';
        }

        $dueFrom = self::date($input['due_from'] ?? null);
        $dueTo = self::date($input['due_to'] ?? null);
        if ($dueFrom !== null && $dueTo !== null && $dueTo < $dueFrom) {
            [$dueFrom, $dueTo] = [$dueTo, $dueFrom];
        }

        $period = AdminPeriodFilter::normalize($input);

        return [
            'customer_id' => $customerId,
            'counterparty_type' => $counterpartyType,
            'counterparty_id' => $counterpartyId,
            'financial_direction' => $direction,
            'currency_code' => $currencyCode,
            'contract_id' => $contractId,
            'accountant_user_id' => $accountantUserId,
            'status' => $status,
            'due_from' => $dueFrom,
            'due_to' => $dueTo,
            'date_from' => $period['date_from'],
            'date_to' => $period['date_to'],
            'date_range_error' => $period['date_range_error'],
        ];
    }

    private static function id(mixed $value): int
    {
        if (! is_scalar($value) || is_bool($value)) {
            return 0;
        }
        $raw = trim((string) $value);
        if ($raw === '' || ! preg_match('/^\d+$/', $raw)) {
            return 0;
        }
        $validated = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        return $validated === false ? 0 : (int) $validated;
    }

    /** @param list<string> $allowed */
    private static function enum(mixed $value, array $allowed): string
    {
        if (! is_scalar($value) || is_bool($value)) {
            return '';
        }
        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, $allowed, true) ? $normalized : '';
    }

    private static function currency(mixed $value): string
    {
        if (! is_scalar($value) || is_bool($value)) {
            return '';
        }
        $currency = strtoupper(trim((string) $value));
        return preg_match('/^[A-Z]{3}$/', $currency) ? $currency : '';
    }

    private static function date(mixed $value): ?string
    {
        if (! is_scalar($value) || is_bool($value)) {
            return null;
        }
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }
}
