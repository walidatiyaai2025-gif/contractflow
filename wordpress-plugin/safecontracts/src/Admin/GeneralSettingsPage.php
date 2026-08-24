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
        <div class="wrap safecontracts-settings safecontracts-general-settings" dir="auto">
            <div class="safecontracts-section-heading safecontracts-page-heading">
                <div>
                    <p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Settings & integrations', 'safecontracts'); ?></p>
                    <h1><?php echo esc_html__('General Settings', 'safecontracts'); ?></h1>
                    <p class="description"><?php echo esc_html__('Manage organization identity and non-secret operational display preferences. Permissions, assignment scope and accounting rules remain server-side.', 'safecontracts'); ?></p>
                </div>
            </div>

            <div class="safecontracts-settings-grid">
                <section class="safecontracts-admin-card safecontracts-settings-card safecontracts-settings-panel">
                    <div>
                        <h2><?php echo esc_html__('Organization & display', 'safecontracts'); ?></h2>
                        <p class="description"><?php echo esc_html__('These values are used by SafeContracts administrative and mobile presentation without changing financial authority.', 'safecontracts'); ?></p>
                    </div>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_ACTION); ?>">
                        <?php wp_nonce_field(self::SAVE_ACTION); ?>
                        <div class="safecontracts-field-grid">
                            <label class="safecontracts-field safecontracts-field--full">
                                <span><?php echo esc_html__('Organization name', 'safecontracts'); ?></span>
                                <input class="widefat" name="organization_name" maxlength="191" required value="<?php echo esc_attr((string) $settings['organization_name']); ?>">
                                <span class="safecontracts-field__hint"><?php echo esc_html__('The business name displayed in SafeContracts-managed experiences.', 'safecontracts'); ?></span>
                            </label>
                            <label class="safecontracts-field">
                                <span><?php echo esc_html__('System currency', 'safecontracts'); ?></span>
                                <select class="widefat" name="currency_code">
                                    <option value=""><?php echo esc_html__('Select currency', 'safecontracts'); ?></option>
                                    <?php foreach ($currencyChoices as $currencyChoice) : ?>
                                        <option value="<?php echo esc_attr($currencyChoice); ?>" <?php selected($selectedCurrency, $currencyChoice); ?>><?php echo esc_html($currencyChoice); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="safecontracts-field__hint"><?php echo esc_html__('Use an approved ISO currency code. Cross-currency values remain separated.', 'safecontracts'); ?></span>
                            </label>
                            <label class="safecontracts-field">
                                <span><?php echo esc_html__('Currency symbol', 'safecontracts'); ?></span>
                                <input class="widefat" name="currency_symbol" maxlength="16" value="<?php echo esc_attr((string) $settings['currency_symbol']); ?>" placeholder="د.ك">
                                <span class="safecontracts-field__hint"><?php echo esc_html__('Presentation symbol only; it does not replace the stored currency code.', 'safecontracts'); ?></span>
                            </label>
                            <label class="safecontracts-field">
                                <span><?php echo esc_html__('Admin page size', 'safecontracts'); ?></span>
                                <input type="number" min="10" max="200" name="admin_page_size" value="<?php echo esc_attr((string) $settings['admin_page_size']); ?>">
                                <span class="safecontracts-field__hint"><?php echo esc_html__('Allowed range: 10–200 records per administrative page.', 'safecontracts'); ?></span>
                            </label>
                        </div>
                        <?php submit_button(__('Save General Settings', 'safecontracts')); ?>
                    </form>
                </section>

                <aside class="safecontracts-admin-card safecontracts-settings-card">
                    <p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Current configuration', 'safecontracts'); ?></p>
                    <h2><?php echo esc_html__('Operational summary', 'safecontracts'); ?></h2>
                    <dl class="safecontracts-system-summary">
                        <div><dt><?php echo esc_html__('Organization', 'safecontracts'); ?></dt><dd><?php echo esc_html((string) ($settings['organization_name'] ?: '—')); ?></dd></div>
                        <div><dt><?php echo esc_html__('Currency', 'safecontracts'); ?></dt><dd><?php echo esc_html($selectedCurrency !== '' ? $selectedCurrency : '—'); ?></dd></div>
                        <div><dt><?php echo esc_html__('Symbol', 'safecontracts'); ?></dt><dd><?php echo esc_html((string) ($settings['currency_symbol'] ?: '—')); ?></dd></div>
                        <div><dt><?php echo esc_html__('Rows per page', 'safecontracts'); ?></dt><dd><?php echo esc_html((string) $settings['admin_page_size']); ?></dd></div>
                    </dl>
                    <p class="description"><?php echo esc_html__('Secrets, authorization, tenant/data scope and financial rules cannot be disabled from this page.', 'safecontracts'); ?></p>
                </aside>
            </div>
        </div>
        <?php
    }
}
