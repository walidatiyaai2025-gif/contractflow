<?php

declare(strict_types=1);

namespace SafeContracts\Rest;

use DomainException;
use InvalidArgumentException;
use SafeContracts\Finance\FinanceOverviewService;
use SafeContracts\Finance\FinanceReadFilters;
use SafeContracts\Roles\Capabilities;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class FinanceController
{
    private const READ_FILTERS = [
        'direction', 'financial_direction', 'currency_code', 'counterparty_type', 'customer_id', 'supplier_id',
        'contract_id', 'counterparty_id', 'accountant_user_id', 'status',
        'due_from', 'due_to', 'aging_bucket', 'limit',
    ];

    public static function register(): void
    {
        register_rest_route(Router::NAMESPACE, '/finance/overview', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'overview'],
            'permission_callback' => [self::class, 'canViewFinance'],
        ]);
        register_rest_route(Router::NAMESPACE, '/finance/obligations', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'obligations'],
            'permission_callback' => [self::class, 'canViewFinance'],
        ]);
    }

    public static function canViewFinance(): bool|WP_Error
    {
        $access = Router::canAccess();
        if ($access !== true) {
            return $access;
        }
        foreach ([
            Capabilities::VIEW_PAYABLES,
            Capabilities::VIEW_RECEIVABLES,
            Capabilities::VIEW_FINANCE,
            Capabilities::MANAGE_FINANCE,
        ] as $capability) {
            if (current_user_can($capability)) {
                return true;
            }
        }
        return RequestGuard::forbidden(
            'safecontracts_finance_view_forbidden',
            __('You do not have permission to view SafeContracts finance.', 'safecontracts')
        );
    }

    public static function overview(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $access = self::canViewFinance();
        if ($access !== true) {
            return $access;
        }
        return self::guard(function () use ($request): WP_REST_Response {
            $filters = FinanceReadFilters::strict(self::requestParams($request));
            $overview = (new FinanceOverviewService())->overview($filters);
            $overview['aging'] = self::aliasAgingRows((array) ($overview['aging'] ?? []));
            $overview['work_queue_preview'] = self::aliasAgingRows((array) ($overview['work_queue_preview'] ?? []));
            return RequestGuard::response($overview, [
                'currency_safe' => true,
                'grouped_by' => ['financial_direction', 'currency_code'],
            ]);
        });
    }

    public static function obligations(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $access = self::canViewFinance();
        if ($access !== true) {
            return $access;
        }
        return self::guard(function () use ($request): WP_REST_Response {
            $filters = FinanceReadFilters::strict(self::requestParams($request));
            $rows = self::aliasAgingRows((new FinanceOverviewService())->obligations($filters));
            return RequestGuard::response($rows, [
                'count' => count($rows),
                'currency_safe' => true,
            ]);
        });
    }

    /** @return array<string,mixed> */
    private static function requestParams(WP_REST_Request $request): array
    {
        $params = ApiAbuseGuard::safeParams($request, self::READ_FILTERS);
        if (isset($params['aging_bucket']) && is_scalar($params['aging_bucket'])) {
            $params['aging_bucket'] = self::canonicalAging((string) $params['aging_bucket']);
        }
        return $params;
    }

    /** @param list<array<string,mixed>> $rows @return list<array<string,mixed>> */
    private static function aliasAgingRows(array $rows): array
    {
        return array_map(static function (array $row): array {
            if (isset($row['aging_bucket'])) {
                $row['aging_bucket'] = self::clientAging((string) $row['aging_bucket']);
            }
            return $row;
        }, $rows);
    }

    private static function canonicalAging(string $bucket): string
    {
        return match (strtolower(trim($bucket))) {
            '1-30' => '1_30',
            '31-60' => '31_60',
            '61-90' => '61_90',
            '90+' => '90_plus',
            default => strtolower(trim($bucket)),
        };
    }

    private static function clientAging(string $bucket): string
    {
        return match (strtolower(trim($bucket))) {
            '1_30' => '1-30',
            '31_60' => '31-60',
            '61_90' => '61-90',
            '90_plus' => '90+',
            default => strtolower(trim($bucket)),
        };
    }

    private static function guard(callable $callback): WP_REST_Response|WP_Error
    {
        try {
            return $callback();
        } catch (InvalidArgumentException $error) {
            return RequestGuard::invalid($error, 'safecontracts_finance_invalid');
        } catch (DomainException $error) {
            return RequestGuard::domain($error, 'safecontracts_finance_forbidden');
        } catch (Throwable $error) {
            return RequestGuard::failure($error, 'safecontracts_finance_failed');
        }
    }
}
