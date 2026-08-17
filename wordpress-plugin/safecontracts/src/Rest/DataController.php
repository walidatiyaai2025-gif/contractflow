<?php

declare(strict_types=1);

namespace SafeContracts\Rest;

use DomainException;
use InvalidArgumentException;
use SafeContracts\Admin\AdminReadRepository;
use SafeContracts\Collections\CollectionReadRepository;
use SafeContracts\Counterparties\CounterpartyReadRepository;
use SafeContracts\FollowUps\FollowUpService;
use SafeContracts\Payments\PaymentRepository;
use SafeContracts\Roles\Capabilities;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class DataController
{
    public static function register(): void
    {
        foreach ([
            '/customers' => 'customers', '/contracts' => 'contracts', '/payments' => 'payments',
            '/collections' => 'collections', '/followups' => 'followUps', '/filters/contracts' => 'contractOptions',
            '/finance/summary' => 'financeSummary',
        ] as $route => $callback) {
            register_rest_route(Router::NAMESPACE, $route, [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, $callback],
                'permission_callback' => [Router::class, 'canAccess'],
            ]);
        }
        foreach ([
            '/customers/(?P<id>\d+)' => 'customer', '/contracts/(?P<id>\d+)' => 'contract',
            '/payments/(?P<id>\d+)' => 'payment', '/collections/(?P<id>\d+)' => 'collection',
            '/payments/(?P<payment_id>\d+)/followups' => 'followUpHistory',
        ] as $route => $callback) {
            register_rest_route(Router::NAMESPACE, $route, [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, $callback],
                'permission_callback' => [Router::class, 'canAccess'],
            ]);
        }
    }

    public static function customers(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return self::guard(function () use ($request): WP_REST_Response {
            $query = ApiListQuery::parse($request, ['customer_id'], ['name', 'id'], 'name');
            $rows = array_map([self::class, 'customerView'], (new AdminReadRepository())->customers($query['filters']));
            return self::page($rows, $query);
        });
    }

    public static function customer(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return self::guard(function () use ($request): WP_REST_Response|WP_Error {
            $rows = (new AdminReadRepository())->customers(['customer_id' => ApiRequest::routeId($request)]);
            return $rows === [] ? ApiResponse::notFound('Customer') : ApiResponse::ok(self::customerView($rows[0]));
        });
    }

    public static function contractOptions(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return self::guard(function () use ($request): WP_REST_Response {
            ApiAbuseGuard::safeParams($request, ['customer_id']);
            $customerId = ApiRequest::optionalCustomerId($request);
            return ApiResponse::ok((new AdminReadRepository())->contractOptions($customerId), [
                'scope' => ApiScope::mode(), 'customer_id' => $customerId, 'client_may_offer_all_option' => true,
            ]);
        });
    }

    public static function contracts(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return self::guard(function () use ($request): WP_REST_Response {
            $query = ApiListQuery::parse(
                $request,
                ['customer_id', 'counterparty_type', 'counterparty_id', 'financial_direction', 'currency_code', 'contract_id', 'accountant_user_id', 'status'],
                ['id', 'contract_number', 'customer_name', 'counterparty_name', 'financial_direction', 'currency_code', 'status', 'start_date', 'end_date'],
                'id',
                'desc'
            );
            $rows = array_map([self::class, 'contractView'], (new CounterpartyReadRepository())->contracts($query['filters']));
            return self::page($rows, $query);
        });
    }

    public static function contract(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return self::guard(function () use ($request): WP_REST_Response|WP_Error {
            $rows = (new CounterpartyReadRepository())->contracts(['contract_id' => ApiRequest::routeId($request)]);
            return $rows === [] ? ApiResponse::notFound('Contract') : ApiResponse::ok(self::contractView($rows[0]));
        });
    }

    public static function payments(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return self::guard(function () use ($request): WP_REST_Response {
            $query = ApiListQuery::parse(
                $request,
                ['customer_id', 'counterparty_type', 'counterparty_id', 'financial_direction', 'currency_code', 'contract_id', 'accountant_user_id', 'status', 'due_from', 'due_to'],
                ['id', 'due_date', 'expected_payment_date', 'remaining_amount', 'financial_direction', 'currency_code', 'status', 'contract_number', 'counterparty_name'],
                'due_date'
            );
            $rows = array_map([self::class, 'paymentListView'], (new CounterpartyReadRepository())->payments($query['filters']));
            return self::page($rows, $query);
        });
    }

    public static function payment(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return self::guard(function () use ($request): WP_REST_Response|WP_Error {
            $row = (new PaymentRepository())->find(ApiRequest::routeId($request));
            if ($row === null) {
                return ApiResponse::notFound('Payment');
            }
            ApiScope::assertAccountant($row['accountant_user_id']);
            return ApiResponse::ok(self::paymentView($row));
        });
    }

    public static function collections(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return self::guard(function () use ($request): WP_REST_Response {
            $query = ApiListQuery::parse(
                $request,
                ['customer_id', 'counterparty_type', 'counterparty_id', 'financial_direction', 'currency_code', 'contract_id', 'accountant_user_id', 'status', 'due_from', 'due_to'],
                ['id', 'collection_date', 'amount', 'financial_direction', 'currency_code', 'due_date', 'remaining_amount', 'counterparty_name'],
                'collection_date',
                'desc'
            );
            $rows = array_map([self::class, 'collectionListView'], (new CounterpartyReadRepository())->settlements($query['filters']));
            return self::page($rows, $query);
        });
    }

    public static function collection(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return self::guard(function () use ($request): WP_REST_Response|WP_Error {
            $row = (new CollectionReadRepository())->find(ApiRequest::routeId($request));
            if ($row === null) {
                return ApiResponse::notFound('Collection');
            }
            ApiScope::assertAccountant($row['accountant_user_id']);
            return ApiResponse::ok(self::collectionView($row));
        });
    }

    public static function financeSummary(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return self::guard(function () use ($request): WP_REST_Response {
            if (! current_user_can(Capabilities::VIEW_FINANCE) && ! current_user_can(Capabilities::MANAGE_FINANCE)) {
                throw new DomainException('You do not have permission to view SafeContracts finance data.');
            }
            $query = ApiListQuery::parse(
                $request,
                ['customer_id', 'counterparty_type', 'counterparty_id', 'financial_direction', 'currency_code', 'contract_id', 'accountant_user_id', 'status', 'due_from', 'due_to'],
                ['financial_direction', 'currency_code'],
                'financial_direction'
            );
            $rows = (new CounterpartyReadRepository())->financialSummary($query['filters']);
            return ApiResponse::ok($rows, [
                'scope' => ApiScope::mode(),
                'grouped_by' => ['financial_direction', 'currency_code'],
                'currency_safe' => true,
            ]);
        });
    }

    public static function followUps(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return self::guard(function () use ($request): WP_REST_Response {
            $query = ApiListQuery::parse(
                $request,
                ['customer_id', 'contract_id', 'accountant_user_id', 'status', 'due_from', 'due_to'],
                ['payment_id', 'due_date', 'expected_payment_date', 'remaining_amount', 'status', 'followup_state'],
                'due_date'
            );
            $rows = self::filterFollowUps((new FollowUpService())->queue(ApiListQuery::BOUNDED_WINDOW), $query['filters']);
            return self::page(array_map([self::class, 'followUpQueueView'], $rows), $query);
        });
    }

    public static function followUpHistory(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return self::guard(function () use ($request): WP_REST_Response|WP_Error {
            $paymentId = ApiRequest::routeId($request, 'payment_id');
            $query = ApiListQuery::pagination(
                $request,
                ['id', 'created_at', 'promised_date', 'deferred_until'],
                'created_at',
                'desc',
                ['payment_id']
            );
            try {
                $rows = (new FollowUpService())->history($paymentId, ApiListQuery::BOUNDED_WINDOW);
            } catch (InvalidArgumentException $error) {
                if ($error->getMessage() === 'Follow-up payment was not found.') {
                    return ApiResponse::notFound('Payment');
                }
                throw $error;
            }
            return self::page(array_map([self::class, 'followUpHistoryView'], $rows), $query);
        });
    }

    private static function guard(callable $callback): WP_REST_Response|WP_Error
    {
        $access = Permission::access();
        if ($access instanceof WP_Error) {
            return $access;
        }
        try {
            return $callback();
        } catch (InvalidArgumentException $error) {
            return ApiResponse::error('safecontracts_invalid_request', $error->getMessage(), 422);
        } catch (DomainException $error) {
            return ApiResponse::error('safecontracts_scope_forbidden', $error->getMessage(), 403);
        } catch (Throwable $error) {
            unset($error);
            return ApiResponse::error('safecontracts_server_error', __('Unable to process the SafeContracts request.', 'safecontracts'), 500);
        }
    }

    /** @param list<array<string,mixed>> $rows @param array{page:int,per_page:int,sort:string,order:string} $query */
    private static function page(array $rows, array $query): WP_REST_Response
    {
        $rows = ApiListQuery::sortRows($rows, $query['sort'], $query['order']);
        $offset = ($query['page'] - 1) * $query['per_page'];
        $items = array_slice($rows, $offset, $query['per_page']);
        return ApiResponse::ok($items, [
            'scope' => ApiScope::mode(),
            'page' => $query['page'],
            'per_page' => $query['per_page'],
            'sort' => $query['sort'],
            'order' => $query['order'],
            'returned' => count($items),
            'available_in_bounded_read' => count($rows),
            'bounded_window' => ApiListQuery::BOUNDED_WINDOW,
            'has_more' => ($offset + count($items)) < count($rows),
        ]);
    }

    /** @param list<array<string,mixed>> $rows @param array<string,mixed> $filters @return list<array<string,mixed>> */
    private static function filterFollowUps(array $rows, array $filters): array
    {
        $paymentStatuses = ['upcoming', 'due_soon', 'due', 'overdue', 'partially_paid', 'paid'];
        return array_values(array_filter($rows, static function (array $row) use ($filters, $paymentStatuses): bool {
            if (($filters['customer_id'] ?? 0) > 0 && (int) ($row['customer_id'] ?? 0) !== (int) $filters['customer_id']) {
                return false;
            }
            if (($filters['contract_id'] ?? 0) > 0 && (int) ($row['contract_id'] ?? 0) !== (int) $filters['contract_id']) {
                return false;
            }
            if (($filters['accountant_user_id'] ?? 0) > 0 && current_user_can(Capabilities::VIEW_ALL)
                && (int) ($row['accountant_user_id'] ?? 0) !== (int) $filters['accountant_user_id']) {
                return false;
            }
            $status = (string) ($filters['status'] ?? '');
            if ($status !== '') {
                $actual = in_array($status, $paymentStatuses, true) ? (string) ($row['status'] ?? '') : (string) ($row['contract_status'] ?? '');
                if ($actual !== $status) {
                    return false;
                }
            }
            $due = (string) ($row['due_date'] ?? '');
            if (($filters['due_from'] ?? null) !== null && $due < (string) $filters['due_from']) {
                return false;
            }
            if (($filters['due_to'] ?? null) !== null && $due > (string) $filters['due_to']) {
                return false;
            }
            return true;
        }));
    }

    private static function customerView(array $row): array { return self::pick($row, ['id','internal_code','name','contact_name','email','phone','is_active']); }
    private static function contractView(array $row): array { return self::pick($row, ['id','contract_number','customer_id','customer_name','supplier_id','supplier_name','counterparty_type','counterparty_id','counterparty_name','financial_direction','currency_code','accountant_user_id','status','start_date','end_date','base_value','is_archived']); }
    private static function paymentListView(array $row): array { return self::pick($row, ['id','contract_id','contract_number','customer_id','customer_name','supplier_id','supplier_name','counterparty_type','counterparty_id','counterparty_name','accountant_user_id','financial_direction','currency_code','sequence_no','reference','due_date','expected_payment_date','original_amount','paid_amount','remaining_amount','status','contract_is_archived']); }
    private static function paymentView(array $row): array { return self::pick($row, ['id','contract_id','counterparty_type','counterparty_id','financial_direction','currency_code','sequence_no','reference','due_date','expected_payment_date','original_amount','paid_amount','remaining_amount','status','accountant_user_id','contract_is_archived']); }
    private static function collectionListView(array $row): array { return self::pick($row, ['id','payment_id','financial_direction','currency_code','amount','collection_date','payment_method_id','payment_method_name','reference','proof_media_id','created_by','created_at','payment_reference','sequence_no','due_date','payment_status','remaining_amount','contract_id','contract_number','accountant_user_id','customer_id','customer_name','supplier_id','supplier_name','counterparty_type','counterparty_id','counterparty_name']); }
    private static function collectionView(array $row): array { return self::pick($row, ['id','payment_id','contract_id','financial_direction','currency_code','amount','collection_date','payment_method_id','payment_method_name','reference','proof_media_id','created_by','created_at','updated_at']); }
    private static function followUpQueueView(array $row): array { return self::pick($row, ['payment_id','contract_id','customer_id','accountant_user_id','contract_status','reference','due_date','expected_payment_date','original_amount','paid_amount','remaining_amount','status','followup_state']); }
    private static function followUpHistoryView(array $row): array { return self::pick($row, ['id','payment_id','state','note','promised_date','deferred_until','created_by','created_at']); }

    /** @param list<string> $fields */
    private static function pick(array $row, array $fields): array
    {
        $result = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $row)) {
                $result[$field] = $row[$field];
            }
        }
        return $result;
    }
}
