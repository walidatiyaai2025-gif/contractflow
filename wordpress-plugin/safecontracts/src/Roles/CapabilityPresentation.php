<?php

declare(strict_types=1);

namespace SafeContracts\Roles;

final class CapabilityPresentation
{
    /** @return array<string,array{label:string,description:string}> */
    public static function all(): array
    {
        return [
            Capabilities::ACCESS => self::item('Access Alkenzy ADV', 'Open the Alkenzy ADV workspace and use the features allowed by the assigned role.'),
            Capabilities::MANAGE_SYSTEM => self::item('Manage system settings', 'Change organization, mobile, translation and other administrator-only system settings.'),
            Capabilities::MANAGE_REFERENCE_DATA => self::item('Manage reference data', 'Maintain controlled reference choices such as payment methods without changing historical transactions.'),
            Capabilities::MANAGE_USERS => self::item('Manage users and roles', 'Assign Alkenzy ADV roles and choose the business permissions available to each role.'),
            Capabilities::VIEW_ALL => self::item('View all business records', 'View records across the full authorized organization scope instead of assigned records only.'),
            Capabilities::VIEW_ASSIGNED => self::item('View assigned records', 'View records assigned to the current user within the authorized business scope.'),
            Capabilities::CREATE_CUSTOMERS => self::item('Create customers', 'Add new customer records.'),
            Capabilities::EDIT_CUSTOMERS => self::item('Edit customers', 'Update existing customer records within the authorized scope.'),
            Capabilities::VIEW_SUPPLIERS => self::item('View suppliers', 'Open supplier records and supplier-linked contracts within the authorized scope.'),
            Capabilities::CREATE_SUPPLIERS => self::item('Create suppliers', 'Add new supplier records.'),
            Capabilities::EDIT_SUPPLIERS => self::item('Edit suppliers', 'Update existing supplier records within the authorized scope.'),
            Capabilities::ARCHIVE_SUPPLIERS => self::item('Archive suppliers', 'Remove suppliers from active operations while preserving required history and financial evidence.'),
            Capabilities::MANAGE_SUPPLIERS => self::item('Manage supplier operations', 'Perform supplier administration and supplier-side payable operations allowed by the system.'),
            Capabilities::CREATE_CONTRACTS => self::item('Create contracts', 'Create customer or supplier contracts using controlled business selections.'),
            Capabilities::EDIT_CONTRACTS => self::item('Edit contracts', 'Update editable contract details while server validation remains authoritative.'),
            Capabilities::ASSIGN_CONTRACTS => self::item('Assign contracts', 'Assign contracts to authorized accountants or responsible users.'),
            Capabilities::CREATE_PAYMENTS => self::item('Create payment schedules', 'Add contractual payment schedule entries for authorized contracts.'),
            Capabilities::EDIT_PAYMENTS => self::item('Edit payment schedules', 'Update permitted payment schedule fields before settlement.'),
            Capabilities::MANAGE_PAYMENTS => self::item('Manage payment operations', 'Perform payment administration, reconciliation and allowed payment actions.'),
            Capabilities::VIEW_FINANCE => self::item('View finance workspace', 'View authorized receivable, payable, aging and cash-flow information.'),
            Capabilities::MANAGE_FINANCE => self::item('Manage finance operations', 'Perform authorized finance actions and settlement workflows.'),
            Capabilities::VIEW_PAYABLES => self::item('View payables', 'View supplier-side amounts that are due for payment.'),
            Capabilities::VIEW_RECEIVABLES => self::item('View receivables', 'View customer-side amounts that are due for collection.'),
            Capabilities::MANAGE_COLLECTIONS => self::item('Record and manage collections', 'Record authorized receipts and manage their supported lifecycle actions.'),
            Capabilities::MANAGE_FOLLOWUPS => self::item('Manage follow-up', 'Record and review operational follow-up actions for outstanding receivables.'),
            Capabilities::VIEW_REPORTS => self::item('View reports', 'Run authorized operational and financial reports.'),
            Capabilities::EXPORT_REPORTS => self::item('Export reports', 'Export authorized report results to supported files such as Excel.'),
            Capabilities::MANAGE_NOTIFICATIONS => self::item('Manage notifications', 'Manage notification rules, schedules, templates and permitted delivery actions.'),
            Capabilities::RUN_IMPORTS => self::item('Run imports', 'Upload, validate, map and execute controlled data imports.'),
            Capabilities::VIEW_AUDIT => self::item('View audit history', 'Review protected operational history and audit evidence.'),
        ];
    }

    public static function label(string $capability): string
    {
        $item = self::all()[$capability] ?? null;
        return is_array($item) ? $item['label'] : __('Permission', 'safecontracts');
    }

    public static function description(string $capability): string
    {
        $item = self::all()[$capability] ?? null;
        return is_array($item) ? $item['description'] : __('What this permission allows', 'safecontracts');
    }

    /** @return array{label:string,description:string} */
    private static function item(string $label, string $description): array
    {
        return [
            'label' => __($label, 'safecontracts'),
            'description' => __($description, 'safecontracts'),
        ];
    }
}
