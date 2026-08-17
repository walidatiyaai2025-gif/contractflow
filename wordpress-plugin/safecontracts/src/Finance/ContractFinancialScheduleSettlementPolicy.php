<?php

declare(strict_types=1);

namespace SafeContracts\Finance;

final class ContractFinancialScheduleSettlementPolicy
{
    public const STATE_UNCOLLECTED = 'uncollected';
    public const STATE_PARTIAL = 'partial';
    public const STATE_SETTLED = 'settled';
    public const STATE_OVER_COLLECTED = 'over_collected';
    public const STATE_VOIDED = 'voided';

    public static function derive(string $scheduleState, Money $scheduled, Money $collected): string
    {
        $state = ContractFinancialPaymentSchedulePolicy::normalizeState($scheduleState);
        if ($state === ContractFinancialPaymentSchedulePolicy::STATE_VOIDED) {
            return self::STATE_VOIDED;
        }
        if ($collected->isZero()) {
            return self::STATE_UNCOLLECTED;
        }

        $comparison = $collected->compare($scheduled);
        if ($comparison < 0) {
            return self::STATE_PARTIAL;
        }
        if ($comparison === 0) {
            return self::STATE_SETTLED;
        }
        return self::STATE_OVER_COLLECTED;
    }
}
