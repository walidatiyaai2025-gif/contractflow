<?php

declare(strict_types=1);

namespace SafeContracts\Rest;

use SafeContracts\Tenancy\TenantDirectoryRepository;
use SafeContracts\Tenancy\TenantContextStore;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

final class TenantsController
{
    public static function register(): void
    {
        register_rest_route(Router::NAMESPACE, '/tenants', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'index'],
            'permission_callback' => [Router::class, 'canAccess'],
        ]);
    }

    public static function index(WP_REST_Request $request): mixed
    {
        $access = Router::canAccess();
        if ($access instanceof WP_Error) {
            return $access;
        }

        TenantContextStore::reset();
        $selectedTenantId = TenantRequestContext::resolve($request, false);
        if ($selectedTenantId instanceof WP_Error) {
            return $selectedTenantId;
        }

        $repository = new TenantDirectoryRepository();
        $tenants = $repository->forUser(get_current_user_id());

        return ApiResponse::ok([
            'items' => $tenants,
            'selected_tenant_id' => $selectedTenantId,
            'selection_header' => TenantRequestContext::HEADER,
        ]);
    }
}
