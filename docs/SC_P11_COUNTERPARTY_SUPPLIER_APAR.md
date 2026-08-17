# SafeContracts P11 — Counterparty, Supplier and AP/AR foundation

## Status and scope

This document defines the SafeContracts P11 domain baseline introduced by `SC-P11-001`.
It is a backend/domain/API foundation. WordPress + the SafeContracts plugin remains the
authoritative source of business rules and authorization; Flutter and other clients are
API consumers.

The change intentionally preserves existing customer-contract behavior while adding a
real Supplier domain and explicit accounts-receivable/accounts-payable semantics.

## Counterparty model

SafeContracts does **not** replace or duplicate the existing Customer master table.
Customer remains the authoritative customer master. Supplier is a separate master data
entity because supplier lifecycle, permissions, archival, and future procurement fields
are independent from customer lifecycle.

Contracts now carry the authoritative polymorphic reference:

- `counterparty_type`: `customer` or `supplier`
- `counterparty_id`: ID in the corresponding master table

`customer_id` remains temporarily as a nullable compatibility bridge:

- Customer contract: `customer_id = counterparty_id`
- Supplier contract: `customer_id = NULL`

New domain/API logic must use `counterparty_type + counterparty_id` as source of truth.
Legacy customer-only code can continue reading `customer_id` until later UI/client
migration removes that dependency.

## Legacy migration safety

Migration `1.16.0` classifies an existing contract as Customer **only** when the legacy
row already has a positive `customer_id`. That existing foreign-key value is objective
proof of the historical relationship.

The migration does not infer or fabricate Supplier rows, Supplier contracts, or currency.
For legacy customer contracts it backfills:

- `counterparty_type = customer`
- `counterparty_id = customer_id`
- `financial_direction = receivable`

Existing scheduled obligations inherit the contract direction/currency. Existing
settlement ledger rows inherit the obligation direction/currency.

If no valid configured three-letter currency exists during migration, the explicit
currency value is `XXX`, meaning **unknown/unspecified legacy currency**. `XXX` must not
be interpreted as KWD, USD, or any other monetary unit. Operators may remediate legacy
currency separately with an audited data-quality process; migration does not guess.

## Supplier lifecycle

Suppliers are stored in `safecontracts_suppliers` with master-data identity/contact fields,
active state, archive state, actor IDs, and UTC timestamps.

Supplier removal is soft archival:

- `is_archived = 1`
- `is_active = 0`
- `archived_by` and `archived_at` are recorded

Archived/inactive suppliers cannot be selected for a new contract or counterparty
assignment. Existing contracts, obligations, and settlement history remain readable;
archiving master data never erases financial history.

## AP/AR direction

Financial direction is persisted, not derived only in the UI:

- Customer contract -> `receivable`
- Supplier contract -> `payable`

The initial P11 foundation deliberately uses this deterministic mapping. A contract may
not arbitrarily override the mapping because that would make customer/supplier and AP/AR
semantics contradictory.

Counterparty type cannot be changed after the first scheduled financial obligation exists.
Changing Customer to Supplier after obligations were created would invert AR to AP on an
existing ledger; the server rejects that operation with a conflict instead.

## Currency propagation

Every new contract has a three-letter `currency_code`. If the create request omits it,
SafeContracts uses the validated General Settings currency; if no configured value is
available, the explicit fallback is `XXX`.

A scheduled obligation copies `financial_direction` and `currency_code` from the
validated contract context. A settlement transaction copies both from the locked
obligation inside the settlement transaction. Clients cannot submit a different direction
or currency for a settlement.

This propagation makes historical rows self-describing and prevents later master-data
changes from silently changing the meaning of ledger history.

## Settlement ledger

The existing `safecontracts_payment_collections` table remains the append-only settlement
ledger for compatibility. Its legacy name does not mean that every row is an inbound
collection after P11:

- `receivable` row = money settled against customer AR
- `payable` row = money settled against supplier AP

The ledger remains transaction-based. P11 does not add an `is_paid` boolean. Scheduled
obligations retain exact `original_amount`, cumulative `paid_amount`,
`remaining_amount`, and controlled lifecycle status; settlement rows remain auditable.

## Currency-safe metrics

`GET /wp-json/safecontracts/v1/finance/summary` is the authoritative P11 AP/AR aggregate
read. It groups by both:

1. `financial_direction`
2. `currency_code`

The server never sums payable with receivable or KWD with USD into one monetary value.
Each returned row is an independent financial bucket with obligation count, scheduled
total, settled total, and outstanding total.

The existing legacy admin dashboard remains customer-oriented for backward compatibility.
It must not be treated as the authoritative cross-counterparty AP/AR aggregate until a
later dashboard/UI migration explicitly moves it to the P11 read model.

## REST/API surface

P11 adds or extends the following server-side API behavior:

- `GET /suppliers` — bounded active Supplier list
- `POST /suppliers` — create Supplier
- `GET /suppliers/{id}` — Supplier detail
- `PATCH /suppliers/{id}` — edit Supplier
- `DELETE /suppliers/{id}` — archive Supplier; no hard delete
- `GET /contracts` — counterparty-aware contract read, retaining legacy customer fields
- `POST /contracts` — create Customer or Supplier contract through explicit counterparty fields
- `PATCH /contracts/{id}/counterparty` — change counterparty only before obligations exist
- `GET /payments` — counterparty-aware AP/AR obligations with direction/currency
- `GET /collections` — counterparty-aware settlement ledger with direction/currency
- `GET /finance/summary` — currency-safe AP/AR grouped totals

Legacy Customer and mobile create paths remain compatible. Customer-only contract creation
is represented as Customer/Receivable and scheduled obligations inherit that explicit
context.

## Authorization

P11 introduces server capabilities:

- `safecontracts_view_suppliers`
- `safecontracts_create_suppliers`
- `safecontracts_edit_suppliers`
- `safecontracts_manage_suppliers`
- `safecontracts_view_finance`
- `safecontracts_manage_finance`

The migration grants the new baseline capabilities once to built-in roles. Runtime code
does not continuously re-grant them, so administrators can customize role capabilities
after upgrade.

Supplier contract creation/assignment requires both contract authority and Supplier view/
management authority. Financial settlement accepts the existing collection-management
capability for compatibility or the new finance-management capability. All contract and
financial reads continue to enforce SafeContracts `VIEW_ALL` / `VIEW_ASSIGNED` data scope.

## Invariants

The P11 foundation must preserve these invariants:

1. A Supplier contract never requires or fabricates a Customer row.
2. Legacy `customer_id` only proves Customer classification, never Supplier classification.
3. Customer means receivable; Supplier means payable in this foundation.
4. Counterparty cannot change after financial obligations exist.
5. Obligation and settlement direction/currency are inherited from validated upstream data.
6. Settlement history is append-only/auditable and is not replaced by a paid boolean.
7. Archived Supplier master data does not erase historical contracts or ledger records.
8. Monetary aggregates are grouped by both direction and currency.
9. Missing legacy currency is represented explicitly as `XXX`; migration never guesses.
10. All writes and scoped reads remain server-authorized regardless of client/UI behavior.

## Validation

`tests/php/p11_counterparty_supplier_apar.php` covers migration/backfill safety, baseline
RBAC, Supplier create/archive behavior, Supplier Contract/AP and Customer Contract/AR,
partial settlements for both directions, counterparty mutation locking after obligations,
REST input validation, historical reads after Supplier archival, route registration, and
currency-safe grouped finance metrics.

The existing complete PHP regression suite remains mandatory in addition to P11 tests.
