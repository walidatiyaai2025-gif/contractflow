<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

use SafeContracts\Audit\AuditRecorder;
use SafeContracts\Admin\ImportsPage;
use SafeContracts\Import\ColumnMapping;
use SafeContracts\Import\DuplicateStrategy;
use SafeContracts\Import\ImportExecutionService;
use SafeContracts\Import\ImportPreviewService;
use SafeContracts\Import\ImportRowValidator;
use SafeContracts\Import\ImportRunRepository;
use SafeContracts\Import\ImportSummaryService;
use SafeContracts\Import\ImportUploadService;
use SafeContracts\Import\PrivateImportStorage;
use SafeContracts\Import\WorkbookReader;
use SafeContracts\Import\WorkbookUploadValidator;
use SafeContracts\Reports\XlsxWorkbook;
use SafeContracts\Roles\Capabilities;

$tests = 0;
function sc_p7v_assert(bool $ok, string $message): void
{
    global $tests;
    $tests++;
    if (! $ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}
function sc_p7v_expect(string $class, callable $fn, string $message): void
{
    try {
        $fn();
    } catch (Throwable $error) {
        sc_p7v_assert($error instanceof $class, $message);
        return;
    }
    sc_p7v_assert(false, $message);
}
function sc_p7v_run_row(string $status): array
{
    return [
        'id' => '77',
        'original_filename' => 'contracts.xlsx',
        'storage_key' => str_repeat('a', 64),
        'file_sha256' => str_repeat('b', 64),
        'file_size' => '2048',
        'status' => $status,
        'selected_sheet' => 'Contracts',
        'discovery_json' => json_encode(['sheets' => [[
            'name' => 'Contracts', 'path' => 'xl/worksheets/sheet1.xml', 'header_row' => 1,
            'headers' => [
                ['column' => 'A', 'original' => 'Customer Name', 'normalized' => 'customer name'],
                ['column' => 'B', 'original' => 'Contract Number', 'normalized' => 'contract number'],
            ],
        ]]], JSON_UNESCAPED_SLASHES),
        'mapping_json' => json_encode(['customer_name' => 'A', 'contract_number' => 'B'], JSON_UNESCAPED_SLASHES),
        'duplicate_strategy' => 'fail',
        'total_rows' => '3',
        'valid_rows' => '2',
        'imported_rows' => '1',
        'skipped_rows' => '0',
        'error_rows' => '1',
        'created_by' => '42',
        'created_at' => '2026-08-15 16:00:00',
        'updated_at' => '2026-08-15 16:01:00',
    ];
}

SafeContracts\Plugin::instance()->boot();
$GLOBALS['sc_test_current_caps'][Capabilities::RUN_IMPORTS] = true;

// SC-P7-009 — safe import summary and lifecycle audit evidence.
$GLOBALS['sc_test_result_queue'][] = [sc_p7v_run_row('completed_with_errors')];
$GLOBALS['sc_test_result_queue'][] = [['error_count' => '2']];
$summary = (new ImportSummaryService())->get(77);
sc_p7v_assert($summary['run_id'] === 77 && $summary['status'] === 'completed_with_errors', 'SC-P7-009 summary returns stable run identity/status');
sc_p7v_assert($summary['counts']['total_rows'] === 3 && $summary['counts']['error_entries'] === 2, 'SC-P7-009 summary exposes row/error counts');
sc_p7v_assert($summary['actor_id'] === 42 && $summary['created_at'] !== '' && $summary['updated_at'] !== '', 'SC-P7-009 summary preserves actor and timestamps');
sc_p7v_assert(! array_key_exists('storage_key', $summary) && ! array_key_exists('file_sha256', $summary), 'SC-P7-009 summary does not expose private storage/hash fields');
$auditContext = ImportSummaryService::auditContext($summary);
sc_p7v_assert(! str_contains(json_encode($auditContext) ?: '', 'contracts.xlsx') && ($auditContext['run_id'] ?? 0) === 77, 'SC-P7-009 audit context omits workbook filename/content while preserving run ID');
foreach (['safecontracts_import_uploaded','safecontracts_import_discovered','safecontracts_import_mapping_saved','safecontracts_import_validated','safecontracts_import_completed'] as $hook) {
    sc_p7v_assert(isset($GLOBALS['sc_test_actions'][$hook]), "SC-P7-009 audit recorder subscribes to {$hook}");
}
$beforeAuditQueries = count($GLOBALS['sc_test_queries']);
do_action('safecontracts_import_completed', [
    'run_id' => 77,
    'status' => 'completed',
    'file_sha256' => 'TOP-SECRET-HASH',
    'storage_key' => 'TOP-SECRET-PATH',
    'counts' => ['total_rows' => 2, 'imported_rows' => 2],
], 42);
$auditQueries = array_slice($GLOBALS['sc_test_queries'], $beforeAuditQueries);
$auditSql = implode("\n", $auditQueries);
sc_p7v_assert(str_contains($auditSql, "'import'") && str_contains($auditSql, "'import_completed'") && str_contains($auditSql, '77'), 'SC-P7-009 completed audit binds import run ID as entity identity');
sc_p7v_assert(! str_contains($auditSql, 'TOP-SECRET-HASH') && ! str_contains($auditSql, 'TOP-SECRET-PATH'), 'SC-P7-009 audit sanitization strips private workbook identifiers');

// SC-P7-010 — Excel upload validation/private staging.
$uploadValidatorSource = file_get_contents((string) (new ReflectionClass(WorkbookUploadValidator::class))->getFileName()) ?: '';
$storageSource = file_get_contents((string) (new ReflectionClass(PrivateImportStorage::class))->getFileName()) ?: '';
$uploadSource = file_get_contents((string) (new ReflectionClass(ImportUploadService::class))->getFileName()) ?: '';
sc_p7v_assert(str_contains($uploadValidatorSource, '20971520') && str_contains($uploadValidatorSource, "'xlsx'"), 'SC-P7-010 upload validation retains XLSX-only 20 MiB boundary');
sc_p7v_assert(str_contains($storageSource, 'SAFECONTRACTS_PRIVATE_DIR') && str_contains($storageSource, 'sys_get_temp_dir()') && ! str_contains($storageSource, 'wp_upload_dir'), 'SC-P7-010 staging defaults outside public uploads with production override');
sc_p7v_assert(str_contains($storageSource, 'is_uploaded_file') && str_contains($storageSource, '0600') && str_contains($storageSource, '0700'), 'SC-P7-010 upload move and filesystem permissions fail closed');
sc_p7v_assert(str_contains($uploadSource, 'safecontracts_import_uploaded') && str_contains($uploadSource, 'safecontracts_import_discovered') && ! str_contains($uploadSource, "'file_sha256' =>"), 'SC-P7-010 upload audit stages avoid workbook hash leakage');

// Build a real workbook used by discovery/mapping/preview validation.
$tmp = tempnam(sys_get_temp_dir(), 'sc-p7v-');
if (! is_string($tmp)) { throw new RuntimeException('Unable to create P7 validation workbook.'); }
$workbook = (new XlsxWorkbook())->build(['Contracts' => [
    ['Customer Name', 'Contract Number', 'Payment Sequence', 'Payment Due Date', 'Payment Amount'],
    ['Acme Co', 'SC-100', '1', '2026-09-01', '100.5000'],
    ['Beta Co', 'SC-200', '', '', ''],
]]);
file_put_contents($tmp, $workbook);

// SC-P7-011 — workbook field discovery remains bounded and non-executing.
$readerSource = file_get_contents((string) (new ReflectionClass(WorkbookReader::class))->getFileName()) ?: '';
sc_p7v_assert(str_contains($readerSource, 'MAX_ZIP_ENTRIES') && str_contains($readerSource, 'MAX_UNCOMPRESSED_BYTES') && str_contains($readerSource, 'MAX_SHEETS') && str_contains($readerSource, 'MAX_COLUMNS'), 'SC-P7-011 XLSX package/sheet/column boundaries are explicit');
sc_p7v_assert(str_contains($readerSource, 'vbaproject.bin') && str_contains($readerSource, 'xl/externallinks/') && str_contains($readerSource, 'connections.xml') && str_contains($readerSource, 'LIBXML_NONET'), 'SC-P7-011 macro/external/XML network content is rejected');
$discovery = ['sheets' => []];
if (class_exists(ZipArchive::class)) {
    $reader = new WorkbookReader();
    $discovery = $reader->discover($tmp);
    sc_p7v_assert(($discovery['sheets'][0]['name'] ?? '') === 'Contracts' && count($discovery['sheets'][0]['headers'] ?? []) === 5, 'SC-P7-011 real workbook headers are discovered deterministically');

    $formulaTmp = $tmp . '-formula.xlsx';
    copy($tmp, $formulaTmp);
    $zip = new ZipArchive();
    if ($zip->open($formulaTmp) !== true) { throw new RuntimeException('Unable to open formula validation workbook.'); }
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if (! is_string($sheetXml)) { throw new RuntimeException('Workbook sheet XML missing.'); }
    $sheetXml = preg_replace('/(<c[^>]*>)/', '$1<f>1+1</f>', $sheetXml, 1) ?? $sheetXml;
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
    $zip->close();
    sc_p7v_expect(InvalidArgumentException::class, fn () => (new WorkbookReader())->discover($formulaTmp), 'SC-P7-011 formula cells fail closed and are never evaluated');
    @unlink($formulaTmp);
} else {
    sc_p7v_assert(str_contains($readerSource, 'SafeContracts XLSX import requires PHP ZipArchive.'), 'SC-P7-011 missing ZipArchive fails explicitly');
}

// SC-P7-012 — mapping remains allow-listed/source-bound and required identities are mandatory.
$sheet = ColumnMapping::sheet($discovery, 'Contracts');
$mapping = (new ColumnMapping())->validate([
    'customer_name' => 'A', 'contract_number' => 'B', 'payment_sequence' => 'C', 'payment_due_date' => 'D', 'payment_amount' => 'E',
], $sheet);
sc_p7v_assert($mapping['customer_name'] === 'A' && $mapping['contract_number'] === 'B', 'SC-P7-012 valid mapping uses discovered source columns');
sc_p7v_expect(InvalidArgumentException::class, fn () => (new ColumnMapping())->validate(['customer_name' => 'A', 'contract_number' => 'A'], $sheet), 'SC-P7-012 duplicate source mapping fails closed');
$adminSource = file_get_contents((string) (new ReflectionClass(ImportsPage::class))->getFileName()) ?: '';
sc_p7v_assert(str_contains($adminSource, 'isEditableRun') && str_contains($adminSource, 'Completed, running and failed import runs are read-only.'), 'SC-P7-012 terminal runs cannot be remapped into an executable state');
sc_p7v_assert(str_contains($adminSource, 'safecontracts_import_mapping_saved') && str_contains($adminSource, 'mapped_fields'), 'SC-P7-012 mapping changes emit bounded audit evidence');

// SC-P7-013 — preview is bounded/read-only.
$previewSource = file_get_contents((string) (new ReflectionClass(ImportPreviewService::class))->getFileName()) ?: '';
sc_p7v_assert(str_contains($previewSource, 'min(100') && ! str_contains($previewSource, '$wpdb') && ! str_contains($previewSource, 'CustomerService') && ! str_contains($previewSource, 'ContractService'), 'SC-P7-013 preview has no business persistence path and remains bounded');
if (class_exists(ZipArchive::class)) {
    $preview = (new ImportPreviewService())->preview($tmp, 'Contracts', 1, $mapping, 10);
    sc_p7v_assert(($preview[0]['row_number'] ?? 0) === 2 && ($preview[0]['data']['contract_number'] ?? '') === 'SC-100', 'SC-P7-013 preview preserves source row identity and mapped values');
}

// SC-P7-014 — row validation rejects malformed/non-scalar domain values.
$rowValidator = new ImportRowValidator();
$valid = $rowValidator->validate([
    'customer_name' => 'Acme', 'customer_email' => 'ops@example.test', 'contract_number' => 'SC-100',
    'contract_start_date' => '2026-08-01', 'contract_end_date' => '2026-12-31', 'contract_base_value' => '1000.25',
    'payment_sequence' => '1', 'payment_due_date' => '2026-09-01', 'payment_amount' => '100.50',
]);
sc_p7v_assert($valid['valid'] === true && $valid['data']['payment_amount'] === '100.5000', 'SC-P7-014 valid row normalization is deterministic');
$invalid = $rowValidator->validate([
    'customer_name' => ['array-not-text'], 'customer_email' => ['bad'], 'contract_number' => '',
    'contract_start_date' => ['bad'], 'contract_base_value' => ['bad'], 'payment_sequence' => ['bad'], 'payment_due_date' => ['bad'], 'payment_amount' => ['bad'],
]);
sc_p7v_assert($invalid['valid'] === false && count($invalid['errors']) >= 3, 'SC-P7-014 complex malformed values fail as validation errors without unsafe casts');

// SC-P7-015 — duplicate behavior is explicit; financial identity cannot be overwritten.
sc_p7v_assert(DuplicateStrategy::normalize('fail') === 'fail' && DuplicateStrategy::normalize('SKIP') === 'skip' && DuplicateStrategy::normalize('update') === 'update', 'SC-P7-015 duplicate strategies are explicit');
sc_p7v_expect(InvalidArgumentException::class, fn () => DuplicateStrategy::normalize('overwrite'), 'SC-P7-015 unknown overwrite strategy fails closed');
$executionSource = file_get_contents((string) (new ReflectionClass(ImportExecutionService::class))->getFileName()) ?: '';
sc_p7v_assert(str_contains($executionSource, 'Duplicate contract belongs to a different customer') && str_contains($executionSource, 'Existing payment amount cannot be changed') && str_contains($executionSource, 'Existing payment reference cannot be changed'), 'SC-P7-015 duplicate update cannot cross customer or rewrite payment financial identity');

// SC-P7-016 — execution validates before writes, is transactional and terminal-state safe.
sc_p7v_assert(str_contains($executionSource, "['mapped', 'validated']") && str_contains($executionSource, 'clearErrors($runId)') && str_contains($executionSource, "updateStatus(\$runId, 'validated'"), 'SC-P7-016 only mapped/validated runs can start a fresh validation attempt');
sc_p7v_assert(str_contains($executionSource, "query('START TRANSACTION')") && str_contains($executionSource, "query('COMMIT')") && str_contains($executionSource, "query('ROLLBACK')"), 'SC-P7-016 row writes retain transaction/rollback semantics');
sc_p7v_assert(str_contains($executionSource, 'CustomerService') && str_contains($executionSource, 'ContractService') && str_contains($executionSource, 'PaymentService'), 'SC-P7-016 execution delegates business mutation to domain services');
$GLOBALS['sc_test_result_queue'][] = [sc_p7v_run_row('completed')];
$queryCountBeforeTerminal = count($GLOBALS['sc_test_queries']);
sc_p7v_expect(DomainException::class, fn () => (new ImportExecutionService())->execute(77, 'fail'), 'SC-P7-016 completed run cannot be executed again');
$terminalQueries = array_slice($GLOBALS['sc_test_queries'], $queryCountBeforeTerminal);
sc_p7v_assert(! str_contains(implode("\n", $terminalQueries), 'START TRANSACTION') && ! str_contains(implode("\n", $terminalQueries), 'DELETE FROM'), 'SC-P7-016 terminal rejection happens before error clearing or mutation');

// SC-P7-017 — row errors are bounded, parameterized and current-attempt scoped.
$repoSource = file_get_contents((string) (new ReflectionClass(ImportRunRepository::class))->getFileName()) ?: '';
sc_p7v_assert(str_contains($repoSource, 'clearErrors') && str_contains($repoSource, 'errorCount') && str_contains($repoSource, 'min(1000'), 'SC-P7-017 error ledger supports retry cleanup, bounded reads and summary count');
sc_p7v_assert(! str_contains($repoSource, 'addslashes(substr($field') && str_contains($repoSource, 'field_name, error_code, message') && str_contains($repoSource, 'VALUES (%d, %d, %s, %s, %s'), 'SC-P7-017 field names/messages use prepared parameters instead of manual SQL quoting');
$queryCountBeforeError = count($GLOBALS['sc_test_queries']);
(new ImportRunRepository())->addError(77, 9, "field'x", 'Validation.Bad Code', '<b>Bad</b> value');
$errorSql = implode("\n", array_slice($GLOBALS['sc_test_queries'], $queryCountBeforeError));
sc_p7v_assert(str_contains($errorSql, "field\\'x") && str_contains($errorSql, 'validation.bad_code') && ! str_contains($errorSql, '<b>'), 'SC-P7-017 error field/code/message are normalized and safely prepared');

@unlink($tmp);
printf("SafeContracts P7 final validation SC-P7-009..017 passed (%d assertions).\n", $tests);
