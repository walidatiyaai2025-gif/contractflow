<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

use DateTimeImmutable;
use SafeContracts\Contracts\ContractMoney;
use SafeContracts\Payments\PaymentStatus;

final class NotificationEngine
{
    public function __construct(
        private ?RecipientResolver $recipients = null,
        private ?NotificationTemplateService $templates = null,
        private ?NotificationSuppressionRepository $suppressions = null
    ) {
        $this->recipients ??= new RecipientResolver();
        $this->templates ??= new NotificationTemplateService();
        $this->suppressions ??= new NotificationSuppressionRepository();
    }

    /** @return array<string,mixed>|null */
    public function plan(
        array $rule,
        array $payment,
        DateTimeImmutable $today,
        int $attemptNo = 0,
        array $context = []
    ): ?array {
        $paymentId = (int) ($payment['id'] ?? 0);
        $contractId = (int) ($payment['contract_id'] ?? 0);
        if ($this->isSettled($payment)) {
            do_action('safecontracts_notification_suppressed', (int) ($rule['id'] ?? 0), $paymentId, 'settled');
            return null;
        }
        if ($paymentId > 0 && $contractId > 0 && $this->suppressions->isSuppressed($paymentId, $contractId)) {
            do_action('safecontracts_notification_suppressed', (int) ($rule['id'] ?? 0), $paymentId, 'administrative_suppression');
            return null;
        }
        if (! NotificationRule::matchesPayment($rule, $payment, $today, $attemptNo)) {
            return null;
        }

        $recipientIds = $this->recipients->resolve(
            $rule,
            isset($payment['accountant_user_id']) ? (int) $payment['accountant_user_id'] : null
        );
        $maxRepeats = (int) ($rule['max_repeats'] ?? 0);
        $escalationRoles = is_array($rule['escalation_roles'] ?? null) ? $rule['escalation_roles'] : [];
        if ($attemptNo > 0 && $attemptNo === $maxRepeats && $escalationRoles !== []) {
            $escalated = $this->recipients->resolve([
                'recipient_roles' => $escalationRoles,
                'recipient_user_ids' => [],
                'target_assigned_accountant' => false,
            ], null);
            $recipientIds = array_values(array_unique(array_merge($recipientIds, $escalated)));
            sort($recipientIds, SORT_NUMERIC);
        }
        if ($recipientIds === []) {
            do_action('safecontracts_notification_suppressed', (int) ($rule['id'] ?? 0), $paymentId, 'no_recipients');
            return null;
        }

        $templateCode = NotificationRule::normalizeCode($rule['template_code'] ?? 'payment_due_soon');
        $counterpartyName = (string) ($payment['counterparty_name'] ?? $payment['customer_name'] ?? $payment['supplier_name'] ?? '');
        $direction = (string) ($payment['financial_direction'] ?? '');
        $renderContext = array_merge([
            // Preserve the historical customer_name token so existing templates
            // still render for supplier/payable rows while adding explicit,
            // direction-aware tokens for new templates.
            'customer_name' => (string) ($payment['customer_name'] ?? $counterpartyName),
            'supplier_name' => (string) ($payment['supplier_name'] ?? ''),
            'counterparty_name' => $counterpartyName,
            'financial_direction' => $direction,
            'contract_number' => (string) ($payment['contract_number'] ?? ''),
            'payment_reference' => (string) ($payment['reference'] ?? ''),
            'due_date' => (string) ($payment['due_date'] ?? ''),
            'remaining_amount' => ContractMoney::normalizeNonNegative($payment['remaining_amount'] ?? ''),
            'days_overdue' => NotificationRule::daysOverdue($payment['due_date'] ?? '', $today),
        ], $context);
        $rendered = $this->templates->render($templateCode, $renderContext);

        $pushData = [
            'payment_id' => $paymentId,
            'rule_code' => (string) ($rule['code'] ?? ''),
            'template_code' => $templateCode,
            'attempt_no' => $attemptNo,
            'icon_key' => $rendered['icon_key'],
        ];
        if (in_array($direction, ['receivable', 'payable'], true)) {
            $pushData['financial_direction'] = $direction;
        }

        $plan = [
            'rule_id' => (int) ($rule['id'] ?? 0),
            'payment_id' => $paymentId,
            'contract_id' => $contractId,
            'recipient_ids' => $recipientIds,
            'template_code' => $templateCode,
            'scheduled_for' => $today->format('Y-m-d'),
            'push_enabled' => ! array_key_exists('push_enabled', $rule) || ! empty($rule['push_enabled']),
            'email_enabled' => ! empty($rule['email_enabled']),
            'email_subject' => $rendered['email_subject'],
            'email_body' => $rendered['email_body'],
            'icon_key' => $rendered['icon_key'],
            'payload' => [
                'title' => $rendered['title'],
                'body' => $rendered['body'],
                'icon_key' => $rendered['icon_key'],
                'data' => $pushData,
            ],
        ];
        do_action('safecontracts_notification_planned', $plan['rule_id'], $paymentId, $recipientIds, $attemptNo);
        return $plan;
    }

    /** @param array<string,mixed> $payment */
    private function isSettled(array $payment): bool
    {
        $status = PaymentStatus::normalize((string) ($payment['status'] ?? ''));
        $remaining = ContractMoney::normalizeNonNegative($payment['remaining_amount'] ?? '');
        return $status === PaymentStatus::PAID || $remaining === '0.0000';
    }
}
