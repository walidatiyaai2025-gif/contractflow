<?php

declare(strict_types=1);

namespace SafeContracts\Rest;

use DomainException;
use InvalidArgumentException;
use SafeContracts\Contracts\ContractMoney;
use SafeContracts\Contracts\ContractService;
use SafeContracts\Roles\Capabilities;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class CounterpartyContractsController
{
    public static function register(): void
    {
        // Adds the modern mutation endpoint to the existing /contracts resource;
        // DataController remains the readable endpoint for the same resource.
        register_rest_route(Router::NAMESPACE, '/contracts', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'create'],
            'permission_callback' => [self::class, 'canCreate'],
        ]);
        register_rest_route(Router::NAMESPACE, '/contracts/(?P<id>\d+)/counterparty', [
            'methods' => 'PATCH',
            'callback' => [self::class, 'assignCounterparty'],
            'permission_callback' => [self::class, 'canAssign'],
        ]);
        register_rest_route(Router::NAMESPACE, '/contracts/(?P<id>\d+)/currency', [
            'methods' => 'PATCH',
            'callback' => [self::class, 'updateCurrency'],
            'permission_callback' => [self::class, 'canEdit'],
        ]);
    }

    public static function canCreate(): bool|WP_Error
    {
        return self::permission(Capabilities::CREATE_CONTRACTS, 'safecontracts_contract_create_forbidden', 'You do not have permission to create contracts.');
    }

    public static function canAssign(): bool|WP_Error
    {
        return self::permission(Capabilities::ASSIGN_CONTRACTS, 'safecontracts_contract_assign_forbidden', 'You do not have permission to assign contract counterparties.');
    }

    public static function canEdit(): bool|WP_Error
    {
        return self::permission(Capabilities::EDIT_CONTRACTS, 'safecontracts_contract_edit_forbidden', 'You do not have permission to edit contracts.');
    }

    public static function create(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return self::guard(function () use ($request): WP_REST_Response {
            $body = self::body($request, [
                'contract_number', 'counterparty_type', 'counterparty_id', 'currency_code',
                'accountant_user_id', 'notes', 'start_date', 'end_date', 'base_value',
            ]);
            foreach (['contract_number', 'counterparty_type', 'counterparty_id'] as $required) {
                if (! array_key_exists($required, $body)) {
                    throw new InvalidArgumentException("{$required} is required.");
                }
            }

            $service = new ContractService();
            $id = $service->create([
                'contract_number' => self::scalar($body['contract_number'], 'contract_number'),
                'counterparty_type' => self::scalar($body['counterparty_type'], 'counterparty_type'),
                'counterparty_id' => self::positiveInt($body['counterparty_id'], 'counterparty_id'),
                'currency_code' => array_key_exists('currency_code', $body)
                    ? self::scalar($body['currency_code'], 'currency_code')
                    : null,
                'accountant_user_id' => array_key_exists('accountant_user_id', $body)
                    ? self::nullablePositiveInt($body['accountant_user_id'], 'accountant_user_id')
                    : null,
                'notes' => array_key_exists('notes', $body) ? self::optionalText($body['notes'], 5000, 'notes') : '',
            ]);

            $hasStart = array_key_exists('start_date', $body);
            $hasEnd = array_key_exists('end_date', $body);
            if ($hasStart xor $hasEnd) {
                throw new InvalidArgumentException('Contract dates must supply start_date and end_date together.');
            }
            if ($hasStart && $hasEnd) {
                $service->updateDates($id, $body['start_date'], $body['end_date']);
            }
            if (array_key_exists('base_value', $body)) {
                $service->updateBaseValue($id, ContractMoney::normalizeNonNegative(self::scalar($body['base_value'], 'base_value')));
            }

            return RequestGuard::response(['id' => $id, 'created' => true], [], 201);
        });
    }

    public static function assignCounterparty(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return self::guard(function () use ($request): WP_REST_Response {
            $body = self::body($request, ['counterparty_type', 'counterparty_id']);
            foreach (['counterparty_type', 'counterparty_id'] as $required) {
                if (! array_key_exists($required, $body)) {
                    throw new InvalidArgumentException("{$required} is required.");
                }
            }
            $id = ApiRequest::routeId($request, 'id');
            (new ContractService())->assignCounterparty(
                $id,
                self::scalar($body['counterparty_type'], 'counterparty_type'),
                self::positiveInt($body['counterparty_id'], 'counterparty_id')
            );
            return RequestGuard::response(['id' => $id, 'updated' => true]);
        });
    }

    public static function updateCurrency(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return self::guard(function () use ($request): WP_REST_Response {
            $body = self::body($request, ['currency_code']);
            if (! array_key_exists('currency_code', $body)) {
                throw new InvalidArgumentException('currency_code is required.');
            }
            $id = ApiRequest::routeId($request, 'id');
            (new ContractService())->updateCurrency($id, self::scalar($body['currency_code'], 'currency_code'));
            return RequestGuard::response(['id' => $id, 'updated' => true]);
        });
    }

    private static function permission(string $capability, string $code, string $message): bool|WP_Error
    {
        $access = Router::canAccess();
        if ($access !== true) {
            return $access;
        }
        return current_user_can($capability) ? true : RequestGuard::forbidden($code, __($message, 'safecontracts'));
    }

    /** @param list<string> $allowed @return array<string,mixed> */
    private static function body(WP_REST_Request $request, array $allowed): array
    {
        $body = $request->get_json_params();
        if (! is_array($body)) {
            throw new InvalidArgumentException('Contract mutation requires a JSON object body.');
        }
        foreach (array_keys($body) as $field) {
            if (! is_string($field) || ! in_array($field, $allowed, true)) {
                throw new InvalidArgumentException('Unsupported contract mutation field.');
            }
        }
        return $body;
    }

    private static function scalar(mixed $value, string $field): string|int|float|bool
    {
        if ($value === null || is_array($value) || is_object($value)) {
            throw new InvalidArgumentException("{$field} must be a scalar value.");
        }
        return $value;
    }

    private static function positiveInt(mixed $value, string $field): int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }
        if (is_string($value) && preg_match('/^[1-9]\d*$/', trim($value))) {
            return (int) trim($value);
        }
        throw new InvalidArgumentException("{$field} must be a positive integer.");
    }

    private static function nullablePositiveInt(mixed $value, string $field): ?int
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }
        return self::positiveInt($value, $field);
    }

    private static function optionalText(mixed $value, int $max, string $field): string
    {
        if ($value === null) {
            return '';
        }
        $text = trim((string) self::scalar($value, $field));
        if (strlen($text) > $max) {
            throw new InvalidArgumentException("{$field} is too long.");
        }
        return $text;
    }

    private static function guard(callable $callback): WP_REST_Response|WP_Error
    {
        try {
            return $callback();
        } catch (InvalidArgumentException $error) {
            return RequestGuard::invalid($error, 'safecontracts_contract_counterparty_invalid');
        } catch (DomainException $error) {
            return RequestGuard::domain($error, 'safecontracts_contract_counterparty_forbidden');
        } catch (Throwable $error) {
            return RequestGuard::failure($error, 'safecontracts_contract_counterparty_failed');
        }
    }
}
