<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Customers\CustomerService;
use SafeContracts\Roles\Capabilities;
use Throwable;

final class CustomersPage
{
    public const SLUG = 'safecontracts-customers';
    public const SAVE_ACTION = 'safecontracts_save_customer';

    public static function register(): void
    {
        add_submenu_page(
            AdminShell::SLUG,
            __('Customers', 'safecontracts'),
            __('Customers', 'safecontracts'),
            Capabilities::ACCESS,
            self::SLUG,
            [self::class, 'render']
        );
    }

    public static function handleSave(): void
    {
        if (! current_user_can(Capabilities::MANAGE_REFERENCE_DATA)) {
            wp_die(__('You do not have permission to manage customers.', 'safecontracts'));
        }
        check_admin_referer(self::SAVE_ACTION);
        $status = 'saved';
        try {
            (new CustomerService())->save([
                'id' => (int) ($_POST['customer_id'] ?? 0),
                'internal_code' => sanitize_text_field((string) ($_POST['internal_code'] ?? '')),
                'name' => sanitize_text_field((string) ($_POST['name'] ?? '')),
                'contact_name' => sanitize_text_field((string) ($_POST['contact_name'] ?? '')),
                'email' => sanitize_text_field((string) ($_POST['email'] ?? '')),
                'phone' => sanitize_text_field((string) ($_POST['phone'] ?? '')),
                'notes' => sanitize_textarea_field((string) ($_POST['notes'] ?? '')),
                'is_active' => isset($_POST['is_active']),
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
        if (! current_user_can(Capabilities::ACCESS)) {
            wp_die(__('You do not have permission to access customers.', 'safecontracts'));
        }
        $read = new AdminReadRepository();
        $customers = $read->customers($_GET);
        $editing = null;
        $editId = max(0, (int) ($_GET['customer_id'] ?? 0));
        if ($editId > 0 && current_user_can(Capabilities::MANAGE_REFERENCE_DATA)) {
            try {
                $editing = (new CustomerService())->find($editId);
            } catch (Throwable $error) {
                unset($error);
            }
        }
        ?>
        <div class="wrap safecontracts-settings" dir="auto">
            <div class="safecontracts-section-heading"><div><p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Master data', 'safecontracts'); ?></p><h1><?php echo esc_html__('Customers', 'safecontracts'); ?></h1></div></div>
            <div class="safecontracts-split-layout">
                <section class="safecontracts-admin-card safecontracts-table-card">
                    <table class="widefat striped"><thead><tr><th><?php echo esc_html__('Code', 'safecontracts'); ?></th><th><?php echo esc_html__('Customer', 'safecontracts'); ?></th><th><?php echo esc_html__('Contact', 'safecontracts'); ?></th><th><?php echo esc_html__('Status', 'safecontracts'); ?></th></tr></thead><tbody>
                    <?php foreach ($customers as $customer) : ?>
                        <tr><td><?php echo esc_html((string) ($customer['internal_code'] ?? '—')); ?></td><td><a href="<?php echo esc_url(add_query_arg(['page' => self::SLUG, 'customer_id' => (int) $customer['id']], admin_url('admin.php'))); ?>"><?php echo esc_html((string) $customer['name']); ?></a></td><td><?php echo esc_html((string) ($customer['contact_name'] ?? $customer['email'] ?? '')); ?></td><td><?php echo ! empty($customer['is_active']) ? esc_html__('Active', 'safecontracts') : esc_html__('Inactive', 'safecontracts'); ?></td></tr>
                    <?php endforeach; ?>
                    </tbody></table>
                </section>
                <?php if (current_user_can(Capabilities::MANAGE_REFERENCE_DATA)) : ?>
                <section class="safecontracts-admin-card">
                    <h2><?php echo $editing ? esc_html__('Edit customer', 'safecontracts') : esc_html__('Add customer', 'safecontracts'); ?></h2>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_ACTION); ?>"><input type="hidden" name="customer_id" value="<?php echo esc_attr((string) ($editing['id'] ?? 0)); ?>"><?php wp_nonce_field(self::SAVE_ACTION); ?>
                        <p><label><?php echo esc_html__('Internal code (optional)', 'safecontracts'); ?><input class="widefat" name="internal_code" maxlength="100" value="<?php echo esc_attr((string) ($editing['internal_code'] ?? '')); ?>"></label></p>
                        <p><label><?php echo esc_html__('Name', 'safecontracts'); ?><input class="widefat" name="name" required maxlength="191" value="<?php echo esc_attr((string) ($editing['name'] ?? '')); ?>"></label></p>
                        <p><label><?php echo esc_html__('Contact name', 'safecontracts'); ?><input class="widefat" name="contact_name" maxlength="191" value="<?php echo esc_attr((string) ($editing['contact_name'] ?? '')); ?>"></label></p>
                        <p><label><?php echo esc_html__('Email', 'safecontracts'); ?><input class="widefat" type="email" name="email" maxlength="191" value="<?php echo esc_attr((string) ($editing['email'] ?? '')); ?>"></label></p>
                        <p><label><?php echo esc_html__('Phone', 'safecontracts'); ?><input class="widefat" name="phone" maxlength="64" value="<?php echo esc_attr((string) ($editing['phone'] ?? '')); ?>"></label></p>
                        <p><label><?php echo esc_html__('Notes', 'safecontracts'); ?><textarea class="widefat" name="notes" rows="4"><?php echo esc_textarea((string) ($editing['notes'] ?? '')); ?></textarea></label></p>
                        <p><label><input type="checkbox" name="is_active" value="1" <?php checked((bool) ($editing['is_active'] ?? true)); ?>> <?php echo esc_html__('Active', 'safecontracts'); ?></label></p>
                        <?php submit_button($editing ? __('Update customer', 'safecontracts') : __('Add customer', 'safecontracts')); ?>
                    </form>
                </section>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
