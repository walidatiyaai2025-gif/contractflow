<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use DateTimeImmutable;
use SafeContracts\Contracts\ContractStatus;
use SafeContracts\Payments\PaymentStatus;

final class DashboardFilters
{
    /** @return array{customer_id:int,contract_id:int,accountant_user_id:int,status:string,due_from:?string,due_to:?string} */
    public static function normalize(array $input): array
    {
        $customerId = self::id($input['customer_id'] ?? null);
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

        return [
            'customer_id' => $customerId,
            'contract_id' => $contractId,
            'accountant_user_id' => $accountantUserId,
            'status' => $status,
            'due_from' => $dueFrom,
            'due_to' => $dueTo,
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
