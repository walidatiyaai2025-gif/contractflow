<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

use DomainException;
use InvalidArgumentException;
use SafeContracts\Roles\Capabilities;

final class DeviceTokenService
{
    public function __construct(private ?DeviceTokenRepository $repository = null)
    {
        $this->repository ??= new DeviceTokenRepository();
    }

    public function registerCurrentUser(array $input): int
    {
        if (! current_user_can(Capabilities::ACCESS)) {
            throw new DomainException('You do not have access to SafeContracts device registration.');
        }
        $userId = get_current_user_id();
        if ($userId <= 0) {
            throw new DomainException('Device registration requires an authenticated WordPress user.');
        }

        $deviceId = trim((string) ($input['device_id'] ?? ''));
        if ($deviceId === '' || strlen($deviceId) > 191 || ! preg_match('/^[A-Za-z0-9._:-]+$/', $deviceId)) {
            throw new InvalidArgumentException('SafeContracts device_id is invalid.');
        }
        $platform = strtolower(trim((string) ($input['platform'] ?? '')));
        if (! in_array($platform, ['android', 'ios', 'web'], true)) {
            throw new InvalidArgumentException('SafeContracts device platform must be android, ios, or web.');
        }
        $token = trim((string) ($input['token'] ?? ''));
        if (strlen($token) < 20 || strlen($token) > 4096 || preg_match('/\s/', $token)) {
            throw new InvalidArgumentException('SafeContracts push token is invalid.');
        }
        $appVersion = $this->optionalText($input['app_version'] ?? null, 64, 'app_version');

        $id = $this->repository->upsert([
            'user_id' => $userId,
            'device_id' => $deviceId,
            'platform' => $platform,
            'token' => $token,
            'token_hash' => hash('sha256', $token),
            'app_version' => $appVersion,
        ]);
        do_action('safecontracts_device_token_registered', $id, $userId, $platform);
        return $id;
    }

    /** @return list<array<string, mixed>> */
    public function currentUserDevices(): array
    {
        if (! current_user_can(Capabilities::ACCESS)) {
            throw new DomainException('You do not have access to SafeContracts devices.');
        }
        $userId = get_current_user_id();
        if ($userId <= 0) {
            return [];
        }
        return $this->repository->metadataForUser($userId);
    }

    public function deactivateCurrentUserDevice(int $tokenId): void
    {
        if (! current_user_can(Capabilities::ACCESS)) {
            throw new DomainException('You do not have access to SafeContracts devices.');
        }
        if ($tokenId <= 0 || ! $this->repository->deactivateForUser($tokenId, get_current_user_id())) {
            throw new DomainException('Device token is outside the current user scope or was not found.');
        }
        do_action('safecontracts_device_token_deactivated', $tokenId, get_current_user_id());
    }

    private function optionalText(mixed $value, int $maxLength, string $field): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $text = trim((string) $value);
        if ($text === '' || strlen($text) > $maxLength) {
            throw new InvalidArgumentException('SafeContracts ' . $field . ' is invalid.');
        }
        return $text;
    }
}
