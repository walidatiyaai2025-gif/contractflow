<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use DateTimeImmutable;
use SafeContracts\Contracts\ContractStatus;
use SafeContracts\Payments\PaymentStatus;

final class DashboardFilters
{
    /** @return array{customer_id:int,contract_id:int,accountant_user_id:int,status:string,due_from:?string,due_to:?string,date_from:?string,date_to:?string,date_range_error:bool} */
    public static function normalize(array $input): array
    {
        $customerId = self::id($input['customer_id'] ?? null);
        $contractId = self::id($input['contract_id'] ?? null);
        $accountantUserId = self::id($input['accountant_user_id'] ?? null);
        $statusValue = $input['status'] ?? '';
        $status = is_scalar($statusValue) && ! is_bool($statusValue)
            ? strtolower(trim((string) $statusValue))
            : '';
        $allowedStatuses = array_values(array_unique(array_merge(
            ['', ContractStatus::DRAFT, ContractStatus::ACTIVE, ContractStatus::COMPLETED, ContractStatus::CANCELLED],
            PaymentStatus::all()
        )));
        if (! in_array($status, $allowedStatuses, true)) {
            $status = '';
        }

        // Keep the legacy payment due-range contract for API/admin backwards
        // compatibility. The new generic display period below never swaps an
        // inverted request: it rejects it so the UI cannot silently change the
        // operator's intended range.
        $dueFrom = self::date($input['due_from'] ?? null);
        $dueTo = self::date($input['due_to'] ?? null);
        if ($dueFrom !== null && $dueTo !== null && $dueTo < $dueFrom) {
            [$dueFrom, $dueTo] = [$dueTo, $dueFrom];
        }

        $period = AdminPeriodFilter::normalize($input);

        return [
            'customer_id' => $customerId,
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
