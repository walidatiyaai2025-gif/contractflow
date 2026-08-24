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
        add_action('admin_head', [self::class, 'printSidebarStyles']);
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

        /*
         * Keep the registered leaf submenu rows in WordPress' authorization
         * structure. Hiding them visually must never weaken core route checks.
         */
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

    /**
     * Keep leaf submenu rows in WordPress' authorization structure but hide
     * their sidebar links. Grouped entries remain visible because their URLs
     * use page=safecontracts plus safecontracts_group.
     */
    public static function printSidebarStyles(): void
    {
        if (! current_user_can(Capabilities::ACCESS)) {
            return;
        }
        ?>
        <style id="safecontracts-grouped-navigation-visibility">
            #toplevel_page_safecontracts .wp-submenu a[href*="page=safecontracts-"] { display: none !important; }
        </style>
        <?php
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
            <div class="safecontracts-section-heading safecontracts-page-heading">
                <div>
                    <p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Safe Contracts workspace', 'safecontracts'); ?></p>
                    <h1 id="safecontracts-navigation-group-title"><?php echo esc_html($definition['title']); ?></h1>
                    <p class="description"><?php echo esc_html($definition['description']); ?></p>
                </div>
            </div>

            <?php if ($items === []) : ?>
                <div class="safecontracts-navigation-group__empty" role="status">
                    <?php echo esc_html__('No pages in this section are available to your current role.', 'safecontracts'); ?>
                </div>
            <?php else : ?>
                <div class="safecontracts-navigation-group__cards">
                    <?php foreach ($items as $item) : ?>
                        <article class="safecontracts-navigation-group__card">
                            <span class="safecontracts-navigation-group__card-icon dashicons <?php echo esc_attr(self::iconForSlug($item['slug'])); ?>" aria-hidden="true"></span>
                            <h2 class="safecontracts-navigation-group__title"><?php echo esc_html($item['title']); ?></h2>
                            <p class="safecontracts-navigation-group__description"><?php echo esc_html(self::itemDescription($item['title'])); ?></p>
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

    public static function highlightGroup(?string $submenuFile, string $parentFile): ?string
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
        $prefix = AdminShell::SLUG . '-';
        $feature = str_starts_with($slug, $prefix)
            ? substr($slug, strlen($prefix))
            : $slug;

        if (self::containsAny($feature, ['customers', 'suppliers', 'contracts'])) {
            return 'contracts';
        }
        if (self::containsAny($feature, ['payment-method', 'payments', 'collections', 'finance', 'reports'])) {
            return 'finance';
        }
        if (self::containsAny($feature, ['follow', 'archive', 'imports'])) {
            return 'operations';
        }
        if (str_contains($feature, 'notification')) {
            return 'notifications';
        }
        if (self::containsAny($feature, ['active-users', 'users-roles'])) {
            return 'access';
        }
        if (self::containsAny($feature, ['settings', 'firebase', 'mobile-configuration', 'translations', 'runtime-inspector'])) {
            return 'system';
        }
        if (str_contains($feature, 'user-guide')) {
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
                'description' => __('Organization settings, email delivery, Firebase, mobile configuration, translations and diagnostics.', 'safecontracts'),
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

    private static function itemDescription(string $title): string
    {
        return sprintf(
            /* translators: %s is the authorized SafeContracts page title. */
            __('Open %s with the permissions assigned to your WordPress account.', 'safecontracts'),
            $title
        );
    }

    private static function iconForSlug(string $slug): string
    {
        $slug = sanitize_key($slug);
        if (str_contains($slug, 'customer')) { return 'dashicons-groups'; }
        if (str_contains($slug, 'supplier')) { return 'dashicons-store'; }
        if (str_contains($slug, 'contract')) { return 'dashicons-media-document'; }
        if (str_contains($slug, 'payment')) { return 'dashicons-money-alt'; }
        if (str_contains($slug, 'collection')) { return 'dashicons-chart-line'; }
        if (str_contains($slug, 'finance')) { return 'dashicons-chart-area'; }
        if (str_contains($slug, 'report')) { return 'dashicons-media-spreadsheet'; }
        if (str_contains($slug, 'follow')) { return 'dashicons-update'; }
        if (str_contains($slug, 'archive')) { return 'dashicons-archive'; }
        if (str_contains($slug, 'import')) { return 'dashicons-upload'; }
        if (str_contains($slug, 'notification')) { return 'dashicons-bell'; }
        if (str_contains($slug, 'users')) { return 'dashicons-admin-users'; }
        if (str_contains($slug, 'firebase')) { return 'dashicons-cloud'; }
        if (str_contains($slug, 'mobile')) { return 'dashicons-smartphone'; }
        if (str_contains($slug, 'translation')) { return 'dashicons-translation'; }
        if (str_contains($slug, 'runtime')) { return 'dashicons-admin-tools'; }
        if (str_contains($slug, 'settings')) { return 'dashicons-admin-generic'; }
        if (str_contains($slug, 'guide')) { return 'dashicons-editor-help'; }
        return 'dashicons-screenoptions';
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
