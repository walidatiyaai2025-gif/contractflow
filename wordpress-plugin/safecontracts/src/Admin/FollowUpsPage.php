<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\FollowUps\FollowUpService;
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
        $service = new FollowUpService();
        try {
            $queue = $service->queue(250);
        } catch (Throwable $error) {
            unset($error);
            $queue = [];
        }
        try {
            $selectedPaymentId = isset($_GET['payment_id']) && $_GET['payment_id'] !== ''
                ? Input::int($_GET['payment_id'], 'Payment ID', 1)
                : 0;
        } catch (Throwable $error) {
            unset($error);
            $selectedPaymentId = 0;
        }
        $history = [];
        if ($selectedPaymentId > 0) {
            try {
                $history = $service->history($selectedPaymentId, 100);
            } catch (Throwable $error) {
                unset($error);
                $history = [];
            }
        }
        ?>
        <div class="wrap safecontracts-settings" dir="auto">
            <div class="safecontracts-section-heading"><div><p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Operational receivables', 'safecontracts'); ?></p><h1><?php echo esc_html__('Follow-up', 'safecontracts'); ?></h1></div></div>
            <div class="safecontracts-split-layout">
                <section class="safecontracts-admin-card safecontracts-table-card">
                    <h2><?php echo esc_html__('Assigned follow-up queue', 'safecontracts'); ?></h2>
                    <table class="widefat striped"><thead><tr><th><?php echo esc_html__('Due', 'safecontracts'); ?></th><th><?php echo esc_html__('Contract', 'safecontracts'); ?></th><th><?php echo esc_html__('Payment', 'safecontracts'); ?></th><th><?php echo esc_html__('Remaining', 'safecontracts'); ?></th><th><?php echo esc_html__('Follow-up state', 'safecontracts'); ?></th></tr></thead><tbody>
                    <?php foreach ($queue as $row) : ?>
                        <tr><td><?php echo esc_html((string) $row['due_date']); ?></td><td>#<?php echo esc_html((string) $row['contract_id']); ?></td><td><a href="<?php echo esc_url(add_query_arg(['page' => self::SLUG, 'payment_id' => (int) $row['payment_id']], admin_url('admin.php'))); ?>"><?php echo esc_html((string) ($row['reference'] ?: '#' . $row['payment_id'])); ?></a></td><td><?php echo esc_html(number_format((float) $row['remaining_amount'], 2)); ?></td><td><?php echo esc_html(self::stateLabel((string) ($row['followup_state'] ?: 'pending'))); ?></td></tr>
                    <?php endforeach; ?>
                    </tbody></table>
                    <p class="description"><?php echo esc_html__('Contractual due date remains the receivable due authority. Promise/deferred dates are operational follow-up state only.', 'safecontracts'); ?></p>
                </section>
                <section class="safecontracts-admin-card">
                    <h2><?php echo esc_html__('Follow-up action & history', 'safecontracts'); ?></h2>
                    <?php if ($selectedPaymentId <= 0) : ?>
                        <p><?php echo esc_html__('Select a payment from the queue to review history or add an operational follow-up action.', 'safecontracts'); ?></p>
                    <?php else : ?>
                        <?php if (current_user_can(Capabilities::MANAGE_FOLLOWUPS)) : ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_ACTION); ?>"><input type="hidden" name="payment_id" value="<?php echo esc_attr((string) $selectedPaymentId); ?>"><?php wp_nonce_field(self::SAVE_ACTION); ?>
                            <p><label><?php echo esc_html__('Action', 'safecontracts'); ?><select class="widefat" name="followup_operation"><option value="note"><?php echo esc_html__('Contact note', 'safecontracts'); ?></option><option value="promise"><?php echo esc_html__('Promise to pay', 'safecontracts'); ?></option><option value="issue"><?php echo esc_html__('Issue', 'safecontracts'); ?></option><option value="defer"><?php echo esc_html__('Deferred', 'safecontracts'); ?></option><option value="escalate"><?php echo esc_html__('Needs escalation', 'safecontracts'); ?></option></select></label></p>
                            <p><label><?php echo esc_html__('Note', 'safecontracts'); ?><textarea class="widefat" name="note" rows="4" maxlength="5000"></textarea></label></p>
                            <p class="safecontracts-field-row"><label><?php echo esc_html__('Promised date', 'safecontracts'); ?><input type="date" name="promised_date"></label><label><?php echo esc_html__('Deferred until', 'safecontracts'); ?><input type="date" name="deferred_until"></label></p>
                            <?php submit_button(__('Save follow-up', 'safecontracts')); ?>
                        </form>
                        <?php endif; ?>
                        <h3><?php echo esc_html__('Append-only history', 'safecontracts'); ?></h3>
                        <table class="widefat striped"><thead><tr><th><?php echo esc_html__('When', 'safecontracts'); ?></th><th><?php echo esc_html__('State', 'safecontracts'); ?></th><th><?php echo esc_html__('Promise / defer', 'safecontracts'); ?></th><th><?php echo esc_html__('Note', 'safecontracts'); ?></th></tr></thead><tbody><?php foreach ($history as $event) : ?><tr><td><?php echo esc_html((string) $event['created_at']); ?></td><td><?php echo esc_html(self::stateLabel((string) $event['state'])); ?></td><td><?php echo esc_html(trim((string) ($event['promised_date'] ?? '') . ' ' . (string) ($event['deferred_until'] ?? ''))); ?></td><td><?php echo esc_html((string) ($event['note'] ?? '')); ?></td></tr><?php endforeach; ?></tbody></table>
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
