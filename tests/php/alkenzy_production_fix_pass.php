<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Admin\DashboardFilters;
use SafeContracts\Support\MoneyFormatter;

$tests = 0;

function alkenzy_fix_assert(bool $condition, string $message): void
{
    global $tests;
    $tests++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

alkenzy_fix_assert(MoneyFormatter::format('372000.00', 'EGP') === 'EGP 372,000', 'EGP 372000.00 is displayed without decimal zeros');
alkenzy_fix_assert(MoneyFormatter::format('44000.00', 'EGP') === 'EGP 44,000', 'EGP 44000.00 is displayed without decimal zeros');
alkenzy_fix_assert(MoneyFormatter::format('0.00', 'EGP') === 'EGP 0', 'EGP zero is displayed without decimal zeros');
alkenzy_fix_assert(MoneyFormatter::format('750000.00', 'EGP') === 'EGP 750,000', 'EGP 750000.00 is displayed without decimal zeros');
alkenzy_fix_assert(MoneyFormatter::format('12.50', 'USD') === 'USD 12.5', 'non-EGP money does not retain unnecessary trailing zeros');
alkenzy_fix_assert(MoneyFormatter::format('12.00', 'USD') === 'USD 12', 'non-EGP .00 is removed by the centralized formatter');

$year = DashboardFilters::normalize(['year' => '2025']);
alkenzy_fix_assert($year['year'] === 2025, 'dashboard year is normalized');
alkenzy_fix_assert($year['date_from'] === '2025-01-01', 'year begins at January 1');
alkenzy_fix_assert($year['date_to'] === '2025-12-31', 'year ends at December 31');
$yearOverride = DashboardFilters::normalize(['year' => '2026', 'date_from' => '2025-06-01', 'date_to' => '2025-06-30']);
alkenzy_fix_assert($yearOverride['date_from'] === '2026-01-01' && $yearOverride['date_to'] === '2026-12-31', 'selected year is the authoritative full-calendar-year context');

$root = dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts';
$dashboard = (string) file_get_contents($root . '/src/Admin/DashboardV2Page.php');
$settlements = (string) file_get_contents($root . '/src/Admin/AdminFinancialSettlementSummary.php');
$contracts = (string) file_get_contents($root . '/src/Admin/ContractsPage.php');
$summaryInjector = (string) file_get_contents($root . '/src/Admin/AdminPageSummaryInjector.php');
$schedule = (string) file_get_contents($root . '/src/Notifications/NotificationScheduleRepository.php');
$engine = (string) file_get_contents($root . '/src/Notifications/NotificationEngine.php');
$center = (string) file_get_contents($root . '/src/Admin/NotificationCenterPage.php');
$emailPage = (string) file_get_contents($root . '/src/Admin/EmailSettingsPage.php');
$plugin = (string) file_get_contents($root . '/safecontracts.php');

alkenzy_fix_assert(str_contains($dashboard, 'AdminFinancialSettlementSummary') && str_contains($dashboard, "'settled_total'"), 'dashboard settled totals come from actual settlement aggregation');
alkenzy_fix_assert(! str_contains($dashboard, "['paid_amount']") && ! str_contains($dashboard, "['remaining_amount']"), 'dashboard totals do not use scheduled-payment paid/remaining snapshots as actual settlements');
alkenzy_fix_assert(str_contains($dashboard, "name=\"year\"") && str_contains($dashboard, 'AdminYearOptions::forCurrentUser'), 'dashboard exposes a dynamic year filter');
alkenzy_fix_assert(str_contains($dashboard, "'financial_direction' =") || str_contains($dashboard, "['financial_direction'] ="), 'dashboard drill-down writes canonical financial direction');
alkenzy_fix_assert(str_contains($dashboard, 'hasDirectionData') && str_contains($dashboard, '$rows !== []'), 'dashboard conditionally suppresses empty financial containers');

alkenzy_fix_assert(str_contains($settlements, 'safecontracts_payment_collections'), 'actual settlement summary reads payment_collections ledger');
alkenzy_fix_assert(str_contains($settlements, 'cl.is_archived = 0') && str_contains($settlements, 'p.is_archived = 0') && str_contains($settlements, 'c.is_archived = 0'), 'actual settlement summary excludes archived/deleted financial records');
alkenzy_fix_assert(str_contains($settlements, "IN ('receivable','payable')") && str_contains($settlements, 'GROUP BY cl.financial_direction'), 'actual settlement totals are direction-aware');
alkenzy_fix_assert(str_contains($settlements, 'cl.collection_date >=') && str_contains($settlements, 'cl.collection_date <='), 'actual settlements obey the selected period/year context');

alkenzy_fix_assert(str_contains($contracts, 'name="financial_direction"') && str_contains($contracts, 'FinancialDirection::RECEIVABLE') && str_contains($contracts, 'FinancialDirection::PAYABLE'), 'contracts page exposes canonical receivable/payable type filter');
alkenzy_fix_assert(str_contains($contracts, 'name="year"') && str_contains($contracts, 'AdminYearOptions::forCurrentUser'), 'contracts page exposes the same year context');
alkenzy_fix_assert(str_contains($contracts, 'MoneyFormatter::format'), 'contracts page uses centralized money formatting');
alkenzy_fix_assert(str_contains($contracts, 'if ($contracts !== [])'), 'contracts table container is not rendered for an empty result set');
alkenzy_fix_assert(str_contains($summaryInjector, 'ContractsPage::SLUG') && str_contains($summaryInjector, 'in_array($page'), 'contracts page is excluded from injected top summary cards');

alkenzy_fix_assert(str_contains($schedule, "LEFT JOIN {$suppliers} su") || str_contains($schedule, 'LEFT JOIN {$suppliers} su'), 'notification scheduling joins suppliers');
alkenzy_fix_assert(str_contains($schedule, "p.financial_direction IN ('receivable','payable')"), 'notification scheduling accepts both receivable and payable obligations');
alkenzy_fix_assert(str_contains($schedule, 'counterparty_name') && str_contains($schedule, 'supplier_name'), 'payable notifications receive supplier counterparty context');
alkenzy_fix_assert(str_contains($schedule, 'ON DUPLICATE KEY UPDATE'), 'notification schedule remains idempotent');
alkenzy_fix_assert(str_contains($engine, "'financial_direction' => \$direction") && str_contains($engine, "'counterparty_name' => \$counterpartyName"), 'notification engine carries direction-aware context into templates/payload');

alkenzy_fix_assert(str_contains($center, 'NotificationInboxState') && str_contains($center, 'read_state') && str_contains($center, 'Mark all as read'), 'notification center is a read/unread inbox with filters and actions');
alkenzy_fix_assert(! str_contains($center, 'name="from_address"') && ! str_contains($center, 'name="from_name"'), 'notification center no longer renders email settings fields');
alkenzy_fix_assert(str_contains($emailPage, 'safecontracts-email-settings') && str_contains($emailPage, 'EmailSettings')->__toString() === false, 'standalone email settings source exists');
alkenzy_fix_assert(str_contains($plugin, 'EmailSettingsPage::register()'), 'standalone email settings page is registered at plugin boot');

// Source-level guard for the dedicated page without relying on translated text.
alkenzy_fix_assert(str_contains($emailPage, 'EmailSettingsPage') && str_contains($emailPage, 'from_address') && str_contains($emailPage, 'from_name'), 'sender settings are rendered only by dedicated Email Settings page');

echo "ALKENZY ADV production finance fix regression passed ({$tests} assertions).\n";
