<?php

declare(strict_types=1);

namespace SafeContracts\Rest;

use LogicException;
use SafeContracts\Tenancy\TenantContextStore;
use SafeContracts\Tenancy\TenantMembershipRepository;
use WP_Error;
use WP_REST_Request;

final class TenantRequestContext
{
    public const HEADER = 'X-ESC-Tenant-ID';

    public static function resolve(WP_REST_Request $request, bool $required = false): int|WP_Error|null
    {
        $userId = get_current_user_id();
        if ($userId <= 0) {
            return ApiResponse::error(
                'esc_tenant_unauthenticated',
                __('Authentication is required to resolve an Enterprise tenant.', 'safecontracts'),
                401
            );
        }

        $raw = self::headerValue($request);
        $memberships = new TenantMembershipRepository();
        $context = TenantContextStore::context();

        if ($raw === '') {
            $tenantIds = $memberships->activeTenantIdsForUser($userId);
            if (count($tenantIds) === 1) {
                try {
                    $context->setTenantId($tenantIds[0]);
                } catch (LogicException) {
                    return self::contextConflict();
                }
                return $tenantIds[0];
            }
            if (! $required) {
                return null;
            }
            if ($tenantIds === []) {
                return ApiResponse::error(
                    'esc_tenant_membership_required',
                    __('An active Enterprise tenant membership is required.', 'safecontracts'),
                    403
                );
            }
            return ApiResponse::error(
                'esc_tenant_selection_required',
                __('Select an Enterprise tenant before using this operation.', 'safecontracts'),
                400
            );
        }

        if (preg_match('/^[1-9][0-9]*$/', $raw) !== 1) {
            return ApiResponse::error(
                'esc_tenant_header_invalid',
                __('The Enterprise tenant header is invalid.', 'safecontracts'),
                400
            );
        }

        $tenantId = (int) $raw;
        if (! $memberships->isActiveMember($tenantId, $userId)) {
            return ApiResponse::error(
                'esc_tenant_forbidden',
                __('You do not have access to the requested Enterprise tenant.', 'safecontracts'),
                403
            );
        }

        try {
            $context->setTenantId($tenantId);
        } catch (LogicException) {
            return self::contextConflict();
        }

        return $tenantId;
    }

    private static function headerValue(WP_REST_Request $request): string
    {
        if (method_exists($request, 'get_header')) {
            return trim((string) $request->get_header(self::HEADER));
        }

        return trim((string) ($_SERVER['HTTP_X_ESC_TENANT_ID'] ?? ''));
    }

    private static function contextConflict(): WP_Error
    {
        return ApiResponse::error(
            'esc_tenant_context_conflict',
            __('Enterprise tenant context is already locked to another tenant.', 'safecontracts'),
            409
        );
    }
}
