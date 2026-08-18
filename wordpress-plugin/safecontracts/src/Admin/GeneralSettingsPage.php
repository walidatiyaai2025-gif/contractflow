<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Roles\Capabilities;
use SafeContracts\Settings\GeneralSettings;
use Throwable;

final class GeneralSettingsPage
{
    public const SLUG = 'safecontracts-settings';
    public const SAVE_ACTION = 'safecontracts_save_general_settings';

    public static function register(): void
    {
        add_submenu_page(AdminShell::SLUG, __('SafeContracts Settings', 'safecontracts'), __('Settings', 'safecontracts'), Capabilities::MANAGE_SYSTEM, self::SLUG, [self::class, 'render']);
    }

    public static function handleSave(): void
    {
        if (! current_user_can(Capabilities::MANAGE_SYSTEM)) {
            wp_die(__('You do not have permission to manage SafeContracts settings.', 'safecontracts'));
        }
        check_admin_referer(self::SAVE_ACTION);
        $status = 'saved';
        try {
            (new GeneralSettings())->save([
                'organization_name' => $_POST['organization_name'] ?? '',
                'currency_code' => strtoupper(sanitize_text_field((string) ($_POST['currency_code'] ?? ''))),
                'currency_symbol' => $_POST['currency_symbol'] ?? '',
                'admin_page_size' => $_POST['admin_page_size'] ?? 50,
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
            wp_die(__('You do not have permission to manage SafeContracts settings.', 'safecontracts'));
        }
        $settings = (new GeneralSettings())->read();
        $selectedCurrency = strtoupper(trim((string) ($settings['currency_code'] ?? '')));
        $currencyChoices = AdminLookupOptions::currencyChoices(new AdminReadRepository(), $selectedCurrency);
        ?>
        <div class="wrap safecontracts-settings" dir="auto">
            <div class="safecontracts-section-heading"><div><p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('System configuration', 'safecontracts'); ?></p><h1><?php echo esc_html__('SafeContracts Settings', 'safecontracts'); ?></h1></div></div>
            <section class="safecontracts-admin-card safecontracts-settings-card">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_ACTION); ?>"><?php wp_nonce_field(self::SAVE_ACTION); ?>
                    <p><label><?php echo esc_html__('Organization name', 'safecontracts'); ?><input class="widefat" name="organization_name" maxlength="191" required value="<?php echo esc_attr($settings['organization_name']); ?>"></label></p>
                    <p><label><?php echo esc_html__('System currency', 'safecontracts'); ?><select class="widefat" name="currency_code"><option value=""><?php echo esc_html__('Select currency', 'safecontracts'); ?></option><?php foreach ($currencyChoices as $currencyChoice) : ?><option value="<?php echo esc_attr($currencyChoice); ?>" <?php selected($selectedCurrency, $currencyChoice); ?>><?php echo esc_html($currencyChoice); ?></option><?php endforeach; ?></select></label></p>
                    <p><label><?php echo esc_html__('Currency symbol', 'safecontracts'); ?><input class="widefat" name="currency_symbol" maxlength="16" value="<?php echo esc_attr($settings['currency_symbol']); ?>" placeholder="د.ك"></label></p>
                    <p class="description"><?php echo esc_html__('Choose the system currency from the approved list and set the display symbol used by mobile financial values. Leaving either blank keeps it explicitly unconfigured.', 'safecontracts'); ?></p>
                    <p><label><?php echo esc_html__('Admin page size', 'safecontracts'); ?><input type="number" min="10" max="200" name="admin_page_size" value="<?php echo esc_attr((string) $settings['admin_page_size']); ?>"></label></p>
                    <?php submit_button(__('Save SafeContracts Settings', 'safecontracts')); ?>
                </form>
                <p class="description"><?php echo esc_html__('These are non-secret operational preferences only. Authorization, assignment scope and financial rules remain server-side and cannot be disabled here.', 'safecontracts'); ?></p>
            </section>
        </div>
        <?php
    }
}
