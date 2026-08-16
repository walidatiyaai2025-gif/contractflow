<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

use InvalidArgumentException;

final class DirectNotificationService
{
    public function __construct(
        private ?DeviceTokenRepository $tokens = null,
        private ?DeliveryLogRepository $deliveries = null,
        private ?EmailSettings $emailSettings = null
    ) {
        $this->tokens ??= new DeviceTokenRepository();
        $this->deliveries ??= new DeliveryLogRepository();
        $this->emailSettings ??= new EmailSettings();
    }

    /** @return array{push_sent:int,push_failed:int,email_sent:int,email_failed:int} */
    public function send(int $userId, string $title, string $body, bool $push, bool $email, string $iconKey = 'safe_contracts'): array
    {
        if ($userId <= 0 || (! $push && ! $email)) {
            throw new InvalidArgumentException('Direct notification requires a user and at least one delivery channel.');
        }
        $title = trim(sanitize_text_field($title));
        $body = trim(sanitize_textarea_field($body));
        if ($title === '' || strlen($title) > 191 || $body === '' || strlen($body) > 4000) {
            throw new InvalidArgumentException('Direct notification title or body is invalid.');
        }
        if (! in_array($iconKey, NotificationTemplate::allowedIconKeys(), true)) {
            throw new InvalidArgumentException('Direct notification icon is invalid.');
        }

        $result = ['push_sent' => 0, 'push_failed' => 0, 'email_sent' => 0, 'email_failed' => 0];
        $today = gmdate('Y-m-d');

        if ($push) {
            $transport = new FirebasePushTransport();
            foreach ($this->tokens->activeForUsers([$userId]) as $device) {
                $delivery = $transport->send((string) $device['token'], [
                    'title' => $title,
                    'body' => $body,
                    'icon_key' => $iconKey,
                    'data' => ['rule_code' => 'manual_message', 'attempt_no' => 0],
                ]);
                $success = ! empty($delivery['success']);
                $success ? $result['push_sent']++ : $result['push_failed']++;
                $this->deliveries->append(null, 0, $userId, (int) $device['id'], 'manual_message', $today, 0, $success ? 'sent' : 'failed', isset($delivery['status_code']) ? (int) $delivery['status_code'] : null, isset($delivery['error_code']) ? (string) $delivery['error_code'] : null, 'push');
            }
        }

        if ($email) {
            $user = function_exists('get_userdata') ? get_userdata($userId) : false;
            $address = is_object($user) ? sanitize_email((string) ($user->user_email ?? '')) : '';
            $settings = $this->emailSettings->get();
            $success = false;
            $error = null;
            if (! $settings['enabled']) {
                $error = 'email_channel_disabled';
            } elseif ($address === '' || ! is_email($address)) {
                $error = 'recipient_email_unavailable';
            } else {
                $headers = ['Content-Type: text/plain; charset=UTF-8'];
                if ($settings['from_address'] !== '' && is_email($settings['from_address'])) {
                    $headers[] = 'From: ' . $settings['from_name'] . ' <' . $settings['from_address'] . '>';
                }
                $success = (bool) wp_mail($address, $title, $body, $headers);
                if (! $success) {
                    $error = 'wp_mail_failed';
                }
            }
            $success ? $result['email_sent']++ : $result['email_failed']++;
            $this->deliveries->append(null, 0, $userId, null, 'manual_message', $today, 0, $success ? 'sent' : 'failed', null, $error, 'email');
        }

        do_action('safecontracts_direct_notification_sent', $userId, $result, get_current_user_id());
        return $result;
    }
}
