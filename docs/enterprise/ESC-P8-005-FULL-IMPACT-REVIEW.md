# ESC-P8-005 Full Impact Review — Contract Deliverables Foundation

Issue: #486  
Phase: ESC-P8 — Obligations, milestones, deliverables, renewals and notices

## Decision

P8-005 fills the Deliverables capability explicitly named in the ESC Master Plan. A Contract Deliverable is a required work product/result, independent from an Obligation or Milestone unless a later reviewed relationship is introduced. The foundation tracks requirement metadata plus delivery/cancellation evidence only.

Lifecycle is `pending -> delivered|cancelled`. Formal acceptance/rejection is deliberately deferred; adding it here would prematurely couple Deliverables to Workflow/Approval semantics. Files, versions and attachments are also deferred to ESC-P10 Documents.

## Impact review

| Dimension | Review / decision |
|---|---|
| Business requirement / domain model | Adds explicit Contract Deliverable identity, metadata, optional contractual due date and terminal delivery/cancellation evidence. |
| Tenant model / isolation | Every Deliverable carries `tenant_id`. Repository derives tenant only from locked `TenantContextStore`; object IDs never authorize access. Parent Contract lock/join is tenant-matched. |
| Database / migrations / indexes | Additive `Migration0047` advances schema from `1.45.0` to `1.46.0` and creates only `safecontracts_contract_deliverables`. Unique UUID, tenant+Contract+code uniqueness and tenant/status/due-date indexes. No existing table altered/dropped. |
| Backend business logic | New `ContractDeliverablePolicy`, Repository and Service. WordPress/plugin remains authoritative. |
| Authorization / scopes / roles | Reads require `ACCESS`; writes require `EDIT_CONTRACTS`; tenant role remains a narrowing ceiling and existing Contract `VIEW_ALL` / own `VIEW_ASSIGNED` scope applies. No new capability. |
| REST API / version compatibility | N/A in P8-005. No route/controller/serializer exposed. |
| WordPress / admin UI | N/A. No admin surface or feature claim. |
| Flutter / mobile / offline state | N/A. No endpoint/model/local state; existing Flutter gates remain mandatory. |
| Android identity / environments | N/A to Deliverables. Existing ESC Android isolation/coexistence source gates unchanged; real-device evidence remains #421. |
| Landing / marketing / public catalog | N/A. Deliverables are not marked public by this task. |
| Design system / theme | N/A. No UI. |
| Feature registry / flags / plans | Deferred to ESC-P13. |
| Search / filter / sort / bulk | Only bounded per-Contract listing, ordered non-null due date then ID. Global search/bulk deferred to ESC-P12. |
| Reports / import / export | N/A in P8-005; deferred to ESC-P12. |
| Notifications / escalation | No reminder, escalation or delivery integration. ESC-P11 remains the delivery abstraction phase. |
| Audit / compliance | Server actor/UTC timestamps retained for create/update and delivered/cancelled transitions. Existing audit tables are not mutated; hooks fire after successful service commands. |
| Documents / storage | Explicitly absent. No document/file/blob/attachment IDs or storage fields. Document metadata/versioning remains ESC-P10. |
| Localization / RTL / LTR / timezone / currency | `due_date` is contractual DATE and timezone-free. Future reminders must interpret tenant timezone explicitly. No currency impact. |
| Security / privacy / rate limits | No external attack surface. Inputs bounded/normalized, tenant identity server-derived, foreign IDs fail closed, archived Contracts cannot mutate Deliverables. REST rate limits unaffected. |
| Performance / concurrency / idempotency | Tenant/Contract/status/due indexes support bounded reads. Create/update/lifecycle writes use transactions and row locks. Status CAS prevents stale terminal writes. Exact same terminal retry is idempotent; different terminal retry fails closed. Metadata exact no-op may return affected_rows=0 under authoritative lock. |
| Automated tests | Dedicated adversarial P8-005 regression checks migration history, schema, policy bounds, tenant/data scope, transactions/locks/CAS, delivery evidence, no delete, no document/Obligation/Milestone/Workflow coupling and no exposed surface. Wired into global backend gate and focused CI. |
| Documentation / demo / onboarding | This review and Issue #486 define the foundation. Demo/onboarding deferred until an exposed surface exists. |
| CI / build / release / rollback | Dedicated `esc-p8-005.yml` plus global ESC Foundation Gate validate exact source. Code rollback leaves an additive unused table; production schema version must not be manually downgraded. |
| Backward compatibility | Existing Contract, Obligation, Milestone, Renewal Terms, Notice Period Rules, Workflow, Approval, financial, notification, admin, mobile and Safe Contract `main` behavior unchanged. |

## Lifecycle invariants

- Initial state is server-selected `pending`.
- `deliverable_code` is immutable after creation and unique per tenant+Contract.
- Metadata may change only while `pending` and parent Contract is unarchived.
- `deliver()` performs `pending -> delivered` and records `delivered_at` / `delivered_by` server-side.
- `cancel()` performs `pending -> cancelled` and records `cancelled_at` / `cancelled_by` server-side.
- Terminal metadata is immutable.
- Exact repeated terminal command is idempotent; different terminal command after terminal state fails closed.
- No physical delete exists.

## Deliberately deferred follow-on work

- formal Deliverable acceptance/rejection and approval semantics;
- document/file/version attachment relationships;
- explicit Obligation↔Deliverable or Milestone↔Deliverable relationships;
- responsible user / OrgUnit assignment;
- reminders/escalation/delivery;
- REST/admin/mobile surfaces;
- reporting/bulk/import/export;
- feature registry / plan entitlement and landing visibility.

## Acceptance evidence required before closure

1. P8-005 focused CI is green on exact PR head.
2. P8-001..P8-004 focused regressions remain green on exact PR head.
3. ESC Foundation backend/tenancy/Android-isolation gate is green on exact PR head.
4. Flutter format/analyze/tests remain green.
5. PR is merged into `enterprise-safecontracts` without overwriting concurrent team work.
6. Post-merge push gates are green before Issue #486 is closed.
