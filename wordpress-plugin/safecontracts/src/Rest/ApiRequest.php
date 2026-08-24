<?php

declare(strict_types=1);

namespace SafeContracts\Rest;

use DateTimeImmutable;
use InvalidArgumentException;
use SafeContracts\Admin\DashboardFilters;
use SafeContracts\Contracts\ContractStatus;
use SafeContracts\Contracts\Counterparty;
use SafeContracts\Payments\FinancialDirection;
use SafeContracts\Payments\PaymentStatus;
use WP_REST_Request;

final class ApiRequest
{
    private const MAX_LIST_OFFSET = 1000000;

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

    /** @return array<string,mixed> */
    public static function filters(WP_REST_Request $request): array
    {
        $params = self::params($request);
        foreach (['customer_id', 'counterparty_id', 'contract_id', 'accountant_user_id'] as $key) {
            if (array_key_exists($key, $params) && $params[$key] !== '' && $params[$key] !== null) {
                self::nonNegativeInt($params[$key], $key);
            }
        }
        self::optionalEnum($params, 'counterparty_type', [Counterparty::CUSTOMER, Counterparty::SUPPLIER]);
        self::optionalEnum($params, 'financial_direction', [FinancialDirection::RECEIVABLE, FinancialDirection::PAYABLE]);
        if (array_key_exists('currency_code', $params) && $params['currency_code'] !== '' && $params['currency_code'] !== null) {
            if (! is_string($params['currency_code'])) {
                throw new InvalidArgumentException('currency_code must be a three-letter string.');
            }
            $currency = strtoupper(trim($params['currency_code']));
            if (! preg_match('/^[A-Z]{3}$/', $currency)) {
                throw new InvalidArgumentException('currency_code must be a three-letter ISO-style code.');
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

        $rawDates = ['due_from' => null, 'due_to' => null];
        foreach (['due_from', 'due_to'] as $key) {
            if (array_key_exists($key, $params) && $params[$key] !== '' && $params[$key] !== null) {
                $rawDates[$key] = self::date($params[$key], $key);
            }
        }
        if ($rawDates['due_from'] !== null && $rawDates['due_to'] !== null && $rawDates['due_to'] < $rawDates['due_from']) {
            throw new InvalidArgumentException('due_to must not be earlier than due_from.');
        }

        return DashboardFilters::normalize($params);
    }

    /** @return array{filters:array<string,mixed>,page:int,per_page:int} */
    public static function listQuery(WP_REST_Request $request): array
    {
        $pagination = self::pagination($request);
        return [
            'filters' => self::filters($request),
            'page' => $pagination['page'],
            'per_page' => $pagination['per_page'],
        ];
    }

    /** @return array{page:int,per_page:int} */
    public static function pagination(WP_REST_Request $request): array
    {
        $params = self::params($request);
        $perPage = array_key_exists('per_page', $params)
            ? self::boundedInt($params['per_page'], 'per_page', 1, 100)
            : 50;
        $maxPage = intdiv(self::MAX_LIST_OFFSET, $perPage) + 1;
        $page = array_key_exists('page', $params)
            ? self::boundedInt($params['page'], 'page', 1, $maxPage)
            : 1;
        $offset = ($page - 1) * $perPage;
        if ($offset > self::MAX_LIST_OFFSET) {
            throw new InvalidArgumentException('page is outside the bounded server query window.');
        }
        return [
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

    /** @param list<string> $allowed */
    private static function optionalEnum(array $params, string $field, array $allowed): void
    {
        if (! array_key_exists($field, $params) || $params[$field] === '' || $params[$field] === null) {
            return;
        }
        if (! is_string($params[$field])) {
            throw new InvalidArgumentException("{$field} must be a string.");
        }
        $value = strtolower(trim((string) $params[$field]));
        if (! in_array($value, $allowed, true)) {
            throw new InvalidArgumentException("{$field} is not supported.");
        }
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
