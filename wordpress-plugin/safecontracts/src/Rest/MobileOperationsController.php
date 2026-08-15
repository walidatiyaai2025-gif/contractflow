<?php

declare(strict_types=1);

namespace SafeContracts\Rest;

use DomainException;
use InvalidArgumentException;
use SafeContracts\Collections\CollectionService;
use SafeContracts\Contracts\ContractService;
use SafeContracts\FollowUps\FollowUpService;
use SafeContracts\Payments\PaymentService;
use SafeContracts\Roles\Capabilities;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class MobileOperationsController
{
    public static function register(): void
    {
        register_rest_route(Router::NAMESPACE, '/contracts/(?P<id>\d+)/light-edit', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'editContract'],
            'permission_callback' => [self::class, 'canEditContract'],
        ]);

        register_rest_route(Router::NAMESPACE, '/payments/(?P<id>\d+)/light-edit', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'editPayment'],
            'permission_callback' => [self::class, 'canEditPayment'],
        ]);

        register_rest_route(Router::NAMESPACE, '/collections', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'recordCollection'],
            'permission_callback' => [self::class, 'canRecordCollection'],
        ]);

        register_rest_route(Router::NAMESPACE, '/payments/(?P<id>\d+)/followups/action', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'recordFollowUp'],
            'permission_callback' => [self::class, 'canManageFollowUps'],
        ]);
    }

    public static function editContract(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $permission = self::canEditContract();
        if ($permission instanceof WP_Error) {
            return $permission;
        }

        try {
            $id = ApiRequest::routeId($request);
            $params = self::safeBody($request, ['id', 'contract_number', 'start_date', 'end_date']);
            $hasNumber = array_key_exists('contract_number', $params);
            $hasStart = array_key_exists('start_date', $params);
            $hasEnd = array_key_exists('end_date', $params);

            if (! $hasNumber && ! $hasStart && ! $hasEnd) {
                throw new InvalidArgumentException('A supported contract light-edit field is required.');
            }
            if ($hasStart !== $hasEnd) {
                throw new InvalidArgumentException('Contract start and end dates must be submitted together.');
            }
            if ($hasNumber && $hasStart) {
                throw new InvalidArgumentException('Contract number and contract dates must be edited in separate requests.');
            }

            $service = new ContractService();
            if ($hasNumber) {
                $service->edit($id, ['contract_number' => $params['contract_number']]);
                return ApiResponse::ok(['contract_id' => $id, 'updated' => ['contract_number']]);
            }

            $service->updateDates($id, $params['start_date'], $params['end_date']);
            return ApiResponse::ok(['contract_id' => $id, 'updated' => ['start_date', 'end_date']]);
        } catch (InvalidArgumentException $error) {
            return RequestGuard::invalid($error, 'safecontracts_contract_edit_invalid');
        } catch (DomainException $error) {
            return RequestGuard::domain($error, 'safecontracts_contract_edit_forbidden');
        } catch (Throwable $error) {
            return RequestGuard::failure($error, 'safecontracts_contract_edit_failed');
        }
    }

    public static function editPayment(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $permission = self::canEditPayment();
        if ($permission instanceof WP_Error) {
            return $permission;
        }

        try {
            $id = ApiRequest::routeId($request);
            $params = self::safeBody($request, ['id', 'expected_payment_date']);
            if (! array_key_exists('expected_payment_date', $params)) {
                throw new InvalidArgumentException('Expected payment date is required for a payment light edit.');
            }

            $service = new PaymentService();
            $payment = $service->find($id);
            $service->updateDates($id, $payment['due_date'], $params['expected_payment_date']);

            return ApiResponse::ok([
                'payment_id' => $id,
                'due_date' => $payment['due_date'],
                'expected_payment_date' => $params['expected_payment_date'] === '' ? null : $params['expected_payment_date'],
            ]);
        } catch (InvalidArgumentException $error) {
            return RequestGuard::invalid($error, 'safecontracts_payment_edit_invalid');
        } catch (DomainException $error) {
            return RequestGuard::domain($error, 'safecontracts_payment_edit_forbidden');
        } catch (Throwable $error) {
            return RequestGuard::failure($error, 'safecontracts_payment_edit_failed');
        }
    }

    public static function recordCollection(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $permission = self::canRecordCollection();
        if ($permission instanceof WP_Error) {
            return $permission;
        }

        try {
            $params = self::safeBody($request, [
                'payment_id', 'amount', 'collection_date', 'payment_method_id',
                'reference', 'details', 'proof_media_id',
            ]);
            $id = (new CollectionService())->record($params);
            return ApiResponse::ok(['collection_id' => $id], ['created' => true], 201);
        } catch (InvalidArgumentException $error) {
            return RequestGuard::invalid($error, 'safecontracts_collection_invalid');
        } catch (DomainException $error) {
            return RequestGuard::domain($error, 'safecontracts_collection_forbidden');
        } catch (Throwable $error) {
            return RequestGuard::failure($error, 'safecontracts_collection_failed');
        }
    }

    public static function recordFollowUp(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $permission = self::canManageFollowUps();
        if ($permission instanceof WP_Error) {
            return $permission;
        }

        try {
            $paymentId = ApiRequest::routeId($request);
            $params = self::safeBody($request, ['id', 'action', 'note', 'date']);
            $action = strtolower(trim((string) ($params['action'] ?? '')));
            $note = $params['note'] ?? null;
            $date = $params['date'] ?? null;
            $service = new FollowUpService();

            $id = match ($action) {
                'note' => $service->addNote($paymentId, $note),
                'promise' => $service->promiseToPay($paymentId, $date, $note),
                'issue' => $service->markIssue($paymentId, $note),
                'defer' => $service->defer($paymentId, $date, $note),
                'escalate' => $service->escalate($paymentId, $note),
                default => throw new InvalidArgumentException('Unsupported follow-up action.'),
            };

            return ApiResponse::ok(['followup_id' => $id, 'payment_id' => $paymentId], ['created' => true], 201);
        } catch (InvalidArgumentException $error) {
            return RequestGuard::invalid($error, 'safecontracts_followup_invalid');
        } catch (DomainException $error) {
            return RequestGuard::domain($error, 'safecontracts_followup_forbidden');
        } catch (Throwable $error) {
            return RequestGuard::failure($error, 'safecontracts_followup_failed');
        }
    }

    public static function canEditContract(): bool|WP_Error
    {
        return Permission::capability(Capabilities::EDIT_CONTRACTS, 'safecontracts_contract_edit_forbidden');
    }

    public static function canEditPayment(): bool|WP_Error
    {
        return Permission::capability(Capabilities::MANAGE_PAYMENTS, 'safecontracts_payment_edit_forbidden');
    }

    public static function canRecordCollection(): bool|WP_Error
    {
        return Permission::capability(Capabilities::MANAGE_COLLECTIONS, 'safecontracts_collection_forbidden');
    }

    public static function canManageFollowUps(): bool|WP_Error
    {
        return Permission::capability(Capabilities::MANAGE_FOLLOWUPS, 'safecontracts_followup_forbidden');
    }

    /** @param list<string> $allowed @return array<string,mixed> */
    private static function safeBody(WP_REST_Request $request, array $allowed): array
    {
        $params = ApiRequest::params($request);
        foreach ($params as $key => $value) {
            if (! is_string($key) || ! in_array($key, $allowed, true)) {
                throw new InvalidArgumentException('Unsupported mobile operation field.');
            }
            if (is_array($value) || is_object($value) || is_resource($value)) {
                throw new InvalidArgumentException("{$key} must be a scalar request value.");
            }
        }
        return $params;
    }
}
