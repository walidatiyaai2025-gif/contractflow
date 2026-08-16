<?php

declare(strict_types=1);

namespace SafeContracts\Roles;

final class RoleRegistrar
{
    public const SYSTEM_ADMIN = 'safecontracts_system_admin';
    public const MANAGER = 'safecontracts_manager';
    public const ACCOUNTANT = 'safecontracts_accountant';
    public const VIEWER = 'safecontracts_viewer';

    /**
     * Register baseline roles/capabilities on activation only.
     * Runtime code must not continuously re-add removed capabilities because
     * Safe Contracts permissions are intentionally configurable per role.
     */
    public static function registerDefaults(): void
    {
        self::registerRole(self::SYSTEM_ADMIN, 'SafeContracts System Administrator', Capabilities::all());

        self::registerRole(self::MANAGER, 'SafeContracts Manager', [
            Capabilities::ACCESS,
            Capabilities::VIEW_ALL,
            Capabilities::CREATE_CUSTOMERS,
            Capabilities::EDIT_CUSTOMERS,
            Capabilities::CREATE_CONTRACTS,
            Capabilities::EDIT_CONTRACTS,
            Capabilities::ASSIGN_CONTRACTS,
            Capabilities::CREATE_PAYMENTS,
            Capabilities::EDIT_PAYMENTS,
            Capabilities::MANAGE_PAYMENTS,
            Capabilities::MANAGE_COLLECTIONS,
            Capabilities::MANAGE_FOLLOWUPS,
            Capabilities::VIEW_REPORTS,
            Capabilities::EXPORT_REPORTS,
            Capabilities::VIEW_AUDIT,
        ]);

        self::registerRole(self::ACCOUNTANT, 'SafeContracts Accountant', [
            Capabilities::ACCESS,
            Capabilities::VIEW_ASSIGNED,
            Capabilities::CREATE_CONTRACTS,
            Capabilities::CREATE_PAYMENTS,
            Capabilities::EDIT_PAYMENTS,
            Capabilities::MANAGE_PAYMENTS,
            Capabilities::MANAGE_COLLECTIONS,
            Capabilities::MANAGE_FOLLOWUPS,
            Capabilities::VIEW_REPORTS,
            Capabilities::EXPORT_REPORTS,
        ]);

        self::registerRole(self::VIEWER, 'SafeContracts Viewer', [
            Capabilities::ACCESS,
            Capabilities::VIEW_ASSIGNED,
            Capabilities::VIEW_REPORTS,
        ]);

        $administrator = get_role('administrator');
        if ($administrator) {
            foreach (Capabilities::all() as $capability) {
                $administrator->add_cap($capability);
            }
        }
    }

    private static function registerRole(string $slug, string $name, array $capabilities): void
    {
        $grants = Capabilities::toGrantArray($capabilities);
        $role = get_role($slug);

        if (! $role) {
            add_role($slug, $name, $grants);
            return;
        }

        // Activation is an explicit reset-to-baseline event for built-in grants.
        foreach ($grants as $capability => $grant) {
            if ($grant) {
                $role->add_cap($capability);
            }
        }
    }
}
