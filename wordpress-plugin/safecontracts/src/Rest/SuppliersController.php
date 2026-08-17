<?php

declare(strict_types=1);

namespace SafeContracts\Rest;

use DomainException;
use InvalidArgumentException;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Suppliers\SupplierService;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class SuppliersController
{
    public static function register(): void
    {
        register_rest_route(Router::NAMESPACE, '/suppliers', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'index'],
                'permission_callback' => [self::class, 'canView'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'create'],
                'permission_callback' => [self::class, 'canCreate'],
            ],
        ]);
        register_rest_route(Router::NAMESPACE, '/suppliers/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'show'],
                'permission_callback' => [self::class, 'canView'],
            ],
            [
                'methods' => 'PATCH',
                'callback' => [self::class, 'update'],
                'permission_callback' => [self::class, 'canEdit'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [self::class, 'archive'],
                'permission_callback' => [self::class, 'canEdit'],
            ],
        ]);
    }

    public static function canView(): bool|WP_Error
    {
        return self::permission(
            [Capabilities::VIEW_SUPPLIERS, Capabilities::MANAGE_SUPPLIERS],
            'safecontracts_suppliers_view_forbidden',
            'You do not have permission to view SafeContracts suppliers.'
        );
    }

    public static function canCreate(): bool|WP_Error
    {
        return self::permission(
            [Capabilities::CREATE_SUPPLIERS, Capabilities::MANAGE_SUPPLIERS],
            'safecontracts_suppliers_create_forbidden',
            'You do not have permission to create SafeContracts suppliers.'
        );
    }

    public static function canEdit(): bool|WP_Error
    {
        return self::permission(
            [Capabilities::EDIT_SUPPLIERS, Capabilities::MANAGE_SUPPLIERS],
            'safecontracts_suppliers_edit_forbidden',
            'You do not have permission to edit SafeContracts suppliers.'
        );
    }

    public static function index(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        unset($request);
        return self::guard(function (): WP_REST_Response {
            $rows = (new SupplierService())->active(500);
            return ApiResponse::ok($rows, [
                'returned' => count($rows),
                'bounded_window' => 500,
            ]);
        });
    }

    public static function show(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return self::guard(function () use ($request): WP_REST_Response|WP_Error {
            $row = (new SupplierService())->find(ApiRequest::routeId($request));
            return $row === null ? ApiResponse::notFound('Supplier') : ApiResponse::ok($row);
        });
    }

    public static function create(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return self::guard(function () use ($request): WP_REST_Response {
            $body = self::body($request);
            unset($body['id']);
            $id = (new SupplierService())->save($body);
            return ApiResponse::ok(['id' => $id, 'created' => true]);
        });
    }

    public static function update(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return self::guard(function () use ($request): WP_REST_Response {
            $body = self::body($request);
            $body['id'] = ApiRequest::routeId($request);
            $id = (new SupplierService())->save($body);
            return ApiResponse::ok(['id' => $id, 'updated' => true]);
        });
    }

    public static function archive(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return self::guard(function () use ($request): WP_REST_Response {
            $id = ApiRequest::routeId($request);
            (new SupplierService())->archive($id);
            return ApiResponse::ok(['id' => $id, 'archived' => true]);
        });
    }

    /** @return array<string,mixed> */
    private static function body(WP_REST_Request $request): array
    {
        $body = $request->get_json_params();
        if (! is_array($body)) {
            throw new InvalidArgumentException('Supplier mutation requires a JSON object body.');
        }
        $allowed = ['id', 'internal_code', 'name', 'contact_name', 'email', 'phone', 'notes', 'is_active'];
        foreach (array_keys($body) as $key) {
            if (! is_string($key) || ! in_array($key, $allowed, true)) {
                throw new InvalidArgumentException('Unsupported supplier mutation field.');
            }
        }
        return $body;
    }

    /** @param list<string> $capabilities */
    private static function permission(array $capabilities, string $code, string $message): bool|WP_Error
    {
        $access = Router::canAccess();
        if ($access !== true) {
            return $access;
        }
        foreach ($capabilities as $capability) {
            if (current_user_can($capability)) {
                return true;
            }
        }
        return RequestGuard::forbidden($code, __($message, 'safecontracts'));
    }

    private static function guard(callable $callback): WP_REST_Response|WP_Error
    {
        try {
            return $callback();
        } catch (InvalidArgumentException $error) {
            return RequestGuard::invalid($error, 'safecontracts_supplier_invalid');
        } catch (DomainException $error) {
            return RequestGuard::domain($error, 'safecontracts_supplier_forbidden');
        } catch (Throwable $error) {
            return RequestGuard::failure($error, 'safecontracts_supplier_failed');
        }
    }
}
