<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Contracts\ContractService;
use SafeContracts\Contracts\DecimalAmount;
use SafeContracts\Contracts\FinancialItemType;
use SafeContracts\Roles\Capabilities;

$financeTests = 0;

function sc_finance_assert(bool $condition, string $message): void
{
    global $financeTests;
    $financeTests++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function sc_finance_expect(string $class, callable $callback, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        sc_finance_assert($error instanceof $class, $message);
        return;
    }

    sc_finance_assert(false, $message);
}

/** @return array<string, mixed> */
function sc_finance_contract(array $overrides = []): array
{
    return array_merge([
        'id' => '501',
        'contract_number' => 'SC-501',
        'customer_id' => '7',
        'accountant_user_id' => '42',
        'status' => 'active',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'base_value' => '1000.0000',
        'notes' => 'Contract notes',
        'is_archived' => '0',
    ], $overrides);
}

$activate = $GLOBALS['sc_test_activation_hooks'][SAFECONTRACTS_FILE];
$activate();

$financeSchema = $GLOBALS['sc_test_dbdelta'][4];
$attachmentSchema = $GLOBALS['sc_test_dbdelta'][5];
sc_finance_assert(str_contains($financeSchema, 'wp_safecontracts_contract_financial_items'), 'financial items use a dedicated prefixed table');
sc_finance_assert(str_contains($financeSchema, 'item_type varchar(16) NOT NULL'), 'financial item type is stored explicitly');
sc_finance_assert(str_contains($financeSchema, 'amount decimal(20,4) NOT NULL'), 'financial item amount uses fixed-point precision');
sc_finance_assert(str_contains($financeSchema, 'KEY contract_type_active_order (contract_id, item_type, is_active, display_order)'), 'financial item reporting path is indexed');
sc_finance_assert(str_contains($attachmentSchema, 'wp_safecontracts_contract_attachments'), 'attachments use a dedicated relation table');
sc_finance_assert(str_contains($attachmentSchema, 'UNIQUE KEY contract_media (contract_id, media_id)'), 'same WordPress media attachment is idempotent per contract');

sc_finance_assert(DecimalAmount::normalize('0012.5') === '12.5000', 'decimal normalization is canonical');
sc_finance_assert(DecimalAmount::add('1000.0000', '25.5000', '4.5000') === '1030.0000', 'fixed-scale addition is exact');
sc_finance_assert(DecimalAmount::subtractNonNegative('1030.0000', '30.0000') === '1000.0000', 'fixed-scale subtraction is exact');
sc_finance_expect(DomainException::class, fn () => DecimalAmount::subtractNonNegative('10', '10.0001'), 'negative reconciliation is rejected');
sc_finance_expect(InvalidArgumentException::class, fn () => DecimalAmount::normalize('1.00001'), 'amounts beyond four decimal places are rejected');

$service = new ContractService();
$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ALL => true,
    Capabilities::EDIT_CONTRACTS => true,
];

$GLOBALS['sc_test_result_queue'] = [[sc_finance_contract()]];
$service->updateDates(501, '2026-02-01', '2026-11-30');
$dateSql = end($GLOBALS['sc_test_queries']);
sc_finance_assert(str_contains((string) $dateSql, "start_date = '2026-02-01'"), 'contract start date is persisted');
sc_finance_assert(str_contains((string) $dateSql, "end_date = '2026-11-30'"), 'contract end date is persisted');
sc_finance_assert(isset($GLOBALS['sc_test_fired_actions']['safecontracts_contract_dates_changed']), 'contract date change emits an audit-ready domain action');

$GLOBALS['sc_test_result_queue'] = [[sc_finance_contract()]];
$beforeBadDates = count($GLOBALS['sc_test_queries']);
sc_finance_expect(InvalidArgumentException::class, fn () => $service->updateDates(501, '2026-12-01', '2026-01-01'), 'end date before start date is rejected');
sc_finance_assert(count($GLOBALS['sc_test_queries']) === $beforeBadDates, 'invalid contract dates do not mutate data');

$GLOBALS['sc_test_result_queue'] = [[sc_finance_contract()]];
$service->setBaseValue(501, '1250.75');
$baseSql = end($GLOBALS['sc_test_queries']);
sc_finance_assert(str_contains((string) $baseSql, "base_value = '1250.7500'"), 'base value is normalized and persisted');
sc_finance_assert(isset($GLOBALS['sc_test_fired_actions']['safecontracts_contract_base_value_changed']), 'base-value change emits an audit-ready action');

$GLOBALS['sc_test_result_queue'] = [[sc_finance_contract()]];
$GLOBALS['wpdb']->insert_id = 3001;
$lineId = $service->addFinancialItem(501, FinancialItemType::LINE, '100.125', 'Media placement', 10);
sc_finance_assert($lineId === 3001, 'financial item create returns inserted ID');
$lineSql = end($GLOBALS['sc_test_queries']);
sc_finance_assert(str_contains((string) $lineSql, "'line'"), 'standard financial line type is persisted');
sc_finance_assert(str_contains((string) $lineSql, "'100.1250'"), 'financial line amount uses canonical fixed precision');

$GLOBALS['sc_test_result_queue'] = [[sc_finance_contract()]];
$GLOBALS['wpdb']->insert_id = 3002;
$service->addFinancialItem(501, 'addition', '25', 'Extra production', 20);
sc_finance_assert(str_contains((string) end($GLOBALS['sc_test_queries']), "'addition'"), 'addition item type is supported');

$GLOBALS['sc_test_result_queue'] = [[sc_finance_contract()]];
$GLOBALS['wpdb']->insert_id = 3003;
$service->addFinancialItem(501, 'discount', '50.5000', 'Commercial discount', 30);
sc_finance_assert(str_contains((string) end($GLOBALS['sc_test_queries']), "'discount'"), 'discount item type is supported');

$GLOBALS['sc_test_result_queue'] = [[sc_finance_contract()]];
$beforeZero = count($GLOBALS['sc_test_queries']);
sc_finance_expect(InvalidArgumentException::class, fn () => $service->addFinancialItem(501, 'line', '0', 'Zero item'), 'zero financial items are rejected');
sc_finance_assert(count($GLOBALS['sc_test_queries']) === $beforeZero, 'invalid zero item does not mutate data');

$GLOBALS['sc_test_result_queue'] = [
    [sc_finance_contract(['base_value' => '1000.0000'])],
    [
        ['item_type' => 'line', 'total' => '200.0000'],
        ['item_type' => 'addition', 'total' => '50.0000'],
        ['item_type' => 'discount', 'total' => '75.0000'],
    ],
];
$reconciliation = $service->reconcile(501);
sc_finance_assert($reconciliation['gross_value'] === '1250.0000', 'gross value equals base + lines + additions');
sc_finance_assert($reconciliation['net_value'] === '1175.0000', 'net value subtracts discounts exactly');
$totalsSql = end($GLOBALS['sc_test_read_queries']);
sc_finance_assert(str_contains((string) $totalsSql, 'WHERE contract_id = 501 AND is_active = 1'), 'reconciliation uses active items for the requested contract only');

$GLOBALS['sc_test_result_queue'] = [
    [sc_finance_contract(['base_value' => '10.0000'])],
    [['item_type' => 'discount', 'total' => '11.0000']],
];
sc_finance_expect(DomainException::class, fn () => $service->reconcile(501), 'discounts cannot silently make net contract value negative');

$GLOBALS['sc_test_result_queue'] = [[sc_finance_contract()]];
$GLOBALS['wpdb']->insert_id = 4001;
$attachmentId = $service->attachMedia(501, 9001, 'Signed contract');
sc_finance_assert($attachmentId === 4001, 'attachment relation returns its ID');
$attachmentSql = end($GLOBALS['sc_test_queries']);
sc_finance_assert(str_contains((string) $attachmentSql, 'wp_safecontracts_contract_attachments'), 'attachment relation uses dedicated table');
sc_finance_assert(str_contains((string) $attachmentSql, 'ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'), 'attachment relinking is idempotent');
sc_finance_assert(isset($GLOBALS['sc_test_fired_actions']['safecontracts_contract_attachment_added']), 'attachment add emits an audit-ready action');

$GLOBALS['sc_test_result_queue'] = [[sc_finance_contract()]];
$service->detachMedia(501, 9001);
sc_finance_assert(str_contains((string) end($GLOBALS['sc_test_queries']), 'SET is_active = 0'), 'attachment removal is non-destructive');

$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ASSIGNED => true,
    Capabilities::EDIT_CONTRACTS => true,
];
$GLOBALS['sc_test_result_queue'] = [[sc_finance_contract(['accountant_user_id' => '99'])]];
$beforeScopeViolation = count($GLOBALS['sc_test_queries']);
sc_finance_expect(DomainException::class, fn () => $service->setBaseValue(501, '5'), 'financial edits cannot bypass Accountant assignment scope');
sc_finance_assert(count($GLOBALS['sc_test_queries']) === $beforeScopeViolation, 'out-of-scope financial edit causes no mutation');

$GLOBALS['sc_test_result_queue'] = [[sc_finance_contract(['accountant_user_id' => '42', 'is_archived' => '1'])]];
$beforeArchived = count($GLOBALS['sc_test_queries']);
sc_finance_expect(DomainException::class, fn () => $service->addFinancialItem(501, 'line', '5', 'Archived mutation'), 'archived contracts reject new financial mutations');
sc_finance_assert(count($GLOBALS['sc_test_queries']) === $beforeArchived, 'archived financial mutation causes no write');

echo "SafeContracts contract finance tests passed ({$financeTests} assertions).\n";
