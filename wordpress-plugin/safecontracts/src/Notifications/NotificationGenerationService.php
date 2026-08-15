<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

use DateTimeImmutable;
use DateTimeZone;

final class NotificationGenerationService
{
    public function __construct(
        private ?NotificationRuleRepository $rules = null,
        private ?NotificationRepository $notifications = null,
        private ?NotificationTemplateService $templates = null,
        private ?RecipientResolver $recipients = null
    ) {
        $this->rules ??= new NotificationRuleRepository();
        $this->notifications ??= new NotificationRepository();
        $this->templates ??= new NotificationTemplateService();
        $this->recipients ??= new RecipientResolver();
    }

    public function generate(?DateTimeImmutable $today = null, int $paymentLimit = 500): int
    {
        $today ??= new DateTimeImmutable('now', $this->timezone());
        $date = new DateTimeImmutable($today->format('Y-m-d'), $today->getTimezone());
        $rules = $this->rules->all(true);
        $payments = $this->notifications->eligiblePayments($paymentLimit);
        $created = 0;

        foreach ($rules as $rule) {
            if (! ($rule['is_active'] ?? false)) {
                continue;
            }
            foreach ($payments as $payment) {
                $occurrenceIndex = NotificationRule::occurrenceIndex($rule, $payment['due_date'] ?? '', $date);
                if ($occurrenceIndex === null) {
                    continue;
                }
                $recipientIds = $this->recipients->resolve(
                    $rule,
                    isset($payment['accountant_user_id']) ? (int) $payment['accountant_user_id'] : null,
                    $occurrenceIndex
                );
                if ($recipientIds === []) {
                    continue;
                }

                $context = $this->templateContext($payment, $date);
                $rendered = $this->templates->render((string) ($rule['template_code'] ?? 'payment_due'), $context);
                foreach ($recipientIds as $userId) {
                    $dedupe = hash('sha256', implode('|', [
                        (string) ($rule['id'] ?? $rule['code'] ?? ''),
                        (string) ($payment['payment_id'] ?? ''),
                        (string) $userId,
                        $date->format('Y-m-d'),
                        (string) $occurrenceIndex,
                    ]));
                    $data = [
                        'type' => 'payment_reminder',
                        'payment_id' => (string) (int) ($payment['payment_id'] ?? 0),
                        'contract_id' => (string) (int) ($payment['contract_id'] ?? 0),
                        'customer_id' => (string) (int) ($payment['customer_id'] ?? 0),
                        'due_date' => (string) ($payment['due_date'] ?? ''),
                        'rule_code' => (string) ($rule['code'] ?? ''),
                        'occurrence_index' => (string) $occurrenceIndex,
                    ];
                    if ($this->notifications->enqueue([
                        'rule_id' => (int) ($rule['id'] ?? 0),
                        'payment_id' => (int) ($payment['payment_id'] ?? 0),
                        'user_id' => $userId,
                        'occurrence_date' => $date->format('Y-m-d'),
                        'occurrence_index' => $occurrenceIndex,
                        'dedupe_key' => $dedupe,
                        'template_code' => (string) ($rule['template_code'] ?? 'payment_due'),
                        'title' => $rendered['title'],
                        'body' => $rendered['body'],
                        'data' => $data,
                    ])) {
                        $created++;
                    }
                }
            }
        }
        return $created;
    }

    /** @param array<string, mixed> $payment @return array<string, string> */
    private function templateContext(array $payment, DateTimeImmutable $today): array
    {
        $dueText = (string) ($payment['due_date'] ?? '');
        $due = DateTimeImmutable::createFromFormat('!Y-m-d', $dueText, $today->getTimezone());
        $daysOverdue = 0;
        if ($due && $today > $due) {
            $daysOverdue = (int) $due->diff($today)->format('%a');
        }
        return [
            'client_name' => (string) ($payment['client_name'] ?? ''),
            'contract_number' => (string) ($payment['contract_number'] ?? ''),
            'payment_reference' => (string) (($payment['payment_reference'] ?? '') ?: ('#' . (int) ($payment['payment_id'] ?? 0))),
            'due_date' => $dueText,
            'original_amount' => (string) ($payment['original_amount'] ?? ''),
            'remaining_amount' => (string) ($payment['remaining_amount'] ?? ''),
            'days_overdue' => (string) $daysOverdue,
        ];
    }

    private function timezone(): DateTimeZone
    {
        if (function_exists('wp_timezone')) {
            $timezone = wp_timezone();
            if ($timezone instanceof DateTimeZone) {
                return $timezone;
            }
        }
        return new DateTimeZone('UTC');
    }
}
