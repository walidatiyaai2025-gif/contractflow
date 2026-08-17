<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

use DomainException;
use InvalidArgumentException;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\NonCoreTenantScope;
use SafeContracts\Tenancy\TenantMembershipRepository;

final class DeviceTokenService
{
    public const APPLICATION_ID = 'com.safecontracts.enterprise';

    public function __construct(
        private ?DeviceTokenRepository $repository = null,
        private ?TenantMembershipRepository $memberships = null
    ) {
        $this->repository ??= new DeviceTokenRepository();
        $this->memberships ??= new TenantMembershipRepository();
    }

    public function register(mixed $token, mixed $platform, mixed $applicationId = null): string
    {
        $normalizedApplicationId = $this->normalizeApplicationId($applicationId);
        $userId = $this->requireAuthenticatedAccess();
        $normalizedToken = $this->normalizeToken($token);
        $normalizedPlatform = $this->normalizePlatform($platform);
        $this->repository->register($userId, $normalizedToken, $normalizedPlatform);
        do_action(
            'safecontracts_device_token_registered',
            $userId,
            hash('sha256', $normalizedToken),
            $normalizedPlatform,
            $normalizedApplicationId
        );
        return $normalizedApplicationId;
    }

    public function revoke(mixed $token, mixed $applicationId = null): string
    {
        $normalizedApplicationId = $this->normalizeApplicationId($applicationId);
        $userId = $this->requireAuthenticatedAccess();
        $normalizedToken = $this->normalizeToken($token);
        $this->repository->revokeOwned($userId, $normalizedToken);
        do_action(
            'safecontracts_device_token_revoked',
            $userId,
            hash('sha256', $normalizedToken),
            $normalizedApplicationId
        );
        return $normalizedApplicationId;
    }

    private function normalizeApplicationId(mixed $value): string
    {
        $applicationId = trim((string) $value);
        if ($applicationId !== self::APPLICATION_ID) {
            throw new InvalidArgumentException(
                'Device registration application identity must be ' . self::APPLICATION_ID . '.'
            );
        }
        return $applicationId;
    }

    private function normalizeToken(mixed $value): string
    {
        $token = trim((string) $value);
        if (strlen($token) < 20 || strlen($token) > 4096 || preg_match('/[\r\n\x00]/', $token)) {
            throw new InvalidArgumentException('Device token is invalid.');
        }
        return $token;
    }

    private function normalizePlatform(mixed $value): string
    {
        $platform = strtolower(trim((string) $value));
        if (! in_array($platform, ['android', 'ios', 'web'], true)) {
            throw new InvalidArgumentException('Device platform must be android, ios, or web.');
        }
        return $platform;
    }

    private function requireAuthenticatedAccess(): int
    {
        if (! current_user_can(Capabilities::ACCESS)) {
            throw new DomainException('You do not have access to SafeContracts device registration.');
        }
        $userId = get_current_user_id();
        if ($userId <= 0) {
            throw new DomainException('Device registration requires an authenticated SafeContracts user.');
        }

        $tenantId = NonCoreTenantScope::tenantId();
        if ($tenantId !== null && ! $this->memberships->isActiveMember($tenantId, $userId)) {
            throw new DomainException('Device registration requires an active membership in the current Enterprise tenant.');
        }
        return $userId;
    }
}
