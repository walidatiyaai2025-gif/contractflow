<?php

declare(strict_types=1);

namespace SafeContracts\Tenancy;

use SafeContracts\Roles\Capabilities;

final class TenantRolePolicy
{
    public const TENANT_ADMIN = 'tenant_admin';
    public const MANAGER = 'manager';
    public const ACCOUNTANT = 'accountant';
    public const VIEWER = 'viewer';
    public const MEMBER = 'member';

    /** @var list<string> */
    private const PLATFORM_GLOBAL_CAPABILITIES = [
        Capabilities::MANAGE_SYSTEM,
        Capabilities::MANAGE_REFERENCE_DATA,
    ];

    /** @var array<string,list<string>> */
    private const ROLE_CAPABILITIES = [
        self::TENANT_ADMIN => [
            Capabilities::ACCESS,
            Capabilities::MANAGE_USERS,
            Capabilities::VIEW_ALL,
            Capabilities::VIEW_ASSIGNED,
            Capabilities::CREATE_CONTRACTS,
            Capabilities::EDIT_CONTRACTS,
            Capabilities::ASSIGN_CONTRACTS,
            Capabilities::MANAGE_PAYMENTS,
            Capabilities::MANAGE_COLLECTIONS,
            Capabilities::MANAGE_FOLLOWUPS,
            Capabilities::VIEW_REPORTS,
            Capabilities::EXPORT_REPORTS,
            Capabilities::MANAGE_NOTIFICATIONS,
            Capabilities::RUN_IMPORTS,
            Capabilities::VIEW_AUDIT,
        ],
        self::MANAGER => [
            Capabilities::ACCESS,
            Capabilities::VIEW_ALL,
            Capabilities::VIEW_ASSIGNED,
            Capabilities::CREATE_CONTRACTS,
            Capabilities::EDIT_CONTRACTS,
            Capabilities::ASSIGN_CONTRACTS,
            Capabilities::MANAGE_PAYMENTS,
            Capabilities::MANAGE_COLLECTIONS,
            Capabilities::MANAGE_FOLLOWUPS,
            Capabilities::VIEW_REPORTS,
            Capabilities::EXPORT_REPORTS,
            Capabilities::VIEW_AUDIT,
        ],
        self::ACCOUNTANT => [
            Capabilities::ACCESS,
            Capabilities::VIEW_ASSIGNED,
            Capabilities::CREATE_CONTRACTS,
            Capabilities::MANAGE_PAYMENTS,
            Capabilities::MANAGE_COLLECTIONS,
            Capabilities::MANAGE_FOLLOWUPS,
            Capabilities::VIEW_REPORTS,
            Capabilities::EXPORT_REPORTS,
        ],
        self::VIEWER => [
            Capabilities::ACCESS,
            Capabilities::VIEW_ASSIGNED,
            Capabilities::VIEW_REPORTS,
        ],
    ];

    public static function normalize(string $roleCode): string
    {
        return strtolower(trim($roleCode));
    }

    public static function isRecognized(string $roleCode): bool
    {
        $roleCode = self::normalize($roleCode);
        return $roleCode === self::MEMBER || array_key_exists($roleCode, self::ROLE_CAPABILITIES);
    }

    public static function allowsCapability(string $roleCode, bool $isOwner, string $capability): bool
    {
        if (in_array($capability, self::PLATFORM_GLOBAL_CAPABILITIES, true)) {
            return true;
        }

        $roleCode = self::normalize($roleCode);
        if (! self::isRecognized($roleCode)) {
            return false;
        }

        // Legacy memberships were created with `member` before a tenant role
        // matrix existed. Keep that code as a narrowing-neutral compatibility
        // role until administrators deliberately remap those memberships.
        if ($roleCode === self::MEMBER) {
            return true;
        }

        // Owner status raises the tenant role ceiling only; the caller must still
        // verify the matching global WordPress capability, so this never escalates.
        if ($isOwner) {
            return true;
        }

        return in_array($capability, self::ROLE_CAPABILITIES[$roleCode] ?? [], true);
    }

    public static function scopeCeiling(string $roleCode, bool $isOwner): string
    {
        $roleCode = self::normalize($roleCode);
        if (! self::isRecognized($roleCode)) {
            return 'none';
        }
        if ($roleCode === self::MEMBER) {
            return 'inherit';
        }
        if ($isOwner || $roleCode === self::TENANT_ADMIN || $roleCode === self::MANAGER) {
            return 'all';
        }
        if ($roleCode === self::ACCOUNTANT || $roleCode === self::VIEWER) {
            return 'assigned';
        }
        return 'none';
    }
}
