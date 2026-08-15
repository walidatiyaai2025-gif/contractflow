<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use InvalidArgumentException;
use SafeContracts\ReferenceData\PaymentMethodRepository;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Support\Input;

final class PaymentMethodsPage
{
    public const SLUG = 'safecontracts-payment-methods';
    public const SAVE_ACTION = 'safecontracts_save_payment_method';

    public static function register(): void
    {
        add_submenu_page(
            AdminShell::SLUG,
            __('Payment Methods', 'safecontracts'),
            __('Payment Methods', 'safecontracts'),
            Capabilities::MANAGE_REFERENCE_DATA,
            self::SLUG,
            [self::class, 'render']
        );
    }

    public static function handleSave(): void
    {
        if (! current_user_can(Capabilities::MANAGE_REFERENCE_DATA)) {
            wp_die(__('You do not have permission to manage SafeContracts reference data.', 'safecontracts'));
        }

        check_admin_referer(self::SAVE_ACTION);

        try {
            $originalCode = sanitize_key(Input::string($_POST['original_code'] ?? '', 'Original payment method code'));
            $requestedCode = sanitize_key(Input::string($_POST['code'] ?? '', 'Payment method code'));
            $code = $originalCode !== '' ? $originalCode : $requestedCode;
            (new PaymentMethodRepository())->save([
                'code' => $code,
                'name' => sanitize_text_field(Input::string($_POST['name'] ?? '', 'Payment method name')),
                'display_order' => $_POST['display_order'] ?? 0,
                'is_active' => isset($_POST['is_active']),
            ]);
            $status = 'saved';
        } catch (InvalidArgumentException $error) {
            unset($error);
            $status = 'invalid';
        }

        wp_safe_redirect(add_query_arg(
            ['page' => self::SLUG, 'safecontracts_status' => $status],
            admin_url('admin.php')
        ));
        exit;
    }

    public static function render(): void
    {
        if (! current_user_can(Capabilities::MANAGE_REFERENCE_DATA)) {
            wp_die(__('You do not have permission to manage SafeContracts reference data.', 'safecontracts'));
        }

        $methods = (new PaymentMethodRepository())->all(false);
        $selected = null;
        try {
            $selectedCode = sanitize_key(Input::string($_GET['method'] ?? '', 'Payment method code'));
        } catch (InvalidArgumentException $error) {
            unset($error);
            $selectedCode = '';
        }
        foreach ($methods as $method) {
            if ($selectedCode !== '' && $method['code'] === $selectedCode) {
                $selected = $method;
                break;
            }
        }
        ?>
        <div class="wrap safecontracts-settings" dir="auto">
            <div class="safecontracts-section-heading"><div><p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Reference data', 'safecontracts'); ?></p><h1><?php echo esc_html__('Payment Methods', 'safecontracts'); ?></h1></div></div>
            <div class="safecontracts-split-layout">
                <section class="safecontracts-admin-card safecontracts-table-card">
                    <table class="widefat striped">
                        <thead><tr><th><?php echo esc_html__('Code', 'safecontracts'); ?></th><th><?php echo esc_html__('Name', 'safecontracts'); ?></th><th><?php echo esc_html__('Order', 'safecontracts'); ?></th><th><?php echo esc_html__('Active', 'safecontracts'); ?></th></tr></thead>
                        <tbody><?php foreach ($methods as $method) : ?><tr><td><a href="<?php echo esc_url(add_query_arg(['page' => self::SLUG, 'method' => $method['code']], admin_url('admin.php'))); ?>"><code><?php echo esc_html($method['code']); ?></code></a></td><td><?php echo esc_html($method['name']); ?></td><td><?php echo esc_html((string) $method['display_order']); ?></td><td><?php echo $method['is_active'] ? esc_html__('Yes', 'safecontracts') : esc_html__('No', 'safecontracts'); ?></td></tr><?php endforeach; ?></tbody>
                    </table>
                </section>
                <section class="safecontracts-admin-card safecontracts-settings-card">
                    <h2><?php echo $selected ? esc_html__('Edit payment method', 'safecontracts') : esc_html__('Add payment method', 'safecontracts'); ?></h2>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_ACTION); ?>"><?php wp_nonce_field(self::SAVE_ACTION); ?>
                        <input type="hidden" name="original_code" value="<?php echo esc_attr((string) ($selected['code'] ?? '')); ?>">
                        <?php if ($selected) : ?><p><strong><?php echo esc_html__('Stable code', 'safecontracts'); ?>:</strong> <code><?php echo esc_html($selected['code']); ?></code></p><?php else : ?><p><label><?php echo esc_html__('Code', 'safecontracts'); ?><input class="widefat" name="code" required maxlength="50"></label></p><?php endif; ?>
                        <p><label><?php echo esc_html__('Name', 'safecontracts'); ?><input class="widefat" name="name" required maxlength="120" value="<?php echo esc_attr((string) ($selected['name'] ?? '')); ?>"></label></p>
                        <p><label><?php echo esc_html__('Order', 'safecontracts'); ?><input type="number" min="0" max="100000" name="display_order" value="<?php echo esc_attr((string) ($selected['display_order'] ?? 0)); ?>"></label></p>
                        <p><label><input type="checkbox" name="is_active" value="1" <?php checked($selected === null || ! empty($selected['is_active'])); ?>> <?php echo esc_html__('Active', 'safecontracts'); ?></label></p>
                        <?php submit_button($selected ? __('Save Payment Method', 'safecontracts') : __('Add Payment Method', 'safecontracts')); ?>
                    </form>
                    <p class="description"><?php echo esc_html__('Collection entry accepts only active SafeContracts payment methods. Method codes are stable once created; names, order and active state remain admin-managed.', 'safecontracts'); ?></p>
                </section>
            </div>
        </div>
        <?php
    }
}
