<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Roles\Capabilities;

final class NavigationCleanup
{
    /** @return list<string> */
    public static function defaultHiddenMenus(): array
    {
        return [
            'index.php',
            'edit.php',
            'upload.php',
            'edit.php?post_type=page',
            'edit-comments.php',
            'themes.php',
            'plugins.php',
            'users.php',
            'tools.php',
            'options-general.php',
        ];
    }

    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'cleanup'], 999);
    }

    public static function cleanup(): void
    {
        if (! current_user_can(Capabilities::ACCESS) || current_user_can(Capabilities::MANAGE_SYSTEM)) {
            return;
        }

        $menus = apply_filters('safecontracts_hidden_admin_menus', self::defaultHiddenMenus());
        if (! is_array($menus)) {
            return;
        }

        foreach (array_values(array_unique(array_map('strval', $menus))) as $menuSlug) {
            $menuSlug = trim($menuSlug);
            if ($menuSlug !== '' && $menuSlug !== AdminShell::SLUG) {
                remove_menu_page($menuSlug);
            }
        }
    }
}
