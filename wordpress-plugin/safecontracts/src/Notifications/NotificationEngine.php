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
        private ?NotificationTemplateService $templates = null
    ) {
        $this->recipients ??= new RecipientResolver();
        $this->templates ??= new NotificationTemplateService();
    }

    /**
     * @param array<string,mixed> $rule
     * @param array<string,mixed> $payment
     * @param array<string,scalar|null> $context
     * @return array{rule_id:int,payment_id:int,recipient_ids:list<int>,template_code:string,scheduled_for:string,payload:array{title:string,body:string,data:array<string,scalar|null>}}|null
     */
    public function plan(
        array $rule,
        array $payment,
        DateTimeImmutable $today,
        int $attemptNo = 0,
        array $context = []
    ): ?array {
        $paymentId = (int) ($payment['id'] ?? 0);
        if ($this->isSettled($payment)) {
            do_action('safecontracts_notification_suppressed', (int) ($rule['id'] ?? 0), $paymentId, 'settled');
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
        $renderContext = array_merge([
            'customer_name' => (string) ($payment['customer_name'] ?? ''),
            'contract_number' => (string) ($payment['contract_number'] ?? ''),
            'payment_reference' => (string) ($payment['reference'] ?? ''),
            'due_date' => (string) ($payment['due_date'] ?? ''),
            'remaining_amount' => ContractMoney::normalizeNonNegative($payment['remaining_amount'] ?? ''),
            'days_overdue' => NotificationRule::daysOverdue($payment['due_date'] ?? '', $today),
        ], $context);
        $rendered = $this->templates->render($templateCode, $renderContext);

        $plan = [
            'rule_id' => (int) ($rule['id'] ?? 0),
            'payment_id' => $paymentId,
            'recipient_ids' => $recipientIds,
            'template_code' => $templateCode,
            'scheduled_for' => $today->format('Y-m-d'),
            'payload' => [
                'title' => $rendered['title'],
                'body' => $rendered['body'],
                'data' => [
                    'payment_id' => $paymentId,
                    'rule_code' => (string) ($rule['code'] ?? ''),
                    'attempt_no' => $attemptNo,
                ],
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
