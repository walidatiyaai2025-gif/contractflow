<?php

declare(strict_types=1);

namespace SafeContracts\Rest;

use DomainException;
use SafeContracts\Roles\Capabilities;

final class ApiScope
{
    public static function assertAccountant(?int $accountantUserId): void
    {
        if (current_user_can(Capabilities::VIEW_ALL)) {
            return;
        }
        if (
            current_user_can(Capabilities::VIEW_ASSIGNED)
            && $accountantUserId !== null
            && $accountantUserId === get_current_user_id()
        ) {
            return;
        }
        throw new DomainException('The requested SafeContracts resource is outside the current user scope.');
    }

    public static function mode(): string
    {
        if (current_user_can(Capabilities::VIEW_ALL)) {
            return 'all';
        }
        if (current_user_can(Capabilities::VIEW_ASSIGNED)) {
            return 'assigned';
        }
        return 'none';
    }
}
