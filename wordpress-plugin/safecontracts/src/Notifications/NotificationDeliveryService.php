<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

final class NotificationDeliveryService
{
    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private ?NotificationRepository $notifications = null,
        private ?DeviceTokenRepository $devices = null,
        private ?FirebasePushClient $push = null
    ) {
        $this->notifications ??= new NotificationRepository();
        $this->devices ??= new DeviceTokenRepository();
        $this->push ??= new FirebasePushClient();
    }

    /** @return array{processed:int,sent:int,failed:int,suppressed:int} */
    public function process(int $limit = 100): array
    {
        $result = ['processed' => 0, 'sent' => 0, 'failed' => 0, 'suppressed' => 0];
        foreach ($this->notifications->ready($limit) as $notification) {
            $id = (int) ($notification['id'] ?? 0);
            if ($id <= 0 || ! $this->notifications->claim($id)) {
                continue;
            }
            $result['processed']++;

            if (! $this->notifications->paymentStillEligible((int) ($notification['payment_id'] ?? 0))) {
                $this->notifications->markSuppressed($id);
                $result['suppressed']++;
                continue;
            }

            $attempt = min(self::MAX_ATTEMPTS, (int) ($notification['attempt_count'] ?? 0) + 1);
            $tokens = $this->devices->activeForUsers([(int) ($notification['user_id'] ?? 0)]);
            if ($tokens === []) {
                $this->notifications->markFailed($id, self::MAX_ATTEMPTS, null, 'no_active_device', 'No active push device is registered for the recipient.');
                $this->notifications->logAttempt($id, null, $attempt, [
                    'success' => false,
                    'http_status' => 0,
                    'error_code' => 'no_active_device',
                    'error_message' => 'No active push device is registered for the recipient.',
                ]);
                $result['failed']++;
                continue;
            }

            $anySuccess = false;
            $retryable = false;
            $lastCode = 'delivery_failed';
            $lastMessage = 'Push delivery failed.';
            foreach ($tokens as $device) {
                $delivery = $this->push->send(
                    $device,
                    (string) ($notification['title'] ?? ''),
                    (string) ($notification['body'] ?? ''),
                    is_array($notification['data'] ?? null) ? $notification['data'] : []
                );
                $this->notifications->logAttempt($id, (int) ($device['id'] ?? 0), $attempt, $delivery);
                if ($delivery['success']) {
                    $anySuccess = true;
                    continue;
                }
                $lastCode = (string) $delivery['error_code'];
                $lastMessage = (string) $delivery['error_message'];
                $retryable = $retryable || $delivery['retryable'];
                if ($delivery['permanent_token_error']) {
                    $this->devices->deactivate((int) ($device['id'] ?? 0), $delivery['error_code']);
                }
            }

            if ($anySuccess) {
                $this->notifications->markSent($id, $attempt);
                $result['sent']++;
                continue;
            }

            $next = null;
            if ($retryable && $attempt < self::MAX_ATTEMPTS) {
                $next = gmdate('Y-m-d H:i:s', time() + $this->backoffSeconds($attempt));
            } else {
                $attempt = self::MAX_ATTEMPTS;
            }
            $this->notifications->markFailed($id, $attempt, $next, $lastCode, $lastMessage);
            $result['failed']++;
        }
        return $result;
    }

    private function backoffSeconds(int $attempt): int
    {
        // 5, 10, 20, 40 minutes; capped well below the hourly scheduler horizon.
        return min(21600, 300 * (2 ** max(0, $attempt - 1)));
    }
}
