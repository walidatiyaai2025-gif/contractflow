<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Roles\Capabilities;

final class UserGuidePage
{
    public const SLUG = 'safecontracts-user-guide';

    public static function register(): void
    {
        add_submenu_page(
            AdminShell::SLUG,
            __('User Guide', 'safecontracts'),
            __('User Guide', 'safecontracts'),
            Capabilities::ACCESS,
            self::SLUG,
            [self::class, 'render']
        );
    }

    public static function registerContextualHelp(): void
    {
        add_action('admin_notices', [self::class, 'renderContextualHelp'], 6);
    }

    public static function renderContextualHelp(): void
    {
        if (! current_user_can(Capabilities::ACCESS)) {
            return;
        }

        $page = isset($_GET['page']) && is_scalar($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';
        if ($page === '' || ! AdminShell::isSafeContractsPage()) {
            return;
        }

        $entry = UserGuideCatalog::forPage($page);
        if ($entry === null || ! current_user_can((string) $entry['capability'])) {
            return;
        }

        echo '<div class="safecontracts-summary-injector" dir="auto">';
        echo '<details class="safecontracts-admin-card safecontracts-user-guide-panel">';
        echo '<summary><strong>' . esc_html__('How to use this page', 'safecontracts') . '</strong></summary>';
        self::renderEntryBody($entry, true);
        echo '</details>';
        echo '</div>';
    }

    public static function render(): void
    {
        if (! current_user_can(Capabilities::ACCESS)) {
            wp_die(__('You do not have permission to access Safe Contracts.', 'safecontracts'));
        }

        $entries = UserGuideCatalog::visibleEntries();
        ?>
        <div class="wrap safecontracts-settings safecontracts-user-guide" dir="auto">
            <div class="safecontracts-section-heading">
                <div>
                    <p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('User Guide', 'safecontracts'); ?></p>
                    <h1><?php echo esc_html__('User Guide', 'safecontracts'); ?></h1>
                    <p><?php echo esc_html__('Use this guide to understand each area before changing production data.', 'safecontracts'); ?></p>
                    <p class="description"><?php echo esc_html__('Only sections available to your current role are shown.', 'safecontracts'); ?></p>
                </div>
            </div>
            <div class="safecontracts-role-grid">
                <?php foreach ($entries as $slug => $entry) : ?>
                    <section class="safecontracts-admin-card">
                        <h2><?php echo esc_html((string) $entry['title']); ?></h2>
                        <?php self::renderEntryBody($entry, false); ?>
                        <?php if ($slug !== self::SLUG) : ?>
                            <p><a class="button" href="<?php echo esc_url(self::pageUrl((string) $slug)); ?>"><?php echo esc_html(__('Open', 'safecontracts') . ' ' . (string) $entry['title']); ?></a></p>
                        <?php endif; ?>
                    </section>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    /** @param array<string,mixed> $entry */
    private static function renderEntryBody(array $entry, bool $withFullGuideLink): void
    {
        echo '<h3>' . esc_html__('What this page does', 'safecontracts') . '</h3>';
        echo '<p>' . esc_html((string) ($entry['purpose'] ?? '')) . '</p>';

        $steps = is_array($entry['steps'] ?? null) ? $entry['steps'] : [];
        if ($steps !== []) {
            echo '<h3>' . esc_html__('Recommended steps', 'safecontracts') . '</h3><ol>';
            foreach ($steps as $step) {
                echo '<li>' . esc_html((string) $step) . '</li>';
            }
            echo '</ol>';
        }

        $related = is_array($entry['related'] ?? null) ? $entry['related'] : [];
        $visibleRelated = array_values(array_filter(
            $related,
            static fn (mixed $item): bool => is_array($item) && current_user_can((string) ($item['capability'] ?? ''))
        ));
        if ($visibleRelated !== []) {
            echo '<h3>' . esc_html__('Related places', 'safecontracts') . '</h3><ul>';
            foreach ($visibleRelated as $item) {
                echo '<li><a href="' . esc_url(self::pageUrl((string) $item['slug'])) . '">' . esc_html((string) $item['label']) . '</a></li>';
            }
            echo '</ul>';
        }

        if ($withFullGuideLink && current_user_can(Capabilities::ACCESS)) {
            echo '<p><a class="button button-secondary" href="' . esc_url(self::pageUrl(self::SLUG)) . '">' . esc_html__('Open full user guide', 'safecontracts') . '</a></p>';
        }
    }

    private static function pageUrl(string $slug): string
    {
        return add_query_arg(['page' => $slug], admin_url('admin.php'));
    }
}
