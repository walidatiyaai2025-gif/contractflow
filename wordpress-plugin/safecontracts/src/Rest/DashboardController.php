<?php

declare(strict_types=1);

namespace SafeContracts\Rest;

use DomainException;
use InvalidArgumentException;
use SafeContracts\Admin\AdminReadRepository;
use SafeContracts\Roles\Capabilities;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class DashboardController
{
    public static function register(): void
    {
        register_rest_route(Router::NAMESPACE, '/dashboard', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'show'],
            'permission_callback' => [self::class, 'canView'],
        ]);
    }

    public static function show(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $filters = RequestGuard::dashboardFilters($request);
            $read = new AdminReadRepository();
            return RequestGuard::response([
                'filters' => $filters,
                'kpis' => $read->kpis($filters),
                'customers' => $read->customerOptions(),
                'contracts' => $read->contractOptions($filters['customer_id']),
            ]);
        } catch (InvalidArgumentException $error) {
            return RequestGuard::invalid($error, 'safecontracts_dashboard_invalid');
        } catch (DomainException $error) {
            return RequestGuard::domain($error, 'safecontracts_dashboard_forbidden');
        } catch (Throwable $error) {
            return RequestGuard::failure($error, 'safecontracts_dashboard_failed');
        }
    }

    public static function canView(): bool|WP_Error
    {
        $access = Router::canAccess();
        if ($access !== true) {
            return $access;
        }
        if (current_user_can(Capabilities::VIEW_ALL) || current_user_can(Capabilities::VIEW_ASSIGNED)) {
            return true;
        }
        return RequestGuard::forbidden(
            'safecontracts_dashboard_scope_forbidden',
            __('You do not have a SafeContracts dashboard data scope.', 'safecontracts')
        );
    }
}
