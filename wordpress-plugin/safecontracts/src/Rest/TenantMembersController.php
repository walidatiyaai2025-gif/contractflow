<?php

declare(strict_types=1);

namespace SafeContracts\Rest;

use InvalidArgumentException;
use RuntimeException;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\TenantMembershipAdminService;
use SafeContracts\Tenancy\TenantRolePolicy;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class TenantMembersController
{
    public static function register(): void
    {
        register_rest_route(Router::NAMESPACE, '/tenant-members', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [self::class, 'index'],
                'permission_callback' => [self::class, 'canManage'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'create'],
                'permission_callback' => [self::class, 'canManage'],
            ],
        ]);

        register_rest_route(Router::NAMESPACE, '/tenant-members/(?P<user_id>\\d+)', [
            [
                'methods' => 'PUT',
                'callback' => [self::class, 'update'],
                'permission_callback' => [self::class, 'canManage'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [self::class, 'deactivate'],
                'permission_callback' => [self::class, 'canManage'],
            ],
        ]);
    }

    public static function canManage(WP_REST_Request $request): bool|WP_Error
    {
        // Resolve here as well as in CoreTenantRestGuard. WordPress evaluates route
        // permission callbacks as part of dispatch, so the permission callback must
        // itself establish the tenant before applying the P2 role ceiling.
        $tenantId = TenantRequestContext::resolve($request, true);
        if ($tenantId instanceof WP_Error) {
            return $tenantId;
        }

        return Permission::capability(
            Capabilities::MANAGE_USERS,
            'safecontracts_tenant_members_forbidden'
        );
    }

    public static function index(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $permission = self::canManage($request);
        if ($permission instanceof WP_Error) {
            return $permission;
        }

        try {
            $items = (new TenantMembershipAdminService())->listForCurrentTenant(get_current_user_id());
            return ApiResponse::ok([
                'items' => array_map([self::class, 'present'], $items),
                'assignable_roles' => TenantRolePolicy::assignableRoles(),
            ]);
        } catch (Throwable $error) {
            return RequestGuard::failure($error, 'safecontracts_tenant_members_list_failed');
        }
    }

    public static function create(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $permission = self::canManage($request);
        if ($permission instanceof WP_Error) {
            return $permission;
        }

        try {
            $input = self::jsonObject($request, ['user_id', 'role_code']);
            $userId = self::positiveUserId($input['user_id'] ?? null);
            $roleCode = self::roleCode($input['role_code'] ?? null);

            $service = new TenantMembershipAdminService();
            $service->assignRole($userId, $roleCode, get_current_user_id());
            $saved = self::findCurrentMembership($service, $userId);

            return ApiResponse::ok([
                'membership' => $saved === null ? ['user_id' => $userId, 'role_code' => $roleCode] : self::present($saved),
                'saved' => true,
            ]);
        } catch (InvalidArgumentException $error) {
            return ApiResponse::error('safecontracts_tenant_member_invalid', $error->getMessage(), 422);
        } catch (RuntimeException $error) {
            return ApiResponse::error('safecontracts_tenant_member_conflict', $error->getMessage(), 409);
        } catch (Throwable $error) {
            return RequestGuard::failure($error, 'safecontracts_tenant_member_save_failed');
        }
    }

    public static function update(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $permission = self::canManage($request);
        if ($permission instanceof WP_Error) {
            return $permission;
        }

        try {
            $userId = self::routeUserId($request);
            $input = self::jsonObject($request, ['role_code']);
            $roleCode = self::roleCode($input['role_code'] ?? null);

            $service = new TenantMembershipAdminService();
            $current = self::findCurrentMembership($service, $userId);
            if ($current !== null && ! empty($current['is_owner'])) {
                throw new RuntimeException('Owner memberships are read-only in the generic tenant-members REST API.');
            }

            $service->assignRole($userId, $roleCode, get_current_user_id());
            $saved = self::findCurrentMembership($service, $userId);

            return ApiResponse::ok([
                'membership' => $saved === null ? ['user_id' => $userId, 'role_code' => $roleCode] : self::present($saved),
                'saved' => true,
            ]);
        } catch (InvalidArgumentException $error) {
            return ApiResponse::error('safecontracts_tenant_member_invalid', $error->getMessage(), 422);
        } catch (RuntimeException $error) {
            return ApiResponse::error('safecontracts_tenant_member_conflict', $error->getMessage(), 409);
        } catch (Throwable $error) {
            return RequestGuard::failure($error, 'safecontracts_tenant_member_save_failed');
        }
    }

    public static function deactivate(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $permission = self::canManage($request);
        if ($permission instanceof WP_Error) {
            return $permission;
        }

        try {
            $userId = self::routeUserId($request);
            $service = new TenantMembershipAdminService();
            $current = self::findCurrentMembership($service, $userId);
            if ($current === null || (string) ($current['status'] ?? '') !== 'active') {
                throw new InvalidArgumentException('The active tenant membership was not found.');
            }
            if (! empty($current['is_owner'])) {
                // Owner transfer/removal is deliberately excluded from this generic
                // endpoint even though the domain layer protects last-owner safety.
                throw new RuntimeException('Owner memberships are read-only in the generic tenant-members REST API.');
            }

            $service->deactivate($userId, get_current_user_id());
            return ApiResponse::ok([
                'user_id' => $userId,
                'deactivated' => true,
            ]);
        } catch (InvalidArgumentException $error) {
            return ApiResponse::error('safecontracts_tenant_member_not_found', $error->getMessage(), 404);
        } catch (RuntimeException $error) {
            return ApiResponse::error('safecontracts_tenant_member_conflict', $error->getMessage(), 409);
        } catch (Throwable $error) {
            return RequestGuard::failure($error, 'safecontracts_tenant_member_deactivate_failed');
        }
    }

    /** @param list<string> $allowed */
    private static function jsonObject(WP_REST_Request $request, array $allowed): array
    {
        $input = $request->get_json_params();
        if (! is_array($input)) {
            throw new InvalidArgumentException('Tenant membership mutations require a JSON object body.');
        }

        foreach (array_keys($input) as $field) {
            if (! is_string($field) || ! in_array($field, $allowed, true)) {
                throw new InvalidArgumentException('Unsupported tenant membership field.');
            }
        }

        return $input;
    }

    private static function positiveUserId(mixed $value): int
    {
        if (is_array($value) || is_object($value) || $value === null) {
            throw new InvalidArgumentException('user_id must be a positive WordPress user id.');
        }
        $userId = (int) $value;
        if ($userId <= 0 || (string) $userId !== trim((string) $value)) {
            throw new InvalidArgumentException('user_id must be a positive WordPress user id.');
        }
        return $userId;
    }

    private static function roleCode(mixed $value): string
    {
        if (is_array($value) || is_object($value) || $value === null) {
            throw new InvalidArgumentException('role_code must be an assignable tenant role.');
        }
        $roleCode = sanitize_key((string) $value);
        if (! TenantRolePolicy::isAssignable($roleCode)) {
            throw new InvalidArgumentException('role_code must be an assignable tenant role.');
        }
        return $roleCode;
    }

    private static function routeUserId(WP_REST_Request $request): int
    {
        if (! method_exists($request, 'get_param')) {
            throw new InvalidArgumentException('A tenant member user id is required.');
        }
        return self::positiveUserId($request->get_param('user_id'));
    }

    /** @return array<string,mixed>|null */
    private static function findCurrentMembership(TenantMembershipAdminService $service, int $userId): ?array
    {
        foreach ($service->listForCurrentTenant(get_current_user_id()) as $membership) {
            if ((int) ($membership['user_id'] ?? 0) === $userId) {
                return $membership;
            }
        }
        return null;
    }

    /** @param array<string,mixed> $membership */
    private static function present(array $membership): array
    {
        $userId = (int) ($membership['user_id'] ?? 0);
        $item = [
            'user_id' => $userId,
            'role_code' => (string) ($membership['role_code'] ?? ''),
            'status' => (string) ($membership['status'] ?? ''),
            'is_owner' => ! empty($membership['is_owner']),
        ];

        if ($userId > 0 && function_exists('get_userdata')) {
            $user = get_userdata($userId);
            if ($user !== false) {
                $item['user'] = [
                    'id' => $userId,
                    'display_name' => (string) ($user->display_name ?? ''),
                    'email' => (string) ($user->user_email ?? ''),
                ];
            }
        }

        return $item;
    }
}
