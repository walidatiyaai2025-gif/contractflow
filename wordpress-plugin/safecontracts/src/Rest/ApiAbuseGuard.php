<?php

declare(strict_types=1);

namespace SafeContracts\Rest;

use InvalidArgumentException;
use WP_REST_Request;

final class ApiAbuseGuard
{
    public const MAX_QUERY_PARAMS = 12;
    public const MAX_STRING_BYTES = 256;

    /**
     * @param list<string> $allowed
     * @return array<string,mixed>
     */
    public static function safeParams(WP_REST_Request $request, array $allowed): array
    {
        $params = ApiRequest::params($request);
        if (count($params) > self::MAX_QUERY_PARAMS) {
            throw new InvalidArgumentException('Too many request parameters.');
        }

        foreach ($params as $key => $value) {
            if (! is_string($key) || ! in_array($key, $allowed, true)) {
                throw new InvalidArgumentException('Unsupported request parameter.');
            }
            if ($value === null) {
                continue;
            }
            if (! is_scalar($value) || is_bool($value)) {
                throw new InvalidArgumentException("{$key} must be a scalar request value.");
            }
            if (is_string($value) && strlen($value) > self::MAX_STRING_BYTES) {
                throw new InvalidArgumentException("{$key} exceeds the allowed length.");
            }
        }

        return $params;
    }

    /** @param array<string,mixed> $params */
    public static function optionalString(array $params, string $key, string $default, int $maxBytes = 64): string
    {
        if (! array_key_exists($key, $params) || $params[$key] === null || $params[$key] === '') {
            return $default;
        }
        if (! is_string($params[$key])) {
            throw new InvalidArgumentException("{$key} must be a string.");
        }
        $value = trim($params[$key]);
        if ($value === '' || strlen($value) > $maxBytes) {
            throw new InvalidArgumentException("{$key} is invalid.");
        }
        return $value;
    }
}
