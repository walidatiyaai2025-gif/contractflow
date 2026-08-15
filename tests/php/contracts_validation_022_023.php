<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Contracts\ContractMoney;
use SafeContracts\Contracts\ContractService;
use SafeContracts\Roles\Capabilities;

$GLOBALS['sc_test_post_types'] = [];
function get_post_type(int $postId): string|false
{
    return $GLOBALS['sc_test_post_types'][$postId] ?? false;
}

$p2FinalTests = 0;

function sc_p2_final_assert(bool $condition, string $message): void
{
    global $p2FinalTests;
    $p2FinalTests++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function sc_p2_final_expect(string $class, callable $callback, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        sc_p2_final_assert($error instanceof $class, $message);
        return;
    }

    sc_p2_final_assert(false, $message);
}

/** @return array<string, mixed> */
function sc_p2_final_contract(array $overrides = []): array
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
        'notes' => 'Current notes',
        'is_archived' => '0',
    ], $overrides);
}

$activate = $GLOBALS['sc_test_activation_hooks'][SAFECONTRACTS_FILE] ?? null;
sc_p2_final_assert(is_callable($activate), 'plugin activation hook is available');
$activate();

$service = new ContractService();
$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ALL => true,
    Capabilities::EDIT_CONTRACTS => true,
];

sc_p2_final_assert(
    ContractMoney::reconcile('1000', '250.1250', '50.5000', '25.1250') === '1275.5000',
    'SC-P2-022 exact reconciliation formula is deterministic'
);
sc_p2_final_assert(
    ContractMoney::reconcile('9999999999999999.9999', '0.0001', '0', '0') === '10000000000000000.0000',
    'SC-P2-022 reconciliation preserves DECIMAL(20,4) precision without float drift'
);

$GLOBALS['sc_test_result_queue'] = [
    [sc_p2_final_contract()],
    [['total' => '250.1250']],
    [
        ['adjustment_type' => 'addition', 'total' => '50.5000'],
        ['adjustment_type' => 'discount', 'total' => '25.1250'],
    ],
];
$reconciliation = $service->reconcile(501);
sc_p2_final_assert($reconciliation['net_value'] === '1275.5000', 'SC-P2-022 service exposes reconciled net value');
sc_p2_final_assert($reconciliation['financial_items'] === '250.1250', 'SC-P2-022 service exposes financial-item component');
sc_p2_final_assert($reconciliation['additions'] === '50.5000', 'SC-P2-022 service exposes addition component');
sc_p2_final_assert($reconciliation['discounts'] === '25.1250', 'SC-P2-022 service exposes discount component');

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ASSIGNED => true];
$GLOBALS['sc_test_result_queue'] = [[sc_p2_final_contract(['accountant_user_id' => '99'])]];
sc_p2_final_expect(DomainException::class, fn () => $service->reconcile(501), 'SC-P2-022 reconciliation rejects out-of-scope Accountant access');

$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ALL => true,
    Capabilities::EDIT_CONTRACTS => true,
];
$GLOBALS['sc_test_result_queue'] = [[sc_p2_final_contract()]];
$service->edit(501, ['notes' => 'Validated contract note']);
sc_p2_final_assert(str_contains((string) end($GLOBALS['sc_test_queries']), "'Validated contract note'"), 'SC-P2-023 notes persist through capability-protected edit');

$GLOBALS['sc_test_post_types'][901] = 'attachment';
$GLOBALS['sc_test_result_queue'] = [[sc_p2_final_contract()]];
$GLOBALS['wpdb']->insert_id = 6101;
$attachmentId = $service->attachMedia(501, 901, 'Signed agreement');
sc_p2_final_assert($attachmentId === 6101, 'SC-P2-023 valid WordPress Media attachment is linked');
$attachmentSql = (string) end($GLOBALS['sc_test_queries']);
sc_p2_final_assert(str_contains($attachmentSql, 'wp_safecontracts_contract_attachments'), 'SC-P2-023 attachment uses dedicated reference table');
sc_p2_final_assert(str_contains($attachmentSql, 'ON DUPLICATE KEY UPDATE'), 'SC-P2-023 attachment linking is idempotent per contract/media');

$GLOBALS['sc_test_result_queue'] = [[sc_p2_final_contract()]];
$service->detachMedia(501, 901);
sc_p2_final_assert(str_starts_with(ltrim((string) end($GLOBALS['sc_test_queries'])), 'DELETE FROM wp_safecontracts_contract_attachments'), 'SC-P2-023 attachment can be detached without deleting Media');

$GLOBALS['sc_test_result_queue'] = [[sc_p2_final_contract()]];
$beforeInvalidMedia = count($GLOBALS['sc_test_queries']);
sc_p2_final_expect(InvalidArgumentException::class, fn () => $service->attachMedia(501, 999, 'Invalid'), 'SC-P2-023 non-Media ID is rejected');
sc_p2_final_assert(count($GLOBALS['sc_test_queries']) === $beforeInvalidMedia, 'SC-P2-023 invalid Media ID cannot mutate data');

$GLOBALS['sc_test_result_queue'] = [[sc_p2_final_contract(['is_archived' => '1'])]];
sc_p2_final_expect(DomainException::class, fn () => $service->edit(501, ['notes' => 'Blocked']), 'SC-P2-023 archived contract notes are frozen');

$GLOBALS['sc_test_result_queue'] = [[sc_p2_final_contract(['is_archived' => '1'])]];
sc_p2_final_expect(DomainException::class, fn () => $service->attachMedia(501, 901, 'Blocked'), 'SC-P2-023 archived contract attachments are frozen');

echo "SafeContracts P2 final validation tests passed ({$p2FinalTests} assertions).\n";
