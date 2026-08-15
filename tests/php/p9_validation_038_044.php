<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Rest\MobileMutationController;
use SafeContracts\Rest\Router;
use SafeContracts\Roles\Capabilities;

$tests = 0;
function sc_p9final_assert(bool $ok, string $message): void
{
    global $tests;
    $tests++;
    if (! $ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function sc_p9final_source(string $relative): string
{
    $source = file_get_contents(dirname(__DIR__, 2) . '/' . $relative);
    sc_p9final_assert($source !== false, 'validation source exists: ' . $relative);
    return $source === false ? '' : $source;
}

SafeContracts\Plugin::instance()->boot();
Router::register();

$contractMutation = sc_p9final_source('wordpress-plugin/safecontracts/src/Rest/ContractMutationController.php');
$mobileMutation = sc_p9final_source('wordpress-plugin/safecontracts/src/Rest/MobileMutationController.php');
$dataController = sc_p9final_source('wordpress-plugin/safecontracts/src/Rest/DataController.php');
$referenceController = sc_p9final_source('wordpress-plugin/safecontracts/src/Rest/ReferenceDataController.php');
$collectionService = sc_p9final_source('wordpress-plugin/safecontracts/src/Collections/CollectionService.php');
$paymentService = sc_p9final_source('wordpress-plugin/safecontracts/src/Payments/PaymentService.php');
$followUpService = sc_p9final_source('wordpress-plugin/safecontracts/src/FollowUps/FollowUpService.php');
$paymentsMobile = sc_p9final_source('mobile/lib/features/payments/payments.dart');
$paymentsScreen = sc_p9final_source('mobile/lib/features/payments/payments_screen.dart');
$collectionDialog = sc_p9final_source('mobile/lib/features/payments/collection_entry_dialog.dart');
$followUpsMobile = sc_p9final_source('mobile/lib/features/followups/followups.dart');
$followUpsScreen = sc_p9final_source('mobile/lib/features/followups/followups_screen.dart');
$appShell = sc_p9final_source('mobile/lib/features/navigation/app_shell.dart');

// SC-P9-038 — Contract light edits — Validate.
foreach ([
    "['contract_number', 'start_date', 'end_date']",
    'Capabilities::EDIT_CONTRACTS',
    'Contract date edits require both start_date and end_date.',
    'Unsupported SafeContracts contract edit field.',
    'new ContractService()',
] as $marker) {
    sc_p9final_assert(str_contains($contractMutation, $marker), 'SC-P9-038 contract light-edit guard present: ' . $marker);
}
sc_p9final_assert(! str_contains($contractMutation, 'base_value'), 'SC-P9-038 financial value is not editable through mobile contract light-edit');

// SC-P9-039 — Payments list — Validate.
foreach ([
    "'page': '\$page'",
    "'sort': 'due_date'",
    "'order': 'asc'",
    'filters.validate()',
    'payments contain duplicate IDs',
] as $marker) {
    sc_p9final_assert(str_contains($paymentsMobile, $marker), 'SC-P9-039 bounded payment-list guard present: ' . $marker);
}
sc_p9final_assert(str_contains($dataController, "'/payments' => 'payments'"), 'SC-P9-039 server payment list remains protected REST data surface');

// SC-P9-040 — Payment details — Validate.
foreach (['originalAmount', 'paidAmount', 'remainingAmount', 'expectedPaymentDate', 'contractIsArchived'] as $marker) {
    sc_p9final_assert(str_contains($paymentsMobile, $marker), 'SC-P9-040 payment detail keeps server field: ' . $marker);
}
sc_p9final_assert(str_contains($paymentsScreen, 'Payment access denied'), 'SC-P9-040 403 detail state remains distinct');
sc_p9final_assert(str_contains($paymentsScreen, 'Payment not found'), 'SC-P9-040 404 detail state remains distinct');
sc_p9final_assert(str_contains($paymentsScreen, 'Dates, balances and status are server-authoritative.'), 'SC-P9-040 UI states server authority explicitly');

// SC-P9-041 — Payment light edits — Validate.
foreach ([
    "'/payments/(?P<id>\\\\d+)/expected-date'",
    'Capabilities::MANAGE_PAYMENTS',
    "\$service->updateDates(\$paymentId, \$payment['due_date'], \$expected)",
    "['id', 'expected_payment_date']",
] as $marker) {
    sc_p9final_assert(str_contains($mobileMutation, $marker), 'SC-P9-041 expected-date boundary preserves guard: ' . $marker);
}
sc_p9final_assert(str_contains($paymentService, 'expected_payment_date is an operational promise/follow-up date only'), 'SC-P9-041 contractual due classification stays based on due_date');

// SC-P9-042 — Collection entry — Validate.
foreach ([
    'Capabilities::MANAGE_COLLECTIONS',
    'new CollectionService()',
    "'payment_method_id'",
    "'proof_media_id'",
] as $marker) {
    sc_p9final_assert(str_contains($mobileMutation, $marker), 'SC-P9-042 collection mutation guard present: ' . $marker);
}
foreach ([
    'beginTransaction()',
    'lockPayment(',
    'Collection amount exceeds the payment remaining balance.',
    'updatePaymentSettlement(',
    'commitTransaction()',
    'rollbackTransaction()',
] as $marker) {
    sc_p9final_assert(str_contains($collectionService, $marker), 'SC-P9-042 backend remains financial authority: ' . $marker);
}
sc_p9final_assert(! str_contains($collectionDialog, 'double.parse(') && ! str_contains($collectionDialog, 'num.parse('), 'SC-P9-042 collection UX does not parse authoritative money as floating point');
sc_p9final_assert(str_contains($collectionDialog, 'server validates scope, payment balance, settlement status and audit history'), 'SC-P9-042 collection dialog declares server-authoritative settlement');

// SC-P9-043 — Payment-method lookup — Validate.
sc_p9final_assert(str_contains($referenceController, 'all(true)'), 'SC-P9-043 server lookup returns active payment methods only');
sc_p9final_assert(str_contains($paymentsMobile, "client.get('reference-data')"), 'SC-P9-043 mobile reads backend reference-data endpoint');
sc_p9final_assert(str_contains($paymentsMobile, 'payment methods contain duplicate IDs'), 'SC-P9-043 mobile rejects duplicate payment-method IDs');
sc_p9final_assert(! str_contains($paymentsMobile, "PaymentMethodOption(id: 1"), 'SC-P9-043 no hardcoded mobile payment-method master list');

// SC-P9-044 — Follow-up workflow — Validate.
foreach ([
    "'note' =>",
    "'promise' =>",
    "'issue' =>",
    "'defer' =>",
    "'escalate' =>",
    'Capabilities::MANAGE_FOLLOWUPS',
    'new FollowUpService()',
    'Promise follow-up requires promised_date only.',
    'Deferred follow-up requires deferred_until only.',
] as $marker) {
    sc_p9final_assert(str_contains($mobileMutation, $marker), 'SC-P9-044 follow-up boundary guard present: ' . $marker);
}
foreach ([
    'FollowUpState::CONTACTED',
    'FollowUpState::PROMISED_TO_PAY',
    'FollowUpState::ISSUE',
    'FollowUpState::DEFERRED',
    'FollowUpState::NEEDS_ESCALATION',
    'safecontracts_followup_recorded',
] as $marker) {
    sc_p9final_assert(str_contains($followUpService, $marker), 'SC-P9-044 domain workflow remains explicit: ' . $marker);
}
sc_p9final_assert(str_contains($followUpsMobile, "'followups',"), 'SC-P9-044 mobile queue uses scoped server endpoint');
sc_p9final_assert(str_contains($followUpsMobile, "client.post(\n      'payments/\$paymentId/followups/record'"), 'SC-P9-044 mobile follow-up mutation uses server endpoint');
sc_p9final_assert(! str_contains($followUpsMobile, 'remaining_amount\':') && ! str_contains($followUpsMobile, "'status': normalizedOperation"), 'SC-P9-044 mobile follow-up mutation never submits financial/status authority');
sc_p9final_assert(str_contains($followUpsScreen, 'canManage'), 'SC-P9-044 mutation UI remains capability gated');
sc_p9final_assert(str_contains($appShell, 'widget.policy.canManageFollowUps'), 'SC-P9-044 app shell uses capability-aware follow-up policy');
sc_p9final_assert(str_contains($appShell, 'widget.policy.canEnterCollection'), 'SC-P9-042 app shell uses feature/capability collection gate');
sc_p9final_assert(str_contains($appShell, "widget.session.can('safecontracts_manage_payments')"), 'SC-P9-041 app shell uses payment capability gate');

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS => true, Capabilities::VIEW_ASSIGNED => true];
sc_p9final_assert(MobileMutationController::canManagePayments() instanceof WP_Error, 'SC-P9-041 missing mutation capability fails closed at runtime');
sc_p9final_assert(MobileMutationController::canManageCollections() instanceof WP_Error, 'SC-P9-042 missing collection capability fails closed at runtime');
sc_p9final_assert(MobileMutationController::canManageFollowUps() instanceof WP_Error, 'SC-P9-044 missing follow-up capability fails closed at runtime');

fwrite(STDOUT, "SafeContracts P9 final validation SC-P9-038..044 passed ({$tests} assertions).\n");
