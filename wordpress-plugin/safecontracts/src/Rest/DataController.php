<?php

declare(strict_types=1);

namespace SafeContracts\Rest;

use DomainException;
use InvalidArgumentException;
use SafeContracts\Admin\AdminReadRepository;
use SafeContracts\Collections\CollectionReadRepository;
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
            $query = ApiRequest::listQuery($request, ['id','name','internal_code'], 'name', 'asc');
            return self::page(array_map([self::class, 'customerView'], (new AdminReadRepository())->customers($query['filters'])), $query);
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
            $customerId = ApiRequest::optionalCustomerId($request);
            return ApiResponse::ok((new AdminReadRepository())->contractOptions($customerId), [
                'scope' => ApiScope::mode(), 'customer_id' => $customerId, 'client_may_offer_all_option' => true,
            ]);
        });
    }

    public static function contracts(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return self::guard(function () use ($request): WP_REST_Response {
            $query = ApiRequest::listQuery($request, ['id','contract_number','customer_name','status','start_date','end_date','base_value'], 'id', 'desc');
            return self::page(array_map([self::class, 'contractView'], (new AdminReadRepository())->contracts($query['filters'])), $query);
        });
    }

    public static function contract(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return self::guard(function () use ($request): WP_REST_Response|WP_Error {
            $rows = (new AdminReadRepository())->contracts(['contract_id' => ApiRequest::routeId($request)]);
            return $rows === [] ? ApiResponse::notFound('Contract') : ApiResponse::ok(self::contractView($rows[0]));
        });
    }

    public static function payments(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return self::guard(function () use ($request): WP_REST_Response {
            $query = ApiRequest::listQuery($request, ['id','due_date','expected_payment_date','sequence_no','remaining_amount','status','customer_name','contract_number'], 'due_date', 'asc');
            return self::page(array_map([self::class, 'paymentListView'], (new AdminReadRepository())->payments($query['filters'])), $query);
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
            $query = ApiRequest::listQuery($request, ['id','collection_date','amount','customer_name','contract_number'], 'collection_date', 'desc');
            return self::page(array_map([self::class, 'collectionListView'], (new AdminReadRepository())->collections($query['filters'])), $query);
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

    public static function followUps(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return self::guard(function () use ($request): WP_REST_Response {
            $query = ApiRequest::listQuery($request, ['payment_id','due_date','remaining_amount','status','customer_id','contract_id'], 'due_date', 'asc');
            $rows = self::filterFollowUps((new FollowUpService())->queue(500), $query['filters']);
            return self::page(array_map([self::class, 'followUpQueueView'], $rows), $query);
        });
    }

    public static function followUpHistory(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return self::guard(function () use ($request): WP_REST_Response {
            $paymentId = ApiRequest::routeId($request, 'payment_id');
            $pagination = ApiRequest::pagination($request);
            $sort = ApiRequest::sort($request, ['id','created_at','state'], 'created_at', 'desc');
            $rows = (new FollowUpService())->history($paymentId, 500);
            return self::page(array_map([self::class, 'followUpHistoryView'], $rows), [
                'page' => $pagination['page'],
                'per_page' => $pagination['per_page'],
                'sort' => $sort['field'],
                'direction' => $sort['direction'],
            ]);
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

    /** @param list<array<string,mixed>> $rows @param array{page:int,per_page:int,sort:string,direction:string} $query */
    private static function page(array $rows, array $query): WP_REST_Response
    {
        $page = $query['page'];
        $perPage = $query['per_page'];
        $sort = $query['sort'];
        $direction = $query['direction'];
        $rows = self::sortRows($rows, $sort, $direction);
        $offset = ($page - 1) * $perPage;
        $items = array_slice($rows, $offset, $perPage);
        return ApiResponse::ok($items, [
            'scope' => ApiScope::mode(),
            'page' => $page,
            'per_page' => $perPage,
            'sort' => $sort,
            'direction' => $direction,
            'returned' => count($items),
            'available_in_bounded_read' => count($rows),
            'has_more' => ($offset + count($items)) < count($rows),
        ]);
    }

    /** @param list<array<string,mixed>> $rows @return list<array<string,mixed>> */
    private static function sortRows(array $rows, string $field, string $direction): array
    {
        usort($rows, static function (array $left, array $right) use ($field, $direction): int {
            $leftValue = $left[$field] ?? null;
            $rightValue = $right[$field] ?? null;
            if ($leftValue === null && $rightValue !== null) { return 1; }
            if ($rightValue === null && $leftValue !== null) { return -1; }

            $comparison = self::compareValues($leftValue, $rightValue);
            if ($comparison !== 0 && $direction === 'desc') {
                $comparison *= -1;
            }
            if ($comparison !== 0) {
                return $comparison;
            }

            foreach (['id','payment_id','contract_id','customer_id'] as $tieField) {
                if ($tieField === $field) { continue; }
                if (array_key_exists($tieField, $left) && array_key_exists($tieField, $right)) {
                    $tie = self::compareValues($left[$tieField], $right[$tieField]);
                    if ($tie !== 0) { return $tie; }
                }
            }
            return 0;
        });
        return array_values($rows);
    }

    private static function compareValues(mixed $left, mixed $right): int
    {
        if ($left === $right) { return 0; }
        if (is_numeric($left) && is_numeric($right)) {
            return (float) $left <=> (float) $right;
        }
        return strnatcasecmp((string) $left, (string) $right);
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
    private static function contractView(array $row): array { return self::pick($row, ['id','contract_number','customer_id','customer_name','accountant_user_id','status','start_date','end_date','base_value','is_archived']); }
    private static function paymentListView(array $row): array { return self::pick($row, ['id','contract_id','contract_number','customer_id','customer_name','accountant_user_id','sequence_no','reference','due_date','expected_payment_date','original_amount','paid_amount','remaining_amount','status','contract_is_archived']); }
    private static function paymentView(array $row): array { return self::pick($row, ['id','contract_id','sequence_no','reference','due_date','expected_payment_date','original_amount','paid_amount','remaining_amount','status','accountant_user_id','contract_is_archived']); }
    private static function collectionListView(array $row): array { return self::pick($row, ['id','payment_id','amount','collection_date','payment_method_id','payment_method_name','reference','proof_media_id','created_by','created_at','payment_reference','sequence_no','due_date','payment_status','remaining_amount','contract_id','contract_number','accountant_user_id','customer_id','customer_name']); }
    private static function collectionView(array $row): array { return self::pick($row, ['id','payment_id','contract_id','amount','collection_date','payment_method_id','payment_method_name','reference','proof_media_id','created_by','created_at','updated_at']); }
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
