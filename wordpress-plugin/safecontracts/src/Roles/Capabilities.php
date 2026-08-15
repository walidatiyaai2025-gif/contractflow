<?php

declare(strict_types=1);

namespace SafeContracts\Roles;

final class Capabilities
{
    public const ACCESS = 'safecontracts_access';
    public const MANAGE_SYSTEM = 'safecontracts_manage_system';
    public const MANAGE_REFERENCE_DATA = 'safecontracts_manage_reference_data';
    public const MANAGE_USERS = 'safecontracts_manage_users';
    public const VIEW_ALL = 'safecontracts_view_all';
    public const VIEW_ASSIGNED = 'safecontracts_view_assigned';
    public const CREATE_CONTRACTS = 'safecontracts_create_contracts';
    public const EDIT_CONTRACTS = 'safecontracts_edit_contracts';
    public const ASSIGN_CONTRACTS = 'safecontracts_assign_contracts';
    public const MANAGE_PAYMENTS = 'safecontracts_manage_payments';
    public const MANAGE_COLLECTIONS = 'safecontracts_manage_collections';
    public const MANAGE_FOLLOWUPS = 'safecontracts_manage_followups';
    public const VIEW_REPORTS = 'safecontracts_view_reports';
    public const EXPORT_REPORTS = 'safecontracts_export_reports';
    public const MANAGE_NOTIFICATIONS = 'safecontracts_manage_notifications';
    public const MANAGE_FIREBASE = 'safecontracts_manage_firebase';
    public const RUN_IMPORTS = 'safecontracts_run_imports';
    public const VIEW_AUDIT = 'safecontracts_view_audit';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::ACCESS,
            self::MANAGE_SYSTEM,
            self::MANAGE_REFERENCE_DATA,
            self::MANAGE_USERS,
            self::VIEW_ALL,
            self::VIEW_ASSIGNED,
            self::CREATE_CONTRACTS,
            self::EDIT_CONTRACTS,
            self::ASSIGN_CONTRACTS,
            self::MANAGE_PAYMENTS,
            self::MANAGE_COLLECTIONS,
            self::MANAGE_FOLLOWUPS,
            self::VIEW_REPORTS,
            self::EXPORT_REPORTS,
            self::MANAGE_NOTIFICATIONS,
            self::MANAGE_FIREBASE,
            self::RUN_IMPORTS,
            self::VIEW_AUDIT,
        ];
    }

    /** @return array<string, bool> */
    public static function toGrantArray(array $capabilities): array
    {
        $grants = ['read' => true];

        foreach ($capabilities as $capability) {
            $grants[$capability] = true;
        }

        return $grants;
    }
}
