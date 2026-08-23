<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Attachments\EntityAttachmentService;
use SafeContracts\Contracts\ContractMoney;
use SafeContracts\Deletion\SafeDeletionService;
use SafeContracts\Payments\PaymentService;
use SafeContracts\Payments\PaymentStatus;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Translations\TranslationCatalog;
use Throwable;

final class PaymentsPage
{
    public const SLUG = 'safecontracts-payments';
    public const SAVE_ACTION = 'safecontracts_save_payment_admin';
    public const DELETE_ACTION = 'safecontracts_delete_payment_admin';

    public static function register(): void
    {
        add_submenu_page(AdminShell::SLUG, __('Payments', 'safecontracts'), __('Payments', 'safecontracts'), Capabilities::ACCESS, self::SLUG, [self::class, 'render']);
    }

    public static function handleSave(): void
    {
        if (! current_user_can(Capabilities::MANAGE_PAYMENTS)) {
            wp_die(__('You do not have permission to manage payments.', 'safecontracts'));
        }
        check_admin_referer(self::SAVE_ACTION);
        $service = new PaymentService();
        $paymentId = max(0, (int) ($_POST['payment_id'] ?? 0));
        $status = 'saved';
        $uploadedMediaIds = [];
        $linkingAttachments = false;
        try {
            $uploadedMediaIds = MultipleAttachmentUploader::upload();
            if ($paymentId === 0) {
                $paymentId = $service->create([
                    'contract_id' => (int) ($_POST['contract_id'] ?? 0),
                    'sequence_no' => (int) ($_POST['sequence_no'] ?? 0),
                    'reference' => sanitize_text_field((string) ($_POST['reference'] ?? '')),
                    'due_date' => (string) ($_POST['due_date'] ?? ''),
                    'expected_payment_date' => (string) ($_POST['expected_payment_date'] ?? ''),
                    'original_amount' => sanitize_text_field((string) ($_POST['original_amount'] ?? '')),
                ]);
            } else {
                $service->updateDates($paymentId, $_POST['due_date'] ?? '', $_POST['expected_payment_date'] ?? null);
            }

            if ($uploadedMediaIds !== []) {
                $attachments = new EntityAttachmentService();
                $attachments->assertCanManage(EntityAttachmentService::PAYMENT, $paymentId);
                $linkingAttachments = true;
                $attachments->attachMany(EntityAttachmentService::PAYMENT, $paymentId, $uploadedMediaIds);
            }
        } catch (Throwable $error) {
            unset($error);
            if (! $linkingAttachments && $uploadedMediaIds !== []) {
                MultipleAttachmentUploader::cleanup($uploadedMediaIds);
            }
            $status = 'invalid';
        }
        wp_safe_redirect(add_query_arg(['page' => self::SLUG, 'payment_id' => $paymentId, 'safecontracts_status' => $status], admin_url('admin.php')));
        exit;
    }

    public static function handleDelete(): void
    {
        if (! current_user_can(Capabilities::MANAGE_PAYMENTS)) {
            wp_die(__('You do not have permission to delete payments.', 'safecontracts'));
        }
        $paymentId = max(0, (int) ($_POST['payment_id'] ?? 0));
        check_admin_referer(self::DELETE_ACTION . '_' . $paymentId);
        $status = 'deleted';
        try {
            (new SafeDeletionService())->archivePayment($paymentId);
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
            wp_die(__('You do not have permission to access payments.', 'safecontracts'));
        }
        $read = new AdminReadRepository();
        $filters = DashboardFilters::normalize($_GET);
        $payments = $read->payments($filters);
        $contracts = $read->contractOptions($filters['customer_id']);
        $selected = null;
        $selectedAttachments = [];
        $selectedId = max(0, (int) ($_GET['payment_id'] ?? 0));
        if ($selectedId > 0) {
            try {
                $selected = (new PaymentService())->find($selectedId);
                if (! empty($selected['is_archived'])) {
                    $selected = null;
                } elseif ($selected !== null) {
                    $selectedAttachments = (new EntityAttachmentService())->all(EntityAttachmentService::PAYMENT, $selectedId);
                }
            } catch (Throwable $error) {
                unset($error);
            }
        }
        $terminal = $selected !== null && (
            (string) $selected['status'] === PaymentStatus::PAID
            || ContractMoney::compare((string) $selected['remaining_amount'], '0.0000') === 0
            || ! empty($selected['contract_is_archived'])
        );
        $status = isset($_GET['safecontracts_status']) && is_scalar($_GET['safecontracts_status']) ? sanitize_key((string) $_GET['safecontracts_status']) : '';
        $canManageAttachments = $selected !== null
            && empty($selected['is_archived'])
            && empty($selected['contract_is_archived'])
            && current_user_can(Capabilities::MANAGE_PAYMENTS);
        ?>
        <div class="wrap safecontracts-settings" dir="auto">
            <div class="safecontracts-section-heading"><div><p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Receivables', 'safecontracts'); ?></p><h1><?php echo esc_html__('Payments', 'safecontracts'); ?></h1></div></div>
            <?php AdminPeriodFilter::render(self::SLUG, $filters, $selectedId > 0 ? ['payment_id' => $selectedId] : []); ?>
            <p class="description"><?php echo esc_html__('The displayed period uses the contractual payment due date.', 'safecontracts'); ?></p>
            <?php if ($status === 'attachment_failed' || $status === 'invalid') : ?><div class="notice notice-error inline"><p><?php echo esc_html__('Payment or attachment was not saved. Check the payment values, file type and permissions.', 'safecontracts'); ?></p></div><?php endif; ?>
            <?php if ($status === 'attachments_added' || $status === 'attachment_removed') : ?><div class="notice notice-success inline"><p><?php echo esc_html__('Payment attachments were updated.', 'safecontracts'); ?></p></div><?php endif; ?>
            <div class="safecontracts-split-layout">
                <section class="safecontracts-admin-card safecontracts-table-card">
                    <table class="widefat striped"><thead><tr><th><?php echo esc_html__('Due date', 'safecontracts'); ?></th><th><?php echo esc_html__('Contract', 'safecontracts'); ?></th><th><?php echo esc_html__('Reference', 'safecontracts'); ?></th><th><?php echo esc_html__('Status', 'safecontracts'); ?></th><th><?php echo esc_html__('Remaining', 'safecontracts'); ?></th><th><?php echo esc_html__('Actions', 'safecontracts'); ?></th></tr></thead><tbody>
                    <?php foreach ($payments as $payment) : ?>
                        <tr>
                            <td><?php echo esc_html((string) $payment['due_date']); ?></td>
                            <td><?php echo esc_html((string) $payment['contract_number']); ?></td>
                            <td><a href="<?php echo esc_url(add_query_arg(['page' => self::SLUG, 'payment_id' => (int) $payment['id']], admin_url('admin.php'))); ?>"><?php echo esc_html((string) ($payment['reference'] ?: '#' . $payment['sequence_no'])); ?></a></td>
                            <td><?php echo esc_html(self::statusLabel((string) $payment['status'])); ?></td>
                            <td><?php echo esc_html(number_format((float) $payment['remaining_amount'], 2)); ?></td>
                            <td>
                                <div class="safecontracts-dashboard-table-actions">
                                    <a class="button button-small" href="<?php echo esc_url(add_query_arg(['page' => self::SLUG, 'payment_id' => (int) $payment['id']], admin_url('admin.php'))); ?>"><?php echo esc_html__('Open', 'safecontracts'); ?></a>
                                    <?php if (current_user_can(Capabilities::MANAGE_PAYMENTS)) : ?>
                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-safecontracts-delete-form data-delete-message="<?php echo esc_attr__('Delete this scheduled payment? Payments with collection history are protected and must have their collections reversed first.', 'safecontracts'); ?>">
                                            <input type="hidden" name="action" value="<?php echo esc_attr(self::DELETE_ACTION); ?>">
                                            <input type="hidden" name="payment_id" value="<?php echo esc_attr((string) $payment['id']); ?>">
                                            <?php wp_nonce_field(self::DELETE_ACTION . '_' . (int) $payment['id']); ?>
                                            <button type="submit" class="button button-small safecontracts-delete-button"><?php echo esc_html__('Delete', 'safecontracts'); ?></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody></table>
                </section>
                <?php if (current_user_can(Capabilities::MANAGE_PAYMENTS)) : ?>
                <section class="safecontracts-admin-card">
                    <h2><?php echo $selected ? esc_html__('Payment details', 'safecontracts') : esc_html__('Schedule payment', 'safecontracts'); ?></h2>
                    <?php if ($selected) : ?>
                        <dl class="safecontracts-detail-list"><div><dt><?php echo esc_html__('Original', 'safecontracts'); ?></dt><dd><?php echo esc_html(number_format((float) $selected['original_amount'], 2)); ?></dd></div><div><dt><?php echo esc_html__('Paid', 'safecontracts'); ?></dt><dd><?php echo esc_html(number_format((float) $selected['paid_amount'], 2)); ?></dd></div><div><dt><?php echo esc_html__('Remaining', 'safecontracts'); ?></dt><dd><?php echo esc_html(number_format((float) $selected['remaining_amount'], 2)); ?></dd></div></dl>
                        <p class="description"><?php echo esc_html__('Contractual due date controls Due/Due Soon/Overdue classification. Expected payment date is operational follow-up only.', 'safecontracts'); ?></p>
                    <?php endif; ?>
                    <?php if (! $terminal) : ?>
                    <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_ACTION); ?>"><input type="hidden" name="payment_id" value="<?php echo esc_attr((string) ($selected['id'] ?? 0)); ?>"><?php wp_nonce_field(self::SAVE_ACTION); ?>
                        <?php if (! $selected) : ?>
                            <p><label><?php echo esc_html__('Contract', 'safecontracts'); ?><select class="widefat" name="contract_id" required><?php foreach ($contracts as $contract) : ?><option value="<?php echo esc_attr((string) $contract['id']); ?>"><?php echo esc_html($contract['contract_number']); ?></option><?php endforeach; ?></select></label></p>
                            <p><label><?php echo esc_html__('Sequence', 'safecontracts'); ?><input class="widefat" type="number" min="1" name="sequence_no" required></label></p>
                            <p><label><?php echo esc_html__('Reference', 'safecontracts'); ?><input class="widefat" name="reference" maxlength="100"></label></p>
                            <p><label><?php echo esc_html__('Original amount', 'safecontracts'); ?><input class="widefat" name="original_amount" inputmode="decimal" required></label></p>
                        <?php endif; ?>
                        <p class="safecontracts-field-row"><label><?php echo esc_html__('Due date', 'safecontracts'); ?><input type="date" name="due_date" required value="<?php echo esc_attr((string) ($selected['due_date'] ?? '')); ?>"></label><label><?php echo esc_html__('Expected payment date', 'safecontracts'); ?><input type="date" name="expected_payment_date" value="<?php echo esc_attr((string) ($selected['expected_payment_date'] ?? '')); ?>"></label></p>
                        <?php EntityAttachmentView::renderUploadField(__('Payment files', 'safecontracts')); ?>
                        <?php submit_button($selected ? __('Save payment dates', 'safecontracts') : __('Schedule payment', 'safecontracts')); ?>
                    </form>
                    <?php else : ?><p class="notice notice-info inline"><?php echo esc_html__('Settled payments are terminal and shown read-only. Payments on archived contracts are also terminal and shown read-only.', 'safecontracts'); ?></p><?php endif; ?>
                    <?php if ($selected) : ?>
                        <hr>
                        <h3><?php echo esc_html__('Payment attachments', 'safecontracts'); ?></h3>
                        <?php EntityAttachmentView::render(EntityAttachmentService::PAYMENT, $selectedId, $selectedAttachments, $canManageAttachments); ?>
                        <?php if ($canManageAttachments) : ?><?php EntityAttachmentView::renderUploadForm(EntityAttachmentService::PAYMENT, $selectedId, __('Add payment files', 'safecontracts')); ?><?php endif; ?>
                    <?php endif; ?>
                </section>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    private static function statusLabel(string $status): string
    {
        return TranslationCatalog::text(ucwords(str_replace('_', ' ', $status)));
    }
}
