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
    private const FIELDS = [
        'internal_code', 'name', 'legal_name', 'trading_name', 'contact_name', 'phone', 'email', 'address',
        'country_code', 'registration_number', 'tax_number', 'default_currency', 'payment_terms', 'status', 'notes',
        'is_active',
    ];

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
                'permission_callback' => [self::class, 'canArchive'],
            ],
        ]);
        register_rest_route(Router::NAMESPACE, '/suppliers/(?P<id>\d+)/archive', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'archive'],
            'permission_callback' => [self::class, 'canArchive'],
        ]);
    }

    public static function canView(): bool|WP_Error
    {
        return self::permission(
            [Capabilities::VIEW_SUPPLIERS, Capabilities::MANAGE_SUPPLIERS, Capabilities::VIEW_ALL, Capabilities::MANAGE_REFERENCE_DATA],
            'safecontracts_suppliers_view_forbidden',
            'You do not have permission to view SafeContracts suppliers.'
        );
    }

    public static function canCreate(): bool|WP_Error
    {
        return self::permission(
            [Capabilities::CREATE_SUPPLIERS, Capabilities::MANAGE_SUPPLIERS, Capabilities::MANAGE_REFERENCE_DATA],
            'safecontracts_suppliers_create_forbidden',
            'You do not have permission to create SafeContracts suppliers.'
        );
    }

    public static function canEdit(): bool|WP_Error
    {
        return self::permission(
            [Capabilities::EDIT_SUPPLIERS, Capabilities::MANAGE_SUPPLIERS, Capabilities::MANAGE_REFERENCE_DATA],
            'safecontracts_suppliers_edit_forbidden',
            'You do not have permission to edit SafeContracts suppliers.'
        );
    }

    public static function canArchive(): bool|WP_Error
    {
        return self::permission(
            [Capabilities::ARCHIVE_SUPPLIERS, Capabilities::MANAGE_SUPPLIERS, Capabilities::MANAGE_REFERENCE_DATA],
            'safecontracts_suppliers_archive_forbidden',
            'You do not have permission to archive SafeContracts suppliers.'
        );
    }

    public static function index(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return self::guard(function () use ($request): WP_REST_Response {
            $params = ApiAbuseGuard::safeParams($request, ['search', 'limit', 'include_archived']);
            $rows = (new SupplierService())->search(
                $params['search'] ?? '',
                isset($params['limit']) ? (int) $params['limit'] : 100,
                self::boolParam($params['include_archived'] ?? false)
            );
            return ApiResponse::ok($rows, [
                'returned' => count($rows),
                'bounded_window' => min(500, max(1, (int) ($params['limit'] ?? 100))),
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
            $input = self::body($request);
            if (! array_key_exists('legal_name', $input) && ! array_key_exists('name', $input)) {
                throw new InvalidArgumentException('Supplier legal name is required.');
            }
            $service = new SupplierService();
            $id = $service->save($input);
            $supplier = $service->find($id);
            if ($supplier === null) {
                throw new DomainException('Supplier was saved but could not be reloaded.');
            }
            return ApiResponse::ok($supplier, ['created' => true]);
        });
    }

    public static function update(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return self::guard(function () use ($request): WP_REST_Response|WP_Error {
            $id = ApiRequest::routeId($request);
            $service = new SupplierService();
            $existing = $service->find($id);
            if ($existing === null) {
                return ApiResponse::notFound('Supplier');
            }
            $changes = self::body($request);
            if ($changes === []) {
                throw new InvalidArgumentException('At least one supplier field is required.');
            }
            $input = array_intersect_key($existing, array_flip(self::FIELDS));
            $input = [...$input, ...$changes, 'id' => $id];
            $service->save($input);
            $supplier = $service->find($id);
            if ($supplier === null) {
                throw new DomainException('Supplier was updated but could not be reloaded.');
            }
            return ApiResponse::ok($supplier, ['updated' => true]);
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
        foreach (array_keys($body) as $field) {
            if (! is_string($field) || ! in_array($field, self::FIELDS, true)) {
                throw new InvalidArgumentException('Unsupported supplier mutation field.');
            }
        }
        return $body;
    }

    private static function boolParam(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes'], true);
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
