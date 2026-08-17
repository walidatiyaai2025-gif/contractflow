<?php

declare(strict_types=1);

namespace SafeContracts\Rest;

use DomainException;
use InvalidArgumentException;
use SafeContracts\Finance\FinanceOverviewService;
use SafeContracts\Finance\SettlementService;
use SafeContracts\Roles\Capabilities;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class FinanceController
{
    private const SETTLEMENT_FIELDS = [
        'payment_id', 'amount', 'transaction_date', 'payment_method_id',
        'reference', 'details', 'proof_media_id', 'idempotency_key',
    ];

    private const READ_FILTERS = [
        'direction', 'currency_code', 'counterparty_type', 'customer_id', 'supplier_id',
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
        register_rest_route(Router::NAMESPACE, '/finance/settlements', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'recordSettlement'],
            'permission_callback' => [self::class, 'canRecord'],
        ]);
        register_rest_route(Router::NAMESPACE, '/finance/obligations/(?P<id>\d+)/transactions', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'transactions'],
            'permission_callback' => [self::class, 'canViewFinance'],
        ]);
    }

    public static function canRecord(): bool|WP_Error
    {
        $access = Router::canAccess();
        if ($access !== true) {
            return $access;
        }
        return current_user_can(Capabilities::RECORD_PAYMENT) || current_user_can(Capabilities::RECORD_RECEIPT)
            ? true
            : RequestGuard::forbidden(
                'safecontracts_finance_settlement_forbidden',
                __('You do not have permission to record SafeContracts financial settlements.', 'safecontracts')
            );
    }

    public static function canViewFinance(): bool|WP_Error
    {
        $access = Router::canAccess();
        if ($access !== true) {
            return $access;
        }
        return current_user_can(Capabilities::VIEW_PAYABLES) || current_user_can(Capabilities::VIEW_RECEIVABLES)
            ? true
            : RequestGuard::forbidden(
                'safecontracts_finance_view_forbidden',
                __('You do not have permission to view SafeContracts finance.', 'safecontracts')
            );
    }

    public static function overview(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return self::guard(function () use ($request): WP_REST_Response {
            $filters = self::readFilters($request);
            return RequestGuard::response((new FinanceOverviewService())->overview($filters));
        });
    }

    public static function obligations(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return self::guard(function () use ($request): WP_REST_Response {
            $filters = self::readFilters($request);
            $rows = (new FinanceOverviewService())->obligations($filters);
            return RequestGuard::response($rows, ['count' => count($rows)]);
        });
    }

    public static function recordSettlement(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return self::guard(function () use ($request): WP_REST_Response {
            $input = self::body($request, self::SETTLEMENT_FIELDS);
            foreach (['payment_id', 'amount', 'transaction_date', 'idempotency_key'] as $required) {
                if (! array_key_exists($required, $input)) {
                    throw new InvalidArgumentException("{$required} is required.");
                }
            }
            $id = (new SettlementService())->record($input);
            return RequestGuard::response(['id' => $id, 'recorded' => true], [], 201);
        });
    }

    public static function transactions(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return self::guard(function () use ($request): WP_REST_Response {
            $paymentId = ApiRequest::routeId($request, 'id');
            $rows = (new SettlementService())->forPayment($paymentId);
            return RequestGuard::response($rows, ['count' => count($rows)]);
        });
    }

    /** @return array<string,mixed> */
    private static function readFilters(WP_REST_Request $request): array
    {
        return ApiAbuseGuard::safeParams($request, self::READ_FILTERS);
    }

    /** @param list<string> $allowed @return array<string,mixed> */
    private static function body(WP_REST_Request $request, array $allowed): array
    {
        $body = $request->get_json_params();
        if (! is_array($body)) {
            throw new InvalidArgumentException('Finance mutation requires a JSON object body.');
        }
        foreach (array_keys($body) as $field) {
            if (! is_string($field) || ! in_array($field, $allowed, true)) {
                throw new InvalidArgumentException('Unsupported finance mutation field.');
            }
        }
        return $body;
    }

    private static function guard(callable $callback): WP_REST_Response|WP_Error
    {
        try {
            return $callback();
        } catch (InvalidArgumentException $error) {
            return RequestGuard::invalid($error, 'safecontracts_finance_invalid');
        } catch (DomainException $error) {
            $message = strtolower($error->getMessage());
            $status = str_contains($message, 'remaining balance')
                || str_contains($message, 'idempotency')
                || str_contains($message, 'reconcile')
                ? 409
                : 403;
            return ApiResponse::error('safecontracts_finance_conflict', $error->getMessage(), $status);
        } catch (Throwable $error) {
            return RequestGuard::failure($error, 'safecontracts_finance_failed');
        }
    }
}
