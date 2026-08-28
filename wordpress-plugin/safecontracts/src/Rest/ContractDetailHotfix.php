<?php

declare(strict_types=1);

namespace SafeContracts\Rest;

use DomainException;
use InvalidArgumentException;
use SafeContracts\Counterparties\CounterpartyReadRepository;
use SafeContracts\Diagnostics\RuntimeInspector;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * 0.3.25 contract-detail compatibility repair with production diagnostics.
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

        $id = 0;
        try {
            $id = ApiRequest::routeId($request);
            RuntimeInspector::begin('rest.contract.detail', [
                'contract_id' => $id,
                'endpoint' => '/contracts/{id}',
            ]);
            RuntimeInspector::stage('contract_page.query');

            $page = (new CounterpartyReadRepository())->contractPage(
                ['contract_id' => $id],
                '',
                'id',
                'desc',
                1,
                1
            );

            RuntimeInspector::stage('contract_page.result', [
                'row_count' => is_array($page['rows'] ?? null) ? count($page['rows']) : 0,
                'total' => (int) ($page['total'] ?? 0),
            ]);
            $rows = $page['rows'] ?? [];
            if ($rows === []) {
                RuntimeInspector::finish();
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

            RuntimeInspector::stage('response.ready', [
                'returned_contract_id' => (int) ($data['id'] ?? 0),
            ]);
            RuntimeInspector::finish();
            return ApiResponse::ok($data, [
                'scope' => ApiScope::mode(),
                'contract_detail_source' => 'contract_page',
            ]);
        } catch (InvalidArgumentException $error) {
            $diagnosticId = RuntimeInspector::capture($error, ['contract_id' => $id]);
            RuntimeInspector::finish();
            return ApiResponse::error(
                'safecontracts_invalid_request',
                $error->getMessage(),
                422,
                ['diagnostic_id' => $diagnosticId]
            );
        } catch (DomainException $error) {
            $diagnosticId = RuntimeInspector::capture($error, ['contract_id' => $id]);
            RuntimeInspector::finish();
            return ApiResponse::error(
                'safecontracts_scope_forbidden',
                $error->getMessage(),
                403,
                ['diagnostic_id' => $diagnosticId]
            );
        } catch (Throwable $error) {
            $diagnosticId = RuntimeInspector::capture($error, ['contract_id' => $id]);
            RuntimeInspector::finish();
            return ApiResponse::error(
                'safecontracts_contract_detail_error',
                __('Unable to load contract details.', 'safecontracts'),
                500,
                ['diagnostic_id' => $diagnosticId]
            );
        }
    }
}
