<?php

declare(strict_types=1);

namespace SafeContracts\Rest;

use DomainException;
use InvalidArgumentException;
use SafeContracts\Contracts\CounterpartyContractService;
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
        register_rest_route(Router::NAMESPACE, '/contracts', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'create'],
            'permission_callback' => [self::class, 'canCreate'],
        ]);
        register_rest_route(Router::NAMESPACE, '/contracts/(?P<id>\d+)/counterparty', [
            'methods' => 'PATCH',
            'callback' => [self::class, 'assign'],
            'permission_callback' => [self::class, 'canAssign'],
        ]);
    }

    public static function canCreate(): bool|WP_Error
    {
        return self::capability(
            Capabilities::CREATE_CONTRACTS,
            'safecontracts_counterparty_contract_create_forbidden',
            'You do not have permission to create SafeContracts contracts.'
        );
    }

    public static function canAssign(): bool|WP_Error
    {
        return self::capability(
            Capabilities::ASSIGN_CONTRACTS,
            'safecontracts_counterparty_contract_assign_forbidden',
            'You do not have permission to assign SafeContracts contract counterparties.'
        );
    }

    public static function create(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return self::guard(function () use ($request): WP_REST_Response {
            $body = $request->get_json_params();
            if (! is_array($body)) {
                throw new InvalidArgumentException('Contract creation requires a JSON object body.');
            }
            self::assertFields($body, [
                'contract_number',
                'counterparty_type',
                'counterparty_id',
                'currency_code',
                'accountant_user_id',
                'notes',
            ]);
            $id = (new CounterpartyContractService())->create($body);
            return ApiResponse::ok(['id' => $id, 'created' => true]);
        });
    }

    public static function assign(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return self::guard(function () use ($request): WP_REST_Response {
            $body = $request->get_json_params();
            if (! is_array($body)) {
                throw new InvalidArgumentException('Counterparty assignment requires a JSON object body.');
            }
            self::assertFields($body, ['counterparty_type', 'counterparty_id']);
            if (! array_key_exists('counterparty_type', $body) || ! array_key_exists('counterparty_id', $body)) {
                throw new InvalidArgumentException('Counterparty assignment requires counterparty_type and counterparty_id.');
            }
            $id = ApiRequest::routeId($request);
            (new CounterpartyContractService())->assign(
                $id,
                $body['counterparty_type'],
                $body['counterparty_id']
            );
            return ApiResponse::ok([
                'id' => $id,
                'counterparty_type' => strtolower(trim((string) $body['counterparty_type'])),
                'counterparty_id' => (int) $body['counterparty_id'],
                'updated' => true,
            ]);
        });
    }

    /** @param list<string> $allowed */
    private static function assertFields(array $body, array $allowed): void
    {
        foreach (array_keys($body) as $key) {
            if (! is_string($key) || ! in_array($key, $allowed, true)) {
                throw new InvalidArgumentException('Unsupported counterparty contract field.');
            }
        }
    }

    private static function capability(string $capability, string $code, string $message): bool|WP_Error
    {
        $access = Router::canAccess();
        if ($access !== true) {
            return $access;
        }
        return current_user_can($capability)
            ? true
            : RequestGuard::forbidden($code, __($message, 'safecontracts'));
    }

    private static function guard(callable $callback): WP_REST_Response|WP_Error
    {
        try {
            return $callback();
        } catch (InvalidArgumentException $error) {
            return RequestGuard::invalid($error, 'safecontracts_counterparty_contract_invalid');
        } catch (DomainException $error) {
            if (str_contains($error->getMessage(), 'financial obligations exist')) {
                return ApiResponse::error('safecontracts_counterparty_contract_conflict', $error->getMessage(), 409);
            }
            return RequestGuard::domain($error, 'safecontracts_counterparty_contract_forbidden');
        } catch (Throwable $error) {
            return RequestGuard::failure($error, 'safecontracts_counterparty_contract_failed');
        }
    }
}
