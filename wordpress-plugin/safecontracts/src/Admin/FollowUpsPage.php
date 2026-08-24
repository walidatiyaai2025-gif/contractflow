<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\FollowUps\FollowUpService;
use SafeContracts\Payments\FinancialDirection;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Support\Input;
use SafeContracts\Translations\RuntimeLabels;
use Throwable;

final class FollowUpsPage
{
    public const SLUG = 'safecontracts-followups';
    public const SAVE_ACTION = 'safecontracts_save_followup_admin';

    public static function register(): void
    {
        add_submenu_page(AdminShell::SLUG, __('Follow-up', 'safecontracts'), __('Follow-up', 'safecontracts'), Capabilities::ACCESS, self::SLUG, [self::class, 'render']);
    }

    public static function handleSave(): void
    {
        if (! current_user_can(Capabilities::MANAGE_FOLLOWUPS)) {
            wp_die(__('You do not have permission to manage follow-up.', 'safecontracts'));
        }
        check_admin_referer(self::SAVE_ACTION);
        $paymentId = 0;
        $status = 'saved';
        $service = new FollowUpService();
        try {
            $paymentId = Input::int($_POST['payment_id'] ?? '', 'Payment ID', 1);
            $operation = Input::oneOf(
                $_POST['followup_operation'] ?? 'note',
                ['note', 'promise', 'issue', 'defer', 'escalate'],
                'Follow-up operation'
            );
            $note = sanitize_text_field(Input::string($_POST['note'] ?? '', 'Follow-up note'));

            if ($operation === 'promise') {
                $service->promiseToPay($paymentId, Input::string($_POST['promised_date'] ?? '', 'Promised date'), $note);
            } elseif ($operation === 'issue') {
                $service->markIssue($paymentId, $note);
            } elseif ($operation === 'defer') {
                $service->defer($paymentId, Input::string($_POST['deferred_until'] ?? '', 'Deferred until'), $note);
            } elseif ($operation === 'escalate') {
                $service->escalate($paymentId, $note);
            } else {
                $service->addNote($paymentId, $note);
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
            wp_die(__('You do not have permission to access follow-up.', 'safecontracts'));
        }

        $filters = DashboardFilters::normalize($_GET);
        $service = new FollowUpService();
        try {
            $selectedPaymentId = isset($_GET['payment_id']) && $_GET['payment_id'] !== ''
                ? Input::int($_GET['payment_id'], 'Payment ID', 1)
                : 0;
        } catch (Throwable $error) {
            unset($error);
            $selectedPaymentId = 0;
        }

        $queue = [];
        $history = [];
        $queueError = false;
        $historyError = false;
        if (empty($filters['date_range_error'])) {
            try {
                $queue = $service->queue(250, $filters['date_from'], $filters['date_to']);
            } catch (Throwable $error) {
                unset($error);
                $queueError = true;
            }
            if ($selectedPaymentId > 0) {
                try {
                    $history = $service->history($selectedPaymentId, 100, $filters['date_from'], $filters['date_to']);
                } catch (Throwable $error) {
                    unset($error);
                    $historyError = true;
                }
            }
        }

        // Reuse the scoped payment read model for display-only context. This
        // keeps counterparty, direction and currency truthful without changing
        // the append-only follow-up service or its mutation rules.
        $paymentContext = [];
        if (! $queueError && empty($filters['date_range_error'])) {
            try {
                foreach ((new AdminReadRepository())->payments($filters) as $payment) {
                    $paymentContext[(int) ($payment['id'] ?? 0)] = $payment;
                }
            } catch (Throwable $error) {
                unset($error);
                $paymentContext = [];
            }
        }

        $status = isset($_GET['safecontracts_status']) && is_scalar($_GET['safecontracts_status'])
            ? sanitize_key((string) $_GET['safecontracts_status'])
            : '';
        ?>
        <div class="wrap safecontracts-settings safecontracts-followups-page" dir="auto">
            <div class="safecontracts-section-heading">
                <div>
                    <p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Operational receivables', 'safecontracts'); ?></p>
                    <h1><?php echo esc_html__('Follow-up', 'safecontracts'); ?></h1>
                    <p class="description"><?php echo esc_html__('Work the real payment queue and keep an append-only operational history. Contractual due dates remain authoritative.', 'safecontracts'); ?></p>
                </div>
            </div>

            <?php if ($status === 'saved') : ?><div class="notice notice-success inline"><p><?php echo esc_html__('Follow-up action recorded.', 'safecontracts'); ?></p></div><?php endif; ?>
            <?php if ($status === 'invalid') : ?><div class="notice notice-error inline"><p><?php echo esc_html__('Follow-up action was not saved. Check the selected action, required date and note.', 'safecontracts'); ?></p></div><?php endif; ?>
            <?php if ($queueError) : ?><div class="notice notice-error inline"><p><?php echo esc_html__('The follow-up queue could not be loaded. No records are being presented as if the queue were empty.', 'safecontracts'); ?></p></div><?php endif; ?>
            <?php if ($historyError) : ?><div class="notice notice-error inline"><p><?php echo esc_html__('Follow-up history could not be loaded for this payment.', 'safecontracts'); ?></p></div><?php endif; ?>

            <?php AdminPeriodFilter::render(self::SLUG, $filters, $selectedPaymentId > 0 ? ['payment_id' => $selectedPaymentId] : []); ?>
            <p class="description"><?php echo esc_html__('The queue period uses contractual payment due date. When a payment is selected, append-only follow-up history uses the follow-up event creation date.', 'safecontracts'); ?></p>

            <div class="safecontracts-split-layout">
                <section class="safecontracts-admin-card safecontracts-table-card">
                    <div class="safecontracts-payment-panel__heading">
                        <div>
                            <h2><?php echo esc_html__('Assigned follow-up queue', 'safecontracts'); ?></h2>
                            <p><?php echo esc_html__('Outstanding scoped payments ordered by contractual due date.', 'safecontracts'); ?></p>
                        </div>
                        <span class="safecontracts-direction-pill"><?php echo esc_html((string) count($queue)); ?></span>
                    </div>

                    <?php if (! $queueError && $queue === []) : ?>
                        <div class="safecontracts-w2-empty"><strong><?php echo esc_html__('No follow-up items match the current period.', 'safecontracts'); ?></strong><span><?php echo esc_html__('Change the period filters to inspect a different due-date window.', 'safecontracts'); ?></span></div>
                    <?php elseif (! $queueError) : ?>
                        <table class="widefat striped">
                            <thead><tr><th><?php echo esc_html__('Due', 'safecontracts'); ?></th><th><?php echo esc_html__('Counterparty', 'safecontracts'); ?></th><th><?php echo esc_html__('Contract', 'safecontracts'); ?></th><th><?php echo esc_html__('Payment', 'safecontracts'); ?></th><th><?php echo esc_html__('Direction', 'safecontracts'); ?></th><th><?php echo esc_html__('Remaining', 'safecontracts'); ?></th><th><?php echo esc_html__('Follow-up state', 'safecontracts'); ?></th></tr></thead>
                            <tbody>
                            <?php foreach ($queue as $row) : ?>
                                <?php
                                $paymentId = (int) ($row['payment_id'] ?? 0);
                                $context = $paymentContext[$paymentId] ?? [];
                                $direction = (string) ($context['financial_direction'] ?? '') === FinancialDirection::PAYABLE ? FinancialDirection::PAYABLE : FinancialDirection::RECEIVABLE;
                                $currency = trim((string) ($context['currency_code'] ?? ''));
                                $remaining = number_format((float) ($row['remaining_amount'] ?? 0), 2);
                                $remaining = preg_replace('/\.00$/', '', $remaining) ?? $remaining;
                                $money = trim(($currency !== '' ? $currency . ' ' : '') . $remaining);
                                ?>
                                <tr>
                                    <td><?php echo esc_html((string) $row['due_date']); ?></td>
                                    <td><?php echo esc_html((string) ($context['counterparty_name'] ?? ('#' . (string) ($row['customer_id'] ?? '')))); ?></td>
                                    <td><?php echo esc_html((string) ($context['contract_number'] ?? ('#' . (string) $row['contract_id']))); ?></td>
                                    <td><a href="<?php echo esc_url(add_query_arg(['page' => self::SLUG, 'payment_id' => $paymentId, 'date_from' => $filters['date_from'], 'date_to' => $filters['date_to']], admin_url('admin.php'))); ?>"><?php echo esc_html((string) ($row['reference'] ?: '#' . $paymentId)); ?></a></td>
                                    <td><span class="safecontracts-direction-pill safecontracts-direction-pill--<?php echo esc_attr($direction === FinancialDirection::PAYABLE ? 'payable' : 'receivable'); ?>"><?php echo esc_html($direction === FinancialDirection::PAYABLE ? __('Payable', 'safecontracts') : __('Receivable', 'safecontracts')); ?></span></td>
                                    <td><strong class="safecontracts-financial-amount--<?php echo esc_attr($direction === FinancialDirection::PAYABLE ? 'payable' : 'receivable'); ?>"><?php echo esc_html($money); ?></strong></td>
                                    <td><?php echo esc_html(self::stateLabel((string) ($row['followup_state'] ?: 'pending'))); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                    <p class="description"><?php echo esc_html__('Contractual due date remains the payment due authority. Promise/deferred dates are operational follow-up state only.', 'safecontracts'); ?></p>
                </section>

                <section class="safecontracts-admin-card">
                    <h2><?php echo esc_html__('Follow-up action & history', 'safecontracts'); ?></h2>
                    <?php if ($selectedPaymentId <= 0) : ?>
                        <div class="safecontracts-w2-empty"><strong><?php echo esc_html__('No payment selected.', 'safecontracts'); ?></strong><span><?php echo esc_html__('Open a payment from the queue to review its append-only history or record a supported follow-up action.', 'safecontracts'); ?></span></div>
                    <?php else : ?>
                        <?php if (current_user_can(Capabilities::MANAGE_FOLLOWUPS)) : ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_ACTION); ?>">
                            <input type="hidden" name="payment_id" value="<?php echo esc_attr((string) $selectedPaymentId); ?>">
                            <?php wp_nonce_field(self::SAVE_ACTION); ?>
                            <p><label><?php echo esc_html__('Action', 'safecontracts'); ?><select class="widefat" name="followup_operation"><option value="note"><?php echo esc_html__('Contact note', 'safecontracts'); ?></option><option value="promise"><?php echo esc_html__('Promise to pay', 'safecontracts'); ?></option><option value="issue"><?php echo esc_html__('Issue', 'safecontracts'); ?></option><option value="defer"><?php echo esc_html__('Deferred', 'safecontracts'); ?></option><option value="escalate"><?php echo esc_html__('Needs escalation', 'safecontracts'); ?></option></select></label></p>
                            <p><label><?php echo esc_html__('Note', 'safecontracts'); ?><textarea class="widefat" name="note" rows="4" maxlength="5000"></textarea></label></p>
                            <p class="safecontracts-field-row"><label><?php echo esc_html__('Promised date', 'safecontracts'); ?><input type="date" name="promised_date"></label><label><?php echo esc_html__('Deferred until', 'safecontracts'); ?><input type="date" name="deferred_until"></label></p>
                            <p class="description"><?php echo esc_html__('Promise date is required only for “Promise to pay”; deferred-until is required only for “Deferred”. Note and escalation operations do not create unsupported phone, email or messaging actions.', 'safecontracts'); ?></p>
                            <?php submit_button(__('Save follow-up', 'safecontracts')); ?>
                        </form>
                        <?php endif; ?>

                        <h3><?php echo esc_html__('Append-only history', 'safecontracts'); ?></h3>
                        <?php if (! $historyError && $history === []) : ?>
                            <div class="safecontracts-w2-empty"><strong><?php echo esc_html__('No follow-up history in this period.', 'safecontracts'); ?></strong><span><?php echo esc_html__('New actions will append here; existing events are never edited in place.', 'safecontracts'); ?></span></div>
                        <?php elseif (! $historyError) : ?>
                            <table class="widefat striped"><thead><tr><th><?php echo esc_html__('When', 'safecontracts'); ?></th><th><?php echo esc_html__('State', 'safecontracts'); ?></th><th><?php echo esc_html__('Promise / defer', 'safecontracts'); ?></th><th><?php echo esc_html__('Note', 'safecontracts'); ?></th></tr></thead><tbody><?php foreach ($history as $event) : ?><tr><td><?php echo esc_html((string) $event['created_at']); ?></td><td><?php echo esc_html(self::stateLabel((string) $event['state'])); ?></td><td><?php echo esc_html(trim((string) ($event['promised_date'] ?? '') . ' ' . (string) ($event['deferred_until'] ?? '')) ?: '—'); ?></td><td><?php echo esc_html((string) (($event['note'] ?? '') !== '' ? $event['note'] : '—')); ?></td></tr><?php endforeach; ?></tbody></table>
                        <?php endif; ?>
                    <?php endif; ?>
                </section>
            </div>
        </div>
        <?php
    }

    private static function stateLabel(string $state): string
    {
        return RuntimeLabels::text(ucwords(str_replace('_', ' ', $state)));
    }
}
