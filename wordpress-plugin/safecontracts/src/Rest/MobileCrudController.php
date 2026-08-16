<?php

declare(strict_types=1);

namespace SafeContracts\Rest;

use DomainException;
use InvalidArgumentException;
use SafeContracts\Contracts\ContractMoney;
use SafeContracts\Contracts\ContractService;
use SafeContracts\Customers\CustomerService;
use SafeContracts\Payments\MobilePaymentService;
use SafeContracts\Roles\Capabilities;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class MobileCrudController
{
    public static function register(): void
    {
        register_rest_route(Router::NAMESPACE, '/mobile/customers/create', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'createCustomer'],
            'permission_callback' => [self::class, 'canCreateCustomers'],
        ]);
        register_rest_route(Router::NAMESPACE, '/mobile/customers/(?P<id>\\d+)/edit', [
            'methods' => 'PATCH',
            'callback' => [self::class, 'editCustomer'],
            'permission_callback' => [self::class, 'canEditCustomers'],
        ]);
        register_rest_route(Router::NAMESPACE, '/mobile/contracts/create', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'createContract'],
            'permission_callback' => [self::class, 'canCreateContracts'],
        ]);
        register_rest_route(Router::NAMESPACE, '/mobile/contracts/(?P<id>\\d+)/edit', [
            'methods' => 'PATCH',
            'callback' => [self::class, 'editContract'],
            'permission_callback' => [self::class, 'canEditContracts'],
        ]);
        register_rest_route(Router::NAMESPACE, '/mobile/payments/create', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'createPayment'],
            'permission_callback' => [self::class, 'canCreatePayments'],
        ]);
        register_rest_route(Router::NAMESPACE, '/mobile/payments/(?P<id>\\d+)/edit', [
            'methods' => 'PATCH',
            'callback' => [self::class, 'editPayment'],
            'permission_callback' => [self::class, 'canEditPayments'],
        ]);
    }

    public static function canCreateCustomers(): bool|WP_Error
    {
        return self::can(Capabilities::CREATE_CUSTOMERS, 'safecontracts_customer_create_forbidden', 'You do not have permission to create SafeContracts customers.');
    }

    public static function canEditCustomers(): bool|WP_Error
    {
        return self::can(Capabilities::EDIT_CUSTOMERS, 'safecontracts_customer_edit_forbidden', 'You do not have permission to edit SafeContracts customers.');
    }

    public static function canCreateContracts(): bool|WP_Error
    {
        return self::can(Capabilities::CREATE_CONTRACTS, 'safecontracts_contract_create_forbidden', 'You do not have permission to create SafeContracts contracts.');
    }

    public static function canEditContracts(): bool|WP_Error
    {
        return self::can(Capabilities::EDIT_CONTRACTS, 'safecontracts_contract_full_edit_forbidden', 'You do not have permission to edit SafeContracts contracts.');
    }

    public static function canCreatePayments(): bool|WP_Error
    {
        return self::can(Capabilities::CREATE_PAYMENTS, 'safecontracts_payment_create_forbidden', 'You do not have permission to create SafeContracts payments.');
    }

    public static function canEditPayments(): bool|WP_Error
    {
        return self::can(Capabilities::EDIT_PAYMENTS, 'safecontracts_payment_full_edit_forbidden', 'You do not have permission to edit SafeContracts payments.');
    }

    public static function createCustomer(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return self::guard(function () use ($request): WP_REST_Response {
            $input = self::body($request, ['internal_code', 'name', 'contact_name', 'email', 'phone', 'notes', 'is_active']);
            if (! array_key_exists('name', $input)) {
                throw new InvalidArgumentException('Customer name is required.');
            }
            $id = (new CustomerService())->save($input);
            return RequestGuard::response(['id' => $id, 'created' => true], [], 201);
        }, 'safecontracts_customer_create_invalid', 'safecontracts_customer_create_failed');
    }

    public static function editCustomer(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return self::guard(function () use ($request): WP_REST_Response {
            $id = ApiRequest::routeId($request, 'id');
            $input = self::body($request, ['internal_code', 'name', 'contact_name', 'email', 'phone', 'notes', 'is_active']);
            if ($input === []) {
                throw new InvalidArgumentException('At least one customer field is required.');
            }
            $service = new CustomerService();
            $existing = $service->find($id);
            if ($existing === null) {
                throw new InvalidArgumentException('Customer was not found.');
            }
            $merged = [
                'id' => $id,
                'internal_code' => $existing['internal_code'] ?? '',
                'name' => $existing['name'] ?? '',
                'contact_name' => $existing['contact_name'] ?? '',
                'email' => $existing['email'] ?? '',
                'phone' => $existing['phone'] ?? '',
                'notes' => $existing['notes'] ?? '',
                'is_active' => (bool) ($existing['is_active'] ?? true),
                ...$input,
            ];
            $service->save($merged);
            return RequestGuard::response(['id' => $id, 'updated' => true]);
        }, 'safecontracts_customer_edit_invalid', 'safecontracts_customer_edit_failed');
    }

    public static function createContract(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return self::guard(function () use ($request): WP_REST_Response {
            $input = self::body($request, [
                'contract_number', 'customer_id', 'accountant_user_id', 'notes', 'start_date', 'end_date', 'base_value',
            ]);
            foreach (['contract_number', 'customer_id'] as $required) {
                if (! array_key_exists($required, $input)) {
                    throw new InvalidArgumentException("{$required} is required.");
                }
            }

            $start = array_key_exists('start_date', $input) ? self::nullableDate($input['start_date'], 'start_date') : null;
            $end = array_key_exists('end_date', $input) ? self::nullableDate($input['end_date'], 'end_date') : null;
            if (($start !== null || $end !== null) && ! current_user_can(Capabilities::EDIT_CONTRACTS)) {
                throw new DomainException('Setting contract dates requires contract edit permission.');
            }
            if ($start !== null && $end !== null && $end < $start) {
                throw new InvalidArgumentException('Contract end date cannot precede start date.');
            }
            $baseValue = null;
            if (array_key_exists('base_value', $input) && trim((string) $input['base_value']) !== '') {
                if (! current_user_can(Capabilities::EDIT_CONTRACTS)) {
                    throw new DomainException('Setting contract value requires contract edit permission.');
                }
                $baseValue = ContractMoney::normalizeNonNegative(self::scalar($input['base_value'], 'base_value'));
            }

            $service = new ContractService();
            $id = $service->create([
                'contract_number' => self::scalar($input['contract_number'], 'contract_number'),
                'customer_id' => self::positiveInt($input['customer_id'], 'customer_id'),
                'accountant_user_id' => array_key_exists('accountant_user_id', $input)
                    ? self::nullablePositiveInt($input['accountant_user_id'], 'accountant_user_id')
                    : null,
                'notes' => array_key_exists('notes', $input) ? self::nullableText($input['notes'], 'notes', 5000) ?? '' : '',
            ]);
            if ($start !== null || $end !== null) {
                $service->updateDates($id, $start, $end);
            }
            if ($baseValue !== null) {
                $service->updateBaseValue($id, $baseValue);
            }
            return RequestGuard::response(['id' => $id, 'created' => true], [], 201);
        }, 'safecontracts_contract_create_invalid', 'safecontracts_contract_create_failed');
    }

    public static function editContract(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return self::guard(function () use ($request): WP_REST_Response {
            $id = ApiRequest::routeId($request, 'id');
            $input = self::body($request, [
                'contract_number', 'notes', 'start_date', 'end_date', 'base_value', 'customer_id', 'accountant_user_id',
            ]);
            if ($input === []) {
                throw new InvalidArgumentException('At least one contract field is required.');
            }

            $service = new ContractService();
            $detailChanges = [];
            if (array_key_exists('contract_number', $input)) {
                $detailChanges['contract_number'] = self::scalar($input['contract_number'], 'contract_number');
            }
            if (array_key_exists('notes', $input)) {
                $detailChanges['notes'] = self::nullableText($input['notes'], 'notes', 5000) ?? '';
            }
            if ($detailChanges !== []) {
                $service->edit($id, $detailChanges);
            }

            $hasStart = array_key_exists('start_date', $input);
            $hasEnd = array_key_exists('end_date', $input);
            if ($hasStart xor $hasEnd) {
                throw new InvalidArgumentException('Contract date edits require both start_date and end_date.');
            }
            if ($hasStart && $hasEnd) {
                $start = self::nullableDate($input['start_date'], 'start_date');
                $end = self::nullableDate($input['end_date'], 'end_date');
                if ($start !== null && $end !== null && $end < $start) {
                    throw new InvalidArgumentException('Contract end date cannot precede start date.');
                }
                $service->updateDates($id, $start, $end);
            }
            if (array_key_exists('base_value', $input)) {
                $service->updateBaseValue($id, self::scalar($input['base_value'], 'base_value'));
            }

            if (array_key_exists('customer_id', $input) || array_key_exists('accountant_user_id', $input)) {
                if (! current_user_can(Capabilities::ASSIGN_CONTRACTS)) {
                    throw new DomainException('Changing contract assignment requires contract assignment permission.');
                }
                if (array_key_exists('customer_id', $input)) {
                    $service->assignCustomer($id, self::positiveInt($input['customer_id'], 'customer_id'));
                }
                if (array_key_exists('accountant_user_id', $input)) {
                    $service->assignAccountant($id, self::nullablePositiveInt($input['accountant_user_id'], 'accountant_user_id'));
                }
            }
            return RequestGuard::response(['id' => $id, 'updated' => true]);
        }, 'safecontracts_contract_full_edit_invalid', 'safecontracts_contract_full_edit_failed');
    }

    public static function createPayment(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return self::guard(function () use ($request): WP_REST_Response {
            $input = self::body($request, [
                'contract_id', 'sequence_no', 'reference', 'due_date', 'expected_payment_date', 'original_amount',
            ]);
            foreach (['contract_id', 'sequence_no', 'due_date', 'original_amount'] as $required) {
                if (! array_key_exists($required, $input)) {
                    throw new InvalidArgumentException("{$required} is required.");
                }
            }
            $id = (new MobilePaymentService())->create($input);
            return RequestGuard::response(['id' => $id, 'created' => true], [], 201);
        }, 'safecontracts_payment_create_invalid', 'safecontracts_payment_create_failed');
    }

    public static function editPayment(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return self::guard(function () use ($request): WP_REST_Response {
            $id = ApiRequest::routeId($request, 'id');
            $input = self::body($request, ['sequence_no', 'reference', 'due_date', 'expected_payment_date', 'original_amount']);
            if ($input === []) {
                throw new InvalidArgumentException('At least one payment field is required.');
            }
            (new MobilePaymentService())->update($id, $input);
            return RequestGuard::response(['id' => $id, 'updated' => true]);
        }, 'safecontracts_payment_full_edit_invalid', 'safecontracts_payment_full_edit_failed');
    }

    private static function can(string $capability, string $code, string $message): bool|WP_Error
    {
        $access = Router::canAccess();
        if ($access !== true) {
            return $access;
        }
        return current_user_can($capability)
            ? true
            : RequestGuard::forbidden($code, __($message, 'safecontracts'));
    }

    private static function guard(callable $callback, string $invalidCode, string $failureCode): WP_REST_Response|WP_Error
    {
        try {
            return $callback();
        } catch (InvalidArgumentException $error) {
            return RequestGuard::invalid($error, $invalidCode);
        } catch (DomainException $error) {
            $message = strtolower($error->getMessage());
            if (str_contains($message, 'archived') || str_contains($message, 'collection has been recorded')) {
                return ApiResponse::error(str_replace('_invalid', '_conflict', $invalidCode), $error->getMessage(), 409);
            }
            return RequestGuard::domain($error, str_replace('_invalid', '_forbidden', $invalidCode));
        } catch (Throwable $error) {
            return RequestGuard::failure($error, $failureCode);
        }
    }

    /** @param list<string> $allowed @return array<string,mixed> */
    private static function body(WP_REST_Request $request, array $allowed): array
    {
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

    private static function scalar(mixed $value, string $field): string|int|float|bool
    {
        if ($value === null || is_array($value) || is_object($value)) {
            throw new InvalidArgumentException("{$field} must be a scalar value.");
        }
        return $value;
    }

    private static function positiveInt(mixed $value, string $field): int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }
        if (is_string($value) && preg_match('/^[1-9]\d*$/', trim($value))) {
            return (int) trim($value);
        }
        throw new InvalidArgumentException("{$field} must be a positive integer.");
    }

    private static function nullablePositiveInt(mixed $value, string $field): ?int
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }
        return self::positiveInt($value, $field);
    }

    private static function nullableText(mixed $value, string $field, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim((string) self::scalar($value, $field));
        if ($text === '') {
            return null;
        }
        if (strlen($text) > $maxLength) {
            throw new InvalidArgumentException("{$field} is too long.");
        }
        return $text;
    }

    private static function nullableDate(mixed $value, string $field): ?string
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }
        $date = trim((string) self::scalar($value, $field));
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $errors = \DateTimeImmutable::getLastErrors();
        if (
            $parsed === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $parsed->format('Y-m-d') !== $date
        ) {
            throw new InvalidArgumentException("{$field} must be a valid YYYY-MM-DD date or blank.");
        }
        return $date;
    }
}
