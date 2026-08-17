# ESC-P8-002 Full Impact Review — Contract Milestone Foundation

Issue: #480  
Phase: ESC-P8 — Obligations, milestones, renewals and notices

## Decision

P8-002 introduces a dedicated tenant-owned Contract Milestone component. A Milestone is a dated or undated contract checkpoint with its own lifecycle (`planned`, `achieved`, `cancelled`). It is not implicitly an Obligation. No mandatory Obligation relationship is introduced; a future relationship task may add an explicit association only when business semantics require it.

## Impact review

| Dimension | Review / decision |
|---|---|
| Business requirement / domain model | Adds Contract Milestone identity, metadata, optional contractual target date and terminal achievement/cancellation evidence. Immutable `milestone_code` is tenant+contract-local. |
| Tenant model / isolation | Every record carries `tenant_id`. Repository derives tenant only from locked `TenantContextStore`; object IDs never authorize access. Parent Contract joins/locks require matching tenant. |
| Database / migrations / indexes | Additive `Migration0044` advances schema from `1.42.0` to `1.43.0` and creates only `safecontracts_contract_milestones`. Unique UUID, tenant+contract+code uniqueness and bounded operational indexes are included. No existing table is altered or dropped. |
| Backend business logic | New `ContractMilestonePolicy`, `ContractMilestoneRepository` and `ContractMilestoneService`. WordPress/plugin remains authoritative. |
| Authorization / scopes / roles | Reads require `ACCESS`; writes require `EDIT_CONTRACTS`; both pass `TenantAuthorization` and existing Contract data scope (`VIEW_ALL` or own `VIEW_ASSIGNED`). No new capability is created. |
| REST API / version compatibility | N/A in P8-002. No route/controller/serializer is exposed. Existing REST contracts remain unchanged. |
| WordPress / admin UI | N/A in this foundation. No admin surface or feature claim. |
| Flutter / mobile / offline state | N/A. No mobile model, endpoint or local state is added. |
| Android identity / environments | N/A to milestone runtime. Existing ESC identity/coexistence gates remain mandatory and are not changed. |
| Landing / marketing / public catalog | N/A. Milestones are not made public/marketable by this task. |
| Design system / theme | N/A. No UI is introduced. |
| Feature registry / flags / plans | Deferred to ESC-P13; no entitlement shortcut is introduced. |
| Search / filter / sort / bulk | Only bounded per-Contract service listing is added, deterministically sorted by non-null `target_date`, then ID. Global search/bulk operations are deferred to ESC-P12. |
| Reports / import / export | N/A in P8-002; deferred to ESC-P12. |
| Notifications / escalation | No reminder, notification or escalation execution. These remain later P8/P11 work. |
| Audit / compliance | Actor and server UTC timestamps are retained on create/update and achieved/cancelled transitions. Existing audit framework is not mutated. Event hooks are emitted only after successful service commands. |
| Documents / storage | N/A. No attachment/document relationship is added. |
| Localization / RTL / LTR / timezone / currency | `target_date` is a contractual `DATE`, intentionally timezone-free. Future reminder execution must interpret it using tenant timezone policy. No currency impact. |
| Security / privacy / rate limits | No new external attack surface. Inputs are bounded/normalized, tenant ownership is server-derived, and foreign IDs fail closed. Existing REST rate limits are unaffected because no route is exposed. |
| Performance / concurrency / idempotency | Tenant/contract/status/date indexes support bounded queries. Mutations use transactions and row locks. Terminal updates use status CAS. Exact same terminal retry is idempotent; a different terminal retry fails closed. Metadata update permits MySQL affected-rows `0` for exact no-op while rejecting failure/cardinality anomalies. |
| Automated tests | Dedicated adversarial P8-002 regression checks schema/versioning, policy bounds, tenant scoping, Contract scope, locks/CAS, server evidence, no-delete, Obligation separation and no exposed surface. It is wired into `scripts/test-php.sh` and a focused workflow. |
| Documentation / demo / onboarding | This Full Impact Review plus Issue #480 document the foundation. Demo/onboarding/UI material is deferred because there is no public surface. |
| CI / build / release / rollback | Dedicated `esc-p8-002.yml` plus global ESC Foundation Gate validate exact source. Rollback is non-destructive at code level: the additive table may remain unused if code is rolled back; schema version must not be manually downgraded in production. |
| Backward compatibility | Existing Contract, Obligation, Workflow, Approval, financial, notification, admin, mobile and Safe Contract `main` behavior is unchanged. |

## Lifecycle and concurrency contract

- Initial state is server-selected `planned`.
- Metadata is mutable only while `planned` and while the parent Contract remains unarchived.
- `achieve()` performs `planned -> achieved` and records `achieved_at` / `achieved_by` server-side.
- `cancel()` performs `planned -> cancelled` and records `cancelled_at` / `cancelled_by` server-side.
- Terminal metadata is immutable.
- Exact repeated terminal command is idempotent.
- Attempting the other terminal state after a terminal transition fails closed.
- No physical delete is available.

## Deliberately deferred follow-on work

- explicit Obligation↔Milestone associations, if later required;
- milestone recurrence/generation;
- responsible user / OrgUnit assignment;
- reminders and escalation;
- renewal computation and notice generation;
- REST/admin/mobile surfaces;
- reporting/bulk/import/export;
- feature registry / plan entitlement and public landing visibility.

## Acceptance evidence required before closure

1. P8-002 focused CI is green on the exact PR head.
2. ESC Foundation backend/tenancy gate is green on the exact PR head.
3. Flutter and Android identity/coexistence source gates remain green.
4. PR is merged into `enterprise-safecontracts` without overwriting concurrent team work.
5. Post-merge push gates are green before Issue #480 is closed.
