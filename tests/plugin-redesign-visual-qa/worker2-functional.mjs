import { chromium } from 'playwright';
import fs from 'node:fs';
import path from 'node:path';

const baseUrl = process.env.SC_QA_BASE_URL || 'http://127.0.0.1:8889';
const username = process.env.SC_QA_USER || 'visual-admin';
const password = process.env.SC_QA_PASSWORD || 'VisualQa-Only-2026!';
const outDir = path.join(process.env.SC_QA_OUTPUT || 'visual-qa-artifacts', '_functional');
fs.mkdirSync(outDir, { recursive: true });

const results = [];
const assert = (condition, message) => {
  if (!condition) throw new Error(message);
};
const record = (screenId, action, evidence = {}) => results.push({screenId, action, ...evidence});

const browser = await chromium.launch({headless: true});
const context = await browser.newContext({viewport: {width: 1440, height: 1000}, acceptDownloads: true});
const page = await context.newPage();

await page.goto(`${baseUrl}/wp-login.php`, {waitUntil: 'domcontentloaded'});
await page.fill('#user_login', username);
await page.fill('#user_pass', password);
await Promise.all([
  page.waitForNavigation({waitUntil: 'domcontentloaded'}),
  page.click('#wp-submit'),
]);
assert(!page.url().includes('wp-login.php'), 'Functional QA login failed.');

async function gotoAdmin(slug, query = '') {
  const suffix = query ? `&${query}` : '';
  const response = await page.goto(`${baseUrl}/wp-admin/admin.php?page=${slug}${suffix}`, {waitUntil: 'networkidle'});
  assert(response && response.status() < 400, `${slug}: HTTP ${response?.status()}`);
  assert(!page.url().includes('wp-login.php'), `${slug}: authentication lost`);
  assert(!(await page.locator('.wp-die-message').count()), `${slug}: wp_die rendered`);
}

async function formForAction(action) {
  const form = page.locator(`form:has(input[name="action"][value="${action}"])`).first();
  assert(await form.count(), `Missing form for action ${action}`);
  return form;
}

async function submitAndWait(form) {
  await Promise.all([
    page.waitForNavigation({waitUntil: 'domcontentloaded'}),
    form.evaluate((node) => node.requestSubmit()),
  ]);
}

async function selectContaining(select, text) {
  const value = await select.locator('option').evaluateAll((options, needle) => {
    const option = options.find((item) => (item.textContent || '').includes(needle));
    return option?.value || '';
  }, text);
  assert(value !== '', `No option containing ${text}`);
  await select.selectOption(value);
  return value;
}

// SC-023 validation fails closed before creating the method used by Collections.
await gotoAdmin('safecontracts-payment-methods');
let form = await formForAction('safecontracts_save_payment_method');
await form.locator('input[name="code"]').fill('qa_invalid_method');
await form.locator('input[name="name"]').fill('');
await form.evaluate((node) => { node.noValidate = true; });
await submitAndWait(form);
assert(new URL(page.url()).searchParams.get('safecontracts_status') === 'invalid', 'SC-023 invalid payment method was not rejected');
record('SC-023', 'reject invalid payment method');

// SC-023 create an active payment method for the settlement flow.
await gotoAdmin('safecontracts-payment-methods');
form = await formForAction('safecontracts_save_payment_method');
await form.locator('input[name="code"]').fill('qa_func_method');
await form.locator('input[name="name"]').fill('QA Functional Method');
await form.locator('input[name="display_order"]').fill('99');
if (!(await form.locator('input[name="is_active"]').isChecked())) await form.locator('input[name="is_active"]').check();
await submitAndWait(form);
assert(new URL(page.url()).searchParams.get('safecontracts_status') === 'saved', 'SC-023 payment method save did not report saved');
assert((await page.locator('body').innerText()).includes('QA Functional Method'), 'SC-023 saved payment method not visible');
record('SC-023', 'create active payment method');

// SC-017 exercise view/filter/edit/delete on a disposable unsettled payment.
await gotoAdmin('safecontracts-payments');
form = await formForAction('safecontracts_save_payment_admin');
await selectContaining(form.locator('select[name="contract_id"]'), 'QA-AR-2026-001');
await form.locator('input[name="reference"]').fill('QA-FUNC-PAYMENT-CRUD');
await form.locator('input[name="original_amount"]').fill('50.00');
await form.locator('input[name="due_date"]').fill('2026-09-15');
await form.locator('input[name="expected_payment_date"]').fill('2026-09-16');
await submitAndWait(form);
let url = new URL(page.url());
assert(url.searchParams.get('safecontracts_status') === 'saved', 'SC-017 CRUD payment save did not report saved');
const crudPaymentId = url.searchParams.get('payment_id');
const crudContractId = url.searchParams.get('contract_id');
assert(crudPaymentId && Number(crudPaymentId) > 0, 'SC-017 CRUD payment save did not return payment_id');
assert(crudContractId && Number(crudContractId) > 0, 'SC-017 CRUD payment save did not preserve contract_id');
record('SC-017', 'create disposable payment for CRUD', {paymentId: crudPaymentId, contractId: crudContractId});

await gotoAdmin('safecontracts-payments', `payment_id=${crudPaymentId}`);
let body = await page.locator('body').innerText();
assert(body.includes('QA-FUNC-PAYMENT-CRUD'), 'SC-017 Open did not render the selected payment details');
record('SC-017', 'open payment details', {paymentId: crudPaymentId});

await gotoAdmin('safecontracts-payments', `payment_id=${crudPaymentId}&payment_action=edit`);
form = await formForAction('safecontracts_save_payment_admin');
assert(await form.locator('input[name="payment_id"]').inputValue() === crudPaymentId, 'SC-017 edit form targets the wrong payment');
await form.locator('input[name="reference"]').fill('QA-FUNC-PAYMENT-CRUD-EDITED');
await form.locator('input[name="original_amount"]').fill('55.00');
await form.locator('input[name="due_date"]').fill('2026-09-20');
await form.locator('input[name="expected_payment_date"]').fill('2026-09-21');
await submitAndWait(form);
assert(new URL(page.url()).searchParams.get('safecontracts_status') === 'saved', 'SC-017 edit did not report saved');
body = await page.locator('body').innerText();
assert(body.includes('QA-FUNC-PAYMENT-CRUD-EDITED'), 'SC-017 edited payment values did not persist');
record('SC-017', 'edit unsettled payment', {paymentId: crudPaymentId});

const crudFilter = `contract_id=${crudContractId}&date_from=2026-09-20&date_to=2026-09-20`;
await gotoAdmin('safecontracts-payments', crudFilter);
body = await page.locator('body').innerText();
assert(body.includes('QA-FUNC-PAYMENT-CRUD-EDITED'), 'SC-017 contract/date filter did not retain matching payment');
assert(await page.locator('select[name="contract_id"]').inputValue() === crudContractId, 'SC-017 contract filter was not applied server-side');
assert(await page.locator('input[name="date_from"]').inputValue() === '2026-09-20', 'SC-017 date_from filter was not applied');
assert(await page.locator('input[name="date_to"]').inputValue() === '2026-09-20', 'SC-017 date_to filter was not applied');
record('SC-017', 'contract and due-date filters');

const crudRow = page.locator('tr').filter({hasText: 'QA-FUNC-PAYMENT-CRUD-EDITED'}).first();
assert(await crudRow.count(), 'SC-017 edited payment row not found for safe delete');
const crudDeleteForm = crudRow.locator('form:has(input[name="action"][value="safecontracts_delete_payment_admin"])');
assert(await crudDeleteForm.count(), 'SC-017 permitted unsettled payment has no delete action');
page.once('dialog', (dialog) => dialog.accept());
await submitAndWait(crudDeleteForm);
assert(new URL(page.url()).searchParams.get('safecontracts_status') === 'deleted', 'SC-017 safe delete did not report deleted');
await gotoAdmin('safecontracts-payments', crudFilter);
body = await page.locator('body').innerText();
assert(!body.includes('QA-FUNC-PAYMENT-CRUD-EDITED'), 'SC-017 archived payment remained in active filtered rows');
record('SC-017', 'delete permitted unsettled payment', {paymentId: crudPaymentId});

// SC-017 create the real AR obligation that subsequent scopes will reconcile.
await gotoAdmin('safecontracts-payments');
form = await formForAction('safecontracts_save_payment_admin');
await selectContaining(form.locator('select[name="contract_id"]'), 'QA-AR-2026-001');
await form.locator('input[name="reference"]').fill('QA-FUNC-PAYMENT');
await form.locator('input[name="original_amount"]').fill('123.45');
await form.locator('input[name="due_date"]').fill('2026-09-30');
await form.locator('input[name="expected_payment_date"]').fill('2026-09-30');
await submitAndWait(form);
url = new URL(page.url());
assert(url.searchParams.get('safecontracts_status') === 'saved', 'SC-017 payment save did not report saved');
const paymentId = url.searchParams.get('payment_id');
assert(paymentId && Number(paymentId) > 0, 'SC-017 payment save did not return payment_id');
assert((await page.locator('body').innerText()).includes('QA-FUNC-PAYMENT'), 'SC-017 saved payment not visible');
record('SC-017', 'create AR payment for settlement', {paymentId});

// SC-018 record a valid collection and prove payment reconciliation.
await gotoAdmin('safecontracts-collections', `payment_id=${paymentId}`);
form = await formForAction('safecontracts_record_collection_admin');
await form.locator('select[name="payment_id"]').selectOption(paymentId);
await form.locator('input[name="amount"]').fill('23.45');
await form.locator('input[name="collection_date"]').fill('2026-08-24');
await selectContaining(form.locator('select[name="payment_method_id"]'), 'QA Functional Method');
await form.locator('input[name="reference"]').fill('QA-FUNC-COLLECTION');
await form.locator('textarea[name="details"]').fill('Worker #2 functional reconciliation evidence');
await submitAndWait(form);
url = new URL(page.url());
assert(url.searchParams.get('safecontracts_status') === 'saved', 'SC-018 valid collection did not report saved');
body = await page.locator('body').innerText();
assert(body.includes('QA Functional Method'), 'SC-018 payment method not visible in collection ledger');
assert(body.includes('23.45'), 'SC-018 collection amount not visible in ledger');
record('SC-018', 'record valid collection');

await gotoAdmin('safecontracts-payments', `payment_id=${paymentId}`);
body = await page.locator('body').innerText();
assert(body.includes('KWD 100.00') || body.includes('KWD 100'), 'SC-018 settlement did not reconcile remaining payment to KWD 100');
record('SC-018', 'verify payment remaining reconciliation', {remaining: 'KWD 100.00'});

// SC-018 negative path: over-settlement must fail closed.
await gotoAdmin('safecontracts-collections', `payment_id=${paymentId}`);
form = await formForAction('safecontracts_record_collection_admin');
await form.locator('select[name="payment_id"]').selectOption(paymentId);
await form.locator('input[name="amount"]').fill('200');
await form.locator('input[name="collection_date"]').fill('2026-08-24');
await selectContaining(form.locator('select[name="payment_method_id"]'), 'QA Functional Method');
await form.locator('input[name="reference"]').fill('QA-FUNC-OVERSETTLE');
await submitAndWait(form);
assert(new URL(page.url()).searchParams.get('safecontracts_status') === 'invalid', 'SC-018 over-settlement was not rejected');
await page.screenshot({path: path.join(outDir, 'SC-018-validation.png'), fullPage: true});
record('SC-018', 'reject over-settlement');

// SC-019 append real follow-up history, exercise a state change and verify date/payment filters.
await gotoAdmin('safecontracts-followups', `payment_id=${paymentId}`);
form = await formForAction('safecontracts_save_followup_admin');
await form.locator('select[name="followup_operation"]').selectOption('note');
await form.locator('textarea[name="note"]').fill('QA functional follow-up event');
await submitAndWait(form);
assert(new URL(page.url()).searchParams.get('safecontracts_status') === 'saved', 'SC-019 follow-up did not report saved');
body = await page.locator('body').innerText();
assert(body.includes('QA functional follow-up event'), 'SC-019 append-only history does not show saved event');
record('SC-019', 'append follow-up event and verify history');

form = await formForAction('safecontracts_save_followup_admin');
await form.locator('select[name="followup_operation"]').selectOption('issue');
await form.locator('textarea[name="note"]').fill('QA functional issue state');
await submitAndWait(form);
assert(new URL(page.url()).searchParams.get('safecontracts_status') === 'saved', 'SC-019 issue state did not report saved');
body = await page.locator('body').innerText();
assert(body.includes('QA functional issue state') && body.includes('Issue'), 'SC-019 issue state/history is not visible');
record('SC-019', 'set and display follow-up issue state');

await gotoAdmin('safecontracts-followups', `payment_id=${paymentId}&date_from=2026-01-01&date_to=2026-12-31`);
body = await page.locator('body').innerText();
assert(body.includes('QA functional issue state'), 'SC-019 payment/date filter lost selected follow-up history');
assert(await page.locator('input[name="date_from"]').inputValue() === '2026-01-01', 'SC-019 date_from filter was not applied');
assert(await page.locator('input[name="date_to"]').inputValue() === '2026-12-31', 'SC-019 date_to filter was not applied');
await page.screenshot({path: path.join(outDir, 'SC-019-history.png'), fullPage: true});
record('SC-019', 'payment and date filters preserve state/history');

// SC-020 exercise real server-side finance filters and a known calculated outstanding value.
await gotoAdmin('safecontracts-finance', 'direction=receivable&currency_code=KWD&due_from=2026-09-30&due_to=2026-09-30');
body = await page.locator('body').innerText();
assert(await page.locator('select[name="direction"]').inputValue() === 'receivable', 'SC-020 receivable direction filter was not applied');
assert(await page.locator('select[name="currency_code"]').inputValue() === 'KWD', 'SC-020 KWD currency filter was not applied');
assert(await page.locator('input[name="due_from"]').inputValue() === '2026-09-30', 'SC-020 due_from filter was not applied');
assert(await page.locator('input[name="due_to"]').inputValue() === '2026-09-30', 'SC-020 due_to filter was not applied');
assert(body.includes('KWD 100.00') || body.includes('KWD 100'), 'SC-020 real filtered outstanding value did not reconcile to KWD 100');
record('SC-020', 'server-side AR/KWD/date filters with calculated outstanding', {outstanding: 'KWD 100.00'});

// SC-021 validation state plus real supported XLSX export.
await gotoAdmin('safecontracts-reports', 'date_from=2026-10-01&date_to=2026-09-01');
body = await page.locator('body').innerText();
assert(body.includes('Invalid period'), 'SC-021 invalid period validation did not render');
await page.screenshot({path: path.join(outDir, 'SC-021-invalid-period.png'), fullPage: true});
record('SC-021', 'invalid period validation');

await gotoAdmin('safecontracts-reports', 'financial_direction=receivable&currency_code=KWD');
form = await formForAction('safecontracts_export_report_xlsx');
const [download] = await Promise.all([
  page.waitForEvent('download'),
  form.evaluate((node) => node.requestSubmit()),
]);
const suggested = download.suggestedFilename();
assert(suggested.toLowerCase().endsWith('.xlsx'), `SC-021 export is not XLSX: ${suggested}`);
await download.saveAs(path.join(outDir, suggested));
record('SC-021', 'real server-side XLSX export', {filename: suggested});

// SC-022 invalid upload fails closed.
await gotoAdmin('safecontracts-imports');
fs.writeFileSync('/tmp/worker2-invalid.txt', 'not an xlsx workbook');
form = await formForAction('safecontracts_import_upload');
await form.locator('input[name="workbook"]').setInputFiles('/tmp/worker2-invalid.txt');
await submitAndWait(form);
assert(new URL(page.url()).searchParams.get('safecontracts_status') === 'invalid_upload', 'SC-022 invalid upload was not rejected');
record('SC-022', 'reject invalid workbook');

// SC-022 real XLSX upload -> mapping -> execution through WordPress admin actions.
await gotoAdmin('safecontracts-imports');
form = await formForAction('safecontracts_import_upload');
await form.locator('input[name="workbook"]').setInputFiles('/tmp/worker2-functional.xlsx');
await submitAndWait(form);
url = new URL(page.url());
assert(url.searchParams.get('safecontracts_status') === 'uploaded', 'SC-022 valid XLSX upload did not report uploaded');
const runId = url.searchParams.get('run_id');
assert(runId && Number(runId) > 0, 'SC-022 upload did not return run_id');

form = await formForAction('safecontracts_import_mapping');
await selectContaining(form.locator('select[name="selected_sheet"]'), 'Contracts');
await form.locator('select[name="mapping[customer_name]"]').selectOption('A');
await form.locator('select[name="mapping[contract_number]"]').selectOption('B');
await submitAndWait(form);
assert(new URL(page.url()).searchParams.get('safecontracts_status') === 'mapped', 'SC-022 mapping did not report mapped');

form = await formForAction('safecontracts_import_execute');
await form.locator('select[name="duplicate_strategy"]').selectOption('fail');
await submitAndWait(form);
url = new URL(page.url());
const importStatus = url.searchParams.get('safecontracts_status') || '';
assert(!['execution_failed', 'invalid_mapping', 'invalid_upload'].includes(importStatus), `SC-022 execution failed: ${importStatus}`);
body = await page.locator('body').innerText();
assert(body.includes('QA-IMPORT-2026-001') || body.toLowerCase().includes('completed'), 'SC-022 executed import has no completion evidence');
await page.screenshot({path: path.join(outDir, 'SC-022-executed-import.png'), fullPage: true});
record('SC-022', 'real XLSX upload/map/execute', {runId, importStatus});

// SC-023 safe deactivate after the method has real settlement history.
await gotoAdmin('safecontracts-payment-methods');
let methodRow = page.locator('tr').filter({hasText: 'qa_func_method'}).first();
assert(await methodRow.count(), 'SC-023 created method row not found for safe deactivate');
const deleteForm = methodRow.locator('form:has(input[name="action"][value="safecontracts_delete_payment_method"])');
assert(await deleteForm.count(), 'SC-023 active method has no safe delete/deactivate form');
page.once('dialog', (dialog) => dialog.accept());
await submitAndWait(deleteForm);
assert(new URL(page.url()).searchParams.get('safecontracts_status') === 'deleted', 'SC-023 safe deactivate did not report deleted');
record('SC-023', 'safe deactivate payment method');

await gotoAdmin('safecontracts-payment-methods', 'method=qa_func_method');
methodRow = page.locator('tr').filter({hasText: 'qa_func_method'}).first();
assert(await methodRow.count(), 'SC-023 inactive method disappeared from administrative history');
assert((await methodRow.innerText()).includes('Inactive'), 'SC-023 deactivated method is not presented as inactive');
record('SC-023', 'inactive state remains administratively visible');

await gotoAdmin('safecontracts-collections', `payment_id=${paymentId}`);
body = await page.locator('body').innerText();
assert(body.includes('QA Functional Method'), 'SC-023 deactivation rewrote historical collection method reference');
const activeMethodOptions = await page.locator('select[name="payment_method_id"] option').allTextContents();
assert(!activeMethodOptions.some((text) => text.includes('QA Functional Method')), 'SC-023 inactive method is still offered for new settlement');
record('SC-023', 'historical settlement method preserved while inactive');

// SC-023 reactivate through the same validated save path; historical references remain untouched.
await gotoAdmin('safecontracts-payment-methods', 'method=qa_func_method');
form = await formForAction('safecontracts_save_payment_method');
assert(await form.locator('input[name="original_code"]').inputValue() === 'qa_func_method', 'SC-023 reactivation edit form lost stable method code');
if (!(await form.locator('input[name="is_active"]').isChecked())) await form.locator('input[name="is_active"]').check();
await submitAndWait(form);
assert(new URL(page.url()).searchParams.get('safecontracts_status') === 'saved', 'SC-023 reactivation did not report saved');
methodRow = page.locator('tr').filter({hasText: 'qa_func_method'}).first();
assert(await methodRow.count(), 'SC-023 reactivated method row not found');
assert((await methodRow.innerText()).includes('Active'), 'SC-023 method did not return to active state');
record('SC-023', 'reactivate payment method with stable code');

await gotoAdmin('safecontracts-collections', `payment_id=${paymentId}`);
body = await page.locator('body').innerText();
assert(body.includes('QA Functional Method'), 'SC-023 reactivation disturbed historical settlement reference');
record('SC-023', 'historical settlement reference preserved after reactivation');

fs.writeFileSync(path.join(outDir, 'worker2-functional-summary.json'), JSON.stringify(results, null, 2));
fs.writeFileSync(path.join(outDir, 'WORKER2_FUNCTIONAL_QA.md'), [
  '# Worker #2 Functional QA',
  '',
  '- Runtime: disposable real WordPress + MySQL + authenticated wp-admin + Playwright.',
  '- SC-017: create, Open, edit, contract/date filters and permitted safe delete PASS; settled test payment then persists for downstream scopes.',
  '- SC-018: collection save, settlement reconciliation, and over-settlement rejection PASS.',
  '- SC-019: append-only history, issue state and payment/date filters PASS.',
  '- SC-020: server-side AR/KWD/due-date filters and known KWD 100 outstanding calculation PASS.',
  '- SC-021: invalid date validation and real XLSX export PASS.',
  '- SC-022: invalid upload rejection and real XLSX upload/map/execute PASS.',
  '- SC-023: validation, active creation, safe deactivate, inactive-state enforcement, historical reference preservation and reactivation PASS.',
  '',
].join('\n'));

await browser.close();
console.log(`Worker #2 functional QA passed (${results.length} evidence points).`);