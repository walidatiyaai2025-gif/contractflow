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

    /**
     * @param array<string,mixed> $context
     * @return array{push_sent:int,push_failed:int,email_sent:int,email_failed:int}
     */
    public function send(
        int $userId,
        string $title,
        string $body,
        bool $push,
        bool $email,
        string $iconKey = 'safe_contracts',
        array $context = []
    ): array {
        if ($userId <= 0 || (! $push && ! $email)) {
            throw new InvalidArgumentException('Direct notification requires a user and at least one delivery channel.');
        }
        $title = trim(function_exists('sanitize_text_field') ? sanitize_text_field($title) : strip_tags($title));
        $body = trim(function_exists('sanitize_textarea_field') ? sanitize_textarea_field($body) : strip_tags($body));
        if ($title === '' || strlen($title) > 191 || $body === '' || strlen($body) > 4000) {
            throw new InvalidArgumentException('Direct notification title or body is invalid.');
        }
        if (! in_array($iconKey, NotificationTemplate::allowedIconKeys(), true)) {
            throw new InvalidArgumentException('Direct notification icon is invalid.');
        }

        $normalized = $this->normalizeContext($context);
        $result = ['push_sent' => 0, 'push_failed' => 0, 'email_sent' => 0, 'email_failed' => 0];
        $today = gmdate('Y-m-d');

        if ($push) {
            $transport = new FirebasePushTransport();
            foreach ($this->tokens->activeForUsers([$userId]) as $device) {
                $delivery = $transport->send((string) $device['token'], [
                    'title' => $title,
                    'body' => $body,
                    'icon_key' => $iconKey,
                    'data' => [
                        'rule_code' => $normalized['event_code'],
                        'event_code' => $normalized['event_code'],
                        'attempt_no' => 0,
                        'icon_key' => $iconKey,
                        'contract_id' => $normalized['contract_id'],
                        'payment_id' => $normalized['payment_id'],
                        'resource_type' => $normalized['resource_type'] ?? '',
                        'resource_id' => $normalized['resource_id'] ?? 0,
                    ],
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
                    $normalized['payment_id'],
                    $userId,
                    (int) $device['id'],
                    $normalized['template_code'],
                    $today,
                    0,
                    $success ? 'sent' : 'failed',
                    isset($delivery['status_code']) ? (int) $delivery['status_code'] : null,
                    $errorCode,
                    'push',
                    $normalized['resource_type'],
                    $normalized['resource_id'],
                    $normalized['contract_id'] > 0 ? $normalized['contract_id'] : null
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
            $this->deliveries->append(
                null,
                $normalized['payment_id'],
                $userId,
                null,
                $normalized['template_code'],
                $today,
                0,
                $success ? 'sent' : 'failed',
                null,
                $error,
                'email',
                $normalized['resource_type'],
                $normalized['resource_id'],
                $normalized['contract_id'] > 0 ? $normalized['contract_id'] : null
            );
        }

        do_action('safecontracts_direct_notification_sent', $userId, $result, get_current_user_id(), $normalized);
        return $result;
    }

    /** @param array<string,mixed> $context @return array{event_code:string,template_code:string,contract_id:int,payment_id:int,resource_type:?string,resource_id:?int} */
    private function normalizeContext(array $context): array
    {
        $eventCode = NotificationRule::normalizeCode($context['event_code'] ?? 'manual_message');
        $templateCode = NotificationRule::normalizeCode($context['template_code'] ?? $eventCode);
        $contractId = max(0, (int) ($context['contract_id'] ?? 0));
        $paymentId = max(0, (int) ($context['payment_id'] ?? 0));

        $resourceType = isset($context['resource_type']) ? strtolower(trim((string) $context['resource_type'])) : null;
        if ($resourceType === '') {
            $resourceType = null;
        }
        if ($resourceType !== null && ! in_array($resourceType, ['contract', 'payment', 'followup'], true)) {
            throw new InvalidArgumentException('Direct notification resource type is invalid.');
        }
        $resourceId = isset($context['resource_id']) ? (int) $context['resource_id'] : null;
        if ($resourceType !== null && ($resourceId === null || $resourceId <= 0)) {
            throw new InvalidArgumentException('Direct notification resource ID must be positive when supplied.');
        }
        if ($resourceType === null) {
            $resourceId = null;
        }

        return [
            'event_code' => $eventCode,
            'template_code' => $templateCode,
            'contract_id' => $contractId,
            'payment_id' => $paymentId,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
        ];
    }
}
