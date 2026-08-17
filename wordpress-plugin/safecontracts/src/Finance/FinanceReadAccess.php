<?php

declare(strict_types=1);

namespace SafeContracts\Finance;

use DomainException;
use SafeContracts\Payments\FinancialDirection;
use SafeContracts\Roles\Capabilities;

final class FinanceReadAccess
{
    /** @return list<string> */
    public static function authorizedDirections(): array
    {
        if (! current_user_can(Capabilities::ACCESS)) {
            return [];
        }
        if (! current_user_can(Capabilities::VIEW_FINANCE) && ! current_user_can(Capabilities::MANAGE_FINANCE)) {
            return [];
        }
        return [FinancialDirection::RECEIVABLE, FinancialDirection::PAYABLE];
    }

    /** @return list<string> */
    public static function resolveDirections(string $requested): array
    {
        $authorized = self::authorizedDirections();
        if ($requested === '') {
            return $authorized;
        }
        $requested = FinancialDirection::normalize($requested);
        if (! in_array($requested, $authorized, true)) {
            throw new DomainException('Requested financial direction is outside the current user permissions.');
        }
        return [$requested];
    }

    /** @return array{clause:string,args:list<mixed>} */
    public static function scopeClause(int $requestedAccountantId): array
    {
        if (current_user_can(Capabilities::VIEW_ALL)) {
            if ($requestedAccountantId > 0) {
                return ['clause' => 'c.accountant_user_id = %d', 'args' => [$requestedAccountantId]];
            }
            return ['clause' => '1 = 1', 'args' => []];
        }
        if (! current_user_can(Capabilities::VIEW_ASSIGNED)) {
            throw new DomainException('SafeContracts finance is outside the current user data scope.');
        }
        return ['clause' => 'c.accountant_user_id = %d', 'args' => [get_current_user_id()]];
    }
}
