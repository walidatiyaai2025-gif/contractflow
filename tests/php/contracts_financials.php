<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Contracts\ContractMoney;
use SafeContracts\Contracts\ContractService;
use SafeContracts\Database\Migrator;
use SafeContracts\Roles\Capabilities;

$GLOBALS['sc_test_post_types'] = [];
if (! function_exists('get_post_type')) {
    function get_post_type(int $postId): string|false
    {
        return $GLOBALS['sc_test_post_types'][$postId] ?? false;
    }
}

$financialTests = 0;

function sc_fin_assert(bool $condition, string $message): void
{
    global $financialTests;
    $financialTests++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function sc_fin_expect(string $class, callable $callback, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        sc_fin_assert($error instanceof $class, $message);
        return;
    }
    sc_fin_assert(false, $message);
}

/** @return array<string, mixed> */
function sc_fin_contract(array $overrides = []): array
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
        'notes' => 'Operational note',
        'is_archived' => '0',
    ], $overrides);
}

$activate = $GLOBALS['sc_test_activation_hooks'][SAFECONTRACTS_FILE] ?? null;
sc_fin_assert(is_callable($activate), 'plugin activation hook is available');
$activate();
sc_fin_assert(Migrator::LATEST_VERSION === '1.7.0', 'collection migration is current after contract/payment schemas');
sc_fin_assert(count($GLOBALS['sc_test_dbdelta']) === 10, 'contract financial/history/payment schemas remain present with collection schema');

$itemSchema = $GLOBALS['sc_test_dbdelta'][4];
$adjustmentSchema = $GLOBALS['sc_test_dbdelta'][5];
$attachmentSchema = $GLOBALS['sc_test_dbdelta'][6];
sc_fin_assert(str_contains($itemSchema, 'wp_safecontracts_contract_financial_items'), 'financial items use dedicated prefixed table');
sc_fin_assert(str_contains($itemSchema, 'amount decimal(20,4) NOT NULL DEFAULT 0.0000'), 'financial item amount uses fixed-point precision');
sc_fin_assert(str_contains($itemSchema, 'KEY contract_order (contract_id, display_order, id)'), 'financial items have contract ordering index');
sc_fin_assert(str_contains($adjustmentSchema, 'wp_safecontracts_contract_adjustments'), 'adjustments use dedicated prefixed table');
sc_fin_assert(str_contains($adjustmentSchema, 'adjustment_type varchar(16) NOT NULL'), 'addition/discount type is persisted explicitly');
sc_fin_assert(str_contains($adjustmentSchema, 'KEY contract_type_order (contract_id, adjustment_type, display_order, id)'), 'adjustment reporting is indexed');
sc_fin_assert(str_contains($attachmentSchema, 'wp_safecontracts_contract_attachments'), 'attachments use document-reference table');
sc_fin_assert(str_contains($attachmentSchema, 'media_id bigint(20) unsigned NOT NULL'), 'attachment stores WordPress Media reference');
sc_fin_assert(str_contains($attachmentSchema, 'UNIQUE KEY contract_media (contract_id, media_id)'), 'duplicate contract/media links are prevented');

$service = new ContractService();
$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ALL => true, Capabilities::EDIT_CONTRACTS => true];

$GLOBALS['sc_test_result_queue'] = [[sc_fin_contract()]];
$service->updateDates(501, '2026-02-01', '2027-01-31');
$dateSql = (string) end($GLOBALS['sc_test_queries']);
sc_fin_assert(str_contains($dateSql, "start_date = '2026-02-01'"), 'authorized date update persists start date');
sc_fin_assert(str_contains($dateSql, "end_date = '2027-01-31'"), 'authorized date update persists end date');
sc_fin_assert(isset($GLOBALS['sc_test_fired_actions']['safecontracts_contract_dates_changed']), 'date update emits domain event');

$GLOBALS['sc_test_result_queue'] = [[sc_fin_contract()]];
$beforeBadDate = count($GLOBALS['sc_test_queries']);
sc_fin_expect(InvalidArgumentException::class, fn () => $service->updateDates(501, '2026-02-30', '2026-03-01'), 'invalid calendar date is rejected');
sc_fin_assert(count($GLOBALS['sc_test_queries']) === $beforeBadDate, 'invalid date does not mutate data');

$GLOBALS['sc_test_result_queue'] = [[sc_fin_contract()]];
$beforeReversedDate = count($GLOBALS['sc_test_queries']);
sc_fin_expect(InvalidArgumentException::class, fn () => $service->updateDates(501, '2026-04-01', '2026-03-31'), 'end date before start date is rejected');
sc_fin_assert(count($GLOBALS['sc_test_queries']) === $beforeReversedDate, 'reversed dates do not mutate data');

$GLOBALS['sc_test_result_queue'] = [[sc_fin_contract()]];
$service->updateBaseValue(501, '1250.5');
sc_fin_assert(str_contains((string) end($GLOBALS['sc_test_queries']), "base_value = '1250.5000'"), 'base value normalizes to four decimal places');

$GLOBALS['sc_test_result_queue'] = [[sc_fin_contract()]];
$GLOBALS['wpdb']->insert_id = 3001;
$itemId = $service->addFinancialItem(501, 'Media package', '250.125', 20);
sc_fin_assert($itemId === 3001, 'financial item returns inserted ID');
$itemSql = (string) end($GLOBALS['sc_test_queries']);
sc_fin_assert(str_contains($itemSql, 'wp_safecontracts_contract_financial_items'), 'financial item writes dedicated table');
sc_fin_assert(str_contains($itemSql, "'250.1250'"), 'financial item amount is exact fixed-point value');

$GLOBALS['sc_test_result_queue'] = [[sc_fin_contract()]];
$GLOBALS['wpdb']->insert_id = 3002;
$additionId = $service->addAdjustment(501, 'ADDITION', 'Extra production', '50.50', 10);
sc_fin_assert($additionId === 3002, 'addition returns inserted ID');
sc_fin_assert(str_contains((string) end($GLOBALS['sc_test_queries']), "'addition'"), 'addition type is normalized');

$GLOBALS['sc_test_result_queue'] = [[sc_fin_contract()]];
$GLOBALS['wpdb']->insert_id = 3003;
$service->addAdjustment(501, 'discount', 'Commercial discount', '25.125', 20);
sc_fin_assert(str_contains((string) end($GLOBALS['sc_test_queries']), "'discount'"), 'discount type is persisted');

$GLOBALS['sc_test_result_queue'] = [[sc_fin_contract()]];
$beforeBadAdjustment = count($GLOBALS['sc_test_queries']);
sc_fin_expect(InvalidArgumentException::class, fn () => $service->addAdjustment(501, 'fee', 'Unknown', '1'), 'unknown adjustment type is rejected');
sc_fin_assert(count($GLOBALS['sc_test_queries']) === $beforeBadAdjustment, 'invalid adjustment does not mutate data');

$GLOBALS['sc_test_result_queue'] = [[sc_fin_contract()], [['total' => '250.1250']], [
    ['adjustment_type' => 'addition', 'total' => '50.5000'],
    ['adjustment_type' => 'discount', 'total' => '25.1250'],
]];
$reconciliation = $service->reconcile(501);
sc_fin_assert($reconciliation['base_value'] === '1000.0000', 'reconciliation exposes base value');
sc_fin_assert($reconciliation['financial_items'] === '250.1250', 'reconciliation exposes financial-item total');
sc_fin_assert($reconciliation['additions'] === '50.5000', 'reconciliation exposes additions');
sc_fin_assert($reconciliation['discounts'] === '25.1250', 'reconciliation exposes discounts');
sc_fin_assert($reconciliation['net_value'] === '1275.5000', 'net value equals base + items + additions - discounts');
sc_fin_assert(ContractMoney::reconcile('9999999999999999.9999', '0.0001', '0', '0') === '10000000000000000.0000', 'reconciliation avoids floating-point precision loss');
sc_fin_assert(ContractMoney::reconcile('10', '0', '0', '20') === '-10.0000', 'transparent reconciliation preserves negative result instead of silently clamping');

$GLOBALS['sc_test_post_types'][901] = 'attachment';
$GLOBALS['sc_test_result_queue'] = [[sc_fin_contract()]];
$GLOBALS['wpdb']->insert_id = 4001;
$attachmentId = $service->attachMedia(501, 901, 'Signed contract');
sc_fin_assert($attachmentId === 4001, 'attachment reference returns inserted ID');
$attachSql = (string) end($GLOBALS['sc_test_queries']);
sc_fin_assert(str_contains($attachSql, 'wp_safecontracts_contract_attachments'), 'attachment writes reference table');
sc_fin_assert(str_contains($attachSql, 'ON DUPLICATE KEY UPDATE'), 'attachment linking is idempotent per contract/media');

$GLOBALS['sc_test_result_queue'] = [[sc_fin_contract()]];
$service->detachMedia(501, 901);
sc_fin_assert(str_starts_with(ltrim((string) end($GLOBALS['sc_test_queries'])), 'DELETE FROM wp_safecontracts_contract_attachments'), 'attachment can be detached without deleting WordPress Media');

$GLOBALS['sc_test_result_queue'] = [[sc_fin_contract()]];
$beforeBadMedia = count($GLOBALS['sc_test_queries']);
sc_fin_expect(InvalidArgumentException::class, fn () => $service->attachMedia(501, 999, 'Not media'), 'non-Media attachment reference is rejected');
sc_fin_assert(count($GLOBALS['sc_test_queries']) === $beforeBadMedia, 'invalid attachment does not mutate data');

$GLOBALS['sc_test_result_queue'] = [[sc_fin_contract()]];
$service->edit(501, ['notes' => 'Updated operational note']);
sc_fin_assert(str_contains((string) end($GLOBALS['sc_test_queries']), "'Updated operational note'"), 'contract notes remain editable under contract edit capability');

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ASSIGNED => true];
$GLOBALS['sc_test_result_queue'] = [[sc_fin_contract(['accountant_user_id' => '99'])]];
sc_fin_expect(DomainException::class, fn () => $service->reconcile(501), 'financial reconciliation respects assigned data scope');

$dbDeltaCount = count($GLOBALS['sc_test_dbdelta']);
do_action('plugins_loaded');
sc_fin_assert(count($GLOBALS['sc_test_dbdelta']) === $dbDeltaCount, 'financial/history/payment/collection migrations are idempotent after stored version is current');

echo "SafeContracts contract financial tests passed ({$financialTests} assertions).\n";
