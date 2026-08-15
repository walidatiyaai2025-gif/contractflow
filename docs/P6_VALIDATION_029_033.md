# P6 validation — SC-P6-029..033

This slice independently validates the SafeContracts operational admin screens after their initial P6 implementation. It does not move P7/P8 work forward.

## SC-P6-029 — Collections screen
- Collection ledger queries remain server-side and assignment-scoped.
- Collection mutation remains capability + nonce gated and delegates to `CollectionService`.
- Active payment-method master data remains authoritative; proof remains optional.
- Presentation contains no direct SQL.

## SC-P6-030 — Follow-up screen
- Follow-up writes require `MANAGE_FOLLOWUPS` and a WordPress nonce.
- Note, promise-to-pay, issue, defer and escalation actions all delegate to `FollowUpService`.
- Promise/deferred dates remain operational metadata and do not rewrite contractual `due_date`.
- Follow-up history remains append-only at the UI boundary.

## SC-P6-031 — Notifications screen
- Notification operations require `MANAGE_NOTIFICATIONS`.
- Rule and delivery-log reads remain bounded through notification services/repositories.
- Raw Firebase credentials/access tokens/device-token values are not rendered.
- Output is escaped and contains no direct SQL.

## SC-P6-032 — Reports screen
- Report aggregates stay server-side and `VIEW_ASSIGNED` cannot be widened through an accountant filter.
- Contractual `due_date` remains authoritative for overdue reporting.
- Filters are normalized through `DashboardFilters` and XLSX generation remains delegated to `ReportExportService`.
- View/export retain capability and nonce boundaries.

## SC-P6-033 — Users/roles screen
- Access remains `MANAGE_USERS` gated.
- Effective grants are read from WordPress roles and the SafeContracts capability registry.
- The directory remains intentionally read-only and introduces no mutation route.
- Password data is not read/rendered; output is escaped.

## Regression evidence

`tests/php/admin_ui_validation_029_033.php` validates these boundaries and is wired into `scripts/test-php.sh`, which is executed by the repository Quality Gates workflow.
