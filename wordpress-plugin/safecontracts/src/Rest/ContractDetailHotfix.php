<?php

declare(strict_types=1);

namespace SafeContracts\Rest;

use SafeContracts\Counterparties\CounterpartyReadRepository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * 0.3.25 contract-detail compatibility repair.
 *
 * The contracts list already uses the server-authoritative contractPage()
 * query, while the single-contract route still used the legacy contracts()
 * read path. Reuse the same bounded query for /contracts/{id} so the detail
 * screen and the list have identical scope/schema behavior.
 */
final class ContractDetailHotfix
{
    public static function register(): void
    {
        register_rest_route(Router::NAMESPACE, '/contracts/(?P<id>\d+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'show'],
            'permission_callback' => [Router::class, 'canAccess'],
        ], true);
    }

    public static function show(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $access = Permission::access();
        if ($access instanceof WP_Error) {
            return $access;
        }

        try {
            $id = ApiRequest::routeId($request);
            $page = (new CounterpartyReadRepository())->contractPage(
                ['contract_id' => $id],
                '',
                'id',
                'desc',
                1,
                1
            );

            $rows = $page['rows'] ?? [];
            if ($rows === []) {
                return ApiResponse::notFound('Contract');
            }

            $row = $rows[0];
            $fields = [
                'id',
                'contract_number',
                'customer_id',
                'customer_name',
                'supplier_id',
                'supplier_name',
                'counterparty_type',
                'counterparty_id',
                'counterparty_name',
                'financial_direction',
                'currency_code',
                'accountant_user_id',
                'status',
                'start_date',
                'end_date',
                'base_value',
                'is_archived',
            ];

            $data = [];
            foreach ($fields as $field) {
                if (array_key_exists($field, $row)) {
                    $data[$field] = $row[$field];
                }
            }

            return ApiResponse::ok($data, [
                'scope' => ApiScope::mode(),
                'contract_detail_source' => 'contract_page',
            ]);
        } catch (\InvalidArgumentException $error) {
            return ApiResponse::error('safecontracts_invalid_request', $error->getMessage(), 422);
        } catch (\DomainException $error) {
            return ApiResponse::error('safecontracts_scope_forbidden', $error->getMessage(), 403);
        } catch (\Throwable $error) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[SafeContracts contract detail] ' . $error->getMessage());
            }
            return ApiResponse::error(
                'safecontracts_contract_detail_error',
                __('Unable to load contract details.', 'safecontracts'),
                500
            );
        }
    }
}
