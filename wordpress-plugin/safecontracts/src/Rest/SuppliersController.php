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
        'internal_code', 'legal_name', 'trading_name', 'contact_name', 'phone', 'email', 'address',
        'country_code', 'registration_number', 'tax_number', 'default_currency', 'payment_terms', 'status', 'notes',
    ];

    public static function register(): void
    {
        register_rest_route(Router::NAMESPACE, '/suppliers', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'list'],
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
                'callback' => [self::class, 'get'],
                'permission_callback' => [self::class, 'canView'],
            ],
            [
                'methods' => 'PATCH',
                'callback' => [self::class, 'update'],
                'permission_callback' => [self::class, 'canEdit'],
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
            current_user_can(Capabilities::VIEW_SUPPLIERS)
                || current_user_can(Capabilities::VIEW_ALL)
                || current_user_can(Capabilities::MANAGE_REFERENCE_DATA),
            'safecontracts_supplier_view_forbidden',
            'You do not have permission to view SafeContracts suppliers.'
        );
    }

    public static function canCreate(): bool|WP_Error
    {
        return self::permission(
            current_user_can(Capabilities::CREATE_SUPPLIERS) || current_user_can(Capabilities::MANAGE_REFERENCE_DATA),
            'safecontracts_supplier_create_forbidden',
            'You do not have permission to create SafeContracts suppliers.'
        );
    }

    public static function canEdit(): bool|WP_Error
    {
        return self::permission(
            current_user_can(Capabilities::EDIT_SUPPLIERS) || current_user_can(Capabilities::MANAGE_REFERENCE_DATA),
            'safecontracts_supplier_edit_forbidden',
            'You do not have permission to edit SafeContracts suppliers.'
        );
    }

    public static function canArchive(): bool|WP_Error
    {
        return self::permission(
            current_user_can(Capabilities::ARCHIVE_SUPPLIERS),
            'safecontracts_supplier_archive_forbidden',
            'You do not have permission to archive SafeContracts suppliers.'
        );
    }

    public static function list(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return self::guard(function () use ($request): WP_REST_Response {
            $params = ApiAbuseGuard::safeParams($request, ['search', 'limit', 'include_archived']);
            $rows = (new SupplierService())->search(
                $params['search'] ?? '',
                isset($params['limit']) ? (int) $params['limit'] : 50,
                self::boolParam($params['include_archived'] ?? false)
            );
            return RequestGuard::response($rows, ['count' => count($rows)]);
        });
    }

    public static function get(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return self::guard(function () use ($request): WP_REST_Response {
            $supplier = (new SupplierService())->find(ApiRequest::routeId($request, 'id'));
            if ($supplier === null) {
                throw new InvalidArgumentException('Supplier was not found.');
            }
            return RequestGuard::response($supplier);
        });
    }

    public static function create(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return self::guard(function () use ($request): WP_REST_Response {
            $input = self::body($request);
            if (! array_key_exists('legal_name', $input)) {
                throw new InvalidArgumentException('Supplier legal name is required.');
            }
            $service = new SupplierService();
            $id = $service->save($input);
            $supplier = $service->find($id);
            if ($supplier === null) {
                throw new DomainException('Supplier was saved but could not be reloaded.');
            }
            return RequestGuard::response($supplier, ['created' => true], 201);
        });
    }

    public static function update(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return self::guard(function () use ($request): WP_REST_Response {
            $id = ApiRequest::routeId($request, 'id');
            $service = new SupplierService();
            $existing = $service->find($id);
            if ($existing === null) {
                throw new InvalidArgumentException('Supplier was not found.');
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
            return RequestGuard::response($supplier, ['updated' => true]);
        });
    }

    public static function archive(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return self::guard(function () use ($request): WP_REST_Response {
            $id = ApiRequest::routeId($request, 'id');
            (new SupplierService())->archive($id);
            return RequestGuard::response(['id' => $id, 'archived' => true]);
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
                throw new InvalidArgumentException('Unsupported supplier field.');
            }
        }
        return $body;
    }

    private static function boolParam(mixed $value): bool
    {
        if (is_bool($value)) return $value;
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes'], true);
    }

    private static function permission(bool $allowed, string $code, string $message): bool|WP_Error
    {
        $access = Router::canAccess();
        if ($access !== true) return $access;
        return $allowed ? true : RequestGuard::forbidden($code, __($message, 'safecontracts'));
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
