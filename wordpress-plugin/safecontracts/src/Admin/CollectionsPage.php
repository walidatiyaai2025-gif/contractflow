<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Attachments\EntityAttachmentRepository;
use SafeContracts\Attachments\EntityAttachmentService;
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
        $collectionId = 0;
        $uploadedMediaIds = [];
        $linkingAttachments = false;
        try {
            $uploadedMediaIds = MultipleAttachmentUploader::upload();
            $paymentId = Input::int($_POST['payment_id'] ?? '', 'Payment ID', 1);
            $paymentMethodId = Input::int($_POST['payment_method_id'] ?? '', 'Payment method ID', 1);
            $proofMediaId = $uploadedMediaIds[0] ?? null;

            $collectionId = (new CollectionService())->record([
                'payment_id' => $paymentId,
                'amount' => sanitize_text_field(Input::string($_POST['amount'] ?? '', 'Collection amount')),
                'collection_date' => Input::string($_POST['collection_date'] ?? '', 'Collection date'),
                'payment_method_id' => $paymentMethodId,
                'reference' => sanitize_text_field(Input::string($_POST['reference'] ?? '', 'Collection reference')),
                'details' => sanitize_text_field(Input::string($_POST['details'] ?? '', 'Collection details')),
                'proof_media_id' => $proofMediaId,
            ]);

            if ($uploadedMediaIds !== []) {
                $attachments = new EntityAttachmentService();
                $attachments->assertCanManage(EntityAttachmentService::COLLECTION, $collectionId);
                $linkingAttachments = true;
                $attachments->attachMany(EntityAttachmentService::COLLECTION, $collectionId, $uploadedMediaIds);
            }
        } catch (Throwable $error) {
            unset($error);
            if (! $linkingAttachments && $uploadedMediaIds !== [] && $collectionId === 0) {
                MultipleAttachmentUploader::cleanup($uploadedMediaIds);
            }
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
        $collectionIds = array_values(array_filter(array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $collections), static fn (int $id): bool => $id > 0));
        $attachmentsByCollection = (new EntityAttachmentRepository())->allForMany(EntityAttachmentService::COLLECTION, $collectionIds);
        try {
            $selectedPaymentId = isset($_GET['payment_id']) && $_GET['payment_id'] !== ''
                ? Input::int($_GET['payment_id'], 'Payment ID', 1)
                : 0;
        } catch (Throwable $error) {
            unset($error);
            $selectedPaymentId = 0;
        }
        $today = function_exists('wp_date') ? wp_date('Y-m-d') : gmdate('Y-m-d');
        $status = isset($_GET['safecontracts_status']) && is_scalar($_GET['safecontracts_status']) ? sanitize_key((string) $_GET['safecontracts_status']) : '';
        $canManage = current_user_can(Capabilities::MANAGE_COLLECTIONS);
        ?>
        <div class="wrap safecontracts-settings" dir="auto">
            <div class="safecontracts-section-heading"><div><p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Cash application', 'safecontracts'); ?></p><h1><?php echo esc_html__('Collections', 'safecontracts'); ?></h1></div></div>
            <?php AdminPeriodFilter::render(self::SLUG, $filters, $selectedPaymentId > 0 ? ['payment_id' => $selectedPaymentId] : []); ?>
            <p class="description"><?php echo esc_html__('The displayed period is applied to the collection date.', 'safecontracts'); ?></p>
            <?php if ($status === 'invalid' || $status === 'attachment_failed') : ?><div class="notice notice-error inline"><p><?php echo esc_html__('Collection or attachment was not saved. Check the amount, payment method, file type and permissions.', 'safecontracts'); ?></p></div><?php endif; ?>
            <?php if ($status === 'attachments_added' || $status === 'attachment_removed') : ?><div class="notice notice-success inline"><p><?php echo esc_html__('Collection attachments were updated.', 'safecontracts'); ?></p></div><?php endif; ?>
            <div class="safecontracts-split-layout">
                <section class="safecontracts-admin-card safecontracts-table-card">
                    <h2><?php echo esc_html__('Collection ledger', 'safecontracts'); ?></h2>
                    <table class="widefat striped"><thead><tr><th><?php echo esc_html__('Date', 'safecontracts'); ?></th><th><?php echo esc_html__('Customer / Contract', 'safecontracts'); ?></th><th><?php echo esc_html__('Payment', 'safecontracts'); ?></th><th><?php echo esc_html__('Method', 'safecontracts'); ?></th><th><?php echo esc_html__('Amount', 'safecontracts'); ?></th><th><?php echo esc_html__('Files', 'safecontracts'); ?></th><th><?php echo esc_html__('Actions', 'safecontracts'); ?></th></tr></thead><tbody>
                    <?php foreach ($collections as $collection) : ?>
                        <?php $collectionId = (int) $collection['id']; $files = $attachmentsByCollection[$collectionId] ?? []; ?>
                        <tr>
                            <td><?php echo esc_html((string) $collection['collection_date']); ?></td>
                            <td><?php echo esc_html((string) $collection['customer_name'] . ' / ' . (string) $collection['contract_number']); ?></td>
                            <td><?php echo esc_html((string) ($collection['payment_reference'] ?: '#' . $collection['sequence_no'])); ?></td>
                            <td><?php echo esc_html((string) $collection['payment_method_name']); ?></td>
                            <td><?php echo esc_html(number_format((float) $collection['amount'], 2)); ?></td>
                            <td>
                                <?php if ($files === [] && ! empty($collection['proof_media_id'])) : ?>
                                    <?php CollectorAttachmentView::render($collection, true); ?>
                                <?php else : ?>
                                    <?php EntityAttachmentView::render(EntityAttachmentService::COLLECTION, $collectionId, $files, $canManage, true); ?>
                                <?php endif; ?>
                                <?php if ($canManage) : ?><?php EntityAttachmentView::renderUploadForm(EntityAttachmentService::COLLECTION, $collectionId, __('Add files', 'safecontracts')); ?><?php endif; ?>
                            </td>
                            <td>
                                <?php if ($canManage) : ?>
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
                <?php if ($canManage) : ?>
                <section class="safecontracts-admin-card">
                    <h2><?php echo esc_html__('Record collection', 'safecontracts'); ?></h2>
                    <p class="description"><?php echo esc_html__('The backend collection service enforces active payment methods, assignment scope, exact remaining balance and atomic settlement reconciliation. You can attach several supporting files to each collection.', 'safecontracts'); ?></p>
                    <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_ACTION); ?>"><?php wp_nonce_field(self::SAVE_ACTION); ?>
                        <p><label><?php echo esc_html__('Payment', 'safecontracts'); ?><select class="widefat" name="payment_id" required><option value="0"><?php echo esc_html__('Select payment', 'safecontracts'); ?></option><?php foreach ($payments as $payment) : ?><option value="<?php echo esc_attr((string) $payment['id']); ?>" <?php selected($selectedPaymentId, (int) $payment['id']); ?>><?php echo esc_html((string) $payment['customer_name'] . ' / ' . (string) $payment['contract_number'] . ' / ' . (string) ($payment['reference'] ?: '#' . $payment['sequence_no']) . ' — ' . number_format((float) $payment['remaining_amount'], 2)); ?></option><?php endforeach; ?></select></label></p>
                        <p class="safecontracts-field-row"><label><?php echo esc_html__('Amount', 'safecontracts'); ?><input type="text" inputmode="decimal" name="amount" required></label><label><?php echo esc_html__('Collection date', 'safecontracts'); ?><input type="date" name="collection_date" value="<?php echo esc_attr($today); ?>" required></label></p>
                        <p><label><?php echo esc_html__('Payment method', 'safecontracts'); ?><select class="widefat" name="payment_method_id" required><option value="0"><?php echo esc_html__('Select active method', 'safecontracts'); ?></option><?php foreach ($methods as $method) : ?><option value="<?php echo esc_attr((string) $method['id']); ?>"><?php echo esc_html((string) $method['name']); ?></option><?php endforeach; ?></select></label></p>
                        <p><label><?php echo esc_html__('Reference', 'safecontracts'); ?><input class="widefat" name="reference" maxlength="191"></label></p>
                        <p><label><?php echo esc_html__('Details', 'safecontracts'); ?><textarea class="widefat" name="details" rows="4" maxlength="5000"></textarea></label></p>
                        <?php EntityAttachmentView::renderUploadField(__('Collection / receipt files', 'safecontracts')); ?>
                        <?php submit_button(__('Record collection', 'safecontracts')); ?>
                    </form>
                </section>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
