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
        $status = isset($_GET['safecontracts_status']) && is_scalar($_GET['safecontracts_status']) ? sanitize_key((string) $_GET['safecontracts_status']) : '';
        $enabledFeatures = (int) ! empty($config['excel_export_enabled']) + (int) ! empty($config['push_notifications_enabled']) + (int) ! empty($config['collection_entry_enabled']);
        ?>
        <div class="wrap safecontracts-settings safecontracts-mobile-configuration" dir="auto">
            <div class="safecontracts-section-heading">
                <div>
                    <p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Mobile bootstrap', 'safecontracts'); ?></p>
                    <h1><?php echo esc_html__('Mobile Configuration', 'safecontracts'); ?></h1>
                    <p><?php echo esc_html__('Manage the existing non-secret mobile bootstrap values and supported feature flags. Unsupported mobile settings are intentionally not invented here.', 'safecontracts'); ?></p>
                </div>
                <span class="safecontracts-state-chip is-success"><?php echo esc_html(sprintf(__('%d of 3 features enabled', 'safecontracts'), $enabledFeatures)); ?></span>
            </div>
            <?php if ($status === 'saved') : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html__('Mobile configuration saved.', 'safecontracts'); ?></p></div><?php endif; ?>
            <?php if ($status === 'invalid') : ?><div class="notice notice-error is-dismissible"><p><?php echo esc_html__('Mobile configuration could not be saved. Review the entered values.', 'safecontracts'); ?></p></div><?php endif; ?>

            <?php AdminSummaryCards::render([
                ['label' => __('Default page size', 'safecontracts'), 'value' => (int) $config['default_page_size']],
                ['label' => __('Enabled feature flags', 'safecontracts'), 'value' => $enabledFeatures],
                ['label' => __('Supported feature flags', 'safecontracts'), 'value' => 3],
            ]); ?>

            <section class="safecontracts-admin-card safecontracts-settings-card">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_ACTION); ?>"><?php wp_nonce_field(self::SAVE_ACTION); ?>

                    <div class="safecontracts-section-heading"><div><h2><?php echo esc_html__('Mobile content', 'safecontracts'); ?></h2><p class="description"><?php echo esc_html__('Non-secret text exposed through the existing mobile configuration model.', 'safecontracts'); ?></p></div></div>
                    <p><label><?php echo esc_html__('Support / footer text', 'safecontracts'); ?><textarea class="widefat" name="support_text" maxlength="500" rows="4"><?php echo esc_textarea((string) $config['support_text']); ?></textarea></label></p>

                    <div class="safecontracts-section-heading"><div><h2><?php echo esc_html__('Runtime defaults', 'safecontracts'); ?></h2></div></div>
                    <p><label><?php echo esc_html__('Default mobile page size', 'safecontracts'); ?><input type="number" min="10" max="200" name="default_page_size" value="<?php echo esc_attr((string) $config['default_page_size']); ?>"></label></p>

                    <div class="safecontracts-section-heading"><div><h2><?php echo esc_html__('Feature availability', 'safecontracts'); ?></h2><p class="description"><?php echo esc_html__('These switches are the complete set currently supported by the stored mobile configuration.', 'safecontracts'); ?></p></div></div>
                    <fieldset>
                        <label class="safecontracts-check-row"><input type="checkbox" name="excel_export_enabled" value="1" <?php checked($config['excel_export_enabled']); ?>><span><strong><?php echo esc_html__('Excel export', 'safecontracts'); ?></strong><br><small class="description"><?php echo esc_html__('Allow the mobile experience to expose its supported export flow.', 'safecontracts'); ?></small></span></label>
                        <label class="safecontracts-check-row"><input type="checkbox" name="push_notifications_enabled" value="1" <?php checked($config['push_notifications_enabled']); ?>><span><strong><?php echo esc_html__('Push notifications', 'safecontracts'); ?></strong><br><small class="description"><?php echo esc_html__('Advertise push-notification availability to the mobile runtime.', 'safecontracts'); ?></small></span></label>
                        <label class="safecontracts-check-row"><input type="checkbox" name="collection_entry_enabled" value="1" <?php checked($config['collection_entry_enabled']); ?>><span><strong><?php echo esc_html__('Collection entry', 'safecontracts'); ?></strong><br><small class="description"><?php echo esc_html__('Allow the existing mobile collection-entry capability when authorized.', 'safecontracts'); ?></small></span></label>
                    </fieldset>
                    <?php submit_button(__('Save Mobile Configuration', 'safecontracts')); ?>
                </form>
                <p class="description"><?php echo esc_html__('This page stores only non-secret bootstrap values in WordPress and does not add or bypass REST authorization, environment configuration or backend business rules.', 'safecontracts'); ?></p>
            </section>
        </div>
        <?php
    }
}
