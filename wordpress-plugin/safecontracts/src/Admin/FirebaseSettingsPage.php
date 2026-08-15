<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Notifications\FirebaseSettings;
use SafeContracts\Roles\Capabilities;
use Throwable;

final class FirebaseSettingsPage
{
    public const SLUG = 'safecontracts-firebase-settings';
    public const SAVE_ACTION = 'safecontracts_save_firebase_settings';

    public static function register(): void
    {
        add_submenu_page(AdminShell::SLUG, __('Firebase Settings', 'safecontracts'), __('Firebase Settings', 'safecontracts'), Capabilities::MANAGE_NOTIFICATIONS, self::SLUG, [self::class, 'render']);
    }

    public static function handleSave(): void
    {
        if (! current_user_can(Capabilities::MANAGE_NOTIFICATIONS)) {
            wp_die(__('You do not have permission to manage Firebase settings.', 'safecontracts'));
        }
        check_admin_referer(self::SAVE_ACTION);
        $status = 'saved';
        try {
            $settings = new FirebaseSettings();
            $settings->savePublic([
                'project_id' => $_POST['project_id'] ?? '',
                'messaging_sender_id' => $_POST['messaging_sender_id'] ?? '',
                'app_id' => $_POST['app_id'] ?? '',
            ]);
            $reference = trim((string) ($_POST['credential_reference'] ?? ''));
            if ($reference !== '') {
                $settings->saveCredentialReference($reference);
            }
        } catch (Throwable $error) {
            unset($error);
            $status = 'invalid';
        }
        wp_safe_redirect(add_query_arg(['page' => self::SLUG, 'safecontracts_status' => $status], admin_url('admin.php')));
        exit;
    }

    public static function render(): void
    {
        if (! current_user_can(Capabilities::MANAGE_NOTIFICATIONS)) {
            wp_die(__('You do not have permission to manage Firebase settings.', 'safecontracts'));
        }
        $settings = new FirebaseSettings();
        $summary = $settings->safeSummary();
        $reference = $settings->credentialReference();
        $ready = ! empty($summary['configured'])
            && trim((string) $summary['project_id']) !== ''
            && trim((string) $summary['messaging_sender_id']) !== ''
            && trim((string) $summary['app_id']) !== '';
        ?>
        <div class="wrap safecontracts-settings" dir="auto">
            <div class="safecontracts-section-heading"><div><p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Push infrastructure', 'safecontracts'); ?></p><h1><?php echo esc_html__('Firebase Settings', 'safecontracts'); ?></h1></div><span class="safecontracts-state-chip <?php echo $ready ? 'is-success' : 'is-warning'; ?>"><?php echo $ready ? esc_html__('Ready', 'safecontracts') : esc_html__('Incomplete', 'safecontracts'); ?></span></div>
            <section class="safecontracts-admin-card safecontracts-settings-card">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_ACTION); ?>"><?php wp_nonce_field(self::SAVE_ACTION); ?>
                    <p><label><?php echo esc_html__('Firebase project ID', 'safecontracts'); ?><input class="widefat" name="project_id" maxlength="191" required value="<?php echo esc_attr((string) $summary['project_id']); ?>"></label></p>
                    <p><label><?php echo esc_html__('Messaging sender ID', 'safecontracts'); ?><input class="widefat" name="messaging_sender_id" maxlength="32" inputmode="numeric" required value="<?php echo esc_attr((string) $summary['messaging_sender_id']); ?>"></label></p>
                    <p><label><?php echo esc_html__('Firebase app ID', 'safecontracts'); ?><input class="widefat" name="app_id" maxlength="191" required value="<?php echo esc_attr((string) $summary['app_id']); ?>"></label></p>
                    <p><label><?php echo esc_html__('Credential reference', 'safecontracts'); ?><input class="widefat" name="credential_reference" maxlength="128" value="<?php echo esc_attr($reference); ?>" placeholder="SAFECONTRACTS_FIREBASE_SERVICE_ACCOUNT"></label></p>
                    <p class="description"><?php echo esc_html__('Enter only the environment/secret identifier. Never paste service-account JSON, private keys, OAuth access tokens or credential contents into WordPress.', 'safecontracts'); ?></p>
                    <?php submit_button(__('Save Firebase Settings', 'safecontracts')); ?>
                </form>
            </section>
        </div>
        <?php
    }
}
