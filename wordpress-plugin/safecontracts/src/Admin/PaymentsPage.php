<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Attachments\EntityAttachmentService;
use SafeContracts\Contracts\ContractMoney;
use SafeContracts\Contracts\ContractService;
use SafeContracts\Deletion\SafeDeletionService;
use SafeContracts\Payments\FinancialDirection;
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
        $contractId = max(0, (int) ($_POST['contract_id'] ?? 0));
        $status = 'saved';
        $uploadedMediaIds = [];
        $linkingAttachments = false;
        try {
            $uploadedMediaIds = MultipleAttachmentUploader::upload();
            if ($paymentId === 0) {
                $paymentId = $service->create([
                    'contract_id' => $contractId,
                    'sequence_no' => (int) ($_POST['sequence_no'] ?? 0),
                    'reference' => sanitize_text_field((string) ($_POST['reference'] ?? '')),
                    'due_date' => (string) ($_POST['due_date'] ?? ''),
                    'expected_payment_date' => (string) ($_POST['expected_payment_date'] ?? ''),
                    'original_amount' => sanitize_text_field((string) ($_POST['original_amount'] ?? '')),
                ]);
            } else {
                $service->updateEditable($paymentId, [
                    'reference' => sanitize_text_field((string) ($_POST['reference'] ?? '')),
                    'due_date' => (string) ($_POST['due_date'] ?? ''),
                    'expected_payment_date' => (string) ($_POST['expected_payment_date'] ?? ''),
                    'original_amount' => sanitize_text_field((string) ($_POST['original_amount'] ?? '')),
                ]);
                $payment = $service->find($paymentId);
                $contractId = (int) ($payment['contract_id'] ?? $contractId);
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
        $args = ['page' => self::SLUG, 'payment_id' => $paymentId, 'safecontracts_status' => $status];
        if ($contractId > 0) {
            $args['contract_id'] = $contractId;
        }
        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
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
        $contracts = $read->contractOptions(0);
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

        $selectedContractId = (int) ($filters['contract_id'] ?? 0);
        if ($selectedContractId <= 0 && $selected !== null) {
            $selectedContractId = (int) ($selected['contract_id'] ?? 0);
        }
        $selectedContract = null;
        $contractTotals = ['scheduled' => '0.0000', 'settled' => '0.0000', 'outstanding' => '0.0000'];
        $contractNetValue = null;
        if ($selectedContractId > 0) {
            try {
                $rows = $read->contracts(['contract_id' => $selectedContractId]);
                $selectedContract = $rows[0] ?? null;
                if ($selectedContract !== null) {
                    $contractTotals = self::contractTotals($read->payments(['contract_id' => $selectedContractId]));
                    $contractNetValue = (new ContractService())->reconcile($selectedContractId)['net_value'] ?? null;
                }
            } catch (Throwable $error) {
                unset($error);
                $selectedContract = null;
            }
        }

        $terminal = $selected !== null && (
            (string) $selected['status'] === PaymentStatus::PAID
            || ContractMoney::compare((string) $selected['remaining_amount'], '0.0000') === 0
            || ! empty($selected['contract_is_archived'])
        );
        $editRequested = $selected !== null && sanitize_key((string) ($_GET['payment_action'] ?? '')) === 'edit';
        $editMode = $editRequested && ! $terminal;
        $status = isset($_GET['safecontracts_status']) && is_scalar($_GET['safecontracts_status']) ? sanitize_key((string) $_GET['safecontracts_status']) : '';
        $canManageAttachments = $selected !== null
            && empty($selected['is_archived'])
            && empty($selected['contract_is_archived'])
            && current_user_can(Capabilities::MANAGE_PAYMENTS);
        ?>
        <div class="wrap safecontracts-settings" dir="auto">
            <div class="safecontracts-section-heading"><div><p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Financial obligations', 'safecontracts'); ?></p><h1><?php echo esc_html__('Payments', 'safecontracts'); ?></h1></div></div>
            <?php if (! empty($filters['date_range_error'])) : ?><div class="notice notice-error inline"><p><?php echo esc_html__('Invalid period. Use valid YYYY-MM-DD dates and make sure the end date is not earlier than the start date.', 'safecontracts'); ?></p></div><?php endif; ?>
            <form class="safecontracts-filter-bar safecontracts-period-filter" method="get">
                <input type="hidden" name="page" value="<?php echo esc_attr(self::SLUG); ?>">
                <label><?php echo esc_html__('Contract filter', 'safecontracts'); ?><select name="contract_id">
                    <option value="0"><?php echo esc_html__('All contracts', 'safecontracts'); ?></option>
                    <?php foreach ($contracts as $contract) : ?>
                        <?php $direction = (string) ($contract['counterparty_type'] ?? '') === 'supplier' ? FinancialDirection::PAYABLE : FinancialDirection::RECEIVABLE; ?>
                        <option value="<?php echo esc_attr((string) $contract['id']); ?>" <?php selected($selectedContractId, (int) $contract['id']); ?>><?php echo esc_html((string) $contract['contract_number'] . ' · ' . (string) ($contract['counterparty_name'] ?? '') . ' · ' . self::directionActionLabel($direction)); ?></option>
                    <?php endforeach; ?>
                </select></label>
                <?php AdminPeriodFilter::renderFields($filters); ?>
                <button class="button button-primary" type="submit"><?php echo esc_html__('Apply filters', 'safecontracts'); ?></button>
                <a class="button" href="<?php echo esc_url(add_query_arg(['page' => self::SLUG], admin_url('admin.php'))); ?>"><?php echo esc_html__('Clear filters', 'safecontracts'); ?></a>
            </form>
            <p class="description"><?php echo esc_html__('The displayed period uses the contractual payment due date.', 'safecontracts'); ?></p>
            <?php if ($status === 'attachment_failed' || $status === 'invalid') : ?><div class="notice notice-error inline"><p><?php echo esc_html__('Payment or attachment was not saved. Check the payment values, file type and permissions.', 'safecontracts'); ?></p></div><?php endif; ?>
            <?php if ($status === 'attachments_added' || $status === 'attachment_removed') : ?><div class="notice notice-success inline"><p><?php echo esc_html__('Payment attachments were updated.', 'safecontracts'); ?></p></div><?php endif; ?>

            <?php if ($selectedContract !== null) : ?>
                <section class="safecontracts-admin-card">
                    <h2><?php echo esc_html__('Contract summary', 'safecontracts'); ?></h2>
                    <dl class="safecontracts-detail-list">
                        <div><dt><?php echo esc_html__('Contract', 'safecontracts'); ?></dt><dd><?php echo esc_html((string) $selectedContract['contract_number']); ?></dd></div>
                        <div><dt><?php echo esc_html__('Counterparty', 'safecontracts'); ?></dt><dd><?php echo esc_html((string) ($selectedContract['counterparty_name'] ?? '')); ?></dd></div>
                        <div><dt><?php echo esc_html__('Obligation type', 'safecontracts'); ?></dt><dd><?php echo esc_html(self::directionActionLabel((string) ($selectedContract['financial_direction'] ?? ''))); ?></dd></div>
                        <div><dt><?php echo esc_html__('Status', 'safecontracts'); ?></dt><dd><?php echo esc_html(self::statusLabel((string) $selectedContract['status'])); ?></dd></div>
                        <div><dt><?php echo esc_html__('Base value', 'safecontracts'); ?></dt><dd><?php echo esc_html(self::money((string) ($selectedContract['base_value'] ?? '0'), (string) ($selectedContract['currency_code'] ?? ''))); ?></dd></div>
                        <div><dt><?php echo esc_html__('Net value', 'safecontracts'); ?></dt><dd><?php echo esc_html(self::money((string) ($contractNetValue ?? $selectedContract['base_value'] ?? '0'), (string) ($selectedContract['currency_code'] ?? ''))); ?></dd></div>
                        <div><dt><?php echo esc_html__('Scheduled total', 'safecontracts'); ?></dt><dd><?php echo esc_html(self::money($contractTotals['scheduled'], (string) ($selectedContract['currency_code'] ?? ''))); ?></dd></div>
                        <div><dt><?php echo esc_html__('Settled total', 'safecontracts'); ?></dt><dd><?php echo esc_html(self::money($contractTotals['settled'], (string) ($selectedContract['currency_code'] ?? ''))); ?></dd></div>
                        <div><dt><?php echo esc_html__('Outstanding total', 'safecontracts'); ?></dt><dd><?php echo esc_html(self::money($contractTotals['outstanding'], (string) ($selectedContract['currency_code'] ?? ''))); ?></dd></div>
                    </dl>
                </section>
            <?php endif; ?>

            <div class="safecontracts-split-layout">
                <section class="safecontracts-admin-card safecontracts-table-card">
                    <table class="widefat striped"><thead><tr><th><?php echo esc_html__('Due date', 'safecontracts'); ?></th><th><?php echo esc_html__('Contract', 'safecontracts'); ?></th><th><?php echo esc_html__('Reference', 'safecontracts'); ?></th><th><?php echo esc_html__('Obligation type', 'safecontracts'); ?></th><th><?php echo esc_html__('Status', 'safecontracts'); ?></th><th><?php echo esc_html__('Remaining', 'safecontracts'); ?></th><th><?php echo esc_html__('Actions', 'safecontracts'); ?></th></tr></thead><tbody>
                    <?php foreach ($payments as $payment) : ?>
                        <?php $rowTerminal = (string) $payment['status'] === PaymentStatus::PAID || ContractMoney::compare((string) $payment['remaining_amount'], '0.0000') === 0 || ! empty($payment['contract_is_archived']); ?>
                        <tr>
                            <td><?php echo esc_html((string) $payment['due_date']); ?></td>
                            <td><?php echo esc_html((string) $payment['contract_number']); ?><br><small><?php echo esc_html((string) ($payment['counterparty_name'] ?? '')); ?></small></td>
                            <td><a href="<?php echo esc_url(self::paymentUrl((int) $payment['id'], $filters, false)); ?>"><?php echo esc_html((string) ($payment['reference'] ?: '#' . $payment['sequence_no'])); ?></a></td>
                            <td><?php echo esc_html(self::directionActionLabel((string) ($payment['financial_direction'] ?? ''))); ?></td>
                            <td><?php echo esc_html(self::statusLabel((string) $payment['status'])); ?></td>
                            <td><?php echo esc_html(self::money((string) $payment['remaining_amount'], (string) ($payment['currency_code'] ?? ''))); ?></td>
                            <td>
                                <div class="safecontracts-dashboard-table-actions">
                                    <a class="button button-small" href="<?php echo esc_url(self::paymentUrl((int) $payment['id'], $filters, false)); ?>"><?php echo esc_html__('Open', 'safecontracts'); ?></a>
                                    <?php if (current_user_can(Capabilities::MANAGE_PAYMENTS) && ! $rowTerminal) : ?><a class="button button-small" href="<?php echo esc_url(self::paymentUrl((int) $payment['id'], $filters, true)); ?>"><?php echo esc_html__('Edit payment', 'safecontracts'); ?></a><?php endif; ?>
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
                    <h2><?php echo $selected ? ($editMode ? esc_html__('Edit payment', 'safecontracts') : esc_html__('Payment details', 'safecontracts')) : esc_html__('Schedule payment', 'safecontracts'); ?></h2>
                    <?php if ($selected) : ?>
                        <dl class="safecontracts-detail-list">
                            <div><dt><?php echo esc_html__('Obligation type', 'safecontracts'); ?></dt><dd><?php echo esc_html(self::directionActionLabel((string) ($selected['financial_direction'] ?? ''))); ?></dd></div>
                            <div><dt><?php echo esc_html__('Original', 'safecontracts'); ?></dt><dd><?php echo esc_html(self::money((string) $selected['original_amount'], (string) ($selected['currency_code'] ?? ''))); ?></dd></div>
                            <div><dt><?php echo esc_html__('Paid', 'safecontracts'); ?></dt><dd><?php echo esc_html(self::money((string) $selected['paid_amount'], (string) ($selected['currency_code'] ?? ''))); ?></dd></div>
                            <div><dt><?php echo esc_html__('Remaining', 'safecontracts'); ?></dt><dd><?php echo esc_html(self::money((string) $selected['remaining_amount'], (string) ($selected['currency_code'] ?? ''))); ?></dd></div>
                        </dl>
                        <p class="description"><?php echo esc_html__('Contractual due date controls Due/Due Soon/Overdue classification. Expected payment date is operational follow-up only.', 'safecontracts'); ?></p>
                    <?php endif; ?>

                    <?php if (! $selected || $editMode) : ?>
                    <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_ACTION); ?>"><input type="hidden" name="payment_id" value="<?php echo esc_attr((string) ($selected['id'] ?? 0)); ?>"><?php wp_nonce_field(self::SAVE_ACTION); ?>
                        <?php if (! $selected) : ?>
                            <?php if ($selectedContract !== null) : ?>
                                <input type="hidden" name="contract_id" value="<?php echo esc_attr((string) $selectedContractId); ?>">
                                <p><strong><?php echo esc_html__('Contract:', 'safecontracts'); ?></strong> <?php echo esc_html((string) $selectedContract['contract_number']); ?> · <?php echo esc_html((string) ($selectedContract['counterparty_name'] ?? '')); ?></p>
                                <p><strong><?php echo esc_html__('Obligation type:', 'safecontracts'); ?></strong> <?php echo esc_html(self::directionActionLabel((string) ($selectedContract['financial_direction'] ?? ''))); ?></p>
                            <?php else : ?>
                                <p><label><?php echo esc_html__('Contract', 'safecontracts'); ?><select class="widefat" name="contract_id" required><option value=""><?php echo esc_html__('Select a contract', 'safecontracts'); ?></option><?php foreach ($contracts as $contract) : $direction = (string) ($contract['counterparty_type'] ?? '') === 'supplier' ? FinancialDirection::PAYABLE : FinancialDirection::RECEIVABLE; ?><option value="<?php echo esc_attr((string) $contract['id']); ?>"><?php echo esc_html((string) $contract['contract_number'] . ' · ' . (string) ($contract['counterparty_name'] ?? '') . ' · ' . self::directionActionLabel($direction)); ?></option><?php endforeach; ?></select></label></p>
                            <?php endif; ?>
                            <p><label><?php echo esc_html__('Sequence', 'safecontracts'); ?><input class="widefat" type="number" min="1" name="sequence_no" required></label></p>
                            <p><label><?php echo esc_html__('Payment reference', 'safecontracts'); ?><input class="widefat" name="reference" maxlength="100"></label></p>
                            <p><label><?php echo esc_html__('Obligation amount', 'safecontracts'); ?><input class="widefat" type="number" min="0.01" step="0.01" name="original_amount" inputmode="decimal" required></label></p>
                        <?php else : ?>
                            <input type="hidden" name="contract_id" value="<?php echo esc_attr((string) $selected['contract_id']); ?>">
                            <p><label><?php echo esc_html__('Payment reference', 'safecontracts'); ?><input class="widefat" name="reference" maxlength="100" value="<?php echo esc_attr((string) ($selected['reference'] ?? '')); ?>"></label></p>
                            <?php if (ContractMoney::compare((string) $selected['paid_amount'], '0.0000') === 0) : ?>
                                <p><label><?php echo esc_html(self::amountLabel((string) ($selected['financial_direction'] ?? ''))); ?><input class="widefat" type="number" min="0.01" step="0.01" name="original_amount" inputmode="decimal" required value="<?php echo esc_attr((string) $selected['original_amount']); ?>"></label></p>
                            <?php else : ?>
                                <input type="hidden" name="original_amount" value="<?php echo esc_attr((string) $selected['original_amount']); ?>">
                                <p><strong><?php echo esc_html(self::amountLabel((string) ($selected['financial_direction'] ?? '')) . ':'); ?></strong> <?php echo esc_html(self::money((string) $selected['original_amount'], (string) ($selected['currency_code'] ?? ''))); ?></p>
                                <p class="description"><?php echo esc_html__('Payment amount is locked after settlement activity. Dates and reference may still be changed.', 'safecontracts'); ?></p>
                            <?php endif; ?>
                        <?php endif; ?>
                        <p class="safecontracts-field-row"><label><?php echo esc_html__('Due date', 'safecontracts'); ?><input type="date" name="due_date" required value="<?php echo esc_attr((string) ($selected['due_date'] ?? '')); ?>"></label><label><?php echo esc_html__('Expected payment date', 'safecontracts'); ?><input type="date" name="expected_payment_date" value="<?php echo esc_attr((string) ($selected['expected_payment_date'] ?? '')); ?>"></label></p>
                        <?php EntityAttachmentView::renderUploadField(__('Payment files', 'safecontracts')); ?>
                        <?php submit_button($selected ? __('Save payment', 'safecontracts') : __('Schedule payment', 'safecontracts')); ?>
                    </form>
                    <?php elseif ($terminal) : ?><p class="notice notice-info inline"><?php echo esc_html__('Settled payments are terminal and shown read-only. Payments on archived contracts are also terminal and shown read-only.', 'safecontracts'); ?></p><?php endif; ?>

                    <?php if ($selected && ! $editMode && ! $terminal) : ?><p><a class="button button-primary" href="<?php echo esc_url(self::paymentUrl($selectedId, $filters, true)); ?>"><?php echo esc_html__('Edit payment', 'safecontracts'); ?></a></p><?php endif; ?>
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

    /** @param list<array<string,mixed>> $payments @return array{scheduled:string,settled:string,outstanding:string} */
    private static function contractTotals(array $payments): array
    {
        $scheduled = '0.0000';
        $settled = '0.0000';
        $outstanding = '0.0000';
        foreach ($payments as $payment) {
            $scheduled = ContractMoney::add($scheduled, ContractMoney::normalizeNonNegative((string) ($payment['original_amount'] ?? '0')));
            $settled = ContractMoney::add($settled, ContractMoney::normalizeNonNegative((string) ($payment['paid_amount'] ?? '0')));
            $outstanding = ContractMoney::add($outstanding, ContractMoney::normalizeNonNegative((string) ($payment['remaining_amount'] ?? '0')));
        }
        return ['scheduled' => $scheduled, 'settled' => $settled, 'outstanding' => $outstanding];
    }

    /** @param array<string,mixed> $filters */
    private static function paymentUrl(int $paymentId, array $filters, bool $edit): string
    {
        $args = ['page' => self::SLUG, 'payment_id' => $paymentId];
        if (($filters['contract_id'] ?? 0) > 0) {
            $args['contract_id'] = (int) $filters['contract_id'];
        }
        if (($filters['date_from'] ?? null) !== null) {
            $args['date_from'] = (string) $filters['date_from'];
        }
        if (($filters['date_to'] ?? null) !== null) {
            $args['date_to'] = (string) $filters['date_to'];
        }
        if ($edit) {
            $args['payment_action'] = 'edit';
        }
        return add_query_arg($args, admin_url('admin.php'));
    }

    private static function directionActionLabel(string $direction): string
    {
        return $direction === FinancialDirection::PAYABLE
            ? __('Accounts Payable · we will pay it', 'safecontracts')
            : __('Accounts Receivable · will be paid to us', 'safecontracts');
    }

    private static function amountLabel(string $direction): string
    {
        return $direction === FinancialDirection::PAYABLE
            ? __('Payable amount', 'safecontracts')
            : __('Receivable amount', 'safecontracts');
    }

    private static function money(string $amount, string $currency): string
    {
        $formatted = number_format((float) $amount, 2, '.', ',');
        $currency = trim($currency);
        return $currency === '' ? $formatted : $currency . ' ' . $formatted;
    }

    private static function statusLabel(string $status): string
    {
        return TranslationCatalog::text(ucwords(str_replace('_', ' ', $status)));
    }
}