<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

use SafeContracts\Tenancy\NonCoreTenantScope;
use SafeContracts\Tenancy\TenantMembershipRepository;

final class EmailDeliveryService
{
    public function __construct(
        private ?DeliveryLogRepository $deliveries = null,
        private ?EmailSettings $settings = null,
        private ?TenantMembershipRepository $memberships = null
    ) {
        $this->deliveries ??= new DeliveryLogRepository();
        $this->settings ??= new EmailSettings();
        $this->memberships ??= new TenantMembershipRepository();
    }

    /**
     * @param array<string,mixed> $plan
     * @return array{attempted:int,sent:int,failed:int,retryable:bool}
     */
    public function deliver(array $plan, int $attemptNo = 0): array
    {
        $config = $this->settings->get();
        $recipients = is_array($plan['recipient_ids'] ?? null)
            ? array_values(array_unique(array_filter(array_map('intval', $plan['recipient_ids']), static fn (int $id): bool => $id > 0)))
            : [];
        $tenantId = NonCoreTenantScope::tenantId();
        if ($tenantId !== null) {
            // Re-check membership immediately before transport so a recipient
            // removed after schedule creation cannot receive from a stale plan.
            $recipients = $this->memberships->filterActiveUserIds($tenantId, $recipients);
        }

        $sent = 0;
        $failed = 0;
        $attempted = 0;

        foreach ($recipients as $userId) {
            $attempted++;
            $user = function_exists('get_userdata') ? get_userdata($userId) : false;
            $rawEmail = is_object($user) ? (string) ($user->user_email ?? '') : '';
            $email = function_exists('sanitize_email') ? sanitize_email($rawEmail) : trim($rawEmail);
            $success = false;
            $error = null;

            if (! $config['enabled']) {
                $error = 'email_channel_disabled';
            } elseif (! EmailSettings::validEmail($email)) {
                $error = 'recipient_email_unavailable';
            } else {
                $headers = [];
                if (EmailSettings::validEmail($config['from_address'])) {
                    $headers[] = 'From: ' . $config['from_name'] . ' <' . $config['from_address'] . '>';
                }
                $success = function_exists('wp_mail') && (bool) wp_mail(
                    $email,
                    (string) ($plan['email_subject'] ?? $plan['payload']['title'] ?? ''),
                    (string) ($plan['email_body'] ?? $plan['payload']['body'] ?? ''),
                    $headers
                );
                if (! $success) {
                    $error = 'wp_mail_failed';
                }
            }

            $success ? $sent++ : $failed++;
            $this->deliveries->append(
                isset($plan['rule_id']) ? (int) $plan['rule_id'] : null,
                (int) ($plan['payment_id'] ?? 0),
                $userId,
                null,
                (string) ($plan['template_code'] ?? ''),
                (string) ($plan['scheduled_for'] ?? ''),
                $attemptNo,
                $success ? 'sent' : 'failed',
                null,
                $error,
                'email'
            );
            do_action('safecontracts_notification_email_attempted', (int) ($plan['rule_id'] ?? 0), (int) ($plan['payment_id'] ?? 0), $userId, $success ? 'sent' : 'failed', $error);
        }

        return [
            'attempted' => $attempted,
            'sent' => $sent,
            'failed' => $failed,
            'retryable' => $failed > 0,
        ];
    }
}
