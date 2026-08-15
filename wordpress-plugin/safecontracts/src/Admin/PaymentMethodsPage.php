<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use InvalidArgumentException;
use SafeContracts\ReferenceData\PaymentMethodRepository;
use SafeContracts\Roles\Capabilities;

final class PaymentMethodsPage
{
    public const SLUG = 'safecontracts-payment-methods';
    public const SAVE_ACTION = 'safecontracts_save_payment_method';

    public static function register(): void
    {
        add_options_page(
            __('SafeContracts Payment Methods', 'safecontracts'),
            __('SafeContracts Payment Methods', 'safecontracts'),
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
            (new PaymentMethodRepository())->save([
                'code' => sanitize_key((string) ($_POST['code'] ?? '')),
                'name' => sanitize_text_field((string) ($_POST['name'] ?? '')),
                'sort_order' => (int) ($_POST['sort_order'] ?? 0),
                'is_active' => isset($_POST['is_active']),
            ]);
            $status = 'saved';
        } catch (InvalidArgumentException $error) {
            $status = 'invalid';
        }

        wp_safe_redirect(add_query_arg(
            ['page' => self::SLUG, 'safecontracts_status' => $status],
            admin_url('options-general.php')
        ));
        exit;
    }

    public static function render(): void
    {
        if (! current_user_can(Capabilities::MANAGE_REFERENCE_DATA)) {
            wp_die(__('You do not have permission to manage SafeContracts reference data.', 'safecontracts'));
        }

        $methods = (new PaymentMethodRepository())->all(false);
        ?>
        <div class="wrap safecontracts-settings">
            <h1><?php echo esc_html__('SafeContracts — Payment Methods', 'safecontracts'); ?></h1>
            <p><?php echo esc_html__('Manage the payment methods used by collections and mobile reference data.', 'safecontracts'); ?></p>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Code', 'safecontracts'); ?></th>
                        <th><?php echo esc_html__('Name', 'safecontracts'); ?></th>
                        <th><?php echo esc_html__('Order', 'safecontracts'); ?></th>
                        <th><?php echo esc_html__('Active', 'safecontracts'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($methods as $method) : ?>
                        <tr>
                            <td><?php echo esc_html($method['code']); ?></td>
                            <td><?php echo esc_html($method['name']); ?></td>
                            <td><?php echo esc_html((string) $method['sort_order']); ?></td>
                            <td><?php echo $method['is_active'] ? esc_html__('Yes', 'safecontracts') : esc_html__('No', 'safecontracts'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h2><?php echo esc_html__('Add or update method', 'safecontracts'); ?></h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_ACTION); ?>">
                <?php wp_nonce_field(self::SAVE_ACTION); ?>
                <p><label><?php echo esc_html__('Code', 'safecontracts'); ?> <input name="code" required maxlength="50"></label></p>
                <p><label><?php echo esc_html__('Name', 'safecontracts'); ?> <input name="name" required maxlength="120"></label></p>
                <p><label><?php echo esc_html__('Order', 'safecontracts'); ?> <input type="number" min="0" name="sort_order" value="0"></label></p>
                <p><label><input type="checkbox" name="is_active" value="1" checked> <?php echo esc_html__('Active', 'safecontracts'); ?></label></p>
                <?php submit_button(__('Save Payment Method', 'safecontracts')); ?>
            </form>
        </div>
        <?php
    }
}
