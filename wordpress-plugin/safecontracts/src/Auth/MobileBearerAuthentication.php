<?php

declare(strict_types=1);

namespace SafeContracts\Auth;

final class MobileBearerAuthentication
{
    public static function register(): void
    {
        add_filter('determine_current_user', [self::class, 'authenticate'], 20);
    }

    public static function authenticate(mixed $userId): int
    {
        $resolved = is_int($userId) ? $userId : (int) $userId;
        if ($resolved > 0) {
            return $resolved;
        }

        $token = self::bearerToken();
        if ($token === null) {
            return 0;
        }

        return (new MobileSessionStore())->resolve($token);
    }

    public static function bearerToken(): ?string
    {
        $header = '';
        foreach (['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $key) {
            $value = $_SERVER[$key] ?? '';
            if (is_string($value) && trim($value) !== '') {
                $header = trim($value);
                break;
            }
        }
        if ($header === '' || ! preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return null;
        }

        $token = trim((string) ($matches[1] ?? ''));
        return MobileSessionStore::looksLikeToken($token) ? $token : null;
    }
}
