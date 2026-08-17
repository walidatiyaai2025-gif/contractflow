<?php

declare(strict_types=1);

namespace SafeContracts\Finance;

use UnexpectedValueException;

final class ContractFinancialReconciliationCalculator
{
    /**
     * @param array{profile:array{id:int,currency:string},base:array{amount:string,currency:string,profile_id:int,revision_number:int},adjustments:list<array{line_uuid:string,revision_number:int,kind:string,amount:string,currency:string,state:string,profile_id:int}>} $snapshot
     * @return array{currency:string,base_value:string,additions_total:string,discounts_total:string,gross_value:string,net_value:string,active_addition_count:int,active_discount_count:int,voided_line_count:int}
     */
    public static function reconcile(array $snapshot): array
    {
        $profileId = (int) ($snapshot['profile']['id'] ?? 0);
        $currency = CurrencyCode::from($snapshot['profile']['currency'] ?? null);
        if ($profileId <= 0) {
            throw new UnexpectedValueException('Enterprise financial reconciliation profile identity is invalid.');
        }

        $baseProfileId = (int) ($snapshot['base']['profile_id'] ?? 0);
        $baseCurrency = CurrencyCode::from($snapshot['base']['currency'] ?? null);
        if ($baseProfileId !== $profileId || ! $baseCurrency->equals($currency)) {
            throw new UnexpectedValueException('Enterprise financial reconciliation base value differs from the authoritative profile.');
        }
        $base = Money::of($snapshot['base']['amount'] ?? null, $currency);
        if ($base->compare(Money::of('0', $currency)) < 0) {
            throw new UnexpectedValueException('Enterprise financial reconciliation base value cannot be negative.');
        }

        $zero = Money::of('0', $currency);
        $additions = $zero;
        $discounts = $zero;
        $activeAdditionCount = 0;
        $activeDiscountCount = 0;
        $voidedLineCount = 0;

        $adjustments = $snapshot['adjustments'] ?? null;
        if (! is_array($adjustments) || count($adjustments) > ContractFinancialAdjustmentPolicy::MAX_LINES) {
            throw new UnexpectedValueException('Enterprise financial reconciliation adjustment snapshot is invalid or exceeds its bound.');
        }

        foreach ($adjustments as $adjustment) {
            if (! is_array($adjustment) || (int) ($adjustment['profile_id'] ?? 0) !== $profileId) {
                throw new UnexpectedValueException('Enterprise financial reconciliation adjustment profile identity is invalid.');
            }
            $state = ContractFinancialAdjustmentPolicy::normalizeState($adjustment['state'] ?? null);
            $kind = ContractFinancialAdjustmentPolicy::normalizeKind($adjustment['kind'] ?? null);
            $money = Money::of($adjustment['amount'] ?? null, $adjustment['currency'] ?? null);
            if (! $money->currency()->equals($currency) || $money->compare($zero) < 0) {
                throw new UnexpectedValueException('Enterprise financial reconciliation adjustment amount or currency is invalid.');
            }

            if ($state === ContractFinancialAdjustmentPolicy::STATE_VOIDED) {
                $voidedLineCount++;
                continue;
            }
            if ($kind === ContractFinancialAdjustmentPolicy::KIND_ADDITION) {
                $additions = $additions->add($money);
                $activeAdditionCount++;
                continue;
            }
            if ($kind === ContractFinancialAdjustmentPolicy::KIND_DISCOUNT) {
                $discounts = $discounts->add($money);
                $activeDiscountCount++;
                continue;
            }
            throw new UnexpectedValueException('Enterprise financial reconciliation contains an unsupported active adjustment kind.');
        }

        $gross = $base->add($additions);
        $net = $gross->subtract($discounts);

        return [
            'currency' => $currency->value(),
            'base_value' => $base->amount(),
            'additions_total' => $additions->amount(),
            'discounts_total' => $discounts->amount(),
            'gross_value' => $gross->amount(),
            'net_value' => $net->amount(),
            'active_addition_count' => $activeAdditionCount,
            'active_discount_count' => $activeDiscountCount,
            'voided_line_count' => $voidedLineCount,
        ];
    }
}
