<?php

declare(strict_types=1);

namespace SafeContracts\Lifecycle;

use SafeContracts\Database\Migrator;
use SafeContracts\Roles\RoleRegistrar;

final class Activator
{
    public static function activate(): void
    {
        self::assertRuntimeRequirements();

        (new Migrator())->migrate();
        RoleRegistrar::registerDefaults();

        if (false === get_option('safecontracts_installed_at', false)) {
            update_option('safecontracts_installed_at', gmdate('c'), false);
        }

        update_option('safecontracts_plugin_version', SAFECONTRACTS_VERSION, false);
        do_action('safecontracts_activated', SAFECONTRACTS_VERSION);
    }

    private static function assertRuntimeRequirements(): void
    {
        if (version_compare(PHP_VERSION, '8.1.0', '<')) {
            deactivate_plugins(plugin_basename(SAFECONTRACTS_FILE));
            wp_die(
                esc_html__('SafeContracts requires PHP 8.1 or newer.', 'safecontracts'),
                esc_html__('SafeContracts activation failed', 'safecontracts'),
                ['back_link' => true]
            );
        }
    }
}
