# P1 Reference Data & Customer Validation — SC-P1-005..009

Scope: SafeContracts P1 Master Data tasks SC-P1-005 through SC-P1-009.

## SC-P1-005 — Reference-data administration

- WordPress settings page: `SafeContracts — Payment Methods`.
- Administrators can add/update a method by stable code, rename it, set display order, and activate/deactivate it.
- Mutations use the shared `PaymentMethodRepository`; UI code does not duplicate persistence rules.
- Writes are nonce-protected in WordPress and use prepared SQL.

## SC-P1-006 — Reference-data APIs

Versioned namespace remains `safecontracts/v1`.

- `GET /payment-methods` returns active methods only, ordered by `display_order`, for authenticated SafeContracts users within normal access rules.
- `GET /admin/payment-methods` returns all methods, including inactive methods, only to reference-data administrators.
- `POST /admin/payment-methods` creates or updates by stable method code and applies validation/sanitization server-side.

Mobile remains a server-authoritative consumer and must not hard-code payment methods.

## SC-P1-007 — Master-data authorization

- New capability: `safecontracts_manage_reference_data`.
- SafeContracts System Administrator and native WordPress Administrator receive the capability.
- Manager and Accountant do not receive it by default.
- Migration `1.2.0` grants the new capability once on plugin upgrade to existing administrator roles, avoiding a reactivation requirement while preserving later capability customization.
- WordPress administration and admin REST read/write operations enforce the capability server-side.

## SC-P1-008 — Customer entity model validation

Automated PHP regression checks validate that the customer table contains:

- required customer name;
- contact name, email, phone and notes fields;
- explicit active/archive state;
- creator audit link and timestamps;
- active/name lookup index.

## SC-P1-009 — Customer optional internal code validation

Automated PHP regression checks validate that `internal_code`:

- is nullable and therefore not mandatory;
- has a unique index when supplied;
- remains part of the dedicated customer master table rather than WordPress post/meta storage.

## Quality gates

The PR carrying this report must pass repository standards, PHP/backend regression tests, Dart formatting, Flutter analysis and Flutter tests before the five issues are closed.
