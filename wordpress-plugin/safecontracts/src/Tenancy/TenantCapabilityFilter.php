<?php

declare(strict_types=1);

namespace SafeContracts\Tenancy;

use SafeContracts\Roles\Capabilities;

final class TenantCapabilityFilter
{
    /** @var list<string> */
    private const TENANT_CAPABILITIES = [
        Capabilities::ACCESS,
        Capabilities::VIEW_ALL,
        Capabilities::VIEW_ASSIGNED,
        Capabilities::CREATE_CONTRACTS,
        Capabilities::EDIT_CONTRACTS,
        Capabilities::ASSIGN_CONTRACTS,
        Capabilities::MANAGE_PAYMENTS,
        Capabilities::MANAGE_COLLECTIONS,
        Capabilities::MANAGE_FOLLOWUPS,
        Capabilities::VIEW_REPORTS,
        Capabilities::EXPORT_REPORTS,
        Capabilities::MANAGE_NOTIFICATIONS,
        Capabilities::RUN_IMPORTS,
        Capabilities::VIEW_AUDIT,
    ];

    private static int $bypassDepth = 0;

    public static function register(): void
    {
        add_filter('user_has_cap', [self::class, 'filter'], 20, 4);
    }

    /**
     * @param array<string,mixed> $allCaps
     * @return array<string,mixed>
     */
    public static function filter(array $allCaps, mixed $requestedCaps = null, mixed $args = null, mixed $user = null): array
    {
        unset($requestedCaps, $args, $user);
        if (self::$bypassDepth > 0) {
            return $allCaps;
        }

        foreach (self::TENANT_CAPABILITIES as $capability) {
            if (empty($allCaps[$capability])) {
                continue;
            }
            if (! TenantAuthorization::allowsCapability($capability)) {
                $allCaps[$capability] = false;
            }
        }

        return $allCaps;
    }

    /**
     * Read a WordPress capability without applying the tenant role ceiling.
     * This is reserved for control-plane operations such as rendering the tenant
     * switcher, where an invalid previous tenant role must not lock the user out
     * of selecting another active membership.
     */
    public static function globalCapabilityGranted(string $capability): bool
    {
        self::$bypassDepth++;
        try {
            return current_user_can($capability);
        } finally {
            self::$bypassDepth--;
        }
    }
}
