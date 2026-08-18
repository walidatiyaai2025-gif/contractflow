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
    public const CREATE_CUSTOMERS = 'safecontracts_create_customers';
    public const EDIT_CUSTOMERS = 'safecontracts_edit_customers';
    public const VIEW_SUPPLIERS = 'safecontracts_view_suppliers';
    public const CREATE_SUPPLIERS = 'safecontracts_create_suppliers';
    public const EDIT_SUPPLIERS = 'safecontracts_edit_suppliers';
    public const ARCHIVE_SUPPLIERS = 'safecontracts_archive_suppliers';
    public const MANAGE_SUPPLIERS = 'safecontracts_manage_suppliers';
    public const CREATE_CONTRACTS = 'safecontracts_create_contracts';
    public const EDIT_CONTRACTS = 'safecontracts_edit_contracts';
    public const ASSIGN_CONTRACTS = 'safecontracts_assign_contracts';
    public const CREATE_PAYMENTS = 'safecontracts_create_payments';
    public const EDIT_PAYMENTS = 'safecontracts_edit_payments';
    public const MANAGE_PAYMENTS = 'safecontracts_manage_payments';
    public const VIEW_FINANCE = 'safecontracts_view_finance';
    public const MANAGE_FINANCE = 'safecontracts_manage_finance';
    public const VIEW_PAYABLES = 'safecontracts_view_payables';
    public const VIEW_RECEIVABLES = 'safecontracts_view_receivables';
    public const MANAGE_COLLECTIONS = 'safecontracts_manage_collections';
    public const MANAGE_FOLLOWUPS = 'safecontracts_manage_followups';
    public const VIEW_REPORTS = 'safecontracts_view_reports';
    public const EXPORT_REPORTS = 'safecontracts_export_reports';
    public const MANAGE_NOTIFICATIONS = 'safecontracts_manage_notifications';
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
            self::CREATE_CUSTOMERS,
            self::EDIT_CUSTOMERS,
            self::VIEW_SUPPLIERS,
            self::CREATE_SUPPLIERS,
            self::EDIT_SUPPLIERS,
            self::ARCHIVE_SUPPLIERS,
            self::MANAGE_SUPPLIERS,
            self::CREATE_CONTRACTS,
            self::EDIT_CONTRACTS,
            self::ASSIGN_CONTRACTS,
            self::CREATE_PAYMENTS,
            self::EDIT_PAYMENTS,
            self::MANAGE_PAYMENTS,
            self::VIEW_FINANCE,
            self::MANAGE_FINANCE,
            self::VIEW_PAYABLES,
            self::VIEW_RECEIVABLES,
            self::MANAGE_COLLECTIONS,
            self::MANAGE_FOLLOWUPS,
            self::VIEW_REPORTS,
            self::EXPORT_REPORTS,
            self::MANAGE_NOTIFICATIONS,
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
