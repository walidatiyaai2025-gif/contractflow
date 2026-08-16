<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Collections\CollectionService;
use SafeContracts\Deletion\SafeDeletionService;
use SafeContracts\ReferenceData\PaymentMethodRepository;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Support\Input;
use Throwable;

final class CollectionsPage
{
    public const SLUG = 'safecontracts-collections';
    public const SAVE_ACTION = 'safecontracts_record_collection_admin';
    public const DELETE_ACTION = 'safecontracts_delete_collection_admin';

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
        $paymentId = 0;
        try {
            $paymentId = Input::int($_POST['payment_id'] ?? '', 'Payment ID', 1);
            $paymentMethodId = Input::int($_POST['payment_method_id'] ?? '', 'Payment method ID', 1);
            $proofRaw = $_POST['proof_media_id'] ?? '';
            $proofMediaId = ($proofRaw === '' || $proofRaw === null)
                ? null
                : Input::int($proofRaw, 'Proof media ID', 1);

            (new CollectionService())->record([
                'payment_id' => $paymentId,
                'amount' => sanitize_text_field(Input::string($_POST['amount'] ?? '', 'Collection amount')),
                'collection_date' => Input::string($_POST['collection_date'] ?? '', 'Collection date'),
                'payment_method_id' => $paymentMethodId,
                'reference' => sanitize_text_field(Input::string($_POST['reference'] ?? '', 'Collection reference')),
                'details' => sanitize_text_field(Input::string($_POST['details'] ?? '', 'Collection details')),
                'proof_media_id' => $proofMediaId,
            ]);
        } catch (Throwable $error) {
            unset($error);
            $status = 'invalid';
        }
        wp_safe_redirect(add_query_arg(['page' => self::SLUG, 'payment_id' => $paymentId, 'safecontracts_status' => $status], admin_url('admin.php')));
        exit;
    }

    public static function handleDelete(): void
    {
        if (! current_user_can(Capabilities::MANAGE_COLLECTIONS)) {
            wp_die(__('You do not have permission to delete collections.', 'safecontracts'));
        }
        $collectionId = max(0, (int) ($_POST['collection_id'] ?? 0));
        check_admin_referer(self::DELETE_ACTION . '_' . $collectionId);
        $status = 'deleted';
        try {
            (new SafeDeletionService())->archiveCollection($collectionId);
        } catch (Throwable $error) {
            unset($error);
            $status = 'delete_failed';
        }
        wp_safe_redirect(add_query_arg(['page' => self::SLUG, 'safecontracts_status' => $status], admin_url('admin.php')));
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
        try {
            $selectedPaymentId = isset($_GET['payment_id']) && $_GET['payment_id'] !== ''
                ? Input::int($_GET['payment_id'], 'Payment ID', 1)
                : 0;
        } catch (Throwable $error) {
            unset($error);
            $selectedPaymentId = 0;
        }
        $today = function_exists('wp_date') ? wp_date('Y-m-d') : gmdate('Y-m-d');
        ?>
        <div class="wrap safecontracts-settings" dir="auto">
            <div class="safecontracts-section-heading"><div><p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Cash application', 'safecontracts'); ?></p><h1><?php echo esc_html__('Collections', 'safecontracts'); ?></h1></div></div>
            <?php AdminPeriodFilter::render(self::SLUG, $filters, $selectedPaymentId > 0 ? ['payment_id' => $selectedPaymentId] : []); ?>
            <p class="description"><?php echo esc_html__('The displayed period is applied to the collection date.', 'safecontracts'); ?></p>
            <div class="safecontracts-split-layout">
                <section class="safecontracts-admin-card safecontracts-table-card">
                    <h2><?php echo esc_html__('Collection ledger', 'safecontracts'); ?></h2>
                    <table class="widefat striped"><thead><tr><th><?php echo esc_html__('Date', 'safecontracts'); ?></th><th><?php echo esc_html__('Customer / Contract', 'safecontracts'); ?></th><th><?php echo esc_html__('Payment', 'safecontracts'); ?></th><th><?php echo esc_html__('Method', 'safecontracts'); ?></th><th><?php echo esc_html__('Amount', 'safecontracts'); ?></th><th><?php echo esc_html__('Proof', 'safecontracts'); ?></th><th><?php echo esc_html__('Actions', 'safecontracts'); ?></th></tr></thead><tbody>
                    <?php foreach ($collections as $collection) : ?>
                        <tr>
                            <td><?php echo esc_html((string) $collection['collection_date']); ?></td>
                            <td><?php echo esc_html((string) $collection['customer_name'] . ' / ' . (string) $collection['contract_number']); ?></td>
                            <td><?php echo esc_html((string) ($collection['payment_reference'] ?: '#' . $collection['sequence_no'])); ?></td>
                            <td><?php echo esc_html((string) $collection['payment_method_name']); ?></td>
                            <td><?php echo esc_html(number_format((float) $collection['amount'], 2)); ?></td>
                            <td><?php CollectorAttachmentView::render($collection, true); ?></td>
                            <td>
                                <?php if (current_user_can(Capabilities::MANAGE_COLLECTIONS)) : ?>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-safecontracts-delete-form data-delete-message="<?php echo esc_attr__('Delete/reverse this collection? The payment paid amount, remaining amount and status will be recalculated from the remaining active collection ledger.', 'safecontracts'); ?>">
                                        <input type="hidden" name="action" value="<?php echo esc_attr(self::DELETE_ACTION); ?>">
                                        <input type="hidden" name="collection_id" value="<?php echo esc_attr((string) $collection['id']); ?>">
                                        <?php wp_nonce_field(self::DELETE_ACTION . '_' . (int) $collection['id']); ?>
                                        <button type="submit" class="button button-small safecontracts-delete-button"><?php echo esc_html__('Delete', 'safecontracts'); ?></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
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
