<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

$tests = 0;
function sc_400_assert(bool $ok, string $message): void
{
    global $tests;
    $tests++;
    if (! $ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}
function sc_400_source(string $relative): string
{
    $path = dirname(__DIR__, 2) . '/' . $relative;
    $source = file_get_contents($path);
    sc_400_assert($source !== false, '#400 source exists: ' . $relative);
    return $source === false ? '' : $source;
}

$deletion = sc_400_source('wordpress-plugin/safecontracts/src/Deletion/SafeDeletionService.php');
$migration = sc_400_source('wordpress-plugin/safecontracts/src/Database/Migrations/Migration0013SafeDeletion.php');
$migrator = sc_400_source('wordpress-plugin/safecontracts/src/Database/Migrator.php');
$audit = sc_400_source('wordpress-plugin/safecontracts/src/Audit/SafeDeletionAuditRecorder.php');
$plugin = sc_400_source('wordpress-plugin/safecontracts/src/Plugin.php');
$read = sc_400_source('wordpress-plugin/safecontracts/src/Admin/AdminReadRepository.php');
$dashboard = sc_400_source('wordpress-plugin/safecontracts/src/Admin/DashboardPage.php');
$feedbackJs = sc_400_source('wordpress-plugin/safecontracts/assets/admin/safecontracts-admin-feedback.js');
$followups = sc_400_source('wordpress-plugin/safecontracts/src/FollowUps/FollowUpRepository.php');
$translationCatalog = sc_400_source('wordpress-plugin/safecontracts/src/Translations/TranslationCatalog.php');

// Safe-delete semantics: no physical financial/history deletion.
sc_400_assert(! str_contains(strtoupper($deletion), 'DELETE FROM'), '#400 safe deletion service contains no SQL hard delete');
foreach (['archiveCustomer', 'archivePayment', 'archiveCollection', 'archivePaymentMethod'] as $method) {
    sc_400_assert(str_contains($deletion, 'function ' . $method), '#400 safe deletion service exposes ' . $method);
}
foreach (['START TRANSACTION', 'ROLLBACK', 'ContractMoney::subtract', 'PaymentStatus::temporalForDueDate', 'is_archived = 1'] as $marker) {
    sc_400_assert(str_contains($deletion, $marker), '#400 collection/payment deletion safety marker exists: ' . $marker);
}
sc_400_assert(str_contains($deletion, 'Payments with collected amounts cannot be deleted'), '#400 payment deletion protects collected financial evidence');
sc_400_assert(str_contains($deletion, 'Reverse their collections first'), '#400 payment deletion requires collection reversal before archive');

// Schema is additive and migration registry is contiguous through 1.12.0.
foreach (['is_archived', 'archived_by', 'archived_at', 'archived_payment_date', 'archived_due'] as $marker) {
    sc_400_assert(str_contains($migration, $marker), '#400 additive archive schema contains ' . $marker);
}
sc_400_assert(str_contains($migrator, "public const LATEST_VERSION = '1.12.0';"), '#400 migrator latest version is 1.12.0');
sc_400_assert(str_contains($migrator, "'1.12.0' => Migration0013SafeDeletion::class"), '#400 migration 1.12.0 is registered');

// Every requested admin surface has capability+nonce localized Delete wiring.
$pageContracts = [
    'wordpress-plugin/safecontracts/src/Admin/CustomersPage.php' => ['DELETE_ACTION', 'archiveCustomer', 'MANAGE_REFERENCE_DATA'],
    'wordpress-plugin/safecontracts/src/Admin/ContractsPage.php' => ['DELETE_ACTION', 'ContractArchiveService', 'MANAGE_SYSTEM'],
    'wordpress-plugin/safecontracts/src/Admin/PaymentsPage.php' => ['DELETE_ACTION', 'archivePayment', 'MANAGE_PAYMENTS'],
    'wordpress-plugin/safecontracts/src/Admin/CollectionsPage.php' => ['DELETE_ACTION', 'archiveCollection', 'MANAGE_COLLECTIONS'],
    'wordpress-plugin/safecontracts/src/Admin/PaymentMethodsPage.php' => ['DELETE_ACTION', 'archivePaymentMethod', 'MANAGE_REFERENCE_DATA'],
];
foreach ($pageContracts as $path => $markers) {
    $source = sc_400_source($path);
    foreach ($markers as $marker) {
        sc_400_assert(str_contains($source, $marker), '#400 delete page marker exists in ' . $path . ': ' . $marker);
    }
    sc_400_assert(str_contains($source, 'check_admin_referer'), '#400 delete page uses nonce protection: ' . $path);
    sc_400_assert(str_contains($source, 'data-safecontracts-delete-form'), '#400 delete page uses explicit confirmation: ' . $path);
    sc_400_assert(str_contains($source, "esc_html__('Delete', 'safecontracts')"), '#400 delete action uses SafeContracts localization domain: ' . $path);
    sc_400_assert(! str_contains($source, 'Delete / حذف') && ! str_contains($source, ' / حذف'), '#404 delete action no longer hard-codes two languages: ' . $path);
}
sc_400_assert(str_contains($translationCatalog, "'Delete' => 'حذف'"), '#400/#404 central catalog preserves Arabic delete translation');

foreach (['CustomersPage::DELETE_ACTION', 'ContractsPage::DELETE_ACTION', 'PaymentsPage::DELETE_ACTION', 'CollectionsPage::DELETE_ACTION', 'PaymentMethodsPage::DELETE_ACTION'] as $marker) {
    sc_400_assert(str_contains($plugin, $marker), '#400 plugin boots delete handler: ' . $marker);
}
sc_400_assert(str_contains($plugin, 'SafeDeletionAuditRecorder::register()'), '#400 safe deletion audit recorder is booted');
foreach (['customer_archived', 'payment_archived', 'collection_archived', 'payment_method_archived'] as $event) {
    sc_400_assert(str_contains($audit, $event), '#400 audit event is durable: ' . $event);
}

// Archived records must leave active dashboards, ledgers and follow-up queues.
foreach (['c.is_archived = 0', 'p.is_archived = 0', 'cl.is_archived = 0'] as $marker) {
    sc_400_assert(str_contains($read, $marker), '#400 operational reads exclude archived rows: ' . $marker);
}
sc_400_assert(str_contains($followups, 'p.is_archived = 0'), '#400 follow-up queue excludes archived payments');

// Dashboard currency comes from SafeContracts settings; amounts remain values, not recalculated currency conversions.
foreach (['GeneralSettings', 'currency_symbol', 'currency_code', 'safecontracts-currency-badge', 'self::money'] as $marker) {
    sc_400_assert(str_contains($dashboard, $marker), '#400 dashboard currency marker exists: ' . $marker);
}
sc_400_assert(! str_contains($dashboard, 'exchange_rate') && ! str_contains($dashboard, 'currency_convert'), '#400 dashboard never recalculates authoritative amounts');

// Per-record confirmations are supported and locale remains observational only.
sc_400_assert(str_contains($feedbackJs, 'form.dataset.deleteMessage'), '#400 delete confirmations support record-specific safety copy');
sc_400_assert(! str_contains($plugin, "add_filter('locale'") && ! str_contains($plugin, 'switch_to_locale'), '#400 plugin bootstrap does not force WordPress locale');
sc_400_assert(! str_contains($deletion, "add_filter('locale'") && ! str_contains($deletion, 'switch_to_locale'), '#400 safe deletion does not alter WordPress locale');

printf("SafeContracts issue #400/#404 admin safe-delete/currency localization regression passed (%d assertions).\n", $tests);
