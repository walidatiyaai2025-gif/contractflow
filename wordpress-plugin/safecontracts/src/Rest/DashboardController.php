<?php

declare(strict_types=1);

namespace SafeContracts\Rest;

use DomainException;
use InvalidArgumentException;
use SafeContracts\Admin\AdminReadRepository;
use SafeContracts\Finance\FinanceOverviewService;
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
        $access = self::canView();
        if ($access instanceof WP_Error) {
            return $access;
        }

        try {
            $filters = RequestGuard::strictDashboardFilters($request);
            $read = new AdminReadRepository();
            $kpis = $read->kpis($filters);

            // Legacy KPI money fields remain receivable-specific, but the
            // headline contract count must represent every contract visible in
            // the current authorization/filter scope (Customer + Supplier).
            // This fixes the 0.3.2 mobile dashboard under-count without mixing
            // AP and AR monetary authority.
            $contractFilters = $filters;
            if (! in_array((string) ($contractFilters['status'] ?? ''), ['draft', 'active', 'completed', 'cancelled'], true)) {
                $contractFilters['status'] = '';
            }
            $allContracts = array_values(array_filter(
                $read->contracts($contractFilters),
                static fn (array $row): bool => empty($row['is_archived'])
            ));
            $kpis['contract_count'] = (string) count($allContracts);

            return RequestGuard::response([
                'filters' => $filters,
                'kpis' => $kpis,
                'customers' => $read->customerOptions(),
                'contracts' => $read->contractOptions((int) ($filters['customer_id'] ?? 0)),
                'finance' => (new FinanceOverviewService())->overview($filters),
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
        return Router::canAccess();
    }
}
