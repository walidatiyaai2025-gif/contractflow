<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

use SafeContracts\Roles\RoleRegistrar;

/**
 * Compatibility policy for notification recipient roles persisted by older
 * SafeContracts builds. Admin input remains strict in NotificationRule; this
 * policy is only for reading/repairing stored production data without allowing
 * an obsolete role to abort the notification scheduler.
 */
final class NotificationRecipientRolePolicy
{
    /** @return list<string> */
    public static function allowed(): array
    {
        return [
            RoleRegistrar::SYSTEM_ADMIN,
            RoleRegistrar::MANAGER,
            RoleRegistrar::ACCOUNTANT,
            RoleRegistrar::VIEWER,
        ];
    }

    /**
     * Normalize persisted roles fail-closed.
     *
     * Known historical aliases are mapped to the current SafeContracts role.
     * Unknown WordPress/plugin roles are dropped rather than broadened.
     *
     * @return list<string>
     */
    public static function normalizeStoredRoles(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $allowed = array_flip(self::allowed());
        $roles = [];
        foreach ($value as $role) {
            $slug = strtolower(trim((string) $role));
            if ($slug === '') {
                continue;
            }

            $slug = self::legacyAlias($slug);
            if (! isset($allowed[$slug])) {
                continue;
            }
            $roles[$slug] = $slug;
        }

        return array_values($roles);
    }

    private static function legacyAlias(string $slug): string
    {
        return match ($slug) {
            'administrator', 'admin', 'safecontracts_admin' => RoleRegistrar::SYSTEM_ADMIN,
            'manager' => RoleRegistrar::MANAGER,
            'accountant' => RoleRegistrar::ACCOUNTANT,
            'viewer' => RoleRegistrar::VIEWER,
            default => $slug,
        };
    }
}
