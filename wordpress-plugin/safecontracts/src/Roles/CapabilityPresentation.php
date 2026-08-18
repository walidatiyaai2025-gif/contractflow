<?php

declare(strict_types=1);

namespace SafeContracts\Roles;

final class CapabilityPresentation
{
    /** @return array<string,array{label:string,description:string}> */
    public static function all(): array
    {
        return [
            Capabilities::ACCESS => self::item(__('Access Alkenzy ADV', 'safecontracts'), __('Open the Alkenzy ADV workspace and use the features allowed by the assigned role.', 'safecontracts')),
            Capabilities::MANAGE_SYSTEM => self::item(__('Manage system settings', 'safecontracts'), __('Change organization, mobile, translation and other administrator-only system settings.', 'safecontracts')),
            Capabilities::MANAGE_REFERENCE_DATA => self::item(__('Manage reference data', 'safecontracts'), __('Maintain controlled reference choices such as payment methods without changing historical transactions.', 'safecontracts')),
            Capabilities::MANAGE_USERS => self::item(__('Manage users and roles', 'safecontracts'), __('Assign Alkenzy ADV roles and choose the business permissions available to each role.', 'safecontracts')),
            Capabilities::VIEW_ALL => self::item(__('View all business records', 'safecontracts'), __('View records across the full authorized organization scope instead of assigned records only.', 'safecontracts')),
            Capabilities::VIEW_ASSIGNED => self::item(__('View assigned records', 'safecontracts'), __('View records assigned to the current user within the authorized business scope.', 'safecontracts')),
            Capabilities::CREATE_CUSTOMERS => self::item(__('Create customers', 'safecontracts'), __('Add new customer records.', 'safecontracts')),
            Capabilities::EDIT_CUSTOMERS => self::item(__('Edit customers', 'safecontracts'), __('Update existing customer records within the authorized scope.', 'safecontracts')),
            Capabilities::VIEW_SUPPLIERS => self::item(__('View suppliers', 'safecontracts'), __('Open supplier records and supplier-linked contracts within the authorized scope.', 'safecontracts')),
            Capabilities::CREATE_SUPPLIERS => self::item(__('Create suppliers', 'safecontracts'), __('Add new supplier records.', 'safecontracts')),
            Capabilities::EDIT_SUPPLIERS => self::item(__('Edit suppliers', 'safecontracts'), __('Update existing supplier records within the authorized scope.', 'safecontracts')),
            Capabilities::ARCHIVE_SUPPLIERS => self::item(__('Archive suppliers', 'safecontracts'), __('Remove suppliers from active operations while preserving required history and financial evidence.', 'safecontracts')),
            Capabilities::MANAGE_SUPPLIERS => self::item(__('Manage supplier operations', 'safecontracts'), __('Perform supplier administration and supplier-side payable operations allowed by the system.', 'safecontracts')),
            Capabilities::CREATE_CONTRACTS => self::item(__('Create contracts', 'safecontracts'), __('Create customer or supplier contracts using controlled business selections.', 'safecontracts')),
            Capabilities::EDIT_CONTRACTS => self::item(__('Edit contracts', 'safecontracts'), __('Update editable contract details while server validation remains authoritative.', 'safecontracts')),
            Capabilities::ASSIGN_CONTRACTS => self::item(__('Assign contracts', 'safecontracts'), __('Assign contracts to authorized accountants or responsible users.', 'safecontracts')),
            Capabilities::CREATE_PAYMENTS => self::item(__('Create payment schedules', 'safecontracts'), __('Add contractual payment schedule entries for authorized contracts.', 'safecontracts')),
            Capabilities::EDIT_PAYMENTS => self::item(__('Edit payment schedules', 'safecontracts'), __('Update permitted payment schedule fields before settlement.', 'safecontracts')),
            Capabilities::MANAGE_PAYMENTS => self::item(__('Manage payment operations', 'safecontracts'), __('Perform payment administration, reconciliation and allowed payment actions.', 'safecontracts')),
            Capabilities::VIEW_FINANCE => self::item(__('View finance workspace', 'safecontracts'), __('View authorized receivable, payable, aging and cash-flow information.', 'safecontracts')),
            Capabilities::MANAGE_FINANCE => self::item(__('Manage finance operations', 'safecontracts'), __('Perform authorized finance actions and settlement workflows.', 'safecontracts')),
            Capabilities::VIEW_PAYABLES => self::item(__('View payables', 'safecontracts'), __('View supplier-side amounts that are due for payment.', 'safecontracts')),
            Capabilities::VIEW_RECEIVABLES => self::item(__('View receivables', 'safecontracts'), __('View customer-side amounts that are due for collection.', 'safecontracts')),
            Capabilities::MANAGE_COLLECTIONS => self::item(__('Record and manage collections', 'safecontracts'), __('Record authorized receipts and manage their supported lifecycle actions.', 'safecontracts')),
            Capabilities::MANAGE_FOLLOWUPS => self::item(__('Manage follow-up', 'safecontracts'), __('Record and review operational follow-up actions for outstanding receivables.', 'safecontracts')),
            Capabilities::VIEW_REPORTS => self::item(__('View reports', 'safecontracts'), __('Run authorized operational and financial reports.', 'safecontracts')),
            Capabilities::EXPORT_REPORTS => self::item(__('Export reports', 'safecontracts'), __('Export authorized report results to supported files such as Excel.', 'safecontracts')),
            Capabilities::MANAGE_NOTIFICATIONS => self::item(__('Manage notifications', 'safecontracts'), __('Manage notification rules, schedules, templates and permitted delivery actions.', 'safecontracts')),
            Capabilities::RUN_IMPORTS => self::item(__('Run imports', 'safecontracts'), __('Upload, validate, map and execute controlled data imports.', 'safecontracts')),
            Capabilities::VIEW_AUDIT => self::item(__('View audit history', 'safecontracts'), __('Review protected operational history and audit evidence.', 'safecontracts')),
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
        return ['label' => $label, 'description' => $description];
    }
}
