<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Contracts\ContractMoney;
use SafeContracts\Contracts\ContractService;
use SafeContracts\Roles\Capabilities;

$GLOBALS['sc_test_post_types'] = [];
if (! function_exists('get_post_type')) {
    function get_post_type(int $postId): string|false
    {
        return $GLOBALS['sc_test_post_types'][$postId] ?? false;
    }
}

$tests = 0;

function sc_p2_final_assert(bool $condition, string $message): void
{
    global $tests;
    $tests++;
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
        'notes' => 'Initial note',
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

$GLOBALS['sc_test_result_queue'] = [
    [sc_p2_final_contract()],
    [['total' => '250.1250']],
    [
        ['adjustment_type' => 'addition', 'total' => '50.5000'],
        ['adjustment_type' => 'discount', 'total' => '25.1250'],
    ],
];
$reconciliation = $service->reconcile(501);
sc_p2_final_assert($reconciliation['base_value'] === '1000.0000', 'reconciliation exposes the base value');
sc_p2_final_assert($reconciliation['financial_items'] === '250.1250', 'reconciliation exposes financial lines');
sc_p2_final_assert($reconciliation['additions'] === '50.5000', 'reconciliation exposes additions');
sc_p2_final_assert($reconciliation['discounts'] === '25.1250', 'reconciliation exposes discounts');
sc_p2_final_assert($reconciliation['net_value'] === '1275.5000', 'net value equals base + lines + additions - discounts');
sc_p2_final_assert(
    ContractMoney::reconcile('9999999999999999.9999', '0.0001', '0', '0') === '10000000000000000.0000',
    'net-value arithmetic remains exact at DECIMAL(20,4) boundary'
);
sc_p2_final_assert(
    ContractMoney::reconcile('10', '0', '0', '20') === '-10.0000',
    'reconciliation remains transparent when discounts exceed gross value'
);

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ASSIGNED => true];
$GLOBALS['sc_test_result_queue'] = [[sc_p2_final_contract(['accountant_user_id' => '99'])]];
sc_p2_final_expect(DomainException::class, fn () => $service->reconcile(501), 'reconciliation cannot bypass Accountant assigned scope');

$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ALL => true,
    Capabilities::EDIT_CONTRACTS => true,
];
$GLOBALS['sc_test_result_queue'] = [[sc_p2_final_contract()]];
$service->edit(501, ['notes' => 'Collection handover note']);
sc_p2_final_assert(str_contains((string) end($GLOBALS['sc_test_queries']), "'Collection handover note'"), 'authorized contract notes are persisted');

$GLOBALS['sc_test_post_types'][901] = 'attachment';
$GLOBALS['sc_test_result_queue'] = [[sc_p2_final_contract()]];
$GLOBALS['wpdb']->insert_id = 9101;
$attachmentId = $service->attachMedia(501, 901, 'Signed contract');
sc_p2_final_assert($attachmentId === 9101, 'attachment link returns relation ID');
$attachSql = (string) end($GLOBALS['sc_test_queries']);
sc_p2_final_assert(str_contains($attachSql, 'wp_safecontracts_contract_attachments'), 'attachment is stored as SafeContracts relation');
sc_p2_final_assert(str_contains($attachSql, 'ON DUPLICATE KEY UPDATE'), 'attachment linking is idempotent');

$GLOBALS['sc_test_result_queue'] = [[sc_p2_final_contract()]];
$service->detachMedia(501, 901);
$detachSql = (string) end($GLOBALS['sc_test_queries']);
sc_p2_final_assert(str_starts_with(ltrim($detachSql), 'DELETE FROM wp_safecontracts_contract_attachments'), 'detach removes only the contract/media relation');
sc_p2_final_assert(! str_contains($detachSql, 'wp_posts'), 'detach never deletes the underlying WordPress Media object');

$GLOBALS['sc_test_result_queue'] = [[sc_p2_final_contract()]];
$mutationsBeforeBadMedia = count($GLOBALS['sc_test_queries']);
sc_p2_final_expect(InvalidArgumentException::class, fn () => $service->attachMedia(501, 999, 'Invalid'), 'non-Media attachment reference is rejected');
sc_p2_final_assert(count($GLOBALS['sc_test_queries']) === $mutationsBeforeBadMedia, 'invalid attachment causes no mutation');

$GLOBALS['sc_test_result_queue'] = [[sc_p2_final_contract(['is_archived' => '1'])]];
$mutationsBeforeArchivedNote = count($GLOBALS['sc_test_queries']);
sc_p2_final_expect(DomainException::class, fn () => $service->edit(501, ['notes' => 'Should fail']), 'archived contract notes are frozen');
sc_p2_final_assert(count($GLOBALS['sc_test_queries']) === $mutationsBeforeArchivedNote, 'archived note attempt causes no mutation');

$GLOBALS['sc_test_result_queue'] = [[sc_p2_final_contract(['is_archived' => '1'])]];
$mutationsBeforeArchivedAttachment = count($GLOBALS['sc_test_queries']);
sc_p2_final_expect(DomainException::class, fn () => $service->attachMedia(501, 901), 'archived contract attachments are frozen');
sc_p2_final_assert(count($GLOBALS['sc_test_queries']) === $mutationsBeforeArchivedAttachment, 'archived attachment attempt causes no mutation');

echo "SafeContracts P2 final validation SC-P2-022..023 passed ({$tests} assertions).\n";
