<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Collections\CollectionService;
use SafeContracts\Payments\PaymentStatus;
use SafeContracts\Roles\Capabilities;

$GLOBALS['sc_test_post_types'] = [];
if (! function_exists('get_post_type')) {
    function get_post_type(int $postId): string|false
    {
        return $GLOBALS['sc_test_post_types'][$postId] ?? false;
    }
}

$validationTests = 0;

function sc_p3v19_assert(bool $condition, string $message): void
{
    global $validationTests;
    $validationTests++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function sc_p3v19_expect(string $class, callable $callback, string $message): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        sc_p3v19_assert($error instanceof $class, $message);
        return;
    }

    sc_p3v19_assert(false, $message);
}

/** @return array<string, mixed> */
function sc_p3v19_payment(array $overrides = []): array
{
    return array_merge([
        'id' => '7001',
        'contract_id' => '501',
        'sequence_no' => '1',
        'reference' => 'VAL-019',
        'due_date' => '2026-09-15',
        'expected_payment_date' => null,
        'original_amount' => '500.0000',
        'paid_amount' => '0.0000',
        'remaining_amount' => '500.0000',
        'status' => PaymentStatus::UPCOMING,
        'accountant_user_id' => '42',
        'contract_is_archived' => '0',
    ], $overrides);
}

/** @return list<string> */
function sc_p3v19_mutations_since(int $offset): array
{
    return array_slice($GLOBALS['sc_test_queries'], $offset);
}

$activate = $GLOBALS['sc_test_activation_hooks'][SAFECONTRACTS_FILE] ?? null;
sc_p3v19_assert(is_callable($activate), 'plugin activation hook is available');
$activate();

$collectionSchema = $GLOBALS['sc_test_dbdelta'][9] ?? '';
sc_p3v19_assert(str_contains($collectionSchema, 'wp_safecontracts_payment_collections'), 'collection ledger schema is installed');
sc_p3v19_assert(str_contains($collectionSchema, 'payment_method_id bigint(20) unsigned NOT NULL'), 'SC-P3-019 payment method is mandatory at schema level');
sc_p3v19_assert(str_contains($collectionSchema, 'proof_media_id bigint(20) unsigned NULL'), 'SC-P3-020 proof remains nullable at schema level');

$collections = new CollectionService();
$GLOBALS['sc_test_current_caps'] = [
    Capabilities::ACCESS => true,
    Capabilities::VIEW_ALL => true,
    Capabilities::MANAGE_COLLECTIONS => true,
];

// SC-P3-019 — Mandatory payment method validation.
$beforeMissingMethod = count($GLOBALS['sc_test_queries']);
sc_p3v19_expect(
    InvalidArgumentException::class,
    fn () => $collections->record([
        'payment_id' => 7001,
        'amount' => '25',
        'collection_date' => '2026-08-15',
    ]),
    'SC-P3-019 missing payment method is rejected'
);
sc_p3v19_assert(
    count($GLOBALS['sc_test_queries']) === $beforeMissingMethod,
    'SC-P3-019 missing payment method fails before opening a transaction'
);

$GLOBALS['sc_test_result_queue'] = [
    [sc_p3v19_payment()],
    [],
];
$beforeInactiveMethod = count($GLOBALS['sc_test_queries']);
sc_p3v19_expect(
    InvalidArgumentException::class,
    fn () => $collections->record([
        'payment_id' => 7001,
        'amount' => '25',
        'collection_date' => '2026-08-15',
        'payment_method_id' => 999,
    ]),
    'SC-P3-019 inactive or unknown payment method is rejected'
);
sc_p3v19_assert(
    sc_p3v19_mutations_since($beforeInactiveMethod) === ['START TRANSACTION', 'ROLLBACK'],
    'SC-P3-019 inactive payment method rolls back without ledger mutation'
);

$GLOBALS['sc_test_result_queue'] = [
    [sc_p3v19_payment()],
    [['id' => '2']],
    [['total' => '0.0000']],
];
$GLOBALS['wpdb']->insert_id = 9201;
$beforeActiveMethod = count($GLOBALS['sc_test_queries']);
$activeMethodId = $collections->record([
    'payment_id' => 7001,
    'amount' => '50',
    'collection_date' => '2026-08-15',
    'payment_method_id' => 2,
]);
$activeMethodWrites = sc_p3v19_mutations_since($beforeActiveMethod);
$activeMethodSql = implode("\n", $activeMethodWrites);
sc_p3v19_assert($activeMethodId === 9201, 'SC-P3-019 active payment method allows collection creation');
sc_p3v19_assert(str_contains($activeMethodSql, 'INSERT INTO wp_safecontracts_payment_collections'), 'SC-P3-019 active method reaches ledger insert');
sc_p3v19_assert(str_contains($activeMethodSql, ', 2,'), 'SC-P3-019 selected payment method ID is persisted');
sc_p3v19_assert($activeMethodWrites[0] === 'START TRANSACTION' && end($activeMethodWrites) === 'COMMIT', 'SC-P3-019 valid method participates in atomic transaction');

// SC-P3-020 — Optional collection proof validation.
$GLOBALS['sc_test_result_queue'] = [
    [sc_p3v19_payment()],
    [['id' => '1']],
    [['total' => '0.0000']],
];
$GLOBALS['wpdb']->insert_id = 9202;
$beforeNoProof = count($GLOBALS['sc_test_queries']);
$noProofId = $collections->record([
    'payment_id' => 7001,
    'amount' => '60',
    'collection_date' => '2026-08-15',
    'payment_method_id' => 1,
]);
$noProofSql = implode("\n", sc_p3v19_mutations_since($beforeNoProof));
sc_p3v19_assert($noProofId === 9202, 'SC-P3-020 collection can be recorded without proof');
sc_p3v19_assert(str_contains($noProofSql, 'NULL'), 'SC-P3-020 omitted proof is persisted as NULL');

$GLOBALS['sc_test_post_types'][901] = 'attachment';
$GLOBALS['sc_test_result_queue'] = [
    [sc_p3v19_payment()],
    [['id' => '1']],
    [['total' => '0.0000']],
];
$GLOBALS['wpdb']->insert_id = 9203;
$beforeProof = count($GLOBALS['sc_test_queries']);
$proofId = $collections->record([
    'payment_id' => 7001,
    'amount' => '70',
    'collection_date' => '2026-08-15',
    'payment_method_id' => 1,
    'proof_media_id' => 901,
]);
$proofSql = implode("\n", sc_p3v19_mutations_since($beforeProof));
sc_p3v19_assert($proofId === 9203, 'SC-P3-020 valid WordPress Media proof is accepted');
sc_p3v19_assert(str_contains($proofSql, '901'), 'SC-P3-020 valid proof Media ID is persisted');

$beforeInvalidProof = count($GLOBALS['sc_test_queries']);
sc_p3v19_expect(
    InvalidArgumentException::class,
    fn () => $collections->record([
        'payment_id' => 7001,
        'amount' => '10',
        'collection_date' => '2026-08-15',
        'payment_method_id' => 1,
        'proof_media_id' => 999,
    ]),
    'SC-P3-020 non-attachment proof is rejected'
);
sc_p3v19_assert(
    count($GLOBALS['sc_test_queries']) === $beforeInvalidProof,
    'SC-P3-020 invalid proof fails before transaction and financial mutation'
);

// SC-P3-021 — Partial collection validation.
$GLOBALS['sc_test_result_queue'] = [
    [sc_p3v19_payment()],
    [['id' => '2']],
    [['total' => '0.0000']],
];
$GLOBALS['wpdb']->insert_id = 9301;
$beforePartial = count($GLOBALS['sc_test_queries']);
$partialId = $collections->record([
    'payment_id' => 7001,
    'amount' => '125.5',
    'collection_date' => '2026-08-15',
    'payment_method_id' => 2,
]);
$partialWrites = sc_p3v19_mutations_since($beforePartial);
$partialSql = implode("\n", $partialWrites);
sc_p3v19_assert($partialId === 9301, 'SC-P3-021 partial collection returns ledger ID');
sc_p3v19_assert($partialWrites[0] === 'START TRANSACTION' && end($partialWrites) === 'COMMIT', 'SC-P3-021 partial collection is atomic');
sc_p3v19_assert(str_contains($partialSql, "'125.5000'"), 'SC-P3-021 partial amount is normalized to DECIMAL(20,4)');
sc_p3v19_assert(str_contains($partialSql, "paid_amount = '125.5000'"), 'SC-P3-021 partial collection updates cumulative paid amount');
sc_p3v19_assert(str_contains($partialSql, "remaining_amount = '374.5000'"), 'SC-P3-021 partial collection preserves exact remaining balance');
sc_p3v19_assert(str_contains($partialSql, "status = 'partially_paid'"), 'SC-P3-021 partial collection sets partially_paid');
sc_p3v19_assert(isset($GLOBALS['sc_test_fired_actions']['safecontracts_collection_recorded']), 'SC-P3-021 partial collection emits ledger domain event');
sc_p3v19_assert(isset($GLOBALS['sc_test_fired_actions']['safecontracts_payment_settled']), 'SC-P3-021 partial collection emits settlement domain event');

// SC-P3-022 — Full settlement validation after an existing partial collection.
$GLOBALS['sc_test_result_queue'] = [
    [sc_p3v19_payment([
        'paid_amount' => '125.5000',
        'remaining_amount' => '374.5000',
        'status' => PaymentStatus::PARTIALLY_PAID,
    ])],
    [['id' => '2']],
    [['total' => '125.5000']],
];
$GLOBALS['wpdb']->insert_id = 9302;
$beforeFull = count($GLOBALS['sc_test_queries']);
$fullId = $collections->record([
    'payment_id' => 7001,
    'amount' => '374.5',
    'collection_date' => '2026-08-16',
    'payment_method_id' => 2,
]);
$fullWrites = sc_p3v19_mutations_since($beforeFull);
$fullSql = implode("\n", $fullWrites);
sc_p3v19_assert($fullId === 9302, 'SC-P3-022 full settlement returns ledger ID');
sc_p3v19_assert($fullWrites[0] === 'START TRANSACTION' && end($fullWrites) === 'COMMIT', 'SC-P3-022 full settlement is atomic');
sc_p3v19_assert(str_contains($fullSql, "paid_amount = '500.0000'"), 'SC-P3-022 full settlement reaches original amount exactly');
sc_p3v19_assert(str_contains($fullSql, "remaining_amount = '0.0000'"), 'SC-P3-022 full settlement zeroes remaining amount');
sc_p3v19_assert(str_contains($fullSql, "status = 'paid'"), 'SC-P3-022 full settlement sets paid status');

// SC-P3-023 — Remaining-balance integrity validation.
$GLOBALS['sc_test_result_queue'] = [
    [sc_p3v19_payment([
        'paid_amount' => '125.5000',
        'remaining_amount' => '374.5000',
        'status' => PaymentStatus::PARTIALLY_PAID,
    ])],
    [['id' => '2']],
    [['total' => '125.5000']],
];
$beforeOverCollection = count($GLOBALS['sc_test_queries']);
sc_p3v19_expect(
    DomainException::class,
    fn () => $collections->record([
        'payment_id' => 7001,
        'amount' => '374.5001',
        'collection_date' => '2026-08-16',
        'payment_method_id' => 2,
    ]),
    'SC-P3-023 collection cannot exceed exact remaining balance'
);
sc_p3v19_assert(
    sc_p3v19_mutations_since($beforeOverCollection) === ['START TRANSACTION', 'ROLLBACK'],
    'SC-P3-023 over-collection rolls back before ledger or balance write'
);

$GLOBALS['sc_test_result_queue'] = [
    [sc_p3v19_payment([
        'paid_amount' => '100.0000',
        'remaining_amount' => '400.0000',
        'status' => PaymentStatus::PARTIALLY_PAID,
    ])],
    [['id' => '2']],
    [['total' => '125.5000']],
];
$beforeLedgerMismatch = count($GLOBALS['sc_test_queries']);
sc_p3v19_expect(
    DomainException::class,
    fn () => $collections->record([
        'payment_id' => 7001,
        'amount' => '1',
        'collection_date' => '2026-08-16',
        'payment_method_id' => 2,
    ]),
    'SC-P3-023 stored paid amount must equal authoritative ledger total'
);
sc_p3v19_assert(
    sc_p3v19_mutations_since($beforeLedgerMismatch) === ['START TRANSACTION', 'ROLLBACK'],
    'SC-P3-023 ledger mismatch blocks further mutation atomically'
);

$GLOBALS['sc_test_result_queue'] = [
    [sc_p3v19_payment([
        'paid_amount' => '125.5000',
        'remaining_amount' => '375.0000',
        'status' => PaymentStatus::PARTIALLY_PAID,
    ])],
    [['id' => '2']],
    [['total' => '125.5000']],
];
$beforeRemainingMismatch = count($GLOBALS['sc_test_queries']);
sc_p3v19_expect(
    DomainException::class,
    fn () => $collections->record([
        'payment_id' => 7001,
        'amount' => '1',
        'collection_date' => '2026-08-16',
        'payment_method_id' => 2,
    ]),
    'SC-P3-023 stored remaining amount must equal original minus ledger total'
);
sc_p3v19_assert(
    sc_p3v19_mutations_since($beforeRemainingMismatch) === ['START TRANSACTION', 'ROLLBACK'],
    'SC-P3-023 remaining-balance mismatch rolls back without compounding corruption'
);

$GLOBALS['sc_test_result_queue'] = [
    [sc_p3v19_payment([
        'paid_amount' => '125.5000',
        'remaining_amount' => '374.5000',
        'status' => PaymentStatus::UPCOMING,
    ])],
    [['id' => '2']],
    [['total' => '125.5000']],
];
$beforeStatusMismatch = count($GLOBALS['sc_test_queries']);
sc_p3v19_expect(
    DomainException::class,
    fn () => $collections->record([
        'payment_id' => 7001,
        'amount' => '1',
        'collection_date' => '2026-08-16',
        'payment_method_id' => 2,
    ]),
    'SC-P3-023 financial status must reconcile with collected amount'
);
sc_p3v19_assert(
    sc_p3v19_mutations_since($beforeStatusMismatch) === ['START TRANSACTION', 'ROLLBACK'],
    'SC-P3-023 financial-status mismatch blocks mutation atomically'
);

echo "SafeContracts P3 validation SC-P3-019..023 passed ({$validationTests} assertions).\n";
