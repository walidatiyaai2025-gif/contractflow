<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

final class EmailDeliveryService
{
    public function __construct(
        private ?DeliveryLogRepository $deliveries = null,
        private ?EmailSettings $settings = null
    ) {
        $this->deliveries ??= new DeliveryLogRepository();
        $this->settings ??= new EmailSettings();
    }

    /**
     * @param array<string,mixed> $plan
     * @return array{attempted:int,sent:int,failed:int,retryable:bool}
     */
    public function deliver(array $plan, int $attemptNo = 0): array
    {
        $config = $this->settings->get();
        $recipients = is_array($plan['recipient_ids'] ?? null) ? array_values(array_unique(array_map('intval', $plan['recipient_ids']))) : [];
        $sent = 0;
        $failed = 0;
        $attempted = 0;

        foreach ($recipients as $userId) {
            if ($userId <= 0) {
                continue;
            }
            $attempted++;
            $user = function_exists('get_userdata') ? get_userdata($userId) : false;
            $email = is_object($user) ? sanitize_email((string) ($user->user_email ?? '')) : '';
            $success = false;
            $error = null;

            if (! $config['enabled']) {
                $error = 'email_channel_disabled';
            } elseif ($email === '' || ! is_email($email)) {
                $error = 'recipient_email_unavailable';
            } else {
                $headers = [];
                if ($config['from_address'] !== '' && is_email($config['from_address'])) {
                    $headers[] = 'From: ' . $config['from_name'] . ' <' . $config['from_address'] . '>';
                }
                $success = (bool) wp_mail(
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
