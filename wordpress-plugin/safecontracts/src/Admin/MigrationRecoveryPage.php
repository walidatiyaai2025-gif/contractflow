<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Database\MigrationGuard;
use SafeContracts\Database\Migrator;
use SafeContracts\Roles\Capabilities;

final class MigrationRecoveryPage
{
    public const SLUG = 'safecontracts-migration-recovery';

    public static function register(): void
    {
        add_action('admin_notices', [self::class, 'renderNotice'], 1);
        add_action('admin_menu', [self::class, 'registerPage'], 1);
        add_action('admin_enqueue_scripts', [AdminShell::class, 'enqueueAssets']);
    }

    public static function registerPage(): void
    {
        add_menu_page(
            __('Database upgrade requires attention', 'safecontracts'),
            __('Alkenzy ADV Recovery', 'safecontracts'),
            Capabilities::MANAGE_SYSTEM,
            self::SLUG,
            [self::class, 'render'],
            'dashicons-shield-alt',
            2
        );
    }

    public static function renderNotice(): void
    {
        if (! current_user_can(Capabilities::MANAGE_SYSTEM) || MigrationGuard::failureState() === null) {
            return;
        }
        $url = add_query_arg(['page' => self::SLUG], admin_url('admin.php'));
        echo '<div class="notice notice-error"><p><strong>' . esc_html__('Database upgrade requires attention', 'safecontracts') . '</strong></p>';
        echo '<p>' . esc_html__('Alkenzy ADV stopped the database upgrade before marking it complete. Review the migration journal and rollback runbook before retrying.', 'safecontracts') . '</p>';
        echo '<p><a class="button button-primary" href="' . esc_url($url) . '">' . esc_html__('Review production rollback guide', 'safecontracts') . '</a></p></div>';
    }

    public static function render(): void
    {
        if (! current_user_can(Capabilities::MANAGE_SYSTEM)) {
            wp_die(__('You do not have permission to manage SafeContracts settings.', 'safecontracts'));
        }

        $failure = MigrationGuard::failureState() ?? [];
        $current = (string) get_option(Migrator::VERSION_OPTION, '0.0.0');
        ?>
        <div class="wrap safecontracts-settings safecontracts-migration-recovery" dir="auto">
            <div class="safecontracts-section-heading safecontracts-page-heading">
                <div>
                    <p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Protected recovery mode', 'safecontracts'); ?></p>
                    <h1><?php echo esc_html__('Database Upgrade Recovery', 'safecontracts'); ?></h1>
                    <p class="description"><?php echo esc_html__('SafeContracts stopped the migration before declaring it complete. Keep business operations paused until the recorded failure is reconciled.', 'safecontracts'); ?></p>
                </div>
            </div>

            <div class="safecontracts-migration-recovery__alert" role="alert">
                <strong><?php echo esc_html__('Do not repeatedly retry this migration.', 'safecontracts'); ?></strong>
                <p><?php echo esc_html__('Verify the pre-deployment backup, migration journal and exact plugin package before one controlled recovery attempt.', 'safecontracts'); ?></p>
            </div>

            <div class="safecontracts-settings-grid">
                <section class="safecontracts-admin-card safecontracts-table-card">
                    <div class="safecontracts-section-heading">
                        <div><h2><?php echo esc_html__('Recorded migration state', 'safecontracts'); ?></h2></div>
                    </div>
                    <table class="widefat striped">
                        <tbody>
                            <tr><th><?php echo esc_html__('Current database version', 'safecontracts'); ?></th><td><code><?php echo esc_html($current); ?></code></td></tr>
                            <tr><th><?php echo esc_html__('Expected plugin database version', 'safecontracts'); ?></th><td><code><?php echo esc_html(Migrator::LATEST_VERSION); ?></code></td></tr>
                            <tr><th><?php echo esc_html__('Migration target', 'safecontracts'); ?></th><td><?php echo esc_html((string) ($failure['to_version'] ?? '—')); ?></td></tr>
                            <tr><th><?php echo esc_html__('Rollback status', 'safecontracts'); ?></th><td><?php echo esc_html((string) ($failure['rollback_status'] ?? '—')); ?></td></tr>
                            <tr><th><?php echo esc_html__('Recorded at', 'safecontracts'); ?></th><td><?php echo esc_html((string) ($failure['recorded_at'] ?? '—')); ?></td></tr>
                        </tbody>
                    </table>
                </section>

                <aside class="safecontracts-admin-card">
                    <p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Operator evidence', 'safecontracts'); ?></p>
                    <h2><?php echo esc_html__('Recovery runbook', 'safecontracts'); ?></h2>
                    <p><?php echo esc_html__('Follow the repository-controlled production rollback procedure. Do not bypass schema validation or manually mark the migration complete.', 'safecontracts'); ?></p>
                    <p><code>docs/ALKENZY_PRODUCTION_MIGRATION_ROLLBACK.md</code></p>
                </aside>
            </div>

            <section class="safecontracts-admin-card" style="margin-top:16px">
                <h2><?php echo esc_html__('Production recovery sequence', 'safecontracts'); ?></h2>
                <ol class="safecontracts-migration-recovery__sequence">
                    <li><?php echo esc_html__('Keep Alkenzy ADV business operations stopped and do not repeatedly retry the migration.', 'safecontracts'); ?></li>
                    <li><?php echo esc_html__('Verify the pre-deployment database backup and the exact plugin package used for this deployment.', 'safecontracts'); ?></li>
                    <li><?php echo esc_html__('If application rollback succeeded, investigate and correct the failed migration before one controlled retry.', 'safecontracts'); ?></li>
                    <li><?php echo esc_html__('If rollback failed or schema integrity is uncertain, restore the verified pre-deployment database backup and matching plugin release.', 'safecontracts'); ?></li>
                    <li><?php echo esc_html__('Run database, API and business smoke checks before reopening production operations.', 'safecontracts'); ?></li>
                </ol>
            </section>
        </div>
        <?php
    }
}
