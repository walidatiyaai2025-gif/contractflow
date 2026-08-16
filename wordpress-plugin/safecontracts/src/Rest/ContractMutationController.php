<?php

declare(strict_types=1);

namespace SafeContracts\Rest;

use DomainException;
use InvalidArgumentException;
use SafeContracts\Contracts\ContractService;
use SafeContracts\Roles\Capabilities;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class ContractMutationController
{
    public static function register(): void
    {
        register_rest_route(Router::NAMESPACE, '/contracts/(?P<id>\\d+)/light', [
            'methods' => 'PATCH',
            'callback' => [self::class, 'editContract'],
            'permission_callback' => [self::class, 'canEditContracts'],
        ]);
        register_rest_route(Router::NAMESPACE, '/contracts/(?P<id>\\d+)/accountant', [
            'methods' => 'PATCH',
            'callback' => [self::class, 'assignAccountant'],
            'permission_callback' => [self::class, 'canAssignContracts'],
        ]);
    }

    public static function canEditContracts(): bool|WP_Error
    {
        $access = Router::canAccess();
        if ($access !== true) {
            return $access;
        }
        if (current_user_can(Capabilities::EDIT_CONTRACTS)) {
            return true;
        }
        return RequestGuard::forbidden(
            'safecontracts_contract_light_edit_forbidden',
            __('You do not have permission to edit SafeContracts contracts.', 'safecontracts')
        );
    }

    public static function canAssignContracts(): bool|WP_Error
    {
        $access = Router::canAccess();
        if ($access !== true) {
            return $access;
        }
        if (current_user_can(Capabilities::ASSIGN_CONTRACTS)) {
            return true;
        }
        return RequestGuard::forbidden(
            'safecontracts_contract_accountant_assign_forbidden',
            __('You do not have permission to assign SafeContracts contracts.', 'safecontracts')
        );
    }

    public static function editContract(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $permission = self::canEditContracts();
        if ($permission instanceof WP_Error) {
            return $permission;
        }

        try {
            $contractId = ApiRequest::routeId($request, 'id');
            $body = $request->get_json_params();
            if (! is_array($body)) {
                throw new InvalidArgumentException('SafeContracts contract edits require a JSON object body.');
            }

            $allowed = ['contract_number', 'start_date', 'end_date'];
            foreach (array_keys($body) as $field) {
                if (! is_string($field) || ! in_array($field, $allowed, true)) {
                    throw new InvalidArgumentException('Unsupported SafeContracts contract edit field.');
                }
            }

            $hasNumber = array_key_exists('contract_number', $body);
            $hasStart = array_key_exists('start_date', $body);
            $hasEnd = array_key_exists('end_date', $body);
            if (! $hasNumber && ! $hasStart && ! $hasEnd) {
                throw new InvalidArgumentException('At least one supported contract field is required.');
            }
            if ($hasStart xor $hasEnd) {
                throw new InvalidArgumentException('Contract date edits require both start_date and end_date.');
            }

            $service = new ContractService();
            if ($hasNumber) {
                if (is_array($body['contract_number']) || is_object($body['contract_number']) || $body['contract_number'] === null) {
                    throw new InvalidArgumentException('contract_number must be a string.');
                }
                $number = trim((string) $body['contract_number']);
                if ($number === '' || strlen($number) > 100) {
                    throw new InvalidArgumentException('contract_number must contain 1 to 100 characters.');
                }
                $service->edit($contractId, ['contract_number' => $number]);
            }

            if ($hasStart && $hasEnd) {
                $start = self::nullableDate($body['start_date'], 'start_date');
                $end = self::nullableDate($body['end_date'], 'end_date');
                if ($start !== null && $end !== null && $end < $start) {
                    throw new InvalidArgumentException('Contract end date cannot precede start date.');
                }
                $service->updateDates($contractId, $start, $end);
            }

            return RequestGuard::response([
                'id' => $contractId,
                'updated' => true,
                'fields' => array_values(array_keys($body)),
            ]);
        } catch (InvalidArgumentException $error) {
            return RequestGuard::invalid($error, 'safecontracts_contract_light_edit_invalid');
        } catch (DomainException $error) {
            if (str_starts_with($error->getMessage(), 'Archived ')) {
                return ApiResponse::error(
                    'safecontracts_contract_light_edit_conflict',
                    $error->getMessage(),
                    409
                );
            }
            return RequestGuard::domain($error, 'safecontracts_contract_light_edit_forbidden');
        } catch (Throwable $error) {
            return RequestGuard::failure($error, 'safecontracts_contract_light_edit_failed');
        }
    }

    public static function assignAccountant(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $permission = self::canAssignContracts();
        if ($permission instanceof WP_Error) {
            return $permission;
        }

        try {
            $contractId = ApiRequest::routeId($request, 'id');
            $body = $request->get_json_params();
            if (! is_array($body) || count($body) !== 1 || ! array_key_exists('accountant_user_id', $body)) {
                throw new InvalidArgumentException('Responsible accountant assignment requires accountant_user_id only.');
            }
            $value = $body['accountant_user_id'];
            if (is_array($value) || is_object($value) || is_bool($value) || $value === null) {
                throw new InvalidArgumentException('accountant_user_id must be a positive integer.');
            }
            if (is_string($value)) {
                $value = trim($value);
                if ($value === '' || ! ctype_digit($value)) {
                    throw new InvalidArgumentException('accountant_user_id must be a positive integer.');
                }
            } elseif (! is_int($value)) {
                throw new InvalidArgumentException('accountant_user_id must be a positive integer.');
            }
            $accountantUserId = (int) $value;
            if ($accountantUserId <= 0) {
                throw new InvalidArgumentException('accountant_user_id must be a positive integer.');
            }

            (new ContractService())->assignAccountant($contractId, $accountantUserId);

            return RequestGuard::response([
                'id' => $contractId,
                'accountant_user_id' => $accountantUserId,
                'updated' => true,
            ]);
        } catch (InvalidArgumentException $error) {
            return RequestGuard::invalid($error, 'safecontracts_contract_accountant_assign_invalid');
        } catch (DomainException $error) {
            return RequestGuard::domain($error, 'safecontracts_contract_accountant_assign_forbidden');
        } catch (Throwable $error) {
            return RequestGuard::failure($error, 'safecontracts_contract_accountant_assign_failed');
        }
    }

    private static function nullableDate(mixed $value, string $field): ?string
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }
        if (is_array($value) || is_object($value)) {
            throw new InvalidArgumentException("{$field} must be a valid YYYY-MM-DD date or null.");
        }
        $date = trim((string) $value);
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $errors = \DateTimeImmutable::getLastErrors();
        if ($parsed === false ||
            ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) ||
            $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException("{$field} must be a valid YYYY-MM-DD date or null.");
        }
        return $date;
    }
}
