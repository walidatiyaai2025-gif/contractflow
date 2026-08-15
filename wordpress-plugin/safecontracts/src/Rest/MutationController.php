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

final class MutationController
{
    public static function register(): void
    {
        register_rest_route(Router::NAMESPACE, '/contracts/(?P<id>\\d+)/light', [
            'methods' => 'PATCH',
            'callback' => [self::class, 'editContract'],
            'permission_callback' => [self::class, 'canEditContracts'],
        ]);
        register_rest_route(Router::NAMESPACE, '/payments/(?P<id>\\d+)/expected-date', [
            'methods' => 'PATCH',
            'callback' => [self::class, 'editPaymentExpectedDate'],
            'permission_callback' => [self::class, 'canManagePayments'],
        ]);
        register_rest_route(Router::NAMESPACE, '/collections/record', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'recordCollection'],
            'permission_callback' => [self::class, 'canManageCollections'],
        ]);
        register_rest_route(Router::NAMESPACE, '/payments/(?P<payment_id>\\d+)/followups/record', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'recordFollowUp'],
            'permission_callback' => [self::class, 'canManageFollowUps'],
        ]);
    }

    public static function editContract(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $permission = self::canEditContracts();
        if ($permission instanceof WP_Error) {
            return $permission;
        }

        try {
            $contractId = self::routeId($request, 'id');
            $input = self::body($request, ['id', 'contract_number', 'start_date', 'end_date']);
            self::assertBodyRouteId($input, 'id', $contractId);
            unset($input['id']);

            $hasNumber = array_key_exists('contract_number', $input);
            $hasStart = array_key_exists('start_date', $input);
            $hasEnd = array_key_exists('end_date', $input);
            if (! $hasNumber && ! $hasStart && ! $hasEnd) {
                throw new InvalidArgumentException('At least one supported contract field is required.');
            }
            if ($hasStart xor $hasEnd) {
                throw new InvalidArgumentException('Contract light date edits require both start_date and end_date.');
            }

            $number = null;
            if ($hasNumber) {
                $number = trim((string) self::scalar($input['contract_number'], 'contract_number'));
                if ($number === '' || strlen($number) > 100) {
                    throw new InvalidArgumentException('contract_number must contain 1 to 100 characters.');
                }
            }
            $start = $hasStart ? self::nullableDate($input['start_date'], 'start_date') : null;
            $end = $hasEnd ? self::nullableDate($input['end_date'], 'end_date') : null;
            if ($hasStart && $hasEnd && $start !== null && $end !== null && $end < $start) {
                throw new InvalidArgumentException('Contract end date cannot precede start date.');
            }

            $service = new ContractService();
            if ($hasNumber) {
                $service->edit($contractId, ['contract_number' => $number]);
            }
            if ($hasStart && $hasEnd) {
                $service->updateDates($contractId, $start, $end);
            }

            return RequestGuard::response([
                'id' => $contractId,
                'updated' => true,
                'fields' => array_values(array_keys($input)),
            ]);
        } catch (InvalidArgumentException $error) {
            return RequestGuard::invalid($error, 'safecontracts_contract_light_edit_invalid');
        } catch (DomainException $error) {
            return self::domainMutationError($error, 'safecontracts_contract_light_edit_forbidden', 'safecontracts_contract_light_edit_conflict');
        } catch (Throwable $error) {
            return RequestGuard::failure($error, 'safecontracts_contract_light_edit_failed');
        }
    }

    public static function editPaymentExpectedDate(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $permission = self::canManagePayments();
        if ($permission instanceof WP_Error) {
            return $permission;
        }

        try {
            $paymentId = self::routeId($request, 'id');
            $input = self::body($request, ['id', 'expected_payment_date']);
            self::assertBodyRouteId($input, 'id', $paymentId);
            unset($input['id']);
            if (! array_key_exists('expected_payment_date', $input)) {
                throw new InvalidArgumentException('expected_payment_date is required.');
            }

            $service = new PaymentService();
            $payment = $service->find($paymentId);
            $expected = self::nullableDate($input['expected_payment_date'], 'expected_payment_date');
            $service->updateDates($paymentId, $payment['due_date'], $expected);

            return RequestGuard::response([
                'id' => $paymentId,
                'updated' => true,
                'expected_payment_date' => $expected,
            ]);
        } catch (InvalidArgumentException $error) {
            return RequestGuard::invalid($error, 'safecontracts_payment_light_edit_invalid');
        } catch (DomainException $error) {
            return self::domainMutationError($error, 'safecontracts_payment_light_edit_forbidden', 'safecontracts_payment_light_edit_conflict');
        } catch (Throwable $error) {
            return RequestGuard::failure($error, 'safecontracts_payment_light_edit_failed');
        }
    }

    public static function recordCollection(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $permission = self::canManageCollections();
        if ($permission instanceof WP_Error) {
            return $permission;
        }

        try {
            $input = self::body($request, [
                'payment_id', 'amount', 'collection_date', 'payment_method_id', 'reference', 'proof_media_id',
            ]);
            foreach (['payment_id', 'amount', 'collection_date', 'payment_method_id'] as $required) {
                if (! array_key_exists($required, $input)) {
                    throw new InvalidArgumentException("{$required} is required.");
                }
            }

            $safeInput = [];
            foreach ($input as $field => $value) {
                $safeInput[$field] = self::nullableScalar($value, $field);
            }
            $collectionId = (new CollectionService())->record($safeInput);

            return RequestGuard::response([
                'id' => $collectionId,
                'payment_id' => (int) $safeInput['payment_id'],
                'recorded' => true,
            ], [], 201);
        } catch (InvalidArgumentException $error) {
            return RequestGuard::invalid($error, 'safecontracts_collection_record_invalid');
        } catch (DomainException $error) {
            return self::domainMutationError($error, 'safecontracts_collection_record_forbidden', 'safecontracts_collection_record_conflict');
        } catch (Throwable $error) {
            return RequestGuard::failure($error, 'safecontracts_collection_record_failed');
        }
    }

    public static function recordFollowUp(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $permission = self::canManageFollowUps();
        if ($permission instanceof WP_Error) {
            return $permission;
        }

        try {
            $paymentId = self::routeId($request, 'payment_id');
            $input = self::body($request, [
                'payment_id', 'operation', 'note', 'promised_date', 'deferred_until',
            ]);
            self::assertBodyRouteId($input, 'payment_id', $paymentId);
            unset($input['payment_id']);
            if (! array_key_exists('operation', $input)) {
                throw new InvalidArgumentException('Follow-up operation is required.');
            }

            $operation = strtolower(trim((string) self::scalar($input['operation'], 'operation')));
            $note = array_key_exists('note', $input)
                ? self::nullableScalar($input['note'], 'note')
                : null;
            $promisedDate = array_key_exists('promised_date', $input)
                ? self::nullableDate($input['promised_date'], 'promised_date')
                : null;
            $deferredUntil = array_key_exists('deferred_until', $input)
                ? self::nullableDate($input['deferred_until'], 'deferred_until')
                : null;

            $service = new FollowUpService();
            $followUpId = match ($operation) {
                'note' => self::followUpNoDates($promisedDate, $deferredUntil, fn (): int => $service->addNote($paymentId, $note)),
                'promise' => self::followUpPromise($promisedDate, $deferredUntil, fn (mixed $date): int => $service->promiseToPay($paymentId, $date, $note)),
                'issue' => self::followUpNoDates($promisedDate, $deferredUntil, fn (): int => $service->markIssue($paymentId, $note)),
                'defer' => self::followUpDeferred($promisedDate, $deferredUntil, fn (mixed $date): int => $service->defer($paymentId, $date, $note)),
                'escalate' => self::followUpNoDates($promisedDate, $deferredUntil, fn (): int => $service->escalate($paymentId, $note)),
                default => throw new InvalidArgumentException('Follow-up operation is not supported.'),
            };

            return RequestGuard::response([
                'id' => $followUpId,
                'payment_id' => $paymentId,
                'operation' => $operation,
                'recorded' => true,
            ], [], 201);
        } catch (InvalidArgumentException $error) {
            return RequestGuard::invalid($error, 'safecontracts_followup_record_invalid');
        } catch (DomainException $error) {
            return self::domainMutationError($error, 'safecontracts_followup_record_forbidden', 'safecontracts_followup_record_conflict');
        } catch (Throwable $error) {
            return RequestGuard::failure($error, 'safecontracts_followup_record_failed');
        }
    }

    public static function canEditContracts(): bool|WP_Error
    {
        return self::can(Capabilities::EDIT_CONTRACTS, 'safecontracts_contract_light_edit_forbidden', 'You do not have permission to edit SafeContracts contracts.');
    }

    public static function canManagePayments(): bool|WP_Error
    {
        return self::can(Capabilities::MANAGE_PAYMENTS, 'safecontracts_payment_light_edit_forbidden', 'You do not have permission to manage SafeContracts payments.');
    }

    public static function canManageCollections(): bool|WP_Error
    {
        return self::can(Capabilities::MANAGE_COLLECTIONS, 'safecontracts_collection_record_forbidden', 'You do not have permission to record SafeContracts collections.');
    }

    public static function canManageFollowUps(): bool|WP_Error
    {
        return self::can(Capabilities::MANAGE_FOLLOWUPS, 'safecontracts_followup_record_forbidden', 'You do not have permission to manage SafeContracts follow-up.');
    }

    private static function can(string $capability, string $code, string $message): bool|WP_Error
    {
        $access = Router::canAccess();
        if ($access !== true) {
            return $access;
        }
        if (current_user_can($capability)) {
            return true;
        }
        return RequestGuard::forbidden($code, __($message, 'safecontracts'));
    }

    /** @param list<string> $allowed @return array<string,mixed> */
    private static function body(WP_REST_Request $request, array $allowed): array
    {
        if (! method_exists($request, 'get_json_params')) {
            throw new InvalidArgumentException('SafeContracts mutation requests require a JSON object body.');
        }
        $body = $request->get_json_params();
        if (! is_array($body)) {
            throw new InvalidArgumentException('SafeContracts mutation requests require a JSON object body.');
        }
        foreach (array_keys($body) as $field) {
            if (! is_string($field) || ! in_array($field, $allowed, true)) {
                throw new InvalidArgumentException('Unsupported SafeContracts mutation field.');
            }
        }
        return $body;
    }

    private static function routeId(WP_REST_Request $request, string $key): int
    {
        if (method_exists($request, 'get_url_params')) {
            $params = $request->get_url_params();
            if (is_array($params) && array_key_exists($key, $params)) {
                $value = $params[$key];
                if ((is_int($value) && $value > 0) || (is_string($value) && preg_match('/^[1-9]\\d*$/', trim($value)))) {
                    return (int) $value;
                }
                throw new InvalidArgumentException("{$key} must be a positive integer.");
            }
        }
        return ApiRequest::routeId($request, $key);
    }

    /** @param array<string,mixed> $input */
    private static function assertBodyRouteId(array $input, string $field, int $routeId): void
    {
        if (! array_key_exists($field, $input)) {
            return;
        }
        $value = self::nullableScalar($input[$field], $field);
        if ($value === null || ! preg_match('/^[1-9]\\d*$/', trim((string) $value)) || (int) $value !== $routeId) {
            throw new InvalidArgumentException("{$field} must match the route identifier when supplied.");
        }
    }

    private static function scalar(mixed $value, string $field): string|int|float|bool
    {
        if (is_array($value) || is_object($value) || $value === null) {
            throw new InvalidArgumentException("{$field} must be a scalar value.");
        }
        return $value;
    }

    private static function nullableScalar(mixed $value, string $field): string|int|float|bool|null
    {
        if ($value === null) {
            return null;
        }
        return self::scalar($value, $field);
    }

    private static function nullableDate(mixed $value, string $field): ?string
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }
        $date = trim((string) self::scalar($value, $field));
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $errors = \DateTimeImmutable::getLastErrors();
        if ($parsed === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException("{$field} must be a valid YYYY-MM-DD date or null.");
        }
        return $date;
    }

    private static function domainMutationError(DomainException $error, string $forbiddenCode, string $conflictCode): WP_Error
    {
        if (str_starts_with($error->getMessage(), 'Archived ')) {
            return ApiResponse::error($conflictCode, $error->getMessage(), 409);
        }
        return RequestGuard::domain($error, $forbiddenCode);
    }

    private static function followUpNoDates(mixed $promisedDate, mixed $deferredUntil, callable $callback): int
    {
        if ($promisedDate !== null || $deferredUntil !== null) {
            throw new InvalidArgumentException('This follow-up operation does not accept date fields.');
        }
        return $callback();
    }

    private static function followUpPromise(mixed $promisedDate, mixed $deferredUntil, callable $callback): int
    {
        if ($promisedDate === null || $deferredUntil !== null) {
            throw new InvalidArgumentException('Promise follow-up requires promised_date only.');
        }
        return $callback($promisedDate);
    }

    private static function followUpDeferred(mixed $promisedDate, mixed $deferredUntil, callable $callback): int
    {
        if ($deferredUntil === null || $promisedDate !== null) {
            throw new InvalidArgumentException('Deferred follow-up requires deferred_until only.');
        }
        return $callback($deferredUntil);
    }
}
