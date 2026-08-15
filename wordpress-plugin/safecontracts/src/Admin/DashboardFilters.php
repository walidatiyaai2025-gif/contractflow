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
        $customerId = max(0, (int) ($input['customer_id'] ?? 0));
        $contractId = max(0, (int) ($input['contract_id'] ?? 0));
        $accountantUserId = max(0, (int) ($input['accountant_user_id'] ?? 0));
        $status = strtolower(trim((string) ($input['status'] ?? '')));
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

    private static function date(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }
}
