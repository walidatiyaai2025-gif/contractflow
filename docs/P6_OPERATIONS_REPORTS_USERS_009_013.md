# P6 Operations, Reports & Users — SC-P6-009..013

This slice extends the SafeContracts WordPress administration workspace without moving business logic into presentation code.

## SC-P6-009 — Collections screen

- Collection rows are read through the scoped admin read model.
- Collection creation delegates to `CollectionService::record()`.
- Active payment-method options come from `PaymentMethodRepository`.
- The backend remains authoritative for assignment scope, payment-method validity, over-collection protection, proof validation, transactionality and settlement reconciliation.
- Proof remains optional.

## SC-P6-010 — Follow-up screen

- Queue and history are provided by `FollowUpService`.
- Contact notes, promise-to-pay, issue, deferred and escalation actions delegate to domain methods.
- Contractual `due_date` remains financial due authority; promised/deferred dates are operational follow-up state only.
- Follow-up history is displayed as append-only history.

## SC-P6-011 — Notifications screen

- Rule metadata comes from `NotificationRuleService`.
- Recent delivery metadata comes from `DeliveryLogRepository`.
- Firebase credentials, service-account material and raw device-token values are intentionally not exposed.
- This task is an operational screen; notification/Firebase settings remain dedicated later P6 tasks.

## SC-P6-012 — Reports screen

- Reports reuse `DashboardFilters` and `AdminReadRepository` scope semantics.
- Contract, scheduled receivable, remaining, overdue, collection-ledger and follow-up totals are computed server-side.
- Contractual due dates remain authoritative for overdue exposure.
- Excel generation is intentionally not implemented here; SC-P6-019 owns the exporter.

## SC-P6-013 — Users/Roles screen

- Role grants are read from WordPress `get_role()` and SafeContracts capability definitions.
- Membership is read from WordPress `get_users()`.
- The screen is read-only in this task and does not expose passwords or authentication credentials.
- Role/capability mutation remains subject to later settings/administration work.

## UI boundary

All five screens inherit the SafeContracts navy/blue/green admin identity and layer `safecontracts-admin-ops.css` after the core stylesheet for responsive tables, RTL behavior and role/capability layouts.
