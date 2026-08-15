<?php

declare(strict_types=1);

namespace SafeContracts\Rest;

use DomainException;
use InvalidArgumentException;
use SafeContracts\Admin\AdminReadRepository;
use SafeContracts\Collections\CollectionRepository;
use SafeContracts\FollowUps\FollowUpService;
use SafeContracts\Payments\PaymentRepository;
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
            '/customers' => 'customers',
            '/contracts' => 'contracts',
            '/payments' => 'payments',
            '/collections' => 'collections',
            '/followups' => 'followUps',
            '/filters/contracts' => 'contractOptions',
        ] as $route => $callback) {
            register_rest_route(Router::NAMESPACE, $route, [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, $callback],
                'permission_callback' => [Router::class, 'canAccess'],
            ]);
        }

        foreach ([
            '/customers/(?P<id>\d+)' => 'customer',
            '/contracts/(?P<id>\d+)' => 'contract',
            '/payments/(?P<id>\d+)' => 'payment',
            '/collections/(?P<id>\d+)' => 'collection',
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
            $query = ApiRequest::listQuery($request);
            $rows = (new AdminReadRepository())->customers($query['filters']);
            $rows = array_map([self::class, 'customerView'], $rows);
            return self::page($rows, $query['page'], $query['per_page']);
        });
    }

    public static function customer(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return self::guard(function () use ($request): WP_REST_Response|WP_Error {
            $id = ApiRequest::routeId($request);
            $rows = (new AdminReadRepository())->customers(['customer_id' => $id]);
            if ($rows === []) {
                return ApiResponse::notFound('Customer');
            }
            return ApiResponse::ok(self::customerView($rows[0]));
        });
    }

    public static function contractOptions(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return self::guard(function () use ($request): WP_REST_Response {
            $customerId = ApiRequest::optionalCustomerId($request);
            $rows = (new AdminReadRepository())->contractOptions($customerId);
            return ApiResponse::ok($rows, [
                'scope' => ApiScope::mode(),
                'customer_id' => $customerId,
                'client_may_offer_all_option' => true,
            ]);
        });
    }

    public static function contracts(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return self::guard(function () use ($request): WP_REST_Response {
            $query = ApiRequest::listQuery($request);
            $rows = (new AdminReadRepository())->contracts($query['filters']);
            $rows = array_map([self::class, 'contractView'], $rows);
            return self::page($rows, $query['page'], $query['per_page']);
        });
    }

    public static function contract(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return self::guard(function () use ($request): WP_REST_Response|WP_Error {
            $id = ApiRequest::routeId($request);
            $rows = (new AdminReadRepository())->contracts(['contract_id' => $id]);
            if ($rows === []) {
                return ApiResponse::notFound('Contract');
            }
            return ApiResponse::ok(self::contractView($rows[0]));
        });
    }

    public static function payments(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return self::guard(function () use ($request): WP_REST_Response {
            $query = ApiRequest::listQuery($request);
            $rows = (new AdminReadRepository())->payments($query['filters']);
            $rows = array_map([self::class, 'paymentListView'], $rows);
            return self::page($rows, $query['page'], $query['per_page']);
        });
    }

    public static function payment(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return self::guard(function () use ($request): WP_REST_Response|WP_Error {
            $id = ApiRequest::routeId($request);
            $row = (new PaymentRepository())->find($id);
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
            $query = ApiRequest::listQuery($request);
            $rows = (new AdminReadRepository())->collections($query['filters']);
            $rows = array_map([self::class, 'collectionListView'], $rows);
            return self::page($rows, $query['page'], $query['per_page']);
        });
    }

    public static function collection(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return self::guard(function () use ($request): WP_REST_Response|WP_Error {
            $id = ApiRequest::routeId($request);
            $row = (new CollectionRepository())->find($id);
            if ($row === null) {
                return ApiResponse::notFound('Collection');
            }
            $payment = (new PaymentRepository())->find($row['payment_id']);
            if ($payment === null) {
                return ApiResponse::notFound('Payment');
            }
            ApiScope::assertAccountant($payment['accountant_user_id']);
            return ApiResponse::ok(self::collectionView($row));
        });
    }

    public static function followUps(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return self::guard(function () use ($request): WP_REST_Response {
            $query = ApiRequest::listQuery($request);
            $limit = min(500, $query['page'] * $query['per_page']);
            $rows = (new FollowUpService())->queue($limit);
            $rows = array_map([self::class, 'followUpQueueView'], $rows);
            return self::page($rows, $query['page'], $query['per_page']);
        });
    }

    public static function followUpHistory(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return self::guard(function () use ($request): WP_REST_Response {
            $paymentId = ApiRequest::routeId($request, 'payment_id');
            $query = ApiRequest::listQuery($request);
            $limit = min(500, $query['page'] * $query['per_page']);
            $rows = (new FollowUpService())->history($paymentId, $limit);
            $rows = array_map([self::class, 'followUpHistoryView'], $rows);
            return self::page($rows, $query['page'], $query['per_page']);
        });
    }

    private static function guard(callable $callback): WP_REST_Response|WP_Error
    {
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

    /** @param list<array<string,mixed>> $rows */
    private static function page(array $rows, int $page, int $perPage): WP_REST_Response
    {
        $offset = ($page - 1) * $perPage;
        $items = array_slice($rows, $offset, $perPage);
        return ApiResponse::ok($items, [
            'scope' => ApiScope::mode(),
            'page' => $page,
            'per_page' => $perPage,
            'returned' => count($items),
            'available_in_bounded_read' => count($rows),
            'has_more' => ($offset + count($items)) < count($rows),
        ]);
    }

    private static function customerView(array $row): array
    {
        return self::pick($row, ['id', 'internal_code', 'name', 'contact_name', 'email', 'phone', 'is_active']);
    }

    private static function contractView(array $row): array
    {
        return self::pick($row, ['id', 'contract_number', 'customer_id', 'customer_name', 'accountant_user_id', 'status', 'start_date', 'end_date', 'base_value', 'is_archived']);
    }

    private static function paymentListView(array $row): array
    {
        return self::pick($row, ['id', 'contract_id', 'contract_number', 'customer_id', 'customer_name', 'accountant_user_id', 'sequence_no', 'reference', 'due_date', 'expected_payment_date', 'original_amount', 'paid_amount', 'remaining_amount', 'status', 'contract_is_archived']);
    }

    private static function paymentView(array $row): array
    {
        return self::pick($row, ['id', 'contract_id', 'sequence_no', 'reference', 'due_date', 'expected_payment_date', 'original_amount', 'paid_amount', 'remaining_amount', 'status', 'accountant_user_id', 'contract_is_archived']);
    }

    private static function collectionListView(array $row): array
    {
        return self::pick($row, ['id', 'payment_id', 'amount', 'collection_date', 'payment_method_id', 'payment_method_name', 'reference', 'proof_media_id', 'created_by', 'created_at', 'payment_reference', 'sequence_no', 'due_date', 'payment_status', 'remaining_amount', 'contract_id', 'contract_number', 'accountant_user_id', 'customer_id', 'customer_name']);
    }

    private static function collectionView(array $row): array
    {
        return self::pick($row, ['id', 'payment_id', 'amount', 'collection_date', 'payment_method_id', 'reference', 'proof_media_id', 'created_by', 'created_at', 'updated_at']);
    }

    private static function followUpQueueView(array $row): array
    {
        return self::pick($row, ['payment_id', 'contract_id', 'customer_id', 'accountant_user_id', 'contract_status', 'reference', 'due_date', 'expected_payment_date', 'original_amount', 'paid_amount', 'remaining_amount', 'status', 'followup_state']);
    }

    private static function followUpHistoryView(array $row): array
    {
        return self::pick($row, ['id', 'payment_id', 'state', 'note', 'promised_date', 'deferred_until', 'created_by', 'created_at']);
    }

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
