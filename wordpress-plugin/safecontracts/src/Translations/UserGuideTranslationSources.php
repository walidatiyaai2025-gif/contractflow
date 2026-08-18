<?php

declare(strict_types=1);

namespace SafeContracts\Translations;

/**
 * Static discovery hints for UserGuideCatalog wording.
 *
 * The guide catalog builds entries from reusable strings. TranslationCatalog
 * intentionally discovers literal gettext calls from source files, so these
 * no-op hints keep every guide sentence visible/editable in the translation
 * dashboard while runtime resolution still happens in UserGuideCatalog.
 */
final class UserGuideTranslationSources
{
    public static function hints(): void
    {
        __('Dashboard gives you a current operational summary and shortcuts to the main business areas.', 'safecontracts');
        __('Review the key indicators first, then open the related list to investigate the underlying records.', 'safecontracts');
        __('Use filters and search to find an existing record before creating a duplicate.', 'safecontracts');
        __('Customers stores customer master data used by receivable contracts and collection workflows.', 'safecontracts');
        __('Create or edit a customer here, then go to Contracts to create a customer receivable contract.', 'safecontracts');
        __('Suppliers stores supplier master data used by payable contracts and payment obligations.', 'safecontracts');
        __('Create or edit a supplier here, then go to Contracts to create a supplier payable contract.', 'safecontracts');
        __('Contracts is the authoritative workspace for customer receivable and supplier payable agreements.', 'safecontracts');
        __('Choose the counterparty from the provided list, confirm direction and dates, then save the contract.', 'safecontracts');
        __('Review the selected business entity and the entered dates before saving.', 'safecontracts');
        __('Payments manages contractual due schedule entries and their outstanding balances.', 'safecontracts');
        __('Choose the contract from the list, review due date and amount, then save the schedule entry.', 'safecontracts');
        __('Select records from the available lists instead of typing internal IDs or codes.', 'safecontracts');
        __('Collections records money received against authorized receivable payments.', 'safecontracts');
        __('Choose the payment from the available list and record the receipt details; do not type payment IDs manually.', 'safecontracts');
        __('Follow-up tracks operational contact, promises and escalation for outstanding receivables.', 'safecontracts');
        __('Select an outstanding payment from the queue, review its history, then add the next follow-up action.', 'safecontracts');
        __('Notification Center manages templates, rules and controlled direct notification operations.', 'safecontracts');
        __('Review the rule and recipient scope before sending or changing notification content.', 'safecontracts');
        __('Notifications shows operational notification activity and delivery outcomes.', 'safecontracts');
        __('Use delivery status and filters here; change configuration from Notification Settings when needed.', 'safecontracts');
        __('Notification Schedule controls when scheduled reminder work is executed and reviewed.', 'safecontracts');
        __('Review the configured schedule and pending rows before running any permitted manual action.', 'safecontracts');
        __('Finance combines authorized receivable, payable, aging and cash-flow views.', 'safecontracts');
        __('Start from the finance summary, then open the relevant customer, supplier or contract for source details.', 'safecontracts');
        __('Reports provides server-calculated operational and financial reporting for your authorized scope.', 'safecontracts');
        __('Set the required filters, run the report, then export only after reviewing the on-screen result.', 'safecontracts');
        __('Active Users shows current system activity without exposing authentication secrets.', 'safecontracts');
        __('Use this page for operational visibility; manage role membership from Users & Roles.', 'safecontracts');
        __('Users & Roles controls Alkenzy ADV role membership and business permissions.', 'safecontracts');
        __('Choose a user and role from the lists, then configure permissions using the clear business labels.', 'safecontracts');
        __('Archive contains records removed from active operations while preserving required evidence.', 'safecontracts');
        __('Review archived history here; return to the source business page to work with active records.', 'safecontracts');
        __('Imports provides a controlled path for bringing validated workbook data into Alkenzy ADV.', 'safecontracts');
        __('Upload the workbook, inspect it, map columns, review validation results, then execute the import.', 'safecontracts');
        __('Settings contains organization-wide operational preferences used by Alkenzy ADV.', 'safecontracts');
        __('Change settings only after confirming the production impact; use controlled choices where provided.', 'safecontracts');
        __('Payment Methods maintains the controlled list of methods users can choose when recording collections.', 'safecontracts');
        __('Create or deactivate a method here, then choose it from the list when recording a collection.', 'safecontracts');
        __('Notification Settings controls notification behavior and recipient rules.', 'safecontracts');
        __('Review trigger timing, recipient scope and repeat limits before saving a rule.', 'safecontracts');
        __('Firebase Settings connects Alkenzy ADV mobile notifications to the approved Firebase project.', 'safecontracts');
        __('Verify the project and service account, run the connection test, then test notification delivery.', 'safecontracts');
        __('Mobile Configuration controls server-provided mobile feature flags and safe defaults.', 'safecontracts');
        __('Review each feature flag and page-size setting before publishing configuration changes.', 'safecontracts');
        __('Translations manages the English and Arabic wording used across Alkenzy ADV surfaces.', 'safecontracts');
        __('Search for the source phrase, edit the required language override, then save and verify the affected screen.', 'safecontracts');
        __('The User Guide explains every Alkenzy ADV area and links you to the next related task.', 'safecontracts');
        __('Choose a section below to read its purpose and recommended steps, then open that page when you are ready.', 'safecontracts');
        __('Only sections available to your current role are shown.', 'safecontracts');
    }
}
