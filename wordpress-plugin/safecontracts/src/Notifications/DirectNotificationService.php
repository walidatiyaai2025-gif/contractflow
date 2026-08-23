<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

use InvalidArgumentException;
use Throwable;

final class DirectNotificationService
{
    public function __construct(
        private ?DeviceTokenRepository $tokens = null,
        private ?DeliveryLogRepository $deliveries = null,
        private ?EmailSettings $emailSettings = null,
        private ?SmtpSettings $smtpSettings = null,
        private ?DirectSmtpTransport $smtpTransport = null
    ) {
        $this->tokens ??= new DeviceTokenRepository();
        $this->deliveries ??= new DeliveryLogRepository();
        $this->emailSettings ??= new EmailSettings();
        $this->smtpSettings ??= new SmtpSettings();
        $this->smtpTransport ??= new DirectSmtpTransport();
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
                    'data' => ['rule_code' => 'manual_message', 'attempt_no' => 0, 'icon_key' => $iconKey],
                ]);
                $success = ! empty($delivery['success']);
                $errorCode = isset($delivery['error_code']) ? strtolower(trim((string) $delivery['error_code'])) : null;
                $success ? $result['push_sent']++ : $result['push_failed']++;

                if (! $success && $errorCode === 'firebase_token_not_found') {
                    try {
                        $this->tokens->deactivateOwnedById($userId, (int) $device['id']);
                        do_action('safecontracts_notification_device_deactivated', $userId, (int) $device['id'], 'firebase_token_not_found');
                    } catch (Throwable) {
                        do_action('safecontracts_notification_device_deactivation_failed', $userId, (int) $device['id'], 'firebase_token_not_found');
                    }
                }

                $this->deliveries->append(
                    null,
                    0,
                    $userId,
                    (int) $device['id'],
                    'manual_message',
                    $today,
                    0,
                    $success ? 'sent' : 'failed',
                    isset($delivery['status_code']) ? (int) $delivery['status_code'] : null,
                    $errorCode,
                    'push'
                );
            }
        }

        if ($email) {
            $user = function_exists('get_userdata') ? get_userdata($userId) : false;
            $rawAddress = is_object($user) ? (string) ($user->user_email ?? '') : '';
            $address = function_exists('sanitize_email') ? sanitize_email($rawAddress) : trim($rawAddress);
            $settings = $this->emailSettings->get();
            $smtp = $this->smtpSettings->get();
            $success = false;
            $error = null;
            if (! $settings['enabled']) {
                $error = 'email_channel_disabled';
            } elseif (! EmailSettings::validEmail($address)) {
                $error = 'recipient_email_unavailable';
            } else {
                $delivery = $this->smtpTransport->send(
                    $address,
                    $title,
                    $body,
                    $smtp,
                    (string) $settings['from_name'],
                    (string) $settings['from_address']
                );
                $success = ! empty($delivery['success']);
                $error = isset($delivery['error_code']) && is_string($delivery['error_code']) ? $delivery['error_code'] : null;
            }
            $success ? $result['email_sent']++ : $result['email_failed']++;
            $this->deliveries->append(null, 0, $userId, null, 'manual_message', $today, 0, $success ? 'sent' : 'failed', null, $error, 'email');
        }

        do_action('safecontracts_direct_notification_sent', $userId, $result, get_current_user_id());
        return $result;
    }
}
