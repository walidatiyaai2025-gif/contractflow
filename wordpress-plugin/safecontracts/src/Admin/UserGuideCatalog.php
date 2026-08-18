<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Roles\Capabilities;

final class UserGuideCatalog
{
    /**
     * @return array<string,array{
     *   title:string,
     *   capability:string,
     *   purpose:string,
     *   steps:list<string>,
     *   related:list<array{slug:string,label:string,capability:string}>
     * }>
     */
    public static function entries(): array
    {
        return [
            AdminShell::SLUG => self::entry(
                'Dashboard', Capabilities::ACCESS,
                'Dashboard gives you a current operational summary and shortcuts to the main business areas.',
                ['Review the key indicators first, then open the related list to investigate the underlying records.', 'Use filters and search to find an existing record before creating a duplicate.'],
                [self::related(ContractsPage::SLUG, 'Contracts', Capabilities::ACCESS), self::related(FinancePage::SLUG, 'Finance', Capabilities::VIEW_FINANCE), self::related(ReportsPage::SLUG, 'Reports', Capabilities::VIEW_REPORTS)]
            ),
            CustomersPage::SLUG => self::entry(
                'Customers', Capabilities::ACCESS,
                'Customers stores customer master data used by receivable contracts and collection workflows.',
                ['Use filters and search to find an existing record before creating a duplicate.', 'Create or edit a customer here, then go to Contracts to create a customer receivable contract.'],
                [self::related(ContractsPage::SLUG, 'Contracts', Capabilities::ACCESS), self::related(CollectionsPage::SLUG, 'Collections', Capabilities::MANAGE_COLLECTIONS)]
            ),
            SuppliersPage::SLUG => self::entry(
                'Suppliers', Capabilities::VIEW_SUPPLIERS,
                'Suppliers stores supplier master data used by payable contracts and payment obligations.',
                ['Use filters and search to find an existing record before creating a duplicate.', 'Create or edit a supplier here, then go to Contracts to create a supplier payable contract.'],
                [self::related(ContractsPage::SLUG, 'Contracts', Capabilities::ACCESS), self::related(FinancePage::SLUG, 'Finance', Capabilities::VIEW_FINANCE)]
            ),
            ContractsPage::SLUG => self::entry(
                'Contracts', Capabilities::ACCESS,
                'Contracts is the authoritative workspace for customer receivable and supplier payable agreements.',
                ['Choose the counterparty from the provided list, confirm direction and dates, then save the contract.', 'Review the selected business entity and the entered dates before saving.'],
                [self::related(CustomersPage::SLUG, 'Customers', Capabilities::ACCESS), self::related(SuppliersPage::SLUG, 'Suppliers', Capabilities::VIEW_SUPPLIERS), self::related(PaymentsPage::SLUG, 'Payments', Capabilities::ACCESS)]
            ),
            PaymentsPage::SLUG => self::entry(
                'Payments', Capabilities::ACCESS,
                'Payments manages contractual due schedule entries and their outstanding balances.',
                ['Choose the contract from the list, review due date and amount, then save the schedule entry.', 'Select records from the available lists instead of typing internal IDs or codes.'],
                [self::related(ContractsPage::SLUG, 'Contracts', Capabilities::ACCESS), self::related(CollectionsPage::SLUG, 'Collections', Capabilities::MANAGE_COLLECTIONS), self::related(FollowUpsPage::SLUG, 'Follow-up', Capabilities::MANAGE_FOLLOWUPS)]
            ),
            CollectionsPage::SLUG => self::entry(
                'Collections', Capabilities::MANAGE_COLLECTIONS,
                'Collections records money received against authorized receivable payments.',
                ['Choose the payment from the available list and record the receipt details; do not type payment IDs manually.', 'Review the selected business entity and the entered dates before saving.'],
                [self::related(PaymentsPage::SLUG, 'Payments', Capabilities::ACCESS), self::related(FinancePage::SLUG, 'Finance', Capabilities::VIEW_FINANCE)]
            ),
            FollowUpsPage::SLUG => self::entry(
                'Follow-up', Capabilities::MANAGE_FOLLOWUPS,
                'Follow-up tracks operational contact, promises and escalation for outstanding receivables.',
                ['Select an outstanding payment from the queue, review its history, then add the next follow-up action.', 'Select records from the available lists instead of typing internal IDs or codes.'],
                [self::related(PaymentsPage::SLUG, 'Payments', Capabilities::ACCESS), self::related(ReportsPage::SLUG, 'Reports', Capabilities::VIEW_REPORTS)]
            ),
            NotificationCenterPage::SLUG => self::entry(
                'Notification Center', Capabilities::MANAGE_NOTIFICATIONS,
                'Notification Center manages templates, rules and controlled direct notification operations.',
                ['Review the rule and recipient scope before sending or changing notification content.', 'Review the selected business entity and the entered dates before saving.'],
                [self::related(NotificationsPage::SLUG, 'Notifications', Capabilities::MANAGE_NOTIFICATIONS), self::related(NotificationSettingsPage::SLUG, 'Notification Settings', Capabilities::MANAGE_NOTIFICATIONS)]
            ),
            NotificationsPage::SLUG => self::entry(
                'Notifications', Capabilities::MANAGE_NOTIFICATIONS,
                'Notifications shows operational notification activity and delivery outcomes.',
                ['Use delivery status and filters here; change configuration from Notification Settings when needed.', 'Use filters and search to find an existing record before creating a duplicate.'],
                [self::related(NotificationSettingsPage::SLUG, 'Notification Settings', Capabilities::MANAGE_NOTIFICATIONS), self::related(NotificationSchedulePage::SLUG, 'Notification Schedule', Capabilities::MANAGE_NOTIFICATIONS)]
            ),
            NotificationSchedulePage::SLUG => self::entry(
                'Notification Schedule', Capabilities::MANAGE_NOTIFICATIONS,
                'Notification Schedule controls when scheduled reminder work is executed and reviewed.',
                ['Review the configured schedule and pending rows before running any permitted manual action.', 'Review the rule and recipient scope before sending or changing notification content.'],
                [self::related(NotificationCenterPage::SLUG, 'Notification Center', Capabilities::MANAGE_NOTIFICATIONS), self::related(NotificationSettingsPage::SLUG, 'Notification Settings', Capabilities::MANAGE_NOTIFICATIONS)]
            ),
            FinancePage::SLUG => self::entry(
                'Finance', Capabilities::VIEW_FINANCE,
                'Finance combines authorized receivable, payable, aging and cash-flow views.',
                ['Start from the finance summary, then open the relevant customer, supplier or contract for source details.', 'Use filters and search to find an existing record before creating a duplicate.'],
                [self::related(CustomersPage::SLUG, 'Customers', Capabilities::ACCESS), self::related(SuppliersPage::SLUG, 'Suppliers', Capabilities::VIEW_SUPPLIERS), self::related(ContractsPage::SLUG, 'Contracts', Capabilities::ACCESS)]
            ),
            ReportsPage::SLUG => self::entry(
                'Reports', Capabilities::VIEW_REPORTS,
                'Reports provides server-calculated operational and financial reporting for your authorized scope.',
                ['Set the required filters, run the report, then export only after reviewing the on-screen result.', 'Use filters and search to find an existing record before creating a duplicate.'],
                [self::related(FinancePage::SLUG, 'Finance', Capabilities::VIEW_FINANCE), self::related(AdminShell::SLUG, 'Dashboard', Capabilities::ACCESS)]
            ),
            ActiveUsersPage::SLUG => self::entry(
                'Active Users', Capabilities::MANAGE_USERS,
                'Active Users shows current system activity without exposing authentication secrets.',
                ['Use this page for operational visibility; manage role membership from Users & Roles.', 'Use filters and search to find an existing record before creating a duplicate.'],
                [self::related(UsersRolesPage::SLUG, 'Users & Roles', Capabilities::MANAGE_USERS)]
            ),
            UsersRolesPage::SLUG => self::entry(
                'Users & Roles', Capabilities::MANAGE_USERS,
                'Users & Roles controls Alkenzy ADV role membership and business permissions.',
                ['Choose a user and role from the lists, then configure permissions using the clear business labels.', 'Select records from the available lists instead of typing internal IDs or codes.'],
                [self::related(ActiveUsersPage::SLUG, 'Active Users', Capabilities::MANAGE_USERS)]
            ),
            ArchivePage::SLUG => self::entry(
                'Archive', Capabilities::VIEW_AUDIT,
                'Archive contains records removed from active operations while preserving required evidence.',
                ['Review archived history here; return to the source business page to work with active records.', 'Use filters and search to find an existing record before creating a duplicate.'],
                [self::related(CustomersPage::SLUG, 'Customers', Capabilities::ACCESS), self::related(ContractsPage::SLUG, 'Contracts', Capabilities::ACCESS)]
            ),
            ImportsPage::SLUG => self::entry(
                'Imports', Capabilities::RUN_IMPORTS,
                'Imports provides a controlled path for bringing validated workbook data into Alkenzy ADV.',
                ['Upload the workbook, inspect it, map columns, review validation results, then execute the import.', 'Review the selected business entity and the entered dates before saving.'],
                [self::related(ReportsPage::SLUG, 'Reports', Capabilities::VIEW_REPORTS)]
            ),
            GeneralSettingsPage::SLUG => self::entry(
                'Settings', Capabilities::MANAGE_SYSTEM,
                'Settings contains organization-wide operational preferences used by Alkenzy ADV.',
                ['Change settings only after confirming the production impact; use controlled choices where provided.', 'Review the selected business entity and the entered dates before saving.'],
                [self::related(MobileConfigurationPage::SLUG, 'Mobile Configuration', Capabilities::MANAGE_SYSTEM), self::related(TranslationsPage::SLUG, 'Translations', Capabilities::MANAGE_SYSTEM)]
            ),
            PaymentMethodsPage::SLUG => self::entry(
                'Payment Methods', Capabilities::MANAGE_REFERENCE_DATA,
                'Payment Methods maintains the controlled list of methods users can choose when recording collections.',
                ['Create or deactivate a method here, then choose it from the list when recording a collection.', 'Use filters and search to find an existing record before creating a duplicate.'],
                [self::related(CollectionsPage::SLUG, 'Collections', Capabilities::MANAGE_COLLECTIONS)]
            ),
            NotificationSettingsPage::SLUG => self::entry(
                'Notification Settings', Capabilities::MANAGE_NOTIFICATIONS,
                'Notification Settings controls notification behavior and recipient rules.',
                ['Review trigger timing, recipient scope and repeat limits before saving a rule.', 'Review the rule and recipient scope before sending or changing notification content.'],
                [self::related(NotificationCenterPage::SLUG, 'Notification Center', Capabilities::MANAGE_NOTIFICATIONS), self::related(NotificationSchedulePage::SLUG, 'Notification Schedule', Capabilities::MANAGE_NOTIFICATIONS)]
            ),
            FirebaseSettingsPage::SLUG => self::entry(
                'Firebase Settings', Capabilities::MANAGE_SYSTEM,
                'Firebase Settings connects Alkenzy ADV mobile notifications to the approved Firebase project.',
                ['Verify the project and service account, run the connection test, then test notification delivery.', 'Change settings only after confirming the production impact; use controlled choices where provided.'],
                [self::related(MobileConfigurationPage::SLUG, 'Mobile Configuration', Capabilities::MANAGE_SYSTEM), self::related(NotificationSettingsPage::SLUG, 'Notification Settings', Capabilities::MANAGE_NOTIFICATIONS)]
            ),
            MobileConfigurationPage::SLUG => self::entry(
                'Mobile Configuration', Capabilities::MANAGE_SYSTEM,
                'Mobile Configuration controls server-provided mobile feature flags and safe defaults.',
                ['Review each feature flag and page-size setting before publishing configuration changes.', 'Change settings only after confirming the production impact; use controlled choices where provided.'],
                [self::related(FirebaseSettingsPage::SLUG, 'Firebase Settings', Capabilities::MANAGE_SYSTEM), self::related(TranslationsPage::SLUG, 'Translations', Capabilities::MANAGE_SYSTEM)]
            ),
            TranslationsPage::SLUG => self::entry(
                'Translations', Capabilities::MANAGE_SYSTEM,
                'Translations manages the English and Arabic wording used across Alkenzy ADV surfaces.',
                ['Search for the source phrase, edit the required language override, then save and verify the affected screen.', 'Change settings only after confirming the production impact; use controlled choices where provided.'],
                [self::related(UserGuidePage::SLUG, 'User Guide', Capabilities::ACCESS)]
            ),
            UserGuidePage::SLUG => self::entry(
                'User Guide', Capabilities::ACCESS,
                'The User Guide explains every Alkenzy ADV area and links you to the next related task.',
                ['Choose a section below to read its purpose and recommended steps, then open that page when you are ready.', 'Only sections available to your current role are shown.'],
                [self::related(AdminShell::SLUG, 'Dashboard', Capabilities::ACCESS)]
            ),
        ];
    }

    /** @return array<string,mixed>|null */
    public static function forPage(string $slug): ?array
    {
        $entry = self::entries()[$slug] ?? null;
        return is_array($entry) ? $entry : null;
    }

    /** @return array<string,array<string,mixed>> */
    public static function visibleEntries(): array
    {
        return array_filter(
            self::entries(),
            static fn (array $entry): bool => current_user_can((string) $entry['capability'])
        );
    }

    /** @return array{title:string,capability:string,purpose:string,steps:list<string>,related:list<array{slug:string,label:string,capability:string}>} */
    private static function entry(string $title, string $capability, string $purpose, array $steps, array $related): array
    {
        return [
            'title' => __($title, 'safecontracts'),
            'capability' => $capability,
            'purpose' => __($purpose, 'safecontracts'),
            'steps' => array_map(static fn (string $step): string => __($step, 'safecontracts'), $steps),
            'related' => $related,
        ];
    }

    /** @return array{slug:string,label:string,capability:string} */
    private static function related(string $slug, string $label, string $capability): array
    {
        return ['slug' => $slug, 'label' => __($label, 'safecontracts'), 'capability' => $capability];
    }
}
