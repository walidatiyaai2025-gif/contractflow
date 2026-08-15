<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Roles\Capabilities;
use SafeContracts\Settings\MobileConfiguration;
use Throwable;

final class MobileConfigurationPage
{
    public const SLUG = 'safecontracts-mobile-configuration';
    public const SAVE_ACTION = 'safecontracts_save_mobile_configuration';

    public static function register(): void
    {
        add_submenu_page(AdminShell::SLUG, __('Mobile Configuration', 'safecontracts'), __('Mobile Configuration', 'safecontracts'), Capabilities::MANAGE_SYSTEM, self::SLUG, [self::class, 'render']);
    }

    public static function handleSave(): void
    {
        if (! current_user_can(Capabilities::MANAGE_SYSTEM)) {
            wp_die(__('You do not have permission to manage mobile configuration.', 'safecontracts'));
        }
        check_admin_referer(self::SAVE_ACTION);
        $status = 'saved';
        try {
            (new MobileConfiguration())->save([
                'support_text' => $_POST['support_text'] ?? '',
                'default_page_size' => $_POST['default_page_size'] ?? 25,
                'excel_export_enabled' => isset($_POST['excel_export_enabled']),
                'push_notifications_enabled' => isset($_POST['push_notifications_enabled']),
                'collection_entry_enabled' => isset($_POST['collection_entry_enabled']),
            ]);
        } catch (Throwable $error) {
            unset($error);
            $status = 'invalid';
        }
        wp_safe_redirect(add_query_arg(['page' => self::SLUG, 'safecontracts_status' => $status], admin_url('admin.php')));
        exit;
    }

    public static function render(): void
    {
        if (! current_user_can(Capabilities::MANAGE_SYSTEM)) {
            wp_die(__('You do not have permission to manage mobile configuration.', 'safecontracts'));
        }
        $config = (new MobileConfiguration())->read();
        ?>
        <div class="wrap safecontracts-settings" dir="auto">
            <div class="safecontracts-section-heading"><div><p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Mobile bootstrap', 'safecontracts'); ?></p><h1><?php echo esc_html__('Mobile Configuration', 'safecontracts'); ?></h1></div></div>
            <section class="safecontracts-admin-card safecontracts-settings-card">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_ACTION); ?>"><?php wp_nonce_field(self::SAVE_ACTION); ?>
                    <p><label><?php echo esc_html__('Support / footer text', 'safecontracts'); ?><textarea class="widefat" name="support_text" maxlength="500" rows="4"><?php echo esc_html($config['support_text']); ?></textarea></label></p>
                    <p><label><?php echo esc_html__('Default mobile page size', 'safecontracts'); ?><input type="number" min="10" max="200" name="default_page_size" value="<?php echo esc_attr((string) $config['default_page_size']); ?>"></label></p>
                    <fieldset><legend><?php echo esc_html__('Feature availability', 'safecontracts'); ?></legend>
                        <label class="safecontracts-check-row"><input type="checkbox" name="excel_export_enabled" value="1" <?php checked($config['excel_export_enabled']); ?>><?php echo esc_html__('Excel export', 'safecontracts'); ?></label>
                        <label class="safecontracts-check-row"><input type="checkbox" name="push_notifications_enabled" value="1" <?php checked($config['push_notifications_enabled']); ?>><?php echo esc_html__('Push notifications', 'safecontracts'); ?></label>
                        <label class="safecontracts-check-row"><input type="checkbox" name="collection_entry_enabled" value="1" <?php checked($config['collection_entry_enabled']); ?>><?php echo esc_html__('Collection entry', 'safecontracts'); ?></label>
                    </fieldset>
                    <?php submit_button(__('Save Mobile Configuration', 'safecontracts')); ?>
                </form>
                <p class="description"><?php echo esc_html__('This stores only non-secret bootstrap values in WordPress. P8 will expose the authorized mobile configuration endpoint; this task does not add REST endpoints early.', 'safecontracts'); ?></p>
            </section>
        </div>
        <?php
    }
}
