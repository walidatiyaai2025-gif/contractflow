# SafeContracts — P1 Validation SC-P1-010..013 + P2 Contract Model SC-P2-001

Scope: five production tasks completed as one dependency-ordered delivery.

## SC-P1-010 — Payment method master table — Validate

**Result: PASS**

Validated implementation:

- Dedicated `safecontracts_payment_methods` table exists in the versioned migration framework.
- Stable `code` is unique.
- `name`, `display_order`, `is_active` and timestamps are persisted explicitly.
- Active/reference reads are ordered by `display_order` and then name.
- Administration writes use prepared SQL and stable-code upsert behavior.
- The implementation uses the schema field `display_order` consistently; it does not introduce a conflicting sort column.

Existing regression tests cover the schema, prepared writes, active/all reads and normalization.

## SC-P1-011 — Default payment methods — Validate

**Result: PASS**

The initial reference set is seeded by migration and remains server-authoritative:

1. Cash — `cash`
2. Bank Transfer — `bank_transfer`
3. Wallet — `wallet`

The seed uses `ON DUPLICATE KEY UPDATE`, making migration replay/idempotency safe. Runtime bootstrap does not reseed once the migration version is current.

## SC-P1-012 — Reference-data administration — Validate

**Result: PASS**

- WordPress exposes a SafeContracts payment-method settings page.
- Administration requires `safecontracts_manage_reference_data` server-side.
- SafeContracts System Administrator and native WordPress Administrator receive the capability by default.
- Manager and Accountant do not receive this administration capability by default.
- Add/update, order and active/inactive state use the shared repository rather than duplicating persistence logic in the page.
- WordPress form writes are nonce-protected and REST writes remain independently capability-protected.

## SC-P1-013 — Reference-data APIs — Validate

**Result: PASS**

Versioned namespace remains `safecontracts/v1`.

- `GET /payment-methods` returns active payment methods for authenticated SafeContracts users.
- Active methods are ordered by configured display order.
- `GET /admin/payment-methods` includes inactive values and requires reference-data administration capability.
- `POST /admin/payment-methods` validates/sanitizes input and performs an idempotent server-side write.
- Mobile remains a consumer of WordPress reference data; payment methods are not hard-coded as business truth in Flutter.

These behaviors are covered by the existing PHP foundation regression suite introduced with the reference-data implementation.

---

## SC-P2-001 — Contract data model — Implement

A dedicated `safecontracts_contracts` table is introduced through migration `1.3.0`.

### Fields

| Field | Purpose |
|---|---|
| `id` | Stable internal contract ID |
| `contract_number` | Required unique business contract number/reference |
| `customer_id` | Required relation to SafeContracts customer master data |
| `accountant_user_id` | Responsible Accountant WordPress user relation; nullable to support controlled draft/unassigned states before assignment workflow |
| `status` | Contract lifecycle state, default `draft` |
| `start_date` / `end_date` | Contract date range |
| `base_value` | Fixed-point base contractual value using `DECIMAL(20,4)` |
| `notes` | Operational/general contract notes |
| `is_archived` | Explicit non-destructive archive flag |
| `created_by` / `updated_by` | Actor traceability hooks for later CRUD/audit workflows |
| `created_at` / `updated_at` | Record timestamps |

### Indexes

- Unique contract-number index.
- Customer + status + archive index for customer portfolio filtering.
- Accountant + status + archive index for server-side Accountant scope filtering.
- Start/end date index for date-range reporting.

### Currency rule

V1 uses one system currency. The contract row deliberately does **not** introduce a per-contract `currency_code`; doing so would create competing currency state. Monetary storage uses fixed-point precision, while the system-level currency setting/display behavior is implemented in its dedicated later task.

### Scope boundaries

This task creates the authoritative contract persistence model only. It does **not** prematurely implement later P2 tasks such as:

- contract create/update/archive workflows;
- assignment mutation rules;
- financial line/addition/discount tables;
- net-value reconciliation;
- attachments;
- history/audit events;
- REST CRUD endpoints.

Those remain separate planned issues and will build on this schema.

## Validation

`tests/php/contracts_schema.php` validates the contract schema, indexes, financial precision, one-currency boundary, migration idempotency and non-destructive deactivation. The existing foundation suite is also updated to recognize migration `1.3.0` and the fourth `dbDelta` schema operation.

The PR carrying this work must pass repository standards, PHP/backend tests, Dart formatting, Flutter analysis and Flutter tests before these five issues are closed.
