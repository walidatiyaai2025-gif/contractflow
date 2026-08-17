# ESC-P8-006 Full Impact Review — Contract Term Expiry Evaluation

Issue: #488  
Phase: ESC-P8 — Obligations, milestones, deliverables, notice periods, renewals, expiry and escalation

## Decision

P8-006 implements Contract expiry as a **derived read-only date condition**, not a persisted Contract lifecycle status. The authoritative current-term boundary remains `safecontracts_contracts.end_date`; existing lifecycle status remains `draft|active|completed|cancelled`.

Evaluation requires an explicit contractual `as_of_date`. This avoids treating server/WordPress timezone as tenant truth before tenant timezone support is implemented. UTC is used only as a neutral coordinate for date-only calendar arithmetic; no current clock is consulted.

## Impact review

| Dimension | Review / decision |
|---|---|
| Business requirement / domain model | Adds deterministic term expiry evaluation: `undated`, `not_expired`, `ends_today`, `expired`, plus day distance. No persistent expiry state. |
| Contract term / lifecycle semantics | Existing Contract `end_date` is sole term boundary. Existing Contract status is returned as context but never changed or reinterpreted as a new lifecycle state. |
| Tenant model / isolation | Repository requires core tenant enforcement and derives tenant from `TenantContextStore`. Contract lookup is ID+tenant scoped; object ID never authorizes access. |
| Database / migrations / indexes | No migration, no table/column/index, no schema version advance. Derived state is intentionally not persisted. |
| Backend business logic | Adds pure `ContractTermExpiryPolicy`, read-only tenant repository and `ContractTermExpiryService`. |
| Authorization / scopes / roles | Evaluation requires `ACCESS`, `TenantAuthorization` narrowing, and existing Contract `VIEW_ALL` or own `VIEW_ASSIGNED` scope. No mutation capability or new capability is introduced. |
| REST API / version compatibility | N/A in P8-006. No REST route/controller/serializer. |
| WordPress / admin UI | N/A. No UI/product surface. |
| Flutter / mobile / offline state | N/A. No endpoint/model/local state; existing Flutter gates remain mandatory. |
| Android identity / environments | N/A to expiry evaluation. Existing Android isolation/coexistence source gates remain unchanged; physical-device evidence remains #421. |
| Landing / marketing / public catalog | N/A. No feature claim or public surface. |
| Design system / theme | N/A. No UI. |
| Feature registry / flags / plans | Deferred to ESC-P13. |
| Search / filter / sort / bulk | Single-Contract read evaluation only. Cross-Contract expiry search/dashboard remains ESC-P12 work. |
| Reports / import / export | N/A in P8-006. Evaluation can later support reporting through an explicit integration. |
| Renewal / notice semantics | Renewal Terms and Notice Period Rules are not read or mutated. Expiry evaluation only answers the current Contract term boundary as-of a supplied date. |
| Notifications / escalation | No reminder, escalation, scheduler, notification or provider integration. Escalation remains a separate P8/P11 concern. |
| Audit / compliance | No mutation means no audit event is emitted. The response includes the explicit `as_of_date` so the derived result is reproducible. |
| Documents / storage | N/A. |
| Localization / RTL / LTR / timezone / currency | Dates are contractual `YYYY-MM-DD`. Caller must provide explicit as-of date. No server “today” or tenant timezone inference. UTC is only a neutral date-math coordinate; future tenant-timezone orchestration may supply an authoritative local date. No currency impact. |
| Security / privacy / rate limits | No external attack surface. Strict calendar validation, tenant lookup and Contract data scope fail closed. No REST rate-limit impact. |
| Performance / concurrency / idempotency | One indexed Contract read; no locks/writes are needed because evaluation is a snapshot of read data. Same Contract dates + same as-of date always produce the same result. |
| Automated tests | Focused adversarial regression covers leap-date validation, all expiry boundaries/day distances, no migration, tenant/data-scope source invariants, read-only SQL, no hidden clock, no side effects and no REST exposure. Wired into global backend gate and focused CI. |
| Documentation / demo / onboarding | This review and Issue #488 define the read-only expiry contract. UI/demo deferred until exposed surface exists. |
| CI / build / release / rollback | Dedicated `esc-p8-006.yml` plus global ESC Foundation Gate validate exact source. Rollback removes only code/tests/workflow; no schema rollback exists because schema is unchanged. |
| Backward compatibility | Existing Contract, P8 foundations, Workflow, Approval, financial, notification, admin, mobile and Safe Contract `main` behavior remain unchanged. |

## Evaluation contract

Given explicit `as_of_date` and authoritative Contract `end_date`:

- missing `end_date` -> `undated`, no day distance;
- `as_of_date < end_date` -> `not_expired`, positive `days_until_end`;
- `as_of_date == end_date` -> `ends_today`, `days_until_end = 0`;
- `as_of_date > end_date` -> `expired`, positive `days_past_end`.

All dates must be real canonical `YYYY-MM-DD` values. Leap days are handled as calendar days. Archived/completed/cancelled Contracts can still be evaluated historically; the result is informational and never mutates lifecycle.

## Explicit non-persistence / clock invariants

- No `expired` column/status/table/snapshot.
- No Migrator change or schema version change.
- No write SQL or transaction.
- No Contract `status`, `start_date` or `end_date` mutation.
- No `now`, `current_time`, `gmdate`, `wp_date` or hidden current-date call.
- No Renewal Terms / Notice Period / notification mutation.

## Deliberately deferred follow-on work

- tenant-timezone-aware “current as-of date” orchestration;
- cross-Contract expiry search/dashboard;
- expiry event/history or action processing, if later justified;
- escalation policies and escalation instances;
- reminders/scheduler/provider delivery;
- REST/admin/mobile surfaces;
- reporting/bulk/export integration;
- feature registry / entitlement and landing visibility.

## Acceptance evidence required before closure

1. P8-006 focused CI is green on exact PR head.
2. P8-001..P8-005 focused regressions remain green on exact PR head.
3. ESC Foundation backend/tenancy/Android-isolation gate is green on exact PR head.
4. Flutter format/analyze/tests remain green.
5. PR is merged into `enterprise-safecontracts` without overwriting concurrent team work.
6. Post-merge push gates are green before Issue #488 is closed.
