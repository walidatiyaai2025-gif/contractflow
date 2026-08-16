<?php

declare(strict_types=1);

namespace SafeContracts\Rest;

use InvalidArgumentException;
use RuntimeException;

final class EnterpriseRateLimitStore
{
    private const TABLE_SUFFIX = 'safecontracts_esc_rate_limits';

    /**
     * Atomically increments a fixed-window bucket and returns its current state.
     *
     * @return array{allowed:bool,count:int,retry_after:int}
     */
    public function hit(string $bucketKey, int $limit, int $windowSeconds): array
    {
        if (preg_match('/^[a-f0-9]{64}$/', $bucketKey) !== 1) {
            throw new InvalidArgumentException('Rate-limit bucket keys must be SHA-256 hex digests.');
        }
        if ($limit < 1 || $windowSeconds < 1 || $windowSeconds > 86400) {
            throw new InvalidArgumentException('Rate-limit policy is invalid.');
        }

        global $wpdb;
        if (! is_object($wpdb)) {
            throw new RuntimeException('Enterprise rate limiting requires WordPress $wpdb.');
        }

        $table = $wpdb->prefix . self::TABLE_SUFFIX;
        $sql = $wpdb->prepare(
            "INSERT INTO {$table} (bucket_key, window_expires_at, request_count, updated_at)
             VALUES (%s, DATE_ADD(UTC_TIMESTAMP(), INTERVAL %d SECOND), 1, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE
                request_count = IF(window_expires_at <= UTC_TIMESTAMP(), 1, request_count + 1),
                window_expires_at = IF(window_expires_at <= UTC_TIMESTAMP(), DATE_ADD(UTC_TIMESTAMP(), INTERVAL %d SECOND), window_expires_at),
                updated_at = UTC_TIMESTAMP()",
            $bucketKey,
            $windowSeconds,
            $windowSeconds
        );

        if ($wpdb->query($sql) === false) {
            throw new RuntimeException('Enterprise rate-limit counter update failed.');
        }

        $readSql = $wpdb->prepare(
            "SELECT request_count,
                    GREATEST(1, TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), window_expires_at)) AS retry_after
             FROM {$table}
             WHERE bucket_key = %s
             LIMIT 1",
            $bucketKey
        );
        $rows = $wpdb->get_results($readSql, ARRAY_A);
        if (! is_array($rows) || $rows === [] || ! is_array($rows[0])) {
            throw new RuntimeException('Enterprise rate-limit counter could not be read after update.');
        }

        $count = max(1, (int) ($rows[0]['request_count'] ?? 1));
        $retryAfter = max(1, (int) ($rows[0]['retry_after'] ?? $windowSeconds));

        return [
            'allowed' => $count <= $limit,
            'count' => $count,
            'retry_after' => $retryAfter,
        ];
    }

    public function pruneExpired(int $limit = 200): int
    {
        $limit = max(1, min(1000, $limit));

        global $wpdb;
        if (! is_object($wpdb)) {
            return 0;
        }

        $table = $wpdb->prefix . self::TABLE_SUFFIX;
        $sql = $wpdb->prepare(
            "DELETE FROM {$table}
             WHERE window_expires_at <= UTC_TIMESTAMP()
             LIMIT %d",
            $limit
        );
        $deleted = $wpdb->query($sql);

        return $deleted === false ? 0 : max(0, (int) $deleted);
    }
}
