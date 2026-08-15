<?php

declare(strict_types=1);

namespace SafeContracts\Auth;

final class MobileSessionStore
{
    public const OPTION = 'safecontracts_mobile_sessions_v1';
    public const TOKEN_PREFIX = 'scm_';
    public const TTL_SECONDS = 2592000;
    private const MAX_SESSIONS_PER_USER = 5;
    private const MAX_TOTAL_SESSIONS = 5000;

    /** @return array{token:string,expires_at:int} */
    public function issue(int $userId): array
    {
        if ($userId <= 0) {
            throw new \InvalidArgumentException('Mobile session user ID must be positive.');
        }

        $sessions = $this->pruned($this->all());
        $now = time();
        $token = self::TOKEN_PREFIX . self::base64Url(random_bytes(32));
        $hash = hash('sha256', $token);
        $sessions[$hash] = [
            'user_id' => $userId,
            'created_at' => $now,
            'expires_at' => $now + self::TTL_SECONDS,
        ];
        $sessions = $this->limitForUser($sessions, $userId);
        $sessions = $this->limitTotal($sessions);
        update_option(self::OPTION, $sessions, false);

        return [
            'token' => $token,
            'expires_at' => $now + self::TTL_SECONDS,
        ];
    }

    public function resolve(string $token): int
    {
        if (! self::looksLikeToken($token)) {
            return 0;
        }
        $sessions = $this->all();
        $hash = hash('sha256', $token);
        $session = $sessions[$hash] ?? null;
        if (! is_array($session)) {
            return 0;
        }

        $expiresAt = (int) ($session['expires_at'] ?? 0);
        $userId = (int) ($session['user_id'] ?? 0);
        if ($expiresAt <= time() || $userId <= 0) {
            unset($sessions[$hash]);
            update_option(self::OPTION, $this->pruned($sessions), false);
            return 0;
        }
        return $userId;
    }

    public function revoke(string $token): void
    {
        if (! self::looksLikeToken($token)) {
            return;
        }
        $sessions = $this->all();
        $hash = hash('sha256', $token);
        if (isset($sessions[$hash])) {
            unset($sessions[$hash]);
            update_option(self::OPTION, $this->pruned($sessions), false);
        }
    }

    public function revokeUser(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }
        $sessions = array_filter(
            $this->all(),
            static fn (mixed $session): bool => ! is_array($session) || (int) ($session['user_id'] ?? 0) !== $userId
        );
        update_option(self::OPTION, $this->pruned($sessions), false);
    }

    public static function looksLikeToken(string $token): bool
    {
        return (bool) preg_match('/^scm_[A-Za-z0-9_-]{43}$/', $token);
    }

    /** @return array<string,array<string,int>> */
    private function all(): array
    {
        $stored = get_option(self::OPTION, []);
        return is_array($stored) ? $stored : [];
    }

    /** @param array<string,mixed> $sessions @return array<string,array<string,int>> */
    private function pruned(array $sessions): array
    {
        $now = time();
        $clean = [];
        foreach ($sessions as $hash => $session) {
            if (! is_string($hash) || ! preg_match('/^[a-f0-9]{64}$/', $hash) || ! is_array($session)) {
                continue;
            }
            $userId = (int) ($session['user_id'] ?? 0);
            $createdAt = (int) ($session['created_at'] ?? 0);
            $expiresAt = (int) ($session['expires_at'] ?? 0);
            if ($userId <= 0 || $createdAt <= 0 || $expiresAt <= $now) {
                continue;
            }
            $clean[$hash] = [
                'user_id' => $userId,
                'created_at' => $createdAt,
                'expires_at' => $expiresAt,
            ];
        }
        return $clean;
    }

    /** @param array<string,array<string,int>> $sessions @return array<string,array<string,int>> */
    private function limitForUser(array $sessions, int $userId): array
    {
        $owned = [];
        foreach ($sessions as $hash => $session) {
            if ((int) $session['user_id'] === $userId) {
                $owned[$hash] = $session;
            }
        }
        uasort($owned, static fn (array $a, array $b): int => $b['created_at'] <=> $a['created_at']);
        foreach (array_slice(array_keys($owned), self::MAX_SESSIONS_PER_USER) as $hash) {
            unset($sessions[$hash]);
        }
        return $sessions;
    }

    /** @param array<string,array<string,int>> $sessions @return array<string,array<string,int>> */
    private function limitTotal(array $sessions): array
    {
        if (count($sessions) <= self::MAX_TOTAL_SESSIONS) {
            return $sessions;
        }
        uasort($sessions, static fn (array $a, array $b): int => $b['created_at'] <=> $a['created_at']);
        return array_slice($sessions, 0, self::MAX_TOTAL_SESSIONS, true);
    }

    private static function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
