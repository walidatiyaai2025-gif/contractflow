<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use DomainException;
use SafeContracts\Contracts\ContractMoney;
use SafeContracts\Deletion\SafeDeletionService;
use SafeContracts\Payments\FinancialDirection;
use SafeContracts\Payments\PaymentService;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Translations\TranslationCatalog;
use Throwable;

final class ContractPaymentTree
{
    public const SAVE_ACTION = 'safecontracts_contract_tree_save_payment';
    public const DELETE_ACTION = 'safecontracts_contract_tree_delete_payment';

    public static function register(): void
    {
        add_action('admin_footer', [self::class, 'render']);
        add_action('admin_notices', [self::class, 'renderNotice'], 18);
        add_action('admin_post_' . self::SAVE_ACTION, [self::class, 'handleSave']);
        add_action('admin_post_' . self::DELETE_ACTION, [self::class, 'handleDelete']);
    }

    public static function handleSave(): void
    {
        if (! current_user_can(Capabilities::MANAGE_PAYMENTS)) {
            wp_die(__('You do not have permission to manage payments.', 'safecontracts'));
        }
        check_admin_referer(self::SAVE_ACTION);

        $paymentId = max(0, (int) ($_POST['payment_id'] ?? 0));
        $contractId = max(0, (int) ($_POST['contract_id'] ?? 0));
        $status = 'payment_saved';
        $reason = '';

        try {
            if ($paymentId <= 0 || $contractId <= 0) {
                throw new DomainException('Payment and contract are required.');
            }
            $service = new PaymentService();
            $payment = $service->find($paymentId);
            if ((int) ($payment['contract_id'] ?? 0) !== $contractId) {
                throw new DomainException('Payment does not belong to the selected contract.');
            }
            $service->updateEditable($paymentId, [
                'reference' => sanitize_text_field((string) ($_POST['reference'] ?? '')),
                'due_date' => (string) ($_POST['due_date'] ?? ''),
                'expected_payment_date' => (string) ($_POST['expected_payment_date'] ?? ''),
                'original_amount' => sanitize_text_field((string) ($_POST['original_amount'] ?? '')),
            ]);
        } catch (Throwable $error) {
            $status = 'payment_invalid';
            $message = strtolower($error->getMessage());
            if (str_contains($message, 'cannot exceed the contract value') || str_contains($message, 'maximum additional')) {
                $reason = 'contract_cap';
            } elseif (str_contains($message, 'after settlement activity')) {
                $reason = 'settled_amount_locked';
            }
        }

        self::redirect($contractId, $status, $reason);
    }

    public static function handleDelete(): void
    {
        if (! current_user_can(Capabilities::MANAGE_PAYMENTS)) {
            wp_die(__('You do not have permission to delete payments.', 'safecontracts'));
        }
        $paymentId = max(0, (int) ($_POST['payment_id'] ?? 0));
        $contractId = max(0, (int) ($_POST['contract_id'] ?? 0));
        check_admin_referer(self::DELETE_ACTION . '_' . $paymentId);

        $status = 'payment_deleted';
        try {
            $payment = (new PaymentService())->find($paymentId);
            if ((int) ($payment['contract_id'] ?? 0) !== $contractId) {
                throw new DomainException('Payment does not belong to the selected contract.');
            }
            (new SafeDeletionService())->archivePayment($paymentId);
        } catch (Throwable $error) {
            unset($error);
            $status = 'payment_delete_failed';
        }

        self::redirect($contractId, $status);
    }

    public static function renderNotice(): void
    {
        if (! self::isContractsPage()) {
            return;
        }
        $status = isset($_GET['safecontracts_status']) && is_scalar($_GET['safecontracts_status'])
            ? sanitize_key((string) $_GET['safecontracts_status'])
            : '';
        $reason = isset($_GET['safecontracts_reason']) && is_scalar($_GET['safecontracts_reason'])
            ? sanitize_key((string) $_GET['safecontracts_reason'])
            : '';

        if ($status === 'payment_saved') {
            self::notice('success', self::label('Payment updated successfully from the contract tree.', 'تم تحديث الدفعة بنجاح من شجرة العقد.'));
        } elseif ($status === 'payment_deleted') {
            self::notice('success', self::label('Payment removed from active operations. Financial history remains governed.', 'تم حذف الدفعة من العمليات النشطة مع الحفاظ على السجل المالي المحكوم.'));
        } elseif ($status === 'payment_delete_failed') {
            self::notice('error', self::label('This payment cannot be deleted because governed financial activity depends on it.', 'لا يمكن حذف هذه الدفعة لوجود حركة مالية محكومة مرتبطة بها.'));
        } elseif ($status === 'payment_invalid' && $reason === 'contract_cap') {
            self::notice('error', self::label('The payment was not saved because total scheduled payments cannot exceed the contract value. Reduce this amount or another scheduled payment first.', 'لم يتم حفظ الدفعة لأن إجمالي الدفعات المجدولة لا يمكن أن يتجاوز قيمة العقد. خفّض قيمة هذه الدفعة أو دفعة مجدولة أخرى أولاً.'));
        } elseif ($status === 'payment_invalid' && $reason === 'settled_amount_locked') {
            self::notice('error', self::label('The payment amount is locked after settlement activity. You can still update its description and dates without changing the settled amount.', 'قيمة الدفعة مقفلة بعد وجود تحصيل أو سداد عليها. يمكن تعديل الوصف والتواريخ دون تغيير القيمة التي بدأت عليها التسوية.'));
        } elseif ($status === 'payment_invalid') {
            self::notice('error', self::label('The payment was not saved. Check the amount and dates and make sure the contract payment total remains within the contract value.', 'لم يتم حفظ الدفعة. راجع القيمة والتواريخ وتأكد أن إجمالي دفعات العقد لا يتجاوز قيمة العقد.'));
        }
    }

    public static function render(): void
    {
        if (! self::isContractsPage() || ! current_user_can(Capabilities::ACCESS)) {
            return;
        }

        try {
            $read = new AdminReadRepository();
            $contracts = $read->contracts(DashboardFilters::normalize($_GET));
            $contractIds = array_values(array_filter(array_map(
                static fn (array $contract): int => (int) ($contract['id'] ?? 0),
                $contracts
            ), static fn (int $id): bool => $id > 0));
            $paymentsByContract = (new ContractPaymentTreeRepository())->forVisibleContracts($contractIds);
        } catch (Throwable $error) {
            unset($error);
            return;
        }

        $openContractId = max(0, (int) ($_GET['sc_payment_tree'] ?? 0));
        foreach ($contracts as $contract) {
            $contractId = (int) ($contract['id'] ?? 0);
            if ($contractId <= 0) {
                continue;
            }
            self::renderTemplate($contract, $paymentsByContract[$contractId] ?? [], $openContractId === $contractId);
        }
        self::renderInjectorScript();
    }

    /** @param array<string,mixed> $contract @param list<array<string,mixed>> $payments */
    private static function renderTemplate(array $contract, array $payments, bool $open): void
    {
        $contractId = (int) ($contract['id'] ?? 0);
        $direction = (string) ($contract['financial_direction'] ?? '') === FinancialDirection::PAYABLE
            ? FinancialDirection::PAYABLE
            : FinancialDirection::RECEIVABLE;
        $class = $direction === FinancialDirection::PAYABLE ? 'payable' : 'receivable';
        $currency = (string) ($contract['currency_code'] ?? '');
        $totals = self::totals($payments);
        $canManage = current_user_can(Capabilities::MANAGE_PAYMENTS) && empty($contract['is_archived']);
        $canSettle = current_user_can(Capabilities::MANAGE_COLLECTIONS) && empty($contract['is_archived']);
        $addPaymentUrl = add_query_arg(['page' => PaymentsPage::SLUG, 'contract_id' => $contractId], admin_url('admin.php'));
        ?>
        <template data-safecontracts-payment-tree="<?php echo esc_attr((string) $contractId); ?>">
            <details class="safecontracts-contract-payment-tree safecontracts-contract-payment-tree--<?php echo esc_attr($class); ?>" <?php echo $open ? 'open' : ''; ?>>
                <summary>
                    <span class="safecontracts-contract-payment-tree__title"><span aria-hidden="true">└─</span> <?php echo esc_html__('Payments', 'safecontracts'); ?> <strong><?php echo esc_html((string) count($payments)); ?></strong></span>
                    <span class="safecontracts-contract-payment-tree__metrics">
                        <span><?php echo esc_html__('Scheduled total', 'safecontracts'); ?> <strong><?php echo esc_html(self::signedMoney($totals['original'], $currency, $direction)); ?></strong></span>
                        <span><?php echo esc_html__('Paid', 'safecontracts'); ?> <strong><?php echo esc_html(self::signedMoney($totals['paid'], $currency, $direction)); ?></strong></span>
                        <span><?php echo esc_html__('Remaining', 'safecontracts'); ?> <strong><?php echo esc_html(self::signedMoney($totals['remaining'], $currency, $direction)); ?></strong></span>
                    </span>
                </summary>
                <div class="safecontracts-contract-payment-tree__body">
                    <?php if ($canManage) : ?><p class="safecontracts-contract-payment-tree__toolbar"><a class="button button-small safecontracts-payment-action safecontracts-payment-action--<?php echo esc_attr($class); ?>" href="<?php echo esc_url($addPaymentUrl); ?>"><?php echo esc_html__('Add payment', 'safecontracts'); ?></a></p><?php endif; ?>
                    <?php if ($payments === []) : ?>
                        <p><?php echo esc_html(self::label('No scheduled payments for this contract.', 'لا توجد دفعات مجدولة لهذا العقد.')); ?></p>
                    <?php else : ?>
                        <div class="safecontracts-contract-payment-tree__scroll">
                            <table class="widefat striped safecontracts-contract-payment-tree__table">
                                <thead><tr>
                                    <th>#</th><th><?php echo esc_html__('Payment description', 'safecontracts'); ?></th><th><?php echo esc_html__('Due date', 'safecontracts'); ?></th><th><?php echo esc_html__('Original', 'safecontracts'); ?></th><th><?php echo esc_html__('Paid', 'safecontracts'); ?></th><th><?php echo esc_html__('Remaining', 'safecontracts'); ?></th><th><?php echo esc_html__('Status', 'safecontracts'); ?></th><th><?php echo esc_html__('Actions', 'safecontracts'); ?></th>
                                </tr></thead>
                                <tbody>
                                <?php foreach ($payments as $payment) : ?><?php self::renderPaymentRow($payment, $contractId, $direction, $currency, $canManage, $canSettle); ?><?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </details>
        </template>
        <?php
    }

    /** @param array<string,mixed> $payment */
    private static function renderPaymentRow(array $payment, int $contractId, string $direction, string $currency, bool $canManage, bool $canSettle): void
    {
        $paymentId = (int) ($payment['id'] ?? 0);
        $paid = ContractMoney::normalizeNonNegative((string) ($payment['paid_amount'] ?? '0'));
        $remaining = ContractMoney::normalizeNonNegative((string) ($payment['remaining_amount'] ?? '0'));
        $hasSettlement = ContractMoney::compare($paid, '0.0000') > 0;
        $openUrl = add_query_arg(['page' => PaymentsPage::SLUG, 'contract_id' => $contractId, 'payment_id' => $paymentId], admin_url('admin.php'));
        $collectionUrl = add_query_arg(['page' => CollectionsPage::SLUG, 'payment_id' => $paymentId], admin_url('admin.php'));
        $reference = trim((string) ($payment['reference'] ?? ''));
        $description = $reference !== '' ? $reference : '#' . (string) ($payment['sequence_no'] ?? 0);
        $status = (string) ($payment['status'] ?? '');
        $statusClass = sanitize_key($status);
        ?>
        <tr class="safecontracts-contract-payment-tree__payment safecontracts-contract-payment-tree__payment--<?php echo esc_attr($statusClass); ?>">
            <td><?php echo esc_html((string) ($payment['sequence_no'] ?? '')); ?></td>
            <td><strong><?php echo esc_html($description); ?></strong><?php if (! empty($payment['expected_payment_date'])) : ?><br><small><?php echo esc_html__('Expected payment date', 'safecontracts'); ?>: <?php echo esc_html((string) $payment['expected_payment_date']); ?></small><?php endif; ?></td>
            <td><?php echo esc_html((string) ($payment['due_date'] ?? '')); ?></td>
            <td class="safecontracts-financial-amount--<?php echo esc_attr($direction === FinancialDirection::PAYABLE ? 'payable' : 'receivable'); ?>"><?php echo esc_html(self::signedMoney((string) ($payment['original_amount'] ?? '0'), $currency, $direction)); ?></td>
            <td class="safecontracts-financial-amount--<?php echo esc_attr($direction === FinancialDirection::PAYABLE ? 'payable' : 'receivable'); ?>"><?php echo esc_html(self::signedMoney($paid, $currency, $direction)); ?></td>
            <td class="safecontracts-financial-amount--<?php echo esc_attr($direction === FinancialDirection::PAYABLE ? 'payable' : 'receivable'); ?>"><strong><?php echo esc_html(self::signedMoney($remaining, $currency, $direction)); ?></strong></td>
            <td><span class="safecontracts-contract-payment-tree__status"><?php echo esc_html(self::statusLabel($status)); ?></span></td>
            <td>
                <div class="safecontracts-dashboard-table-actions safecontracts-contract-payment-tree__actions">
                    <a class="button button-small" href="<?php echo esc_url($openUrl); ?>"><?php echo esc_html__('Open', 'safecontracts'); ?></a>
                    <?php if ($canSettle && ContractMoney::compare($remaining, '0.0000') > 0) : ?><a class="button button-small safecontracts-payment-action safecontracts-payment-action--<?php echo esc_attr($direction === FinancialDirection::PAYABLE ? 'payable' : 'receivable'); ?>" href="<?php echo esc_url($collectionUrl); ?>"><?php echo esc_html__('Record collection', 'safecontracts'); ?></a><?php endif; ?>
                    <?php if ($canManage && ContractMoney::compare($remaining, '0.0000') > 0) : ?>
                        <details class="safecontracts-contract-payment-tree__edit">
                            <summary class="button button-small"><?php echo esc_html__('Edit payment', 'safecontracts'); ?></summary>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_ACTION); ?>">
                                <input type="hidden" name="payment_id" value="<?php echo esc_attr((string) $paymentId); ?>">
                                <input type="hidden" name="contract_id" value="<?php echo esc_attr((string) $contractId); ?>">
                                <?php wp_nonce_field(self::SAVE_ACTION); ?>
                                <label><?php echo esc_html__('Payment description', 'safecontracts'); ?><input type="text" name="reference" maxlength="100" value="<?php echo esc_attr((string) ($payment['reference'] ?? '')); ?>"></label>
                                <label><?php echo esc_html__('Due date', 'safecontracts'); ?><input type="date" name="due_date" required value="<?php echo esc_attr((string) ($payment['due_date'] ?? '')); ?>"></label>
                                <label><?php echo esc_html__('Expected payment date', 'safecontracts'); ?><input type="date" name="expected_payment_date" value="<?php echo esc_attr((string) ($payment['expected_payment_date'] ?? '')); ?>"></label>
                                <label><?php echo esc_html__('Amount', 'safecontracts'); ?><input type="text" inputmode="decimal" name="original_amount" required value="<?php echo esc_attr((string) ($payment['original_amount'] ?? '')); ?>" <?php echo $hasSettlement ? 'readonly' : ''; ?>></label>
                                <?php if ($hasSettlement) : ?><small><?php echo esc_html(self::label('Amount is locked because settlement activity already exists.', 'القيمة مقفلة لأن هناك تحصيلاً أو سداداً مسجلاً على الدفعة.')); ?></small><?php endif; ?>
                                <button type="submit" class="button button-primary button-small"><?php echo esc_html__('Save', 'safecontracts'); ?></button>
                            </form>
                        </details>
                    <?php endif; ?>
                    <?php if ($canManage && ! $hasSettlement) : ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-safecontracts-delete-form data-delete-message="<?php echo esc_attr__('Delete this payment? Collection history prevents unsafe deletion.', 'safecontracts'); ?>">
                            <input type="hidden" name="action" value="<?php echo esc_attr(self::DELETE_ACTION); ?>">
                            <input type="hidden" name="payment_id" value="<?php echo esc_attr((string) $paymentId); ?>">
                            <input type="hidden" name="contract_id" value="<?php echo esc_attr((string) $contractId); ?>">
                            <?php wp_nonce_field(self::DELETE_ACTION . '_' . $paymentId); ?>
                            <button type="submit" class="button button-small safecontracts-delete-button"><?php echo esc_html__('Delete', 'safecontracts'); ?></button>
                        </form>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
        <?php
    }

    private static function renderInjectorScript(): void
    {
        ?>
        <script>
        (function () {
            function injectSafeContractsPaymentTrees() {
                var templates = document.querySelectorAll('template[data-safecontracts-payment-tree]');
                if (!templates.length) return;
                var rows = document.querySelectorAll('.safecontracts-contracts .safecontracts-table-card > table.widefat > tbody > tr');
                var rowByContract = {};
                rows.forEach(function (row) {
                    var link = row.querySelector('td:first-child a[href*="contract_id="]');
                    if (!link) return;
                    try {
                        var id = new URL(link.href, window.location.href).searchParams.get('contract_id');
                        if (id) rowByContract[id] = row;
                    } catch (error) {}
                });
                templates.forEach(function (template) {
                    var id = template.getAttribute('data-safecontracts-payment-tree');
                    var parent = rowByContract[id];
                    if (!parent || parent.nextElementSibling && parent.nextElementSibling.classList.contains('safecontracts-contract-payment-tree-row')) return;
                    var row = document.createElement('tr');
                    row.className = 'safecontracts-contract-payment-tree-row';
                    var cell = document.createElement('td');
                    cell.colSpan = Math.max(1, parent.children.length);
                    cell.appendChild(template.content.cloneNode(true));
                    row.appendChild(cell);
                    parent.insertAdjacentElement('afterend', row);
                });
            }
            if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', injectSafeContractsPaymentTrees);
            else injectSafeContractsPaymentTrees();
        }());
        </script>
        <?php
    }

    /** @param list<array<string,mixed>> $payments @return array{original:string,paid:string,remaining:string} */
    private static function totals(array $payments): array
    {
        $totals = ['original' => '0.0000', 'paid' => '0.0000', 'remaining' => '0.0000'];
        foreach ($payments as $payment) {
            $totals['original'] = ContractMoney::add($totals['original'], (string) ($payment['original_amount'] ?? '0'));
            $totals['paid'] = ContractMoney::add($totals['paid'], (string) ($payment['paid_amount'] ?? '0'));
            $totals['remaining'] = ContractMoney::add($totals['remaining'], (string) ($payment['remaining_amount'] ?? '0'));
        }
        return $totals;
    }

    private static function signedMoney(string $amount, string $currency, string $direction): string
    {
        $normalized = ContractMoney::normalizeNonNegative($amount);
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '0000');
        $whole = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $whole) ?? $whole;
        $formatted = $whole . '.' . substr(str_pad($fraction, 2, '0'), 0, 2);
        $currency = trim($currency);
        $formatted = $currency === '' ? $formatted : $currency . ' ' . $formatted;
        if (ContractMoney::compare($normalized, '0.0000') === 0) {
            return $formatted;
        }
        return ($direction === FinancialDirection::PAYABLE ? '− ' : '+ ') . $formatted;
    }

    private static function statusLabel(string $status): string
    {
        return TranslationCatalog::text(ucwords(str_replace('_', ' ', $status)));
    }

    private static function isContractsPage(): bool
    {
        return isset($_GET['page']) && is_scalar($_GET['page']) && sanitize_key((string) $_GET['page']) === ContractsPage::SLUG;
    }

    private static function redirect(int $contractId, string $status, string $reason = ''): never
    {
        $args = ['page' => ContractsPage::SLUG, 'safecontracts_status' => $status];
        if ($contractId > 0) {
            $args['sc_payment_tree'] = $contractId;
        }
        if ($reason !== '') {
            $args['safecontracts_reason'] = $reason;
        }
        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    private static function notice(string $type, string $message): void
    {
        echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }

    private static function label(string $english, string $arabic): string
    {
        return TranslationCatalog::currentLanguage() === 'ar' ? $arabic : $english;
    }
}
