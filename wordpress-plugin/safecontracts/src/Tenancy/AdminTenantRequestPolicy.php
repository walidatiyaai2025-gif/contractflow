<?php

declare(strict_types=1);

namespace SafeContracts\Tenancy;

final class AdminTenantRequestPolicy
{
    /** @var list<string> */
    private const PLATFORM_GLOBAL_PAGES = [
        AdminTenantContext::SELECT_PAGE,
        'safecontracts-active-users',
        'safecontracts-users-roles',
        'safecontracts-settings',
        'safecontracts-payment-methods',
        'safecontracts-firebase-settings',
        'safecontracts-mobile-configuration',
        'safecontracts-translations',
    ];

    /** @var list<string> */
    private const PLATFORM_GLOBAL_ACTIONS = [
        AdminTenantContext::SELECT_ACTION,
        'safecontracts_save_general_settings',
        'safecontracts_save_payment_method',
        'safecontracts_delete_payment_method',
        'safecontracts_save_role_capabilities',
        'safecontracts_assign_user_role',
        'safecontracts_save_firebase_settings',
        'safecontracts_upload_firebase_service_account',
        'safecontracts_delete_firebase_service_account',
        'safecontracts_test_firebase_connection',
        'safecontracts_save_mobile_configuration',
        'safecontracts_save_translations',
    ];

    public static function isTenantOwnedRequest(): bool
    {
        $page = sanitize_key((string) ($_GET['page'] ?? ''));
        if ($page === 'safecontracts' || str_starts_with($page, 'safecontracts-')) {
            return ! self::isPlatformGlobalPage($page);
        }

        $action = sanitize_key((string) ($_REQUEST['action'] ?? ''));
        if (str_starts_with($action, 'safecontracts_')) {
            return ! self::isPlatformGlobalAction($action);
        }

        return false;
    }

    public static function isTenantOwnedPage(): bool
    {
        $page = sanitize_key((string) ($_GET['page'] ?? ''));
        return ($page === 'safecontracts' || str_starts_with($page, 'safecontracts-'))
            && ! self::isPlatformGlobalPage($page);
    }

    public static function isPlatformGlobalPage(string $page): bool
    {
        return in_array(sanitize_key($page), self::PLATFORM_GLOBAL_PAGES, true);
    }

    public static function isPlatformGlobalAction(string $action): bool
    {
        return in_array(sanitize_key($action), self::PLATFORM_GLOBAL_ACTIONS, true);
    }
}
