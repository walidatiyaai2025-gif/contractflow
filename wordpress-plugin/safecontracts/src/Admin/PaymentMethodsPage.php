<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use InvalidArgumentException;
use SafeContracts\Admin\Worker2\RedesignAssets;
use SafeContracts\Deletion\SafeDeletionService;
use SafeContracts\ReferenceData\PaymentMethodRepository;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Support\Input;
use Throwable;

final class PaymentMethodsPage
{
    public const SLUG = 'safecontracts-payment-methods';
    public const SAVE_ACTION = 'safecontracts_save_payment_method';
    public const DELETE_ACTION = 'safecontracts_delete_payment_method';

    public static function register(): void
    {
        // Register the isolated Worker #2 visual layer from a Worker #2-owned
        // route. The registrar itself only enqueues on the seven frozen slugs.
        RedesignAssets::register();

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
        } catch (Throwable $error) {
            unset($error);
            $status = 'invalid';
        }

        wp_safe_redirect(add_query_arg(
            ['page' => self::SLUG, 'safecontracts_status' => $status],
            admin_url('admin.php')
        ));
        exit;
    }

    public static function handleDelete(): void
    {
        if (! current_user_can(Capabilities::MANAGE_REFERENCE_DATA)) {
            wp_die(__('You do not have permission to delete payment methods.', 'safecontracts'));
        }

        $paymentMethodId = max(0, (int) ($_POST['payment_method_id'] ?? 0));
        check_admin_referer(self::DELETE_ACTION . '_' . $paymentMethodId);
        $status = 'deleted';
        try {
            (new SafeDeletionService())->archivePaymentMethod($paymentMethodId);
        } catch (Throwable $error) {
            unset($error);
            $status = 'delete_failed';
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

        // Show inactive methods as well. Historical method references remain
        // authoritative, and an inactive method may be deliberately reopened.
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

        $status = isset($_GET['safecontracts_status']) && is_scalar($_GET['safecontracts_status'])
            ? sanitize_key((string) $_GET['safecontracts_status'])
            : '';
        ?>
        <div class="wrap safecontracts-settings safecontracts-payment-methods-page" dir="auto">
            <div class="safecontracts-section-heading">
                <div>
                    <p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Reference data', 'safecontracts'); ?></p>
                    <h1><?php echo esc_html__('Payment Methods', 'safecontracts'); ?></h1>
                    <p class="description"><?php echo esc_html__('Control the active methods available when recording settlements. Deactivation never rewrites historical collection records.', 'safecontracts'); ?></p>
                </div>
            </div>

            <?php if ($status === 'saved') : ?>
                <div class="notice notice-success inline"><p><?php echo esc_html__('Payment method saved.', 'safecontracts'); ?></p></div>
            <?php elseif ($status === 'deleted') : ?>
                <div class="notice notice-success inline"><p><?php echo esc_html__('Payment method deactivated. Existing settlement history was preserved.', 'safecontracts'); ?></p></div>
            <?php elseif ($status === 'invalid') : ?>
                <div class="notice notice-error inline"><p><?php echo esc_html__('Payment method was not saved. Check the code, name and ordering values.', 'safecontracts'); ?></p></div>
            <?php elseif ($status === 'delete_failed') : ?>
                <div class="notice notice-error inline"><p><?php echo esc_html__('Payment method could not be deactivated.', 'safecontracts'); ?></p></div>
            <?php endif; ?>

            <div class="safecontracts-split-layout">
                <section class="safecontracts-admin-card safecontracts-table-card">
                    <div class="safecontracts-payment-panel__heading">
                        <div>
                            <h2><?php echo esc_html__('Configured methods', 'safecontracts'); ?></h2>
                            <p><?php echo esc_html__('Active methods appear in settlement entry. Inactive methods remain visible here for audit-safe administration.', 'safecontracts'); ?></p>
                        </div>
                        <span class="safecontracts-direction-pill"><?php echo esc_html((string) count($methods)); ?></span>
                    </div>

                    <?php if ($methods === []) : ?>
                        <div class="safecontracts-w2-empty">
                            <strong><?php echo esc_html__('No payment methods configured.', 'safecontracts'); ?></strong>
                            <span><?php echo esc_html__('Add the first method using the form beside this list.', 'safecontracts'); ?></span>
                        </div>
                    <?php else : ?>
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th><?php echo esc_html__('Code', 'safecontracts'); ?></th>
                                    <th><?php echo esc_html__('Name', 'safecontracts'); ?></th>
                                    <th><?php echo esc_html__('Status', 'safecontracts'); ?></th>
                                    <th><?php echo esc_html__('Order', 'safecontracts'); ?></th>
                                    <th><?php echo esc_html__('Actions', 'safecontracts'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($methods as $method) : ?>
                                <?php $isActive = ! empty($method['is_active']); ?>
                                <tr>
                                    <td><a href="<?php echo esc_url(add_query_arg(['page' => self::SLUG, 'method' => $method['code']], admin_url('admin.php'))); ?>"><code><?php echo esc_html($method['code']); ?></code></a></td>
                                    <td><?php echo esc_html($method['name']); ?></td>
                                    <td><span class="safecontracts-w2-status safecontracts-w2-status--<?php echo $isActive ? 'active' : 'inactive'; ?>"><?php echo esc_html($isActive ? __('Active', 'safecontracts') : __('Inactive', 'safecontracts')); ?></span></td>
                                    <td><?php echo esc_html((string) $method['display_order']); ?></td>
                                    <td>
                                        <div class="safecontracts-dashboard-table-actions">
                                            <a class="button button-small" href="<?php echo esc_url(add_query_arg(['page' => self::SLUG, 'method' => $method['code']], admin_url('admin.php'))); ?>"><?php echo esc_html__('Open', 'safecontracts'); ?></a>
                                            <?php if ($isActive) : ?>
                                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-safecontracts-delete-form data-delete-message="<?php echo esc_attr__('Deactivate this payment method from active choices? Existing collection history will keep its method reference.', 'safecontracts'); ?>">
                                                    <input type="hidden" name="action" value="<?php echo esc_attr(self::DELETE_ACTION); ?>">
                                                    <input type="hidden" name="payment_method_id" value="<?php echo esc_attr((string) $method['id']); ?>">
                                                    <?php wp_nonce_field(self::DELETE_ACTION . '_' . (int) $method['id']); ?>
                                                    <button type="submit" class="button button-small safecontracts-delete-button"><?php echo esc_html__('Deactivate', 'safecontracts'); ?></button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </section>

                <section class="safecontracts-admin-card safecontracts-settings-card">
                    <h2><?php echo $selected ? esc_html__('Edit payment method', 'safecontracts') : esc_html__('Add payment method', 'safecontracts'); ?></h2>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_ACTION); ?>">
                        <?php wp_nonce_field(self::SAVE_ACTION); ?>
                        <input type="hidden" name="original_code" value="<?php echo esc_attr((string) ($selected['code'] ?? '')); ?>">

                        <?php if ($selected) : ?>
                            <p><strong><?php echo esc_html__('Stable code', 'safecontracts'); ?>:</strong> <code><?php echo esc_html($selected['code']); ?></code></p>
                        <?php else : ?>
                            <p><label><?php echo esc_html__('Code', 'safecontracts'); ?><input class="widefat" name="code" required maxlength="50" pattern="[a-z0-9][a-z0-9_-]{1,49}" autocomplete="off"></label></p>
                            <p class="description"><?php echo esc_html__('Use 2–50 lowercase letters, numbers, underscores or hyphens. The code is stable after creation.', 'safecontracts'); ?></p>
                        <?php endif; ?>

                        <p><label><?php echo esc_html__('Name', 'safecontracts'); ?><input class="widefat" name="name" required maxlength="120" value="<?php echo esc_attr((string) ($selected['name'] ?? '')); ?>"></label></p>
                        <p><label><?php echo esc_html__('Order', 'safecontracts'); ?><input type="number" min="0" max="100000" name="display_order" value="<?php echo esc_attr((string) ($selected['display_order'] ?? 0)); ?>"></label></p>
                        <p><label><input type="checkbox" name="is_active" value="1" <?php checked($selected === null || ! empty($selected['is_active'])); ?>> <?php echo esc_html__('Active for new settlement entry', 'safecontracts'); ?></label></p>
                        <?php submit_button($selected ? __('Save Payment Method', 'safecontracts') : __('Add Payment Method', 'safecontracts')); ?>
                    </form>
                    <p class="description"><?php echo esc_html__('Collection entry accepts only active payment methods. Deactivation is soft and does not alter historical collections.', 'safecontracts'); ?></p>
                </section>
            </div>
        </div>
        <?php
    }
}
