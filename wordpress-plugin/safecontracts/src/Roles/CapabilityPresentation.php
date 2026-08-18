<?php

declare(strict_types=1);

namespace SafeContracts\Roles;

final class CapabilityPresentation
{
    /** @return array{group:string,label:string,description:string} */
    public static function describe(string $capability): array
    {
        return self::definitions()[$capability] ?? [
            'group' => __('Other permissions', 'safecontracts'),
            'label' => __('Additional system permission', 'safecontracts'),
            'description' => __('Allows access to an additional Safe Contracts function.', 'safecontracts'),
        ];
    }

    /** @return array<string,array{group:string,label:string,description:string}> */
    public static function definitions(): array
    {
        return [
            Capabilities::ACCESS => self::item('General access', 'Access Safe Contracts', 'Open and use the Safe Contracts application.'),
            Capabilities::MANAGE_SYSTEM => self::item('System administration', 'Manage system settings', 'Change system-wide Safe Contracts configuration and administrative settings.'),
            Capabilities::MANAGE_REFERENCE_DATA => self::item('System administration', 'Manage reference lists', 'Maintain controlled lists used by forms, filters and business workflows.'),
            Capabilities::MANAGE_USERS => self::item('Users & access', 'Manage users and roles', 'Assign Safe Contracts roles and control business permissions for users.'),
            Capabilities::VIEW_ALL => self::item('Data access', 'View all business records', 'View records across all customers, suppliers and assigned owners.'),
            Capabilities::VIEW_ASSIGNED => self::item('Data access', 'View assigned records', 'View records assigned to the signed-in user.'),
            Capabilities::CREATE_CUSTOMERS => self::item('Customers', 'Create customers', 'Add new customer records.'),
            Capabilities::EDIT_CUSTOMERS => self::item('Customers', 'Edit customers', 'Update existing customer details.'),
            Capabilities::VIEW_SUPPLIERS => self::item('Suppliers', 'View suppliers', 'View supplier records and supplier-related information.'),
            Capabilities::CREATE_SUPPLIERS => self::item('Suppliers', 'Create suppliers', 'Add new supplier records.'),
            Capabilities::EDIT_SUPPLIERS => self::item('Suppliers', 'Edit suppliers', 'Update existing supplier details.'),
            Capabilities::ARCHIVE_SUPPLIERS => self::item('Suppliers', 'Archive suppliers', 'Archive supplier records that should no longer be active.'),
            Capabilities::MANAGE_SUPPLIERS => self::item('Suppliers', 'Manage supplier operations', 'Perform supplier administration beyond normal create and edit actions.'),
            Capabilities::CREATE_CONTRACTS => self::item('Contracts', 'Create contracts', 'Add new customer or supplier contracts.'),
            Capabilities::EDIT_CONTRACTS => self::item('Contracts', 'Edit contracts', 'Update contract details and permitted contract configuration.'),
            Capabilities::ASSIGN_CONTRACTS => self::item('Contracts', 'Assign contracts', 'Assign contracts to responsible users or teams.'),
            Capabilities::CREATE_PAYMENTS => self::item('Payments', 'Create payment schedules', 'Create payment or collection obligations for contracts.'),
            Capabilities::EDIT_PAYMENTS => self::item('Payments', 'Edit payment schedules', 'Update permitted payment schedule details.'),
            Capabilities::MANAGE_PAYMENTS => self::item('Payments', 'Manage payments', 'Perform advanced payment administration and payment lifecycle actions.'),
            Capabilities::VIEW_FINANCE => self::item('Finance', 'View finance', 'View financial summaries, balances and finance work areas.'),
            Capabilities::MANAGE_FINANCE => self::item('Finance', 'Manage finance', 'Perform financial settlement and finance administration actions.'),
            Capabilities::VIEW_PAYABLES => self::item('Finance', 'View supplier payables', 'View amounts the organization owes to suppliers.'),
            Capabilities::VIEW_RECEIVABLES => self::item('Finance', 'View customer receivables', 'View amounts customers owe to the organization.'),
            Capabilities::MANAGE_COLLECTIONS => self::item('Collections', 'Manage collections', 'Record and manage customer collection activity.'),
            Capabilities::MANAGE_FOLLOWUPS => self::item('Follow-up', 'Manage follow-ups', 'Create and update collection and payment follow-up actions.'),
            Capabilities::VIEW_REPORTS => self::item('Reports', 'View reports', 'Open Safe Contracts operational and financial reports.'),
            Capabilities::EXPORT_REPORTS => self::item('Reports', 'Export reports', 'Export permitted reports and business data.'),
            Capabilities::MANAGE_NOTIFICATIONS => self::item('Notifications', 'Manage notifications', 'Configure notification rules, schedules and delivery settings.'),
            Capabilities::RUN_IMPORTS => self::item('Imports', 'Run data imports', 'Upload, preview and execute supported Safe Contracts imports.'),
            Capabilities::VIEW_AUDIT => self::item('Audit', 'View audit history', 'View audit events and change history available to the user.'),
        ];
    }

    /** @return array{group:string,label:string,description:string} */
    private static function item(string $group, string $label, string $description): array
    {
        return [
            'group' => __($group, 'safecontracts'),
            'label' => __($label, 'safecontracts'),
            'description' => __($description, 'safecontracts'),
        ];
    }
}
