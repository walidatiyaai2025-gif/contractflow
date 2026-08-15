<?php

declare(strict_types=1);

namespace SafeContracts\Rest;

use DomainException;
use InvalidArgumentException;
use SafeContracts\Contracts\ContractService;
use SafeContracts\Roles\Capabilities;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class ContractEditController
{
    public static function register(): void
    {
        register_rest_route(Router::NAMESPACE, '/contracts/(?P<id>\d+)/edit', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'edit'],
            'permission_callback' => [self::class, 'permission'],
        ]);
    }

    public static function permission(): bool|WP_Error
    {
        return Permission::capability(
            Capabilities::EDIT_CONTRACTS,
            'safecontracts_contract_edit_forbidden'
        );
    }

    public static function edit(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $permission = self::permission();
        if ($permission instanceof WP_Error) {
            return $permission;
        }

        try {
            $params = ApiAbuseGuard::safeParams($request, [
                'id', 'operation', 'contract_number', 'start_date', 'end_date', 'base_value', 'status',
            ]);
            $contractId = ApiRequest::routeId($request);
            $operation = ApiAbuseGuard::optionalString($params, 'operation', '', 32);
            $service = new ContractService();

            switch ($operation) {
                case 'contract_number':
                    self::assertShape($params, ['contract_number']);
                    $service->edit($contractId, ['contract_number' => $params['contract_number']]);
                    break;

                case 'dates':
                    self::assertShape($params, ['start_date', 'end_date']);
                    $service->updateDates($contractId, $params['start_date'], $params['end_date']);
                    break;

                case 'base_value':
                    self::assertShape($params, ['base_value']);
                    $service->updateBaseValue($contractId, $params['base_value']);
                    break;

                case 'status':
                    self::assertShape($params, ['status']);
                    if (! is_string($params['status'])) {
                        throw new InvalidArgumentException('status must be a string.');
                    }
                    $service->changeStatus($contractId, $params['status']);
                    break;

                default:
                    throw new InvalidArgumentException('Unsupported contract edit operation.');
            }

            return ApiResponse::ok([
                'contract_id' => $contractId,
                'operation' => $operation,
            ]);
        } catch (InvalidArgumentException $error) {
            if ($error->getMessage() === 'Contract was not found.') {
                return ApiResponse::notFound('Contract');
            }
            return ApiResponse::error(
                'safecontracts_invalid_contract_edit',
                $error->getMessage(),
                400
            );
        } catch (DomainException $error) {
            $message = $error->getMessage();
            if (str_contains($message, 'outside the current user data scope') ||
                str_contains($message, 'permission to edit contract')) {
                return ApiResponse::error(
                    'safecontracts_contract_edit_forbidden',
                    $message,
                    403
                );
            }
            return ApiResponse::error(
                'safecontracts_contract_edit_conflict',
                $message,
                409
            );
        } catch (Throwable) {
            return ApiResponse::error(
                'safecontracts_internal_error',
                __('SafeContracts could not edit this contract.', 'safecontracts'),
                500
            );
        }
    }

    /** @param array<string,mixed> $params @param list<string> $fields */
    private static function assertShape(array $params, array $fields): void
    {
        $allowed = ['id', 'operation', ...$fields];
        foreach (array_keys($params) as $key) {
            if (! in_array($key, $allowed, true)) {
                throw new InvalidArgumentException('Contract edit operation contains unrelated fields.');
            }
        }
        foreach ($fields as $field) {
            if (! array_key_exists($field, $params)) {
                throw new InvalidArgumentException("{$field} is required for this contract edit operation.");
            }
        }
    }
}
