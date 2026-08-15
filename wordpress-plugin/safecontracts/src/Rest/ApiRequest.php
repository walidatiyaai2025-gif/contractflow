<?php

declare(strict_types=1);

namespace SafeContracts\Rest;

use DateTimeImmutable;
use InvalidArgumentException;
use SafeContracts\Admin\DashboardFilters;
use SafeContracts\Contracts\ContractStatus;
use SafeContracts\Payments\PaymentStatus;
use WP_REST_Request;

final class ApiRequest
{
    /** @return array<string,mixed> */
    public static function params(WP_REST_Request $request): array
    {
        if (method_exists($request, 'get_params')) {
            $params = $request->get_params();
            return is_array($params) ? $params : [];
        }
        $params = $request->get_json_params();
        return is_array($params) ? $params : [];
    }

    public static function routeId(WP_REST_Request $request, string $key = 'id'): int
    {
        $value = method_exists($request, 'get_param') ? $request->get_param($key) : (self::params($request)[$key] ?? null);
        return self::positiveInt($value, $key);
    }

    /** @return array{filters:array{customer_id:int,contract_id:int,accountant_user_id:int,status:string,due_from:?string,due_to:?string},page:int,per_page:int} */
    public static function listQuery(WP_REST_Request $request): array
    {
        $params = self::params($request);
        foreach (['customer_id', 'contract_id', 'accountant_user_id'] as $key) {
            if (array_key_exists($key, $params) && $params[$key] !== '' && $params[$key] !== null) {
                self::nonNegativeInt($params[$key], $key);
            }
        }
        if (array_key_exists('status', $params) && $params['status'] !== '' && $params['status'] !== null) {
            if (! is_scalar($params['status']) || is_bool($params['status'])) {
                throw new InvalidArgumentException('status must be a string.');
            }
            $status = strtolower(trim((string) $params['status']));
            $allowed = [
                ContractStatus::DRAFT, ContractStatus::ACTIVE, ContractStatus::COMPLETED, ContractStatus::CANCELLED,
                PaymentStatus::UPCOMING, PaymentStatus::DUE_SOON, PaymentStatus::DUE, PaymentStatus::OVERDUE,
                PaymentStatus::PARTIALLY_PAID, PaymentStatus::PAID,
            ];
            if (! in_array($status, $allowed, true)) {
                throw new InvalidArgumentException('status is not supported.');
            }
        }
        foreach (['due_from', 'due_to'] as $key) {
            if (array_key_exists($key, $params) && $params[$key] !== '' && $params[$key] !== null) {
                self::date($params[$key], $key);
            }
        }

        $page = array_key_exists('page', $params) ? self::boundedInt($params['page'], 'page', 1, 5) : 1;
        $perPage = array_key_exists('per_page', $params) ? self::boundedInt($params['per_page'], 'per_page', 1, 100) : 50;

        return [
            'filters' => DashboardFilters::normalize($params),
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    public static function optionalCustomerId(WP_REST_Request $request): int
    {
        $params = self::params($request);
        if (! array_key_exists('customer_id', $params) || $params['customer_id'] === '' || $params['customer_id'] === null) {
            return 0;
        }
        return self::nonNegativeInt($params['customer_id'], 'customer_id');
    }

    private static function positiveInt(mixed $value, string $field): int
    {
        return self::boundedInt($value, $field, 1, PHP_INT_MAX);
    }

    private static function nonNegativeInt(mixed $value, string $field): int
    {
        return self::boundedInt($value, $field, 0, PHP_INT_MAX);
    }

    private static function boundedInt(mixed $value, string $field, int $min, int $max): int
    {
        if (is_int($value)) {
            $number = $value;
        } elseif (is_string($value) && preg_match('/^\d+$/', trim($value))) {
            $number = (int) trim($value);
        } else {
            throw new InvalidArgumentException("{$field} must be an integer.");
        }
        if ($number < $min || $number > $max) {
            throw new InvalidArgumentException("{$field} is outside the allowed range.");
        }
        return $number;
    }

    private static function date(mixed $value, string $field): string
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException("{$field} must use YYYY-MM-DD.");
        }
        $text = trim($value);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $text);
        if (! $date || $date->format('Y-m-d') !== $text) {
            throw new InvalidArgumentException("{$field} must use a valid YYYY-MM-DD date.");
        }
        return $text;
    }
}
