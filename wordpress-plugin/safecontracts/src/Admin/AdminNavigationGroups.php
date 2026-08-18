<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Roles\Capabilities;

/**
 * Keeps the Alkenzy ADV WordPress admin menu compact without unregistering
 * any existing page callback. Leaf pages remain directly addressable by their
 * historical slugs, while the visible sidebar exposes permission-aware groups.
 */
final class AdminNavigationGroups
{
    public const QUERY_KEY = 'safecontracts_group';

    /** @var array<string,list<array{title:string,capability:string,slug:string}>> */
    private static array $groupItems = [];

    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'organize'], 998);
        add_filter('submenu_file', [self::class, 'highlightGroup'], 20, 2);
    }

    public static function organize(): void
    {
        if (! current_user_can(Capabilities::ACCESS)) {
            return;
        }

        global $submenu;
        $parent = AdminShell::SLUG;
        $rows = $submenu[$parent] ?? [];
        if (! is_array($rows) || $rows === []) {
            return;
        }

        self::$groupItems = [];
        foreach ($rows as $row) {
            if (! is_array($row) || count($row) < 3) {
                continue;
            }

            $capability = (string) ($row[1] ?? '');
            $slug = (string) ($row[2] ?? '');
            if ($slug === '' || $slug === $parent) {
                continue;
            }

            // Keep WordPress authorization authoritative. Group pages only
            // expose leaves the current user is already allowed to open.
            if ($capability === '' || ! current_user_can($capability)) {
                continue;
            }

            $group = self::groupKeyForSlug($slug);
            self::$groupItems[$group][] = [
                'title' => trim(wp_strip_all_tags((string) ($row[0] ?? $slug))),
                'capability' => $capability,
                'slug' => $slug,
            ];
        }

        // Hide every original leaf from the sidebar only. remove_submenu_page()
        // does not unregister the callbacks created by add_submenu_page(), so
        // old URLs, bookmarks and contextual links remain backward-compatible.
        foreach ($rows as $row) {
            if (! is_array($row) || count($row) < 3) {
                continue;
            }
            $slug = (string) ($row[2] ?? '');
            if ($slug !== '' && $slug !== $parent) {
                remove_submenu_page($parent, $slug);
            }
        }

        // WordPress creates a duplicate first submenu for the top-level page.
        // Keep it, but present it with the clear business label Dashboard.
        if (isset($submenu[$parent][0]) && is_array($submenu[$parent][0])) {
            $submenu[$parent][0][0] = __('Dashboard', 'safecontracts');
        }

        foreach (self::definitions() as $key => $definition) {
            if ((self::$groupItems[$key] ?? []) === []) {
                continue;
            }
            $submenu[$parent][] = [
                $definition['title'],
                Capabilities::ACCESS,
                self::groupUrl($key),
            ];
        }
    }

    public static function requestedGroup(): ?string
    {
        $group = sanitize_key((string) ($_GET[self::QUERY_KEY] ?? ''));
        return isset(self::definitions()[$group]) ? $group : null;
    }

    public static function renderRequestedGroup(): bool
    {
        $group = self::requestedGroup();
        if ($group === null) {
            return false;
        }
        self::renderGroup($group);
        return true;
    }

    public static function renderGroup(string $group): void
    {
        if (! current_user_can(Capabilities::ACCESS)) {
            wp_die(__('You do not have permission to access Safe Contracts.', 'safecontracts'));
        }

        $definitions = self::definitions();
        $definition = $definitions[$group] ?? $definitions['other'];
        $items = self::$groupItems[$group] ?? [];
        ?>
        <section class="safecontracts-navigation-group" aria-labelledby="safecontracts-navigation-group-title">
            <div class="safecontracts-section-heading">
                <div>
                    <p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Grouped navigation', 'safecontracts'); ?></p>
                    <h2 id="safecontracts-navigation-group-title"><?php echo esc_html($definition['title']); ?></h2>
                    <p><?php echo esc_html($definition['description']); ?></p>
                </div>
            </div>

            <?php if ($items === []) : ?>
                <div class="notice notice-info inline"><p><?php echo esc_html__('No pages in this section are available to your current role.', 'safecontracts'); ?></p></div>
            <?php else : ?>
                <div class="safecontracts-summary-cards safecontracts-navigation-group__cards">
                    <?php foreach ($items as $item) : ?>
                        <article class="safecontracts-summary-card safecontracts-navigation-group__card">
                            <span class="safecontracts-summary-card__label"><?php echo esc_html($definition['title']); ?></span>
                            <strong class="safecontracts-summary-card__value safecontracts-navigation-group__title"><?php echo esc_html($item['title']); ?></strong>
                            <a class="button button-primary" href="<?php echo esc_url(add_query_arg(['page' => $item['slug']], admin_url('admin.php'))); ?>">
                                <?php echo esc_html__('Open', 'safecontracts'); ?>
                            </a>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php
    }

    public static function highlightGroup(string $submenuFile, string $parentFile): string
    {
        if ($parentFile !== AdminShell::SLUG) {
            return $submenuFile;
        }

        $requested = self::requestedGroup();
        if ($requested !== null) {
            return self::groupUrl($requested);
        }

        $page = sanitize_key((string) ($_GET['page'] ?? ''));
        if ($page === '' || $page === AdminShell::SLUG) {
            return $submenuFile;
        }

        return self::groupUrl(self::groupKeyForSlug($page));
    }

    public static function groupKeyForSlug(string $slug): string
    {
        $slug = sanitize_key($slug);

        if (self::containsAny($slug, ['customers', 'suppliers', 'contracts'])) {
            return 'contracts';
        }
        if (self::containsAny($slug, ['payment-method', 'payments', 'collections', 'finance', 'reports'])) {
            return 'finance';
        }
        if (self::containsAny($slug, ['follow', 'archive', 'imports'])) {
            return 'operations';
        }
        if (str_contains($slug, 'notification')) {
            return 'notifications';
        }
        if (self::containsAny($slug, ['active-users', 'users-roles'])) {
            return 'access';
        }
        if (self::containsAny($slug, ['settings', 'firebase', 'mobile-configuration', 'translations'])) {
            return 'system';
        }
        if (str_contains($slug, 'user-guide')) {
            return 'help';
        }

        return 'other';
    }

    /** @return array<string,array{title:string,description:string}> */
    public static function definitions(): array
    {
        return [
            'contracts' => [
                'title' => __('Parties & Contracts', 'safecontracts'),
                'description' => __('Customers, suppliers and their customer or supplier contracts.', 'safecontracts'),
            ],
            'finance' => [
                'title' => __('Finance', 'safecontracts'),
                'description' => __('Payment schedules, collections, receivables, payables and financial reports.', 'safecontracts'),
            ],
            'operations' => [
                'title' => __('Operations', 'safecontracts'),
                'description' => __('Follow-up, archive and controlled data import operations.', 'safecontracts'),
            ],
            'notifications' => [
                'title' => __('Notifications', 'safecontracts'),
                'description' => __('Notification center, delivery activity, schedules and notification settings.', 'safecontracts'),
            ],
            'access' => [
                'title' => __('Users & Access', 'safecontracts'),
                'description' => __('Active-user visibility, user roles and business permission management.', 'safecontracts'),
            ],
            'system' => [
                'title' => __('Settings & Integrations', 'safecontracts'),
                'description' => __('Organization settings, Firebase, mobile configuration and translations.', 'safecontracts'),
            ],
            'help' => [
                'title' => __('User Guide', 'safecontracts'),
                'description' => __('Clear guidance for every area and the next related task.', 'safecontracts'),
            ],
            'other' => [
                'title' => __('More', 'safecontracts'),
                'description' => __('Additional authorized areas that are not yet assigned to a primary group.', 'safecontracts'),
            ],
        ];
    }

    private static function groupUrl(string $group): string
    {
        return 'admin.php?page=' . AdminShell::SLUG . '&' . self::QUERY_KEY . '=' . rawurlencode($group);
    }

    /** @param list<string> $needles */
    private static function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }
        return false;
    }
}
