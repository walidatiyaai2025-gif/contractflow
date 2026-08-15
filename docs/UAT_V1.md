# SafeContracts V1 — User Acceptance Test Scenarios

Each scenario has explicit preconditions, authorization expectations and pass criteria. UAT data must be disposable non-production test data. Server responses, database balances and audit events are authoritative.

## UAT-ADMIN-01 — System Administrator configuration

**Preconditions:** a WordPress user with the SafeContracts System Administrator baseline capabilities; Firebase test project reference available in the approved secret manager.

**Steps:** open SafeContracts settings; update organization/page-size configuration; update mobile feature flags; update Firebase public identifiers and secret-reference name; review users/roles and audit views.

**Pass criteria:** all management pages are available; invalid values fail visibly; only a Firebase secret *reference identifier* is stored; no secret value is rendered in admin/mobile/API responses; configuration changes are audit-visible.

## UAT-MANAGER-01 — Manager portfolio operation

**Preconditions:** Manager account; at least two customers, two contracts assigned to different accountants, scheduled payments and one overdue payment.

**Steps:** view dashboard with all-accountant scope; filter customer/contract; edit an allowed contract field; reassign a contract; record/manage payment/follow-up operations; export the filtered report.

**Pass criteria:** Manager can view all portfolio data because `safecontracts_view_all` is granted; edits/assignment/payment/follow-up/export actions are capability-gated and audited; export reflects the same authorized filters and server values.

## UAT-ACCOUNTANT-01 — Assigned-only portfolio

**Preconditions:** Accountant account; one contract assigned to that account and one assigned to another accountant.

**Steps:** open dashboard/list/detail for own contract; attempt direct-ID access to the other contract/payment; create an assigned contract; record a valid partial collection; add follow-up state/note; export current authorized data.

**Pass criteria:** assigned data is available; other-accountant direct IDs are denied; new unassigned Accountant contract auto-scopes to the current accountant; Accountant has no default `safecontracts_edit_contracts`; collection/payment/follow-up operations preserve exact server balances and audit history; export cannot widen the assigned scope.

## UAT-VIEWER-01 — Read-only access

**Preconditions:** Viewer account with assigned-scope test data.

**Steps:** view dashboard/reports for assigned data; attempt contract/payment/collection/follow-up/import/settings mutations and Excel export.

**Pass criteria:** assigned read/report views work; mutation/export/import/system-management controls are unavailable or denied server-side; no authoritative data changes.

## UAT-COLLECTION-01 — Partial then full settlement

**Preconditions:** active non-archived contract; scheduled payment with known original amount; active payment method.

**Steps:** record a partial collection; verify paid/remaining/status; then record exactly the remaining amount.

**Pass criteria:** payment method is mandatory; proof is optional Media ID; partial result is `partially_paid`; final remaining is `0.0000` and status is `paid`; ledger total equals stored paid amount; over-collection is rejected; each row write is transactional and audit-visible.

## UAT-NOTIFY-01 — Reminder and inbox flow

**Preconditions:** active due reminder rule; assigned accountant and manager recipients with registered test devices; unpaid eligible payment.

**Steps:** run reminder processing; verify delivery log; open mobile notification inbox; mark delivered item read; repeat processing for the same logical occurrence; settle payment and process again.

**Pass criteria:** recipients are server-derived; duplicate/retry handling is idempotent; inbox is current-user scoped; deep link is a known internal destination with positive ID; settled payment suppresses future reminder planning; Firebase remains transport-only.

## UAT-IMPORT-01 — Validate-before-write import

**Preconditions:** authorized import user; one valid workbook and one workbook containing an invalid row.

**Steps:** upload/discover/map/preview; execute invalid workbook; correct data and execute; test duplicate strategies.

**Pass criteria:** invalid rows are reported before any business write; terminal runs cannot be replayed; each imported business row is transaction protected; duplicate policy is explicit; existing payment amount/reference cannot be silently rewritten; audit summary exists.

## UAT-EXPORT-01 — Scoped XLSX report

**Preconditions:** Manager and Accountant test accounts; report data spanning at least two accountants.

**Steps:** export as Manager with accountant/customer/date filters; export as Accountant using own accessible filters; attempt export without export capability.

**Pass criteria:** XLSX content type/filename are stable; workbook is server-generated; Manager explicit view-all filter is honored; Accountant export contains assigned-only data; missing export capability returns forbidden; no secret/private transport fields are exported.

## UAT-RECOVERY-01 — Restore rehearsal

**Preconditions:** disposable WordPress recovery environment and a backup set produced according to `docs/RECOVERY_RUNBOOK.md`.

**Steps:** restore tables/options/user meta/media/users; restore environment secrets by reference; activate plugin; run migrations and Quality Gates; execute UAT-ACCOUNTANT-01 and UAT-COLLECTION-01 smoke subsets.

**Pass criteria:** IDs and financial/audit relationships are intact; migrations reach the current version without destructive SQL; attachment/proof Media IDs resolve; authorized scope works after restore; no secret value was stored in the repository/runbook.
