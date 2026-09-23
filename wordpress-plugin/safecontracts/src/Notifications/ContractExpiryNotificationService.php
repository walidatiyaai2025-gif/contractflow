<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;

final class ContractExpiryNotificationService
{
    private const OCCURRENCE_OPTION_PREFIX = 'safecontracts_contract_expiry_occurrence_';

    public function __construct(
        private ?NotificationRuleRepository $rules = null,
        private ?NotificationTemplateService $templates = null,
        private ?RecipientResolver $recipients = null,
        private ?DirectNotificationService $direct = null
    ) {
        $this->rules ??= new NotificationRuleRepository();
        $this->templates ??= new NotificationTemplateService();
        $this->recipients ??= new RecipientResolver();
        $this->direct ??= new DirectNotificationService();
    }

    public function run(?DateTimeImmutable $today = null): int
    {
        $timezone = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
        $today ??= new DateTimeImmutable('today', $timezone);
        $rules = $this->rules->activeForTrigger(NotificationRule::TRIGGER_CONTRACT_EXPIRY);
        if ($rules === []) {
            return 0;
        }

        $contracts = $this->candidateContracts();
        $dispatched = 0;

        foreach ($rules as $rule) {
            foreach ($contracts as $contract) {
                if (! NotificationRule::matchesScope($rule, $contract)) {
                    continue;
                }

                $endDate = self::date((string) ($contract['end_date'] ?? ''), $timezone);
                if ($endDate === null || $today > $endDate) {
                    continue;
                }

                $maxRepeats = max(0, (int) ($rule['max_repeats'] ?? 0));
                for ($attemptNo = 0; $attemptNo <= $maxRepeats; $attemptNo++) {
                    try {
                        $target = NotificationRule::targetDate($rule, $endDate->format('Y-m-d'), $attemptNo);
                    } catch (Throwable) {
                        continue;
                    }

                    if ($target > $today) {
                        continue;
                    }

                    // If several configured reminder dates were missed while cron
                    // was unavailable, only send the most recent due reminder.
                    if ($attemptNo < $maxRepeats) {
                        try {
                            $nextTarget = NotificationRule::targetDate($rule, $endDate->format('Y-m-d'), $attemptNo + 1);
                            if ($nextTarget <= $today) {
                                continue;
                            }
                        } catch (Throwable) {
                            // Keep the current valid occurrence if a later cadence
                            // cannot be materialized.
                        }
                    }

                    $recipientIds = $this->recipients->resolve(
                        $rule,
                        isset($contract['accountant_user_id']) ? (int) $contract['accountant_user_id'] : null
                    );
                    if ($attemptNo > 0 && $attemptNo === $maxRepeats) {
                        $escalationRoles = is_array($rule['escalation_roles'] ?? null) ? $rule['escalation_roles'] : [];
                        if ($escalationRoles !== []) {
                            $recipientIds = array_values(array_unique(array_merge(
                                $recipientIds,
                                $this->recipients->resolve([
                                    'recipient_roles' => $escalationRoles,
                                    'recipient_user_ids' => [],
                                    'target_assigned_accountant' => false,
                                ], null)
                            )));
                            sort($recipientIds, SORT_NUMERIC);
                        }
                    }
                    if ($recipientIds === []) {
                        continue;
                    }

                    $daysUntilExpiry = max(0, (int) $today->diff($endDate)->format('%a'));
                    $templateCode = NotificationRule::normalizeCode($rule['template_code'] ?? 'contract_expiry_soon');
                    $rendered = $this->templates->render($templateCode, [
                        'customer_name' => (string) ($contract['customer_name'] ?? $contract['counterparty_name'] ?? ''),
                        'supplier_name' => (string) ($contract['supplier_name'] ?? ''),
                        'counterparty_name' => (string) ($contract['counterparty_name'] ?? ''),
                        'financial_direction' => (string) ($contract['financial_direction'] ?? ''),
                        'currency_code' => (string) ($contract['currency_code'] ?? ''),
                        'contract_number' => (string) ($contract['contract_number'] ?? ''),
                        'payment_reference' => '',
                        'due_date' => $endDate->format('Y-m-d'),
                        'remaining_amount' => '0.0000',
                        'days_overdue' => 0,
                        'contract_end_date' => $endDate->format('Y-m-d'),
                        'days_until_expiry' => $daysUntilExpiry,
                    ]);

                    foreach ($recipientIds as $userId) {
                        $userId = (int) $userId;
                        if ($userId <= 0) {
                            continue;
                        }

                        $occurrenceKey = $this->occurrenceKey(
                            (int) ($rule['id'] ?? 0),
                            (int) ($contract['id'] ?? 0),
                            $attemptNo,
                            $target->format('Y-m-d'),
                            $userId
                        );
                        if (! add_option($occurrenceKey, $target->format('Y-m-d'), '', false)) {
                            continue;
                        }

                        try {
                            $delivery = $this->direct->send(
                                $userId,
                                $rendered['title'],
                                $rendered['body'],
                                ! array_key_exists('push_enabled', $rule) || ! empty($rule['push_enabled']),
                                ! empty($rule['email_enabled']),
                                $rendered['icon_key'],
                                [
                                    'event_code' => (string) ($rule['code'] ?? 'contract_expiry'),
                                    'template_code' => $templateCode,
                                    'contract_id' => (int) ($contract['id'] ?? 0),
                                    'payment_id' => 0,
                                    'resource_type' => 'contract',
                                    'resource_id' => (int) ($contract['id'] ?? 0),
                                ]
                            );
                            $attempted = (int) ($delivery['push_sent'] ?? 0)
                                + (int) ($delivery['push_failed'] ?? 0)
                                + (int) ($delivery['email_sent'] ?? 0)
                                + (int) ($delivery['email_failed'] ?? 0);
                            if ($attempted === 0) {
                                delete_option($occurrenceKey);
                                continue;
                            }
                            $dispatched++;
                        } catch (Throwable $error) {
                            delete_option($occurrenceKey);
                            do_action(
                                'safecontracts_contract_expiry_notification_failed',
                                (int) ($rule['id'] ?? 0),
                                (int) ($contract['id'] ?? 0),
                                $userId,
                                sanitize_key($error->getMessage())
                            );
                        }
                    }

                    // Only one occurrence should be eligible for a contract/rule
                    // on a scheduler tick.
                    break;
                }
            }
        }

        do_action('safecontracts_contract_expiry_notifications_processed', $dispatched);
        return $dispatched;
    }

    /**
     * Build the contract-expiry occurrences that are still pending so the
     * Notification Schedule page can show the same business cadence that the
     * cron dispatcher evaluates.
     *
     * @return list<array<string,mixed>>
     */
    public function scheduledRows(?string $dateFrom = null, ?string $dateTo = null, string $status = '', int $limit = 300): array
    {
        if ($status !== '' && $status !== 'pending') {
            return [];
        }

        $timezone = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
        $today = new DateTimeImmutable('today', $timezone);
        $settings = new NotificationScheduleSettings();
        $rules = $this->rules->activeForTrigger(NotificationRule::TRIGGER_CONTRACT_EXPIRY);
        $contracts = $this->candidateContracts();
        $rows = [];

        foreach ($rules as $rule) {
            foreach ($contracts as $contract) {
                if (! NotificationRule::matchesScope($rule, $contract)) {
                    continue;
                }

                $endDate = self::date((string) ($contract['end_date'] ?? ''), $timezone);
                if ($endDate === null || $today > $endDate) {
                    continue;
                }

                $maxRepeats = max(0, (int) ($rule['max_repeats'] ?? 0));
                for ($attemptNo = 0; $attemptNo <= $maxRepeats; $attemptNo++) {
                    try {
                        $target = NotificationRule::targetDate($rule, $endDate->format('Y-m-d'), $attemptNo);
                    } catch (Throwable) {
                        continue;
                    }

                    if ($target > $endDate) {
                        continue;
                    }

                    $date = $target->format('Y-m-d');
                    if ($dateFrom !== null && $dateFrom !== '' && $date < $dateFrom) {
                        continue;
                    }
                    if ($dateTo !== null && $dateTo !== '' && $date > $dateTo) {
                        continue;
                    }

                    $recipientIds = $this->recipients->resolve(
                        $rule,
                        isset($contract['accountant_user_id']) ? (int) $contract['accountant_user_id'] : null
                    );
                    if ($attemptNo > 0 && $attemptNo === $maxRepeats) {
                        $escalationRoles = is_array($rule['escalation_roles'] ?? null) ? $rule['escalation_roles'] : [];
                        if ($escalationRoles !== []) {
                            $recipientIds = array_values(array_unique(array_merge(
                                $recipientIds,
                                $this->recipients->resolve([
                                    'recipient_roles' => $escalationRoles,
                                    'recipient_user_ids' => [],
                                    'target_assigned_accountant' => false,
                                ], null)
                            )));
                            sort($recipientIds, SORT_NUMERIC);
                        }
                    }
                    if ($recipientIds === []) {
                        continue;
                    }

                    $pendingRecipientIds = [];
                    foreach ($recipientIds as $userId) {
                        $userId = (int) $userId;
                        if ($userId <= 0) {
                            continue;
                        }
                        $occurrenceKey = $this->occurrenceKey(
                            (int) ($rule['id'] ?? 0),
                            (int) ($contract['id'] ?? 0),
                            $attemptNo,
                            $target->format('Y-m-d'),
                            $userId
                        );
                        if (get_option($occurrenceKey, false) === false) {
                            $pendingRecipientIds[] = $userId;
                        }
                    }
                    if ($pendingRecipientIds === []) {
                        continue;
                    }
                    $recipientIds = $pendingRecipientIds;

                    $channels = [];
                    if (! array_key_exists('push_enabled', $rule) || ! empty($rule['push_enabled'])) {
                        $channels[] = 'push';
                    }
                    if (! empty($rule['email_enabled'])) {
                        $channels[] = 'email';
                    }

                    $rows[] = [
                        'id' => 0,
                        'rule_id' => (int) ($rule['id'] ?? 0),
                        'payment_id' => 0,
                        'contract_id' => (int) ($contract['id'] ?? 0),
                        'resource_type' => 'contract',
                        'attempt_no' => $attemptNo,
                        'recipient_ids' => $recipientIds,
                        'template_code' => (string) ($rule['template_code'] ?? 'contract_expiry_soon'),
                        'channel' => $channels !== [] ? implode('+', $channels) : 'none',
                        'scheduled_date' => $date,
                        'scheduled_for' => $settings->scheduledUtc($date),
                        'status' => 'pending',
                        'recipient_count' => count($recipientIds),
                        'sent_count' => 0,
                        'failed_count' => 0,
                        'manual_attempts' => 0,
                        'last_attempt_at' => '',
                        'sent_at' => '',
                        'last_error_code' => '',
                        'rule_code' => (string) ($rule['code'] ?? 'contract_expiry'),
                        'rule_name' => (string) ($rule['name'] ?? 'Contract expiry'),
                        'payment_reference' => '',
                        'due_date' => $endDate->format('Y-m-d'),
                        'end_date' => $endDate->format('Y-m-d'),
                        'financial_direction' => (string) ($contract['financial_direction'] ?? ''),
                        'currency_code' => (string) ($contract['currency_code'] ?? ''),
                        'contract_number' => (string) ($contract['contract_number'] ?? ''),
                        'counterparty_type' => (string) ($contract['counterparty_type'] ?? ''),
                        'counterparty_id' => (int) ($contract['counterparty_id'] ?? 0),
                        'counterparty_name' => (string) ($contract['counterparty_name'] ?? ''),
                        'customer_name' => (string) ($contract['customer_name'] ?? ''),
                        'supplier_name' => (string) ($contract['supplier_name'] ?? ''),
                    ];

                    if (count($rows) >= max(1, min(1000, $limit))) {
                        usort($rows, static fn (array $a, array $b): int => strcmp((string) $b['scheduled_for'], (string) $a['scheduled_for']));
                        return $rows;
                    }
                }
            }
        }

        usort($rows, static fn (array $a, array $b): int => strcmp((string) $b['scheduled_for'], (string) $a['scheduled_for']));
        return $rows;
    }

    /** @return list<array<string,mixed>> */
    private function candidateContracts(int $limit = 5000): array
    {
        global $wpdb;
        if (! is_object($wpdb)) {
            return [];
        }

        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $customers = $wpdb->prefix . 'safecontracts_customers';
        $suppliers = $wpdb->prefix . 'safecontracts_suppliers';
        $limit = max(1, min(10000, $limit));

        $rows = $wpdb->get_results(
            "SELECT c.id, c.contract_number, c.end_date, c.accountant_user_id,
                    c.counterparty_type, c.counterparty_id, c.financial_direction, c.currency_code,
                    CASE WHEN c.counterparty_type = 'customer' THEN cu.name
                         WHEN c.counterparty_type = 'supplier' THEN su.name
                         ELSE NULL END AS counterparty_name,
                    CASE WHEN c.counterparty_type = 'customer' THEN cu.name ELSE NULL END AS customer_name,
                    CASE WHEN c.counterparty_type = 'supplier' THEN su.name ELSE NULL END AS supplier_name
             FROM {$contracts} c
             LEFT JOIN {$customers} cu ON c.counterparty_type = 'customer' AND cu.id = c.counterparty_id
             LEFT JOIN {$suppliers} su ON c.counterparty_type = 'supplier' AND su.id = c.counterparty_id
             WHERE c.is_archived = 0
               AND c.status = 'active'
               AND c.end_date IS NOT NULL
               AND c.end_date <> '0000-00-00'
               AND ((c.counterparty_type = 'customer' AND cu.id IS NOT NULL AND cu.is_active = 1)
                    OR (c.counterparty_type = 'supplier' AND su.id IS NOT NULL AND su.is_active = 1 AND su.is_archived = 0))
             ORDER BY c.end_date ASC, c.id ASC
             LIMIT {$limit}",
            ARRAY_A
        );

        return is_array($rows) ? array_values($rows) : [];
    }

    private function occurrenceKey(int $ruleId, int $contractId, int $attemptNo, string $targetDate, int $userId): string
    {
        $hash = hash('sha256', implode('|', [$ruleId, $contractId, $attemptNo, $targetDate, $userId]));
        return self::OCCURRENCE_OPTION_PREFIX . $hash;
    }

    private static function date(string $value, DateTimeZone $timezone): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', trim($value), $timezone);
        return $date && $date->format('Y-m-d') === trim($value) ? $date : null;
    }
}
