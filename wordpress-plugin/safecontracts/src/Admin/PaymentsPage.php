<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Payments\PaymentService;
use SafeContracts\Payments\PaymentStatus;
use SafeContracts\Roles\Capabilities;
use Throwable;

final class PaymentsPage
{
    public const SLUG = 'safecontracts-payments';
    public const SAVE_ACTION = 'safecontracts_save_payment_admin';

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
        try {
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
            wp_die(__('You do not have permission to access payments.', 'safecontracts'));
        }
        $read = new AdminReadRepository();
        $filters = DashboardFilters::normalize($_GET);
        $payments = $read->payments($filters);
        $contracts = $read->contractOptions($filters['customer_id']);
        $selected = null;
        $selectedId = max(0, (int) ($_GET['payment_id'] ?? 0));
        if ($selectedId > 0) {
            foreach ($payments as $payment) {
                if ((int) $payment['id'] === $selectedId) {
                    $selected = $payment;
                    break;
                }
            }
            if ($selected === null) {
                $rows = $read->payments(['contract_id' => 0]);
                foreach ($rows as $payment) {
                    if ((int) $payment['id'] === $selectedId) {
                        $selected = $payment;
                        break;
                    }
                }
            }
        }
        $terminal = $selected !== null && ((string) $selected['status'] === PaymentStatus::PAID || (float) $selected['remaining_amount'] <= 0.0);
        ?>
        <div class="wrap safecontracts-settings" dir="auto">
            <div class="safecontracts-section-heading"><div><p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Receivables', 'safecontracts'); ?></p><h1><?php echo esc_html__('Payments', 'safecontracts'); ?></h1></div></div>
            <div class="safecontracts-split-layout">
                <section class="safecontracts-admin-card safecontracts-table-card">
                    <table class="widefat striped"><thead><tr><th><?php echo esc_html__('Due date', 'safecontracts'); ?></th><th><?php echo esc_html__('Contract', 'safecontracts'); ?></th><th><?php echo esc_html__('Reference', 'safecontracts'); ?></th><th><?php echo esc_html__('Status', 'safecontracts'); ?></th><th><?php echo esc_html__('Remaining', 'safecontracts'); ?></th></tr></thead><tbody>
                    <?php foreach ($payments as $payment) : ?>
                        <tr><td><?php echo esc_html((string) $payment['due_date']); ?></td><td><?php echo esc_html((string) $payment['contract_number']); ?></td><td><a href="<?php echo esc_url(add_query_arg(['page' => self::SLUG, 'payment_id' => (int) $payment['id']], admin_url('admin.php'))); ?>"><?php echo esc_html((string) ($payment['reference'] ?: '#' . $payment['sequence_no'])); ?></a></td><td><?php echo esc_html((string) $payment['status']); ?></td><td><?php echo esc_html(number_format((float) $payment['remaining_amount'], 2)); ?></td></tr>
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
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_ACTION); ?>"><input type="hidden" name="payment_id" value="<?php echo esc_attr((string) ($selected['id'] ?? 0)); ?>"><?php wp_nonce_field(self::SAVE_ACTION); ?>
                        <?php if (! $selected) : ?>
                            <p><label><?php echo esc_html__('Contract', 'safecontracts'); ?><select class="widefat" name="contract_id" required><?php foreach ($contracts as $contract) : ?><option value="<?php echo esc_attr((string) $contract['id']); ?>"><?php echo esc_html($contract['contract_number']); ?></option><?php endforeach; ?></select></label></p>
                            <p><label><?php echo esc_html__('Sequence', 'safecontracts'); ?><input class="widefat" type="number" min="1" name="sequence_no" required></label></p>
                            <p><label><?php echo esc_html__('Reference', 'safecontracts'); ?><input class="widefat" name="reference" maxlength="100"></label></p>
                            <p><label><?php echo esc_html__('Original amount', 'safecontracts'); ?><input class="widefat" name="original_amount" inputmode="decimal" required></label></p>
                        <?php endif; ?>
                        <p class="safecontracts-field-row"><label><?php echo esc_html__('Due date', 'safecontracts'); ?><input type="date" name="due_date" required value="<?php echo esc_attr((string) ($selected['due_date'] ?? '')); ?>"></label><label><?php echo esc_html__('Expected payment date', 'safecontracts'); ?><input type="date" name="expected_payment_date" value="<?php echo esc_attr((string) ($selected['expected_payment_date'] ?? '')); ?>"></label></p>
                        <?php submit_button($selected ? __('Save payment dates', 'safecontracts') : __('Schedule payment', 'safecontracts')); ?>
                    </form>
                    <?php else : ?><p class="notice notice-info inline"><?php echo esc_html__('Settled payments are terminal and shown read-only.', 'safecontracts'); ?></p><?php endif; ?>
                </section>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
