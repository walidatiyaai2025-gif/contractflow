# ESC-P8-003 Full Impact Review — Contract Renewal Terms Foundation

Issue: #482  
Phase: ESC-P8 — Obligations, milestones, renewals and notices

## Decision

P8-003 introduces tenant-owned Contract Renewal Terms as **configuration only**. The existing Contract `end_date` remains the authoritative current-term boundary. Renewal Terms never create a second expiry date, never extend a Contract, and never generate notices or notifications.

A Contract has at most one current Renewal Terms row. Mode is one of `none`, `manual`, or `automatic`. Enabled modes carry a bounded interval (`interval_value` + `day|month|year`) and may carry a contractual `max_occurrences` cap. Mode `none` canonicalizes interval and occurrence-cap fields to NULL. Updates are revisioned and stale writes fail closed.

## Impact review

| Dimension | Review / decision |
|---|---|
| Business requirement / domain model | Adds renewal policy configuration attached one-to-one to a Contract. No renewal occurrence/history/execution semantics are introduced. |
| Contract term / expiry semantics | Existing `safecontracts_contracts.end_date` remains authoritative for the current term. P8-003 stores no expiry/end date and performs no Contract date write. |
| Tenant model / isolation | Every Renewal Terms row carries `tenant_id`. Repository derives tenant only from locked `TenantContextStore`; object IDs never authorize access. Parent Contract lock/join is tenant-matched. |
| Database / migrations / indexes | Additive `Migration0045` advances schema from `1.43.0` to `1.44.0` and creates only `safecontracts_contract_renewal_terms`. Unique UUID and unique `(tenant_id, contract_id)` enforce identity/one-row-per-Contract. No existing table is altered or dropped. |
| Backend business logic | New `ContractRenewalTermsPolicy`, `ContractRenewalTermsRepository`, and `ContractRenewalTermsService`. WordPress/plugin remains authoritative. |
| Authorization / scopes / roles | Reads require `ACCESS`; create/update require `EDIT_CONTRACTS`; both remain narrowed by `TenantAuthorization` and Contract data scope (`VIEW_ALL` or own `VIEW_ASSIGNED`). No new capability is created. |
| REST API / version compatibility | N/A in P8-003. No route/controller/serializer is exposed. |
| WordPress / admin UI | N/A. No admin surface or feature claim. |
| Flutter / mobile / offline state | N/A. No endpoint/model/local state is added. Existing Flutter gates remain required. |
| Android identity / environments | N/A to Renewal Terms. Existing ESC Android isolation/coexistence source gates remain unchanged; physical-device evidence remains tracked separately. |
| Landing / marketing / public catalog | N/A. Renewal Terms are not made public/marketable by this task. |
| Design system / theme | N/A. No UI is introduced. |
| Feature registry / flags / plans | Deferred to ESC-P13; no entitlement shortcut is introduced. |
| Search / filter / sort / bulk | Only direct ID and per-Contract lookup exist. Global search/filter/bulk operations are deferred to ESC-P12. |
| Reports / import / export | N/A in P8-003; deferred to ESC-P12. |
| Notices / notifications / escalation | Explicitly absent. `automatic` is configuration only and triggers no scheduling, notice, FCM, email, escalation or background action. Notice-period modeling is a separate P8 task. |
| Audit / compliance | Actor and server UTC timestamps are retained. `revision` provides deterministic stale-write evidence. Existing audit tables are not mutated. Hooks fire only after successful service writes. |
| Documents / storage | N/A. No attachments are introduced. |
| Localization / RTL / LTR / timezone / currency | Renewal interval uses value+unit rather than a duplicated absolute date. Future execution must calculate from authoritative Contract boundaries under explicit date/calendar rules. No currency impact. |
| Security / privacy / rate limits | No external attack surface. Inputs are bounded/allowlisted, tenant identity is server-derived, archived/foreign IDs fail closed, and no caller can supply tenant identity. Existing REST rate limits are unaffected. |
| Performance / concurrency / idempotency | Unique tenant+Contract key makes direct lookup bounded. Creation locks Contract and existing Terms. Update locks Terms+Contract, validates caller-observed `expectedRevision`, and performs tenant+ID+revision SQL CAS with atomic `revision = revision + 1`. Exactly one affected row is required. |
| Automated tests | Dedicated adversarial P8-003 regression checks migration history, schema uniqueness, canonical policy, tenant isolation, Contract scope, locks/CAS/revision increment, no delete, no Contract date writes and no exposed execution/API surface. Wired into global backend gate and focused CI. |
| Documentation / demo / onboarding | This review and Issue #482 define the foundation. Demo/onboarding is deferred until an exposed product surface exists. |
| CI / build / release / rollback | Dedicated `esc-p8-003.yml` plus global ESC Foundation Gate validate exact source. Code rollback leaves an additive unused table; production schema version must not be manually downgraded. |
| Backward compatibility | Existing Contract, Obligation, Milestone, Workflow, Approval, financial, notification, admin, mobile and Safe Contract `main` behavior remains unchanged. |

## Configuration invariants

- One Renewal Terms row maximum per tenant+Contract.
- `none` means renewal configuration is disabled and interval/max-occurrence values are canonical NULL.
- `manual` and `automatic` require an interval value from 1 through 10000 and an allowlisted unit (`day`, `month`, `year`).
- `max_occurrences` is optional for enabled modes; when present it is 1 through 10000.
- No physical delete exists; disabling uses mode `none`.
- Every update requires positive `expectedRevision` matching the authoritative row and increments revision exactly once.
- Archived Contracts cannot create or mutate Renewal Terms.
- P8-003 never writes Contract `start_date` or `end_date`.

## Deliberately deferred follow-on work

- renewal occurrence / decision history;
- automatic/manual renewal execution and Contract-date extension;
- successor/child Contract generation;
- notice-period rules and notice instances;
- expiry processing, reminders and escalation;
- responsible user / OrgUnit assignment;
- REST/admin/mobile surfaces;
- reporting/bulk/import/export;
- feature registry / entitlement and public landing visibility.

## Acceptance evidence required before closure

1. P8-003 focused CI is green on the exact PR head.
2. P8-001 and P8-002 focused regressions remain green on the exact PR head.
3. ESC Foundation backend/tenancy/Android-isolation gate is green on the exact PR head.
4. Flutter format/analyze/tests remain green.
5. PR is merged into `enterprise-safecontracts` without overwriting concurrent team work.
6. Post-merge push gates are green before Issue #482 is closed.
