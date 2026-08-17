# P11 Counterparty and Finance Foundation

Task: `SC-P11-001` / issue #548.

## Source of truth

SafeContracts continues to use the WordPress plugin as the authoritative business and financial backend. Flutter and web/admin clients consume server-authorized APIs and must not calculate authoritative balances or infer financial direction locally.

The implementation applies to the SafeContracts code on `main`. It does not derive from or depend on `enterprise-safecontracts`.

## Counterparty model

Contracts now have an explicit counterparty relationship:

- `customer` -> `receivable` (Accounts Receivable / cash expected in)
- `supplier` -> `payable` (Accounts Payable / cash expected out)

The legacy `customer_id` column is retained as a compatibility column and made nullable. New code uses `counterparty_type` + `counterparty_id` as the domain relationship.

Migration of legacy rows is deterministic: the old schema required a valid `customer_id`, so those rows are safely backfilled to `customer` / `receivable`. No supplier classification is fabricated.

## Supplier domain

`safecontracts_suppliers` stores supplier master data including legal/trading identity, contacts, address/country, registration/tax identifiers, default currency, payment terms, status and audit metadata.

Supplier lifecycle is archive/deactivate oriented. No destructive supplier delete path is introduced, so contract and finance history remains addressable.

Duplicate detection checks supplied internal code, registration number and tax number before persistence.

## Contract currency

New explicit-counterparty contracts persist a three-letter currency code. The request may supply it directly; otherwise SafeContracts uses the configured General Settings currency. If neither exists, explicit-counterparty creation fails rather than guessing a currency.

Legacy customer clients remain backward compatible and may continue to operate while historical currency is explicitly unconfigured.

## Financial obligations

The existing scheduled-payment table remains the obligation/schedule store, but P11 adds:

- `financial_direction`
- `currency_code`
- direction/date indexes for AP/AR work queues and reporting

The existing `paid_amount` column is retained as a compatibility storage field for total settled amount. Domain/API code presents the meaning according to direction rather than treating every settlement as an incoming payment.

Financial statuses now distinguish:

- Payable: `partially_paid`, `paid`
- Receivable: `partially_received`, `received`

Temporal states (`upcoming`, `due_soon`, `due`, `overdue`) remain shared because they describe schedule timing rather than cash direction.

## Canonical transaction ledger

`safecontracts_financial_transactions` is an append-oriented transaction ledger for payments and receipts. It records:

- obligation/payment ID
- contract ID
- financial direction
- transaction kind (`payment` or `receipt`)
- amount/currency/date
- optional payment method/reference/details/proof
- idempotency key
- reversal linkage field for future explicit correction workflows
- actor and creation time

No new workflow deletes settled financial history.

## Concurrency and idempotency

`SettlementService` uses a database transaction and `SELECT ... FOR UPDATE` on the obligation before applying a settlement. It validates that stored settled + remaining equals the original amount and rejects over-settlement before mutation.

Each new settlement request requires a stable idempotency key. The ledger has a unique constraint on that key. Replaying the same key for the same operation returns the existing transaction; reusing it for a different financial operation is rejected.

This prevents double-click/retry duplication and protects the final remaining balance from concurrent settlement races.

## RBAC

P11 adds server-side capabilities for:

- supplier view/create/edit/archive
- payable view
- receivable view
- record payment
- record receipt
- modify finance
- approve payment
- finance settings

Migration grants transition defaults to existing built-in roles, while the normal SafeContracts rule remains: role capabilities are configurable and runtime code does not silently re-add removed permissions.

## APIs

New REST capabilities under `/wp-json/safecontracts/v1`:

- `GET/POST /suppliers`
- `GET/PATCH /suppliers/{id}`
- `POST /suppliers/{id}/archive`
- `POST /contracts/create` for explicit-counterparty creation; the established `/contracts` read resource remains read-only
- `PATCH /contracts/{id}/counterparty`
- `PATCH /contracts/{id}/currency`
- `POST /finance/settlements`
- `GET /finance/obligations/{id}/transactions`

All routes enforce SafeContracts access plus the operation-specific capability in the backend.

## Tenant/data isolation decision

The current SafeContracts repository is single-tenant per WordPress installation and does not contain a shared-table `tenant_id` architecture. P11 therefore does not introduce a client-supplied Tenant ID. Site/database isolation remains the tenancy boundary, while existing `VIEW_ALL` / `VIEW_ASSIGNED` accountant scope remains enforced for contract and finance operations.

Introducing multiple tenants inside one WordPress database would require a separate, repository-wide tenancy migration and IDOR review rather than a cosmetic field addition.

## Compatibility

Existing customer-only mobile/web mutations using `customer_id` remain supported. The modern counterparty endpoint is additive and uses the same `ContractService`; no duplicate contract domain is created.

Existing collection history is retained in its original table. P11 introduces the canonical bidirectional transaction ledger without deleting or rewriting legacy finance history. Further UI/reporting slices should consume explicit direction/currency and progressively converge legacy collection presentation onto the canonical finance vocabulary.

## Follow-on slices

The foundation intentionally precedes UI/dashboard work. Next bounded tasks should build on these fields/services for:

1. AP/AR read models, aging, currency-grouped financial summaries and dashboard drill-down.
2. Premium hierarchical web/admin navigation and Supplier/Finance workspaces.
3. Notification/approval/report integration for payable/receivable direction.
4. Flutter Supplier/AP/AR screens and permission-aware quick-create integration.
5. Expanded security/UAT coverage including direction-specific IDOR and real concurrent settlement verification.
