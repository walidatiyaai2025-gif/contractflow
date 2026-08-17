<?php

declare(strict_types=1);

namespace SafeContracts\Finance;

use DomainException;
use InvalidArgumentException;
use SafeContracts\Contracts\ContractMoney;
use SafeContracts\Payments\PaymentStatus;

final class SettlementMath
{
    /** @return array{settled_amount:string,remaining_amount:string,status:string} */
    public static function apply(string $original, string $settled, string $delta, string $direction): array
    {
        $original = ContractMoney::normalizeNonNegative($original);
        $settled = ContractMoney::normalizeNonNegative($settled);
        $delta = ContractMoney::normalizeNonNegative($delta);
        $direction = FinancialDirection::normalize($direction);

        if ($delta === '0.0000') {
            throw new InvalidArgumentException('Settlement amount must be greater than zero.');
        }
        if (ContractMoney::compare($settled, $original) > 0) {
            throw new DomainException('Stored settlement amount exceeds the original obligation.');
        }

        $newSettled = ContractMoney::add($settled, $delta);
        if (ContractMoney::compare($newSettled, $original) > 0) {
            throw new DomainException('Settlement amount exceeds the obligation remaining balance.');
        }
        $remaining = ContractMoney::subtract($original, $newSettled);

        return [
            'settled_amount' => $newSettled,
            'remaining_amount' => $remaining,
            'status' => $remaining === '0.0000'
                ? PaymentStatus::settledForDirection($direction)
                : PaymentStatus::partialForDirection($direction),
        ];
    }
}
