<?php

declare(strict_types=1);

namespace SafeContracts\Rest;

use SafeContracts\Roles\AccessScope;
use SafeContracts\Tenancy\TenantAuthorization;
use WP_Error;

final class Permission
{
    public static function access(): bool|WP_Error
    {
        if (get_current_user_id() <= 0) {
            return ApiResponse::error(
                'safecontracts_unauthenticated',
                __('Authentication is required to access SafeContracts.', 'safecontracts'),
                401
            );
        }

        if (AccessScope::canAccess()) {
            return true;
        }

        return ApiResponse::error(
            'safecontracts_forbidden',
            __('You do not have access to SafeContracts.', 'safecontracts'),
            403
        );
    }

    public static function capability(string $capability, string $code = 'safecontracts_forbidden'): bool|WP_Error
    {
        $access = self::access();
        if ($access instanceof WP_Error) {
            return $access;
        }

        // Tenant roles are a second, narrowing ceiling. They never manufacture
        // a capability that WordPress did not already grant to this user.
        if (current_user_can($capability) && TenantAuthorization::allowsCapability($capability)) {
            return true;
        }

        return ApiResponse::error(
            $code,
            __('You do not have permission to perform this SafeContracts operation.', 'safecontracts'),
            403
        );
    }
}
