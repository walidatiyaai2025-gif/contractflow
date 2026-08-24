<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Payments\PaymentStatus;
use SafeContracts\Rest\DataController;
use SafeContracts\Roles\Capabilities;

$tests = 0;
function sc_b001_assert(bool $ok, string $message): void
{
    global $tests;
    $tests++;
    if (! $ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function sc_b001_payment(array $overrides = []): array
{
    return array_merge([
        'id' => '7001',
        'contract_id' => '501',
        'financial_direction' => 'receivable',
        'currency_code' => 'KWD',
        'sequence_no' => '1',
        'reference' => 'P-001',
        'due_date' => '2020-08-01',
        'expected_payment_date' => '2099-08-01',
        'original_amount' => '500.0000',
        'paid_amount' => '0.0000',
        'remaining_amount' => '500.0000',
        'status' => 'upcoming',
        'accountant_user_id' => '42',
        'contract_is_archived' => '0',
        'counterparty_type' => 'customer',
        'counterparty_id' => '7',
        'contract_number' => 'SC-501',
        'customer_id' => '7',
        'customer_name' => 'Customer',
        'counterparty_name' => 'Customer',
    ], $overrides);
}

$today = new DateTimeImmutable('2026-08-24');
sc_b001_assert(
    PaymentStatus::authoritative('2020-08-01', '0.0000', '500.0000', $today) === PaymentStatus::OVERDUE,
    'B001 stale temporal status is recomputed from contractual due date'
);
sc_b001_assert(
    PaymentStatus::authoritative('2099-08-01', '125.0000', '375.0000', $today) === PaymentStatus::PARTIALLY_PAID,
    'B001 partial settlement state comes from authoritative amounts'
);
sc_b001_assert(
    PaymentStatus::authoritative('2099-08-01', '500.0000', '0.0000', $today) === PaymentStatus::PAID,
    'B001 zero remaining amount is paid'
);

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ALL => true];
$GLOBALS['sc_test_result_queue'] = [[sc_b001_payment()]];
$list = DataController::payments(new WP_REST_Request([
    'page' => '1',
    'per_page' => '50',
    'sort' => 'due_date',
    'order' => 'asc',
]));
sc_b001_assert($list instanceof WP_REST_Response, 'B001 payment list remains available');
sc_b001_assert(
    ($list->data['data'][0]['status'] ?? null) === PaymentStatus::OVERDUE,
    'B001 list projection replaces stale stored upcoming with authoritative overdue'
);

$GLOBALS['sc_test_result_queue'] = [[sc_b001_payment()]];
$detailRequest = new WP_REST_Request();
$detailRequest->set_url_params(['id' => '7001']);
$detail = DataController::payment($detailRequest);
sc_b001_assert($detail instanceof WP_REST_Response, 'B001 payment detail remains available');
sc_b001_assert(
    ($detail->data['data']['status'] ?? null) === PaymentStatus::OVERDUE,
    'B001 detail projection matches list authoritative status'
);

$GLOBALS['sc_test_result_queue'] = [[
    sc_b001_payment(),
    sc_b001_payment([
        'id' => '7002',
        'due_date' => '2099-08-01',
        'contract_number' => 'SC-502',
    ]),
]];
$filtered = DataController::payments(new WP_REST_Request([
    'page' => '1',
    'per_page' => '50',
    'sort' => 'due_date',
    'order' => 'asc',
    'status' => 'overdue',
]));
sc_b001_assert($filtered instanceof WP_REST_Response, 'B001 authoritative status filter remains available');
sc_b001_assert(
    count($filtered->data['data']) === 1 && ($filtered->data['data'][0]['id'] ?? null) === '7001',
    'B001 status filter uses recomputed status instead of stale database status'
);

printf("ALKENZY B001 authoritative payment status passed (%d assertions).\n", $tests);
