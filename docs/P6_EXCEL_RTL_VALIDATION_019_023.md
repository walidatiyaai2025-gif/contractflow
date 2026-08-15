# P6 — Excel, RTL and admin-boundary validation (SC-P6-019..023)

## SC-P6-019 — Excel report generation

SafeContracts now generates a real `.xlsx` OOXML package server-side without a third-party spreadsheet dependency. The admin Reports screen submits the current normalized report filters to a capability-gated `admin-post` action. `ReportExportService` reuses `AdminReadRepository` for customers/contracts/payments/collections and `FollowUpService` for the follow-up queue, so the current server-side data scope remains authoritative.

Workbook sheets are: Summary, Customers, Contracts, Payments, Collections and Follow-up Queue. User-controlled cell values are written as inline strings; values beginning with `=`, `+`, `-` or `@` are prefixed with an apostrophe to prevent spreadsheet formula injection. Export completion emits `safecontracts_export_completed` with filter/row-count metadata only; no secrets or raw tokens are included.

The implementation intentionally does **not** add the P8 Excel REST endpoint. P8 can reuse this generation boundary later.

## SC-P6-020 — RTL/responsive admin UX

A final responsive stylesheet is layered after the existing SafeContracts base/core/operations/settings styles. It covers RTL alignment, narrow-screen filters/forms, touch-scrollable data tables, KPI/role grids and mobile card spacing. CSS remains a presentation concern only and carries no authorization semantics.

## SC-P6-021 — SafeContracts admin shell validation

Validation confirms the shell remains gated by `safecontracts_access`, child pages are recognized for scoped asset loading, unrelated WordPress screens do not receive SafeContracts admin assets, and the identity remains SafeContracts-only. Data authorization still lives in server-side capabilities/scope.

## SC-P6-022 — Login branding validation

Validation confirms login branding changes only presentation (`login_enqueue_scripts`, `login_headerurl`, `login_headertext`). It does not hook authentication/session functions and exposes no credential/token material.

## SC-P6-023 — Admin navigation cleanup validation

Validation confirms operational users with SafeContracts access have irrelevant WordPress menus hidden, while users with system-management capability retain WordPress administration. The SafeContracts menu itself cannot be hidden by the cleanup layer. Menu hiding is explicitly UX-only and never substitutes for server-side authorization.

## Automated evidence

`tests/php/admin_ui_019_023.php` covers OOXML package structure, UTF-8/Arabic preservation, formula-injection protection, scoped export/audit behavior, responsive/RTL asset layering, shell access/asset boundaries, login presentation-only behavior and navigation capability differences.
