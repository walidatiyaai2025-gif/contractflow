<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

use InvalidArgumentException;
use Throwable;

final class FirebaseTestNotificationService
{
    public function __construct(
        private ?DeviceTokenRepository $devices = null,
        private ?PushTransport $transport = null
    ) {
        $this->devices ??= new DeviceTokenRepository();
        $this->transport ??= new FirebasePushTransport();
    }

    /**
     * @return array{
     *   status:'ok'|'partial'|'no_device'|'other_user_device'|'no_usable_token'|'failed',
     *   attempted:int,
     *   succeeded:int,
     *   failed:int,
     *   deactivated:int,
     *   error_codes:list<string>
     * }
     */
    public function sendForUser(int $userId): array
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('Firebase test notification requires a valid WordPress user.');
        }

        $activeRows = $this->devices->activeForUsers([$userId]);
        $usableDevices = [];
        foreach ($activeRows as $device) {
            $deviceId = (int) ($device['id'] ?? 0);
            $ownerId = (int) ($device['user_id'] ?? 0);
            $token = trim((string) ($device['token'] ?? ''));
            if ($deviceId <= 0 || $ownerId !== $userId || $token === '') {
                continue;
            }
            $usableDevices[] = [
                'id' => $deviceId,
                'token' => $token,
            ];
        }

        if ($usableDevices === []) {
            $diagnostics = $this->devices->activeDiagnostics($userId);
            $status = 'no_device';
            if ($diagnostics['current_user_active_devices'] > 0) {
                $status = 'no_usable_token';
            } elseif ($diagnostics['active_devices'] > 0) {
                $status = 'other_user_device';
            }

            return [
                'status' => $status,
                'attempted' => 0,
                'succeeded' => 0,
                'failed' => 0,
                'deactivated' => 0,
                'error_codes' => [],
            ];
        }

        $payload = [
            'title' => 'SafeContracts',
            'body' => 'Firebase test notification delivered successfully.',
            'data' => [],
        ];
        $succeeded = 0;
        $failed = 0;
        $deactivated = 0;
        $errorCodes = [];

        foreach ($usableDevices as $device) {
            try {
                $result = $this->transport->send($device['token'], $payload);
            } catch (Throwable) {
                $result = [
                    'success' => false,
                    'status_code' => 0,
                    'error_code' => 'transport_exception',
                ];
            }

            if (! empty($result['success'])) {
                $succeeded++;
                continue;
            }

            $failed++;
            $errorCode = $this->normalizeErrorCode($result['error_code'] ?? null);
            if (! in_array($errorCode, $errorCodes, true)) {
                $errorCodes[] = $errorCode;
            }

            if ($errorCode === 'firebase_token_not_found') {
                try {
                    $this->devices->deactivateOwnedById($userId, $device['id']);
                    $deactivated++;
                } catch (Throwable) {
                    if (! in_array('device_deactivation_failed', $errorCodes, true)) {
                        $errorCodes[] = 'device_deactivation_failed';
                    }
                }
            }
        }

        $status = $failed === 0 ? 'ok' : ($succeeded > 0 ? 'partial' : 'failed');

        return [
            'status' => $status,
            'attempted' => count($usableDevices),
            'succeeded' => $succeeded,
            'failed' => $failed,
            'deactivated' => $deactivated,
            'error_codes' => $errorCodes,
        ];
    }

    private function normalizeErrorCode(mixed $errorCode): string
    {
        $normalized = strtolower(trim((string) $errorCode));
        if ($normalized === '' || ! preg_match('/^[a-z0-9_]{1,100}$/', $normalized)) {
            return 'firebase_unknown_error';
        }
        return $normalized;
    }
}
