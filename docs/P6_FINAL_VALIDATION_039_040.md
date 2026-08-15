# P6 final validation — SC-P6-039..040

This closes the final two planned P6 tasks after the independent post-validation hardening in #207/#208.

## SC-P6-039 — Excel report generation — Validate

- Report viewing and report export remain separate capabilities (`VIEW_REPORTS` vs `EXPORT_REPORTS`).
- The admin export action and `ReportExportService` both enforce export authorization.
- Filters are normalized server-side and authoritative report/read models are reused.
- Formula-like workbook cell values are emitted as text to prevent spreadsheet formula injection.
- Export audit evidence contains normalized filters and row counts rather than credentials or workbook payloads.

## SC-P6-040 — RTL/responsive admin UX — Validate

- The final responsive stylesheet remains layered after core/operations/settings styles.
- Explicit RTL and WordPress RTL body modes are supported.
- 960px, 782px and 480px breakpoints cover grid collapse, stacked forms and mobile card sizing.
- Wide tables remain horizontally scrollable on narrow screens.
- CSS contains no capability names or authorization logic; authorization remains server-side.

## Regression evidence

`tests/php/admin_ui_validation_039_040.php` is wired into `scripts/test-php.sh` and runs under the repository Quality Gates workflow.
