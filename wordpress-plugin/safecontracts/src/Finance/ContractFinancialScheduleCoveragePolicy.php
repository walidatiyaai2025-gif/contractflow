<?php

declare(strict_types=1);

namespace SafeContracts\Finance;

use InvalidArgumentException;

final class ContractFinancialScheduleCoveragePolicy
{
    public const STATE_UNDER_SCHEDULED = 'under_scheduled';
    public const STATE_ALIGNED = 'aligned';
    public const STATE_OVER_SCHEDULED = 'over_scheduled';

    public static function derive(Money $scheduled, Money $contractNet): string
    {
        if (! $scheduled->currency()->equals($contractNet->currency())) {
            throw new InvalidArgumentException('Schedule coverage requires matching currencies.');
        }

        $comparison = $scheduled->compare($contractNet);
        if ($comparison < 0) {
            return self::STATE_UNDER_SCHEDULED;
        }
        if ($comparison > 0) {
            return self::STATE_OVER_SCHEDULED;
        }
        return self::STATE_ALIGNED;
    }
}
