<?php

declare(strict_types=1);

namespace SafeContracts\Rest;

use SafeContracts\Tenancy\CoreTenantEnforcement;
use WP_Error;
use WP_REST_Request;

final class CoreTenantRestGuard
{
    public static function register(): void
    {
        add_filter('rest_request_before_callbacks', [self::class, 'enforce'], 5, 3);
    }

    public static function enforce(mixed $response, mixed $handler, mixed $request): mixed
    {
        unset($handler);

        if (! CoreTenantEnforcement::isEnabled()) {
            return $response;
        }
        if (! $request instanceof WP_REST_Request || ! method_exists($request, 'get_route')) {
            return $response;
        }

        $route = (string) $request->get_route();
        if (! self::isCoreBusinessRoute($route)) {
            return $response;
        }

        $access = Permission::access();
        if ($access instanceof WP_Error) {
            return $access;
        }

        $tenantId = TenantRequestContext::resolve($request, true);
        return $tenantId instanceof WP_Error ? $tenantId : $response;
    }

    public static function isCoreBusinessRoute(string $route): bool
    {
        return preg_match(
            '#^/safecontracts/v1/(?:customers(?:/|$)|contracts(?:/|$)|payments(?:/|$)|collections(?:/|$)|followups(?:/|$)|filters/contracts(?:/|$)|dashboard(?:/|$)|reports/excel(?:/|$))#',
            $route
        ) === 1;
    }
}
