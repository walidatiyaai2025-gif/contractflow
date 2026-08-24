<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use InvalidArgumentException;
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
    public const WORKER_STYLE_HANDLE = 'safecontracts-plugin-redesign-worker-2';
    public const WORKER_STATE_STYLE_HANDLE = 'safecontracts-plugin-redesign-worker-2-states';
    public const WORKER_SCRIPT_HANDLE = 'safecontracts-plugin-redesign-worker-2-ui';

    /** @var list<string> */
    private const WORKER_ROUTE_SLUGS = [
        'safecontracts-payments',
        'safecontracts-collections',
        'safecontracts-followups',
        'safecontracts-finance',
        'safecontracts-reports',
        'safecontracts-imports',
        self::SLUG,
    ];

    private static bool $redesignAssetsRegistered = false;

    public static function register(): void
    {
        if (! self::$redesignAssetsRegistered) {
            self::$redesignAssetsRegistered = true;
            add_action('admin_enqueue_scripts', [self::class, 'enqueueWorkerRedesignAssets'], 40);
        }

        add_submenu_page(
            AdminShell::SLUG,
            __('Payment Methods', 'safecontracts'),
            __('Payment Methods', 'safecontracts'),
            Capabilities::MANAGE_REFERENCE_DATA,
            self::SLUG,
            [self::class, 'render']
        );
    }

    public static function enqueueWorkerRedesignAssets(): void
    {
        $page = sanitize_key((string) ($_GET['page'] ?? ''));
        if (! in_array($page, self::WORKER_ROUTE_SLUGS, true)) {
            return;
        }

        wp_enqueue_style(
            self::WORKER_STYLE_HANDLE,
            SAFECONTRACTS_URL . 'assets/admin/plugin-redesign/worker-2/finance-operations.css',
            [AdminShell::PREMIUM_STYLE_HANDLE],
            SAFECONTRACTS_VERSION
        );
        wp_enqueue_style(
            self::WORKER_STATE_STYLE_HANDLE,
            SAFECONTRACTS_URL . 'assets/admin/plugin-redesign/worker-2/route-states.css',
            [self::WORKER_STYLE_HANDLE],
            SAFECONTRACTS_VERSION
        );
        wp_enqueue_script(
            self::WORKER_SCRIPT_HANDLE,
            SAFECONTRACTS_URL . 'assets/admin/plugin-redesign/worker-2/finance-operations.js',
            [],
            SAFECONTRACTS_VERSION,
            true
        );
        wp_localize_script(
            self::WORKER_SCRIPT_HANDLE,
            'safecontractsWorker2Ui',
            [
                'emptyScope' => __('No obligations match this scope.', 'safecontracts'),
            ]
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
        <div class="wrap safecontracts-settings safecontracts-payment-methods-page" dir="auto">
            <div class="safecontracts-section-heading"><div><p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Reference data', 'safecontracts'); ?></p><h1><?php echo esc_html__('Payment Methods', 'safecontracts'); ?></h1></div></div>
            <div class="safecontracts-split-layout">
                <section class="safecontracts-admin-card safecontracts-table-card">
                    <table class="widefat striped">
                        <thead><tr><th><?php echo esc_html__('Code', 'safecontracts'); ?></th><th><?php echo esc_html__('Name', 'safecontracts'); ?></th><th><?php echo esc_html__('Status', 'safecontracts'); ?></th><th><?php echo esc_html__('Order', 'safecontracts'); ?></th><th><?php echo esc_html__('Actions', 'safecontracts'); ?></th></tr></thead>
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
                                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-safecontracts-delete-form data-delete-message="<?php echo esc_attr__('Delete this payment method from active choices? Existing collection history will keep its method reference.', 'safecontracts'); ?>">
                                                <input type="hidden" name="action" value="<?php echo esc_attr(self::DELETE_ACTION); ?>">
                                                <input type="hidden" name="payment_method_id" value="<?php echo esc_attr((string) $method['id']); ?>">
                                                <?php wp_nonce_field(self::DELETE_ACTION . '_' . (int) $method['id']); ?>
                                                <button type="submit" class="button button-small safecontracts-delete-button"><?php echo esc_html__('Delete', 'safecontracts'); ?></button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
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
                    <p class="description"><?php echo esc_html__('Collection entry accepts only active SafeContracts payment methods. Delete safely deactivates a method without changing historical collections.', 'safecontracts'); ?></p>
                </section>
            </div>
        </div>
        <?php
    }
}
