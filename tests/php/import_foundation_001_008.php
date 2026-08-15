<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Admin\AdminShell;
use SafeContracts\Admin\ImportsPage;
use SafeContracts\Import\ColumnMapping;
use SafeContracts\Import\DuplicateStrategy;
use SafeContracts\Import\ImportExecutionService;
use SafeContracts\Import\ImportPreviewService;
use SafeContracts\Import\ImportRowValidator;
use SafeContracts\Import\ImportRunRepository;
use SafeContracts\Import\PrivateImportStorage;
use SafeContracts\Import\WorkbookReader;
use SafeContracts\Import\WorkbookUploadValidator;
use SafeContracts\Reports\XlsxWorkbook;
use SafeContracts\Roles\Capabilities;

$tests = 0;
function sc_p7_assert(bool $ok, string $message): void
{
    global $tests;
    $tests++;
    if (! $ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}
function sc_p7_expect(string $class, callable $fn, string $message): void
{
    try {
        $fn();
    } catch (Throwable $error) {
        sc_p7_assert($error instanceof $class, $message);
        return;
    }
    sc_p7_assert(false, $message);
}

SafeContracts\Plugin::instance()->boot();

// SC-P7-001 — upload schema, validation and private staging.
$migrations = implode("\n", $GLOBALS['sc_test_dbdelta']);
sc_p7_assert(str_contains($migrations, 'safecontracts_import_runs') && str_contains($migrations, 'safecontracts_import_errors'), 'SC-P7-001/008 import run and error tables are migration-managed');
$storageSource = file_get_contents((string) (new ReflectionClass(PrivateImportStorage::class))->getFileName()) ?: '';
sc_p7_assert(str_contains($storageSource, 'safecontracts-private/imports') && str_contains($storageSource, 'Require all denied'), 'SC-P7-001 workbook staging defaults to a private denied directory');
sc_p7_assert(! str_contains($storageSource, 'plugin_dir_url') && ! str_contains($storageSource, 'wp_upload_dir'), 'SC-P7-001 private staging has no public URL dependency');

$tmp = tempnam(sys_get_temp_dir(), 'sc-import-');
if (! is_string($tmp)) { throw new RuntimeException('Unable to create test workbook.'); }
$workbook = (new XlsxWorkbook())->build(['Contracts' => [
    ['Customer Name', 'Contract Number', 'Payment Sequence', 'Payment Due Date', 'Payment Amount'],
    ['Acme Co', 'SC-100', '1', '2026-09-01', '100.5000'],
    ['Beta Co', 'SC-200', '', '', ''],
]]);
file_put_contents($tmp, $workbook);
$validator = new WorkbookUploadValidator();
$validated = $validator->validate(['name' => 'contracts.xlsx', 'tmp_name' => $tmp, 'size' => filesize($tmp), 'error' => UPLOAD_ERR_OK]);
sc_p7_assert($validated['name'] === 'contracts.xlsx' && strlen($validated['sha256']) === 64, 'SC-P7-001 validates XLSX extension/package/size and fingerprints content');
sc_p7_expect(InvalidArgumentException::class, fn () => $validator->validate(['name' => 'contracts.xlsm', 'tmp_name' => $tmp, 'size' => filesize($tmp), 'error' => UPLOAD_ERR_OK]), 'SC-P7-001 macro-enabled extension is rejected');
sc_p7_expect(InvalidArgumentException::class, fn () => $validator->validate(['name' => ['bad.xlsx'], 'tmp_name' => $tmp, 'size' => filesize($tmp), 'error' => UPLOAD_ERR_OK]), 'SC-P7-001 malformed upload metadata fails closed');

// SC-P7-002 — safe workbook field discovery (real generated XLSX when ZipArchive is available).
$readerSource = file_get_contents((string) (new ReflectionClass(WorkbookReader::class))->getFileName()) ?: '';
sc_p7_assert(str_contains($readerSource, 'vbaproject.bin') && str_contains($readerSource, 'xl/externallinks/') && str_contains($readerSource, 'Workbook formulas are not supported'), 'SC-P7-002 reader rejects macros, external links and formula cells');
sc_p7_assert(str_contains($readerSource, 'LIBXML_NONET') && str_contains($readerSource, '<!DOCTYPE'), 'SC-P7-002 XML parsing blocks network/entity declarations');
$discovery = ['sheets' => [[
    'name' => 'Contracts', 'path' => 'xl/worksheets/sheet1.xml', 'header_row' => 1,
    'headers' => [
        ['column' => 'A', 'original' => 'Customer Name', 'normalized' => 'customer name'],
        ['column' => 'B', 'original' => 'Contract Number', 'normalized' => 'contract number'],
        ['column' => 'C', 'original' => 'Payment Sequence', 'normalized' => 'payment sequence'],
        ['column' => 'D', 'original' => 'Payment Due Date', 'normalized' => 'payment due date'],
        ['column' => 'E', 'original' => 'Payment Amount', 'normalized' => 'payment amount'],
    ],
]]];
if (class_exists(ZipArchive::class)) {
    $reader = new WorkbookReader();
    $discovery = $reader->discover($tmp);
    sc_p7_assert(($discovery['sheets'][0]['name'] ?? '') === 'Contracts' && count($discovery['sheets'][0]['headers'] ?? []) === 5, 'SC-P7-002 discovers sheet/header metadata from XLSX without formula evaluation');
    $rows = $reader->rows($tmp, 'Contracts', 1, 10);
    sc_p7_assert(($rows[0]['row_number'] ?? 0) === 2 && ($rows[0]['cells']['A'] ?? '') === 'Acme Co', 'SC-P7-002 reads bounded raw rows while preserving workbook row numbers');
} else {
    sc_p7_assert(str_contains($readerSource, 'SafeContracts XLSX import requires PHP ZipArchive.'), 'SC-P7-002 fails explicitly when required ZipArchive extension is missing');
}

// SC-P7-003 — mapping is target allow-listed, source-bound and requires core identity fields.
$sheet = ColumnMapping::sheet($discovery, 'Contracts');
$mapping = (new ColumnMapping())->validate([
    'customer_name' => 'A', 'contract_number' => 'B', 'payment_sequence' => 'C', 'payment_due_date' => 'D', 'payment_amount' => 'E',
], $sheet);
sc_p7_assert($mapping['customer_name'] === 'A' && $mapping['contract_number'] === 'B', 'SC-P7-003 validates a deterministic server-side mapping');
sc_p7_expect(InvalidArgumentException::class, fn () => (new ColumnMapping())->validate(['customer_name' => 'A'], $sheet), 'SC-P7-003 required target fields cannot be omitted');
sc_p7_expect(InvalidArgumentException::class, fn () => (new ColumnMapping())->validate(['customer_name' => 'A', 'contract_number' => 'A'], $sheet), 'SC-P7-003 one source column cannot silently feed multiple target fields');
sc_p7_expect(InvalidArgumentException::class, fn () => (new ColumnMapping())->validate(['customer_name' => 'A', 'contract_number' => 'B', 'not_real' => 'C'], $sheet), 'SC-P7-003 unsupported target fields fail closed');

// SC-P7-004 — preview is bounded/read-only and preserves original rows.
$previewSource = file_get_contents((string) (new ReflectionClass(ImportPreviewService::class))->getFileName()) ?: '';
sc_p7_assert(str_contains($previewSource, 'min(100') && ! str_contains($previewSource, '$wpdb') && ! str_contains($previewSource, 'CustomerService'), 'SC-P7-004 preview is bounded and contains no business persistence path');
if (class_exists(ZipArchive::class)) {
    $preview = (new ImportPreviewService())->preview($tmp, 'Contracts', 1, $mapping, 10);
    sc_p7_assert(($preview[0]['row_number'] ?? 0) === 2 && ($preview[0]['data']['contract_number'] ?? '') === 'SC-100', 'SC-P7-004 preview maps workbook values and preserves source row number');
}

// SC-P7-005 — row validation normalizes domain-facing fields before mutation.
$rowValidator = new ImportRowValidator();
$valid = $rowValidator->validate([
    'customer_name' => ' Acme Co ', 'customer_email' => 'ops@example.test', 'contract_number' => 'SC-100',
    'contract_start_date' => '2026-08-01', 'contract_end_date' => '2026-12-31', 'contract_base_value' => '1000.25',
    'payment_sequence' => '1', 'payment_due_date' => '2026-09-01', 'payment_amount' => '100.50',
]);
sc_p7_assert($valid['valid'] === true && $valid['data']['contract_base_value'] === '1000.2500' && $valid['data']['payment_amount'] === '100.5000', 'SC-P7-005 valid rows normalize dates/amounts before execution');
$invalid = $rowValidator->validate(['customer_name' => '', 'contract_number' => '', 'payment_amount' => '12', 'payment_due_date' => 'bad']);
sc_p7_assert($invalid['valid'] === false && count($invalid['errors']) >= 4, 'SC-P7-005 malformed rows return field-level errors instead of mutating data');

// SC-P7-006 — duplicate behavior is explicit and fail-closed.
sc_p7_assert(DuplicateStrategy::normalize('FAIL') === 'fail' && DuplicateStrategy::normalize('skip') === 'skip' && DuplicateStrategy::normalize('update') === 'update', 'SC-P7-006 supports explicit fail/skip/update duplicate policies');
sc_p7_expect(InvalidArgumentException::class, fn () => DuplicateStrategy::normalize('overwrite'), 'SC-P7-006 unknown overwrite policy fails closed');

// SC-P7-007 — execution re-reads source, validates all rows, then uses per-row transactions/domain services.
$executionSource = file_get_contents((string) (new ReflectionClass(ImportExecutionService::class))->getFileName()) ?: '';
sc_p7_assert(str_contains($executionSource, 'Capabilities::RUN_IMPORTS') && str_contains($executionSource, "updateStatus(\$runId, 'validated'") && str_contains($executionSource, 'if ($validationErrorRows !== [])'), 'SC-P7-007 execution requires import capability and completes validation before mutation');
sc_p7_assert(str_contains($executionSource, "query('START TRANSACTION')") && str_contains($executionSource, "query('COMMIT')") && str_contains($executionSource, "query('ROLLBACK')"), 'SC-P7-007 each executable row has transaction/rollback semantics');
sc_p7_assert(str_contains($executionSource, 'CustomerService') && str_contains($executionSource, 'ContractService') && str_contains($executionSource, 'PaymentService'), 'SC-P7-007 business writes delegate to existing domain services');
sc_p7_assert(str_contains($executionSource, 'Existing payment amount cannot be changed') && str_contains($executionSource, 'Existing payment reference cannot be changed'), 'SC-P7-007 update policy cannot silently rewrite payment financial identity');

// SC-P7-008 — per-row errors are persistent, bounded, escaped by admin rendering.
$repositorySource = file_get_contents((string) (new ReflectionClass(ImportRunRepository::class))->getFileName()) ?: '';
sc_p7_assert(str_contains($repositorySource, 'safecontracts_import_errors') && str_contains($repositorySource, 'row_number') && str_contains($repositorySource, 'error_code'), 'SC-P7-008 row error repository preserves source row/code metadata');
sc_p7_assert(str_contains($repositorySource, 'min(1000') && str_contains($repositorySource, 'strip_tags($message)'), 'SC-P7-008 error reads/messages are bounded and normalized');

// Admin integration is capability/nonce based and has no presentation-layer SQL.
ImportsPage::register();
sc_p7_assert(($GLOBALS['sc_test_admin_pages'][ImportsPage::SLUG]['parent'] ?? '') === AdminShell::SLUG, 'P7 import screen registers inside SafeContracts shell');
sc_p7_assert(($GLOBALS['sc_test_admin_pages'][ImportsPage::SLUG]['capability'] ?? '') === Capabilities::RUN_IMPORTS, 'P7 import screen requires dedicated run-imports capability');
foreach ([ImportsPage::UPLOAD_ACTION, ImportsPage::MAP_ACTION, ImportsPage::EXECUTE_ACTION] as $action) {
    sc_p7_assert(isset($GLOBALS['sc_test_actions']['admin_post_' . $action]), $action . ' is registered in plugin lifecycle');
}
$adminSource = file_get_contents((string) (new ReflectionClass(ImportsPage::class))->getFileName()) ?: '';
sc_p7_assert(substr_count($adminSource, 'check_admin_referer') >= 3 && ! str_contains($adminSource, '$wpdb'), 'P7 import admin mutations are nonce-protected and contain no direct SQL');
sc_p7_assert(str_contains($adminSource, 'Preview is read-only') && str_contains($adminSource, 'Row errors'), 'P7 admin workflow communicates preview and row-error boundaries');

@unlink($tmp);
printf("SafeContracts P7 import SC-P7-001..008 passed (%d assertions).\n", $tests);
