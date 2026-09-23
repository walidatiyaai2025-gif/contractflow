<?php

declare(strict_types=1);

namespace SafeContracts\Auth;

use SafeContracts\Presence\PresenceService;

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

        $resolved = (new MobileSessionStore())->resolve($token);
        if ($resolved > 0) {
            PresenceService::touchMobile($resolved);
        }
        return $resolved;
    }

    public static function bearerToken(): ?string
    {
        $header = self::authorizationHeader();
        if ($header === '' || ! preg_match('/^Bearer\\s+(.+)$/i', $header, $matches)) {
            return null;
        }

        $token = trim((string) ($matches[1] ?? ''));
        return MobileSessionStore::looksLikeToken($token) ? $token : null;
    }

    private static function authorizationHeader(): string
    {
        foreach (['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION', 'Authorization'] as $key) {
            $value = $_SERVER[$key] ?? '';
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        foreach (['getallheaders', 'apache_request_headers'] as $reader) {
            if (! function_exists($reader)) {
                continue;
            }
            $headers = $reader();
            if (! is_array($headers)) {
                continue;
            }
            foreach ($headers as $name => $value) {
                if (! is_string($name) || strcasecmp($name, 'Authorization') !== 0) {
                    continue;
                }
                if (is_string($value) && trim($value) !== '') {
                    return trim($value);
                }
            }
        }

        return '';
    }
}
