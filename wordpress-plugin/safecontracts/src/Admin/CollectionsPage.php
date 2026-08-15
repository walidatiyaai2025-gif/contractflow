<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Collections\CollectionService;
use SafeContracts\ReferenceData\PaymentMethodRepository;
use SafeContracts\Roles\Capabilities;
use Throwable;

final class CollectionsPage
{
    public const SLUG = 'safecontracts-collections';
    public const SAVE_ACTION = 'safecontracts_record_collection_admin';

    public static function register(): void
    {
        add_submenu_page(AdminShell::SLUG, __('Collections', 'safecontracts'), __('Collections', 'safecontracts'), Capabilities::ACCESS, self::SLUG, [self::class, 'render']);
    }

    public static function handleSave(): void
    {
        if (! current_user_can(Capabilities::MANAGE_COLLECTIONS)) {
            wp_die(__('You do not have permission to record collections.', 'safecontracts'));
        }
        check_admin_referer(self::SAVE_ACTION);
        $status = 'saved';
        $paymentId = max(0, (int) ($_POST['payment_id'] ?? 0));
        try {
            (new CollectionService())->record([
                'payment_id' => $paymentId,
                'amount' => sanitize_text_field((string) ($_POST['amount'] ?? '')),
                'collection_date' => (string) ($_POST['collection_date'] ?? ''),
                'payment_method_id' => (int) ($_POST['payment_method_id'] ?? 0),
                'reference' => sanitize_text_field((string) ($_POST['reference'] ?? '')),
                'details' => sanitize_text_field((string) ($_POST['details'] ?? '')),
                'proof_media_id' => (int) ($_POST['proof_media_id'] ?? 0) ?: null,
            ]);
        } catch (Throwable $error) {
            unset($error);
            $status = 'invalid';
        }
        wp_safe_redirect(add_query_arg(['page' => self::SLUG, 'payment_id' => $paymentId, 'safecontracts_status' => $status], admin_url('admin.php')));
        exit;
    }

    public static function render(): void
    {
        if (! current_user_can(Capabilities::ACCESS)) {
            wp_die(__('You do not have permission to access collections.', 'safecontracts'));
        }
        $read = new AdminReadRepository();
        $filters = DashboardFilters::normalize($_GET);
        $collections = $read->collections($filters);
        $payments = $read->payments($filters);
        $methods = (new PaymentMethodRepository())->all(true);
        $selectedPaymentId = max(0, (int) ($_GET['payment_id'] ?? 0));
        $today = function_exists('wp_date') ? wp_date('Y-m-d') : gmdate('Y-m-d');
        ?>
        <div class="wrap safecontracts-settings" dir="auto">
            <div class="safecontracts-section-heading"><div><p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Cash application', 'safecontracts'); ?></p><h1><?php echo esc_html__('Collections', 'safecontracts'); ?></h1></div></div>
            <div class="safecontracts-split-layout">
                <section class="safecontracts-admin-card safecontracts-table-card">
                    <h2><?php echo esc_html__('Collection ledger', 'safecontracts'); ?></h2>
                    <table class="widefat striped"><thead><tr><th><?php echo esc_html__('Date', 'safecontracts'); ?></th><th><?php echo esc_html__('Customer / Contract', 'safecontracts'); ?></th><th><?php echo esc_html__('Payment', 'safecontracts'); ?></th><th><?php echo esc_html__('Method', 'safecontracts'); ?></th><th><?php echo esc_html__('Amount', 'safecontracts'); ?></th></tr></thead><tbody>
                    <?php foreach ($collections as $collection) : ?>
                        <tr><td><?php echo esc_html((string) $collection['collection_date']); ?></td><td><?php echo esc_html((string) $collection['customer_name'] . ' / ' . (string) $collection['contract_number']); ?></td><td><?php echo esc_html((string) ($collection['payment_reference'] ?: '#' . $collection['sequence_no'])); ?></td><td><?php echo esc_html((string) $collection['payment_method_name']); ?></td><td><?php echo esc_html(number_format((float) $collection['amount'], 2)); ?></td></tr>
                    <?php endforeach; ?>
                    </tbody></table>
                </section>
                <?php if (current_user_can(Capabilities::MANAGE_COLLECTIONS)) : ?>
                <section class="safecontracts-admin-card">
                    <h2><?php echo esc_html__('Record collection', 'safecontracts'); ?></h2>
                    <p class="description"><?php echo esc_html__('The backend collection service enforces active payment methods, assignment scope, exact remaining balance and atomic settlement reconciliation. Proof is optional.', 'safecontracts'); ?></p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_ACTION); ?>"><?php wp_nonce_field(self::SAVE_ACTION); ?>
                        <p><label><?php echo esc_html__('Payment', 'safecontracts'); ?><select class="widefat" name="payment_id" required><option value="0"><?php echo esc_html__('Select payment', 'safecontracts'); ?></option><?php foreach ($payments as $payment) : ?><option value="<?php echo esc_attr((string) $payment['id']); ?>" <?php selected($selectedPaymentId, (int) $payment['id']); ?>><?php echo esc_html((string) $payment['customer_name'] . ' / ' . (string) $payment['contract_number'] . ' / ' . (string) ($payment['reference'] ?: '#' . $payment['sequence_no']) . ' — ' . number_format((float) $payment['remaining_amount'], 2)); ?></option><?php endforeach; ?></select></label></p>
                        <p class="safecontracts-field-row"><label><?php echo esc_html__('Amount', 'safecontracts'); ?><input type="text" inputmode="decimal" name="amount" required></label><label><?php echo esc_html__('Collection date', 'safecontracts'); ?><input type="date" name="collection_date" value="<?php echo esc_attr($today); ?>" required></label></p>
                        <p><label><?php echo esc_html__('Payment method', 'safecontracts'); ?><select class="widefat" name="payment_method_id" required><option value="0"><?php echo esc_html__('Select active method', 'safecontracts'); ?></option><?php foreach ($methods as $method) : ?><option value="<?php echo esc_attr((string) $method['id']); ?>"><?php echo esc_html((string) $method['name']); ?></option><?php endforeach; ?></select></label></p>
                        <p><label><?php echo esc_html__('Reference', 'safecontracts'); ?><input class="widefat" name="reference" maxlength="191"></label></p>
                        <p><label><?php echo esc_html__('Details', 'safecontracts'); ?><textarea class="widefat" name="details" rows="4" maxlength="5000"></textarea></label></p>
                        <p><label><?php echo esc_html__('Proof media ID (optional)', 'safecontracts'); ?><input class="widefat" type="number" min="1" name="proof_media_id"></label></p>
                        <?php submit_button(__('Record collection', 'safecontracts')); ?>
                    </form>
                </section>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
