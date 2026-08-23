<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

final class NotificationDeliveryService
{
    public function __construct(
        private ?PushDeliveryService $push = null,
        private ?EmailDeliveryService $email = null
    ) {
        $this->push ??= new PushDeliveryService(new FirebasePushTransport());
        $this->email ??= new EmailDeliveryService();
    }

    /**
     * @param array<string,mixed> $plan
     * @return array{attempted:int,sent:int,failed:int,retryable:bool,channels:list<string>}
     */
    public function deliver(array $plan, int $occurrenceAttemptNo = 0, int $transportAttemptNo = 0): array
    {
        $attempted = 0;
        $sent = 0;
        $failed = 0;
        $retryable = false;
        $channels = [];

        if (! empty($plan['push_enabled'])) {
            $result = $this->push->deliver($plan, $occurrenceAttemptNo, $transportAttemptNo);
            $attempted += (int) ($result['attempted'] ?? 0);
            $sent += (int) ($result['sent'] ?? 0);
            $failed += (int) ($result['failed'] ?? 0);
            $retryable = $retryable || ! empty($result['retryable']);
            $channels[] = 'push';
        }

        if (! empty($plan['email_enabled'])) {
            $result = $this->email->deliver($plan, $occurrenceAttemptNo);
            $attempted += (int) ($result['attempted'] ?? 0);
            $sent += (int) ($result['sent'] ?? 0);
            $failed += (int) ($result['failed'] ?? 0);
            $retryable = $retryable || ! empty($result['retryable']);
            $channels[] = 'email';
        }

        return [
            'attempted' => $attempted,
            'sent' => $sent,
            'failed' => $failed,
            'retryable' => $retryable,
            'channels' => $channels,
        ];
    }
}
