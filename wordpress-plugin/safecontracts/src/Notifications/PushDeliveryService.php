<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

use DateTimeImmutable;
use InvalidArgumentException;
use SafeContracts\Tenancy\NonCoreTenantScope;
use SafeContracts\Tenancy\TenantMembershipRepository;
use Throwable;

final class PushDeliveryService
{
    public const MAX_TRANSPORT_RETRIES = 3;
    private const ALLOWED_DATA_KEYS = ['payment_id', 'rule_code', 'attempt_no', 'icon_key'];

    public function __construct(
        private PushTransport $transport,
        private ?DeviceTokenRepository $tokens = null,
        private ?DeliveryLogRepository $deliveries = null,
        private ?TenantMembershipRepository $memberships = null
    ) {
        $this->tokens ??= new DeviceTokenRepository();
        $this->deliveries ??= new DeliveryLogRepository();
        $this->memberships ??= new TenantMembershipRepository();
    }

    /**
     * @param array{
     *   rule_id:int,payment_id:int,recipient_ids:list<int>,template_code:string,scheduled_for:string,
     *   payload:array{title:string,body:string,data?:array<string,scalar|null>}
     * } $plan
     * @return array{attempted:int,sent:int,failed:int,retryable:bool}
     */
    public function deliver(array $plan, int $attemptNo = 0): array
    {
        if ($attemptNo < 0 || $attemptNo > self::MAX_TRANSPORT_RETRIES) {
            throw new InvalidArgumentException('Notification transport attempt is outside the retry policy.');
        }
        $ruleId = (int) ($plan['rule_id'] ?? 0);
        $paymentId = (int) ($plan['payment_id'] ?? 0);
        if ($ruleId <= 0 || $paymentId <= 0) {
            throw new InvalidArgumentException('Notification delivery requires positive rule and payment IDs.');
        }
        $scheduledFor = $this->normalizeDate($plan['scheduled_for'] ?? '');
        $templateCode = NotificationRule::normalizeCode($plan['template_code'] ?? '');
        $payload = $this->normalizePayload($plan['payload'] ?? []);
        $recipientIds = is_array($plan['recipient_ids'] ?? null)
            ? array_values(array_unique(array_filter(array_map('intval', $plan['recipient_ids']), static fn (int $id): bool => $id > 0)))
            : [];
        $tenantId = NonCoreTenantScope::tenantId();
        if ($tenantId !== null) {
            // Membership is re-evaluated at the final transport boundary so a user
            // removed after schedule creation cannot receive from a stale plan.
            $recipientIds = $this->memberships->filterActiveUserIds($tenantId, $recipientIds);
        }
        $deviceRows = $this->tokens->activeForUsers($recipientIds);

        $sent = 0;
        $failed = 0;
        foreach ($deviceRows as $device) {
            $result = null;
            try {
                $result = $this->transport->send($device['token'], $payload);
            } catch (Throwable) {
                $result = ['success' => false, 'status_code' => 0, 'error_code' => 'transport_exception'];
            }

            $success = (bool) ($result['success'] ?? false);
            $status = $success ? 'sent' : 'failed';
            $success ? $sent++ : $failed++;
            $this->deliveries->append(
                $ruleId,
                $paymentId,
                $device['user_id'],
                $device['id'],
                $templateCode,
                $scheduledFor,
                $attemptNo,
                $status,
                isset($result['status_code']) ? (int) $result['status_code'] : null,
                isset($result['error_code']) ? (string) $result['error_code'] : null,
                'push'
            );
            do_action(
                'safecontracts_notification_delivery_attempted',
                $ruleId,
                $paymentId,
                $device['user_id'],
                $device['id'],
                $status,
                $attemptNo
            );
        }

        return [
            'attempted' => count($deviceRows),
            'sent' => $sent,
            'failed' => $failed,
            'retryable' => $failed > 0 && $this->canRetry($attemptNo),
        ];
    }

    public function canRetry(int $attemptNo): bool
    {
        return $attemptNo >= 0 && $attemptNo < self::MAX_TRANSPORT_RETRIES;
    }

    public function retryDelaySeconds(int $attemptNo): int
    {
        if (! $this->canRetry($attemptNo)) {
            return 0;
        }
        return min(3600, 60 * (2 ** $attemptNo));
    }

    /** @return array{title:string,body:string,data?:array<string,scalar|null>} */
    private function normalizePayload(mixed $payload): array
    {
        if (! is_array($payload)) {
            throw new InvalidArgumentException('Notification push payload must be an array.');
        }
        $title = trim(strip_tags((string) ($payload['title'] ?? '')));
        $body = trim(strip_tags((string) ($payload['body'] ?? '')));
        if ($title === '' || strlen($title) > 191 || $body === '' || strlen($body) > 4000) {
            throw new InvalidArgumentException('Notification push title/body are invalid.');
        }
        $normalized = ['title' => $title, 'body' => $body];
        if (isset($payload['icon_key'])) {
            $icon = sanitize_key((string) $payload['icon_key']);
            if (! in_array($icon, NotificationTemplate::allowedIconKeys(), true)) {
                throw new InvalidArgumentException('Notification icon metadata is invalid.');
            }
            $normalized['icon_key'] = $icon;
        }
        if (isset($payload['data'])) {
            if (! is_array($payload['data']) || count($payload['data']) > count(self::ALLOWED_DATA_KEYS)) {
                throw new InvalidArgumentException('Notification push data is invalid or exceeds the metadata limit.');
            }
            $data = [];
            foreach ($payload['data'] as $key => $value) {
                $key = (string) $key;
                if (! in_array($key, self::ALLOWED_DATA_KEYS, true)) {
                    throw new InvalidArgumentException('Notification push data contains an unsupported metadata field.');
                }
                if ($key === 'payment_id') {
                    if (! is_int($value) || $value <= 0) {
                        throw new InvalidArgumentException('Notification payment metadata must be a positive integer.');
                    }
                } elseif ($key === 'attempt_no') {
                    if (! is_int($value) || $value < 0 || $value > 100) {
                        throw new InvalidArgumentException('Notification attempt metadata is invalid.');
                    }
                } else {
                    if (! is_string($value) || $value === '' || strlen($value) > 100 || ! preg_match('/^[a-z0-9_.-]+$/', $value)) {
                        throw new InvalidArgumentException('Notification string metadata is invalid.');
                    }
                }
                $data[$key] = $value;
            }
            $normalized['data'] = $data;
        }
        return $normalized;
    }

    private function normalizeDate(mixed $value): string
    {
        $date = trim((string) $value);
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (! $parsed || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('Notification scheduled date must use YYYY-MM-DD.');
        }
        return $date;
    }
}
