# ESC-P8-004 Full Impact Review — Contract Notice Period Rules Foundation

Issue: #484  
Phase: ESC-P8 — Obligations, milestones, renewals and notices

## Decision

P8-004 introduces tenant-owned Contract Notice Period Rules as **contractual lead-time configuration only**. A rule states the minimum notice duration for a scenario and direction. It does not store or calculate a notice date, effective date, expiry date, delivery schedule, or renewal decision.

This domain is intentionally separate from the legacy `safecontracts_notification_rules` and `safecontracts_notification_schedule`, which are delivery/scheduling infrastructure and include payment-oriented semantics. Future notice instances may integrate with delivery only through an explicit reviewed task.

## Impact review

| Dimension | Review / decision |
|---|---|
| Business requirement / domain model | Adds multiple Contract-local Notice Period Rules with immutable machine code, purpose, direction, minimum duration, active state, notes and revision. |
| Notice / date semantics | Rules store duration only (`period_value` + `day|month|year`). No `notice_date`, `scheduled_for`, `effective_date`, `expiry_date`, or duplicate Contract end date is persisted. |
| Tenant model / isolation | Every row carries `tenant_id`. Repository derives tenant only from locked `TenantContextStore`; object IDs never authorize access. Parent Contract joins/locks require matching tenant. |
| Database / migrations / indexes | Additive `Migration0046` advances schema from `1.44.0` to `1.45.0` and creates only `safecontracts_contract_notice_period_rules`. Unique UUID and tenant+Contract+code uniqueness; operational tenant/Contract/active/purpose indexes. No existing table is altered/dropped. |
| Backend business logic | New `ContractNoticePeriodRulePolicy`, Repository and Service. WordPress/plugin remains authoritative. |
| Authorization / scopes / roles | Reads require `ACCESS`; create/update require `EDIT_CONTRACTS`; both remain narrowed by `TenantAuthorization` and Contract data scope (`VIEW_ALL` or own `VIEW_ASSIGNED`). No capability added. |
| REST API / version compatibility | N/A in P8-004. No route/controller/serializer exposed. |
| WordPress / admin UI | N/A. No admin surface or product claim. |
| Flutter / mobile / offline state | N/A. No endpoint/model/local state. Existing Flutter gates remain required. |
| Android identity / environments | N/A to Notice Period Rules. Existing ESC Android isolation/coexistence source gates remain unchanged; physical-device evidence remains separately tracked in #421. |
| Landing / marketing / public catalog | N/A. No public/plan visibility. |
| Design system / theme | N/A. No UI. |
| Feature registry / flags / plans | Deferred to ESC-P13. |
| Search / filter / sort / bulk | Only bounded per-Contract listing exists, ordered active-first then purpose/code/ID. Global search/filter/bulk is deferred to ESC-P12. |
| Reports / import / export | N/A in P8-004; deferred to ESC-P12. |
| Renewals / expiry | P8-004 never writes Renewal Terms and never writes Contract `start_date`/`end_date`. Renewal/non-renewal purposes are descriptive rule semantics only. |
| Notifications / scheduling / escalation | Explicitly absent. No legacy notification-rule/schedule writes, no email/FCM/push, no cron/scheduler integration, no escalation. |
| Audit / compliance | Actor and server UTC timestamps are retained. Monotonic revision provides stale-write evidence. Hooks fire only after successful service writes. Existing audit history is not mutated. |
| Documents / storage | N/A. No attachment/document relation. |
| Localization / RTL / LTR / timezone / currency | Duration terms are timezone-independent configuration. Future notice-date computation must use explicit effective-date/calendar/timezone policy. No currency impact. |
| Security / privacy / rate limits | No external attack surface. Inputs are bounded/allowlisted, tenant identity is server-derived, foreign IDs fail closed, archived Contracts cannot mutate rules. REST rate limits unaffected because no route exists. |
| Performance / concurrency / idempotency | Tenant+Contract indexes support bounded list/read. Creation locks Contract plus duplicate code identity. Update locks Rule+Contract, checks caller-observed revision and uses tenant+ID+revision SQL CAS with atomic revision increment. Exactly one affected row required. |
| Automated tests | Dedicated adversarial P8-004 regression checks historical migration mapping, schema, policy bounds, tenant/data scope, immutable code, transaction/locks/CAS, no delete, no date/execution fields, and no notification/renewal/Workflow coupling. Wired into global backend gate and focused CI. |
| Documentation / demo / onboarding | This review and Issue #484 define the foundation. Demo/onboarding deferred until exposed surface exists. |
| CI / build / release / rollback | Dedicated `esc-p8-004.yml` plus global ESC Foundation Gate validate exact source. Code rollback leaves an additive unused table; production schema version must not be manually downgraded. |
| Backward compatibility | Existing Contract, Obligation, Milestone, Renewal Terms, Workflow, Approval, financial, notification, admin, mobile and Safe Contract `main` behavior remains unchanged. |

## Configuration invariants

- Multiple Notice Period Rules may exist per Contract, each identified by immutable tenant+Contract-local `notice_code`.
- Purpose is allowlisted to `renewal_election`, `non_renewal`, `termination`, or `other`.
- Direction is allowlisted to `outbound`, `inbound`, or `either` from the tenant perspective.
- Minimum notice duration is required, bounded 1..10000, with unit `day`, `month`, or `year`.
- `is_active = 0` deactivates configuration; no physical delete exists.
- Every update requires exact positive `expectedRevision` and increments revision once.
- Archived Contracts cannot create or mutate rules.
- P8-004 stores no computed/effective/scheduled/expiry date and writes no Contract term date.

## Deliberately deferred follow-on work

- notice instances / delivery history;
- effective-date and notice-date calculation rules;
- renewal/non-renewal/termination execution;
- business-day/holiday calendar semantics;
- reminder/escalation and email/FCM delivery;
- responsible user / OrgUnit assignment;
- REST/admin/mobile surfaces;
- reporting/bulk/import/export;
- feature registry / entitlement and public landing visibility.

## Acceptance evidence required before closure

1. P8-004 focused CI is green on the exact PR head.
2. P8-001/P8-002/P8-003 regressions remain green on exact PR head.
3. ESC Foundation backend/tenancy/Android-isolation gate is green on exact PR head.
4. Flutter format/analyze/tests remain green.
5. PR is merged into `enterprise-safecontracts` without overwriting concurrent team work.
6. Post-merge push gates are green before Issue #484 is closed.
