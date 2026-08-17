# ESC-P8-001 Full Impact Review — Contract Obligation Foundation

## Scope decision

ESC-P8-001 introduces the first P8 runtime domain: tenant-owned Contract Obligations. It is deliberately a backend foundation only. Milestones, renewals, notices, recurrence, assignment, reminders/escalation, REST/admin/mobile surfaces and public marketing remain separate follow-up work.

## Business requirement / domain model

An Obligation is an explicit contractual responsibility attached to one existing Contract. It has a server-generated UUID, immutable contract-local machine code, title, optional description, optional contractual due date and lifecycle `open -> completed|cancelled`. Completion/cancellation are terminal in this foundation; reopening is not implemented.

## Tenant model / isolation

Affected and mandatory. Every row owns `tenant_id`. Repository access derives tenant identity only from locked `TenantContextStore`; caller-supplied tenant identity does not exist. Contract parent lookup, obligation lookup, lists, locks and mutations are tenant-scoped. Obligation-to-Contract locking joins on both contract ID and tenant ownership.

## Database / migrations / indexes

Affected. Additive `Migration0043EnterpriseContractObligations` advances schema from `1.41.0` to `1.42.0` and creates only `safecontracts_contract_obligations`. It does not alter or delete legacy Contract, financial, Workflow or Approval tables. UUID and tenant+contract+code uniqueness are enforced. Tenant/contract/status/due-date indexes support bounded future operational queries.

Rollback policy remains the repository convention: migration is forward-only/additive; production rollback is application rollback with the additive table retained unless a separately reviewed data migration explicitly removes it.

## Backend business logic

Affected. New `ContractObligationPolicy`, `ContractObligationRepository` and `ContractObligationService` own validation, persistence and lifecycle behavior. No existing Contract/Workflow/Approval service is modified or duplicated.

## Authorization / scopes / roles

Affected. Reads require `ACCESS`; mutations require `EDIT_CONTRACTS`. Both are narrowed by `TenantAuthorization`. Contract data scope remains authoritative: `VIEW_ALL` or the current user's own `VIEW_ASSIGNED` Contract. Object IDs do not grant access.

## REST API / compatibility

Reviewed, intentionally deferred. No P8 REST route or response contract is added. Existing `safecontracts/v1` behavior is unchanged. A later P8 REST task must call this service boundary rather than accessing the table directly.

## WordPress / admin UI

Reviewed, deferred. No admin menu, screen, form or bulk action is added. This avoids prematurely fixing UX before milestones/notices/assignment semantics are defined.

## Flutter / mobile / offline state

Reviewed, deferred. No Flutter model, cache or UI is added. Mobile remains unaffected and must continue to pass existing format/analyze/tests.

## Android identity / build environments

N/A to local feature behavior, but release isolation remains mandatory. ESC Android identity/artifact gates must remain green and no Safe Contract mobile identity is touched.

## Landing / marketing / public feature catalog

Reviewed, deferred. Contract Obligations are not yet a public ESC feature claim because there is no supported end-user surface. Landing content and public lifecycle state must not advertise P8-001 as generally available.

## Design system / theme

N/A for this backend-only foundation. Future admin/mobile obligation screens must use the ESC design system.

## Feature registry / flags / subscription plans

Reviewed, deferred. No entitlement is introduced before the user-facing feature boundary exists. The backend foundation must not be treated as automatic plan availability.

## Search / filter / sort / bulk actions

Partially affected internally. Repository listing is bounded and deterministic for one Contract, ordering contractual due dates first and undated obligations last. Cross-contract search, advanced filters and bulk operations are deferred.

## Reports / import / export

Reviewed, deferred. No report columns, imports or exports are added. Later exposure must preserve tenant + Contract scope and cannot query obligations globally by object ID.

## Notifications / escalation

Reviewed, deferred. P8-001 persists contractual due dates only. It does not schedule reminders, derive tenant-local delivery instants or send notifications. Later scheduling must interpret dates using tenant timezone policy explicitly.

## Audit / compliance

Affected at domain-evidence level. Rows retain created/updated actors/timestamps and terminal completion/cancellation actor/timestamp evidence. Domain hooks are emitted for newly committed create/update/terminal operations. A broader immutable audit-event integration can be added when the P8 audit/public surface is specified.

## Documents / storage

N/A. Obligations have no attachment/document ownership in this foundation. Later evidence-document links must use the existing document/security model rather than storing blobs here.

## Localization / RTL / timezone / currency

Reviewed. Stored title/description are UTF-8-capable business text. `due_date` is a contractual `DATE`, deliberately not a timestamp; timezone-specific reminder instant calculation is deferred. No currency behavior is introduced. No UI strings/RTL layout are added.

## Security / privacy / rate limits

Affected through tenant isolation and authorization. No tenant input, raw SQL object identifier or physical delete API is exposed. Internal service methods are not public REST endpoints, so new rate-limit buckets are not required yet. Future REST exposure must use the established tenant REST guard and rate limiting.

## Performance / concurrency / idempotency

Affected. Lists are bounded. Create/update/lifecycle writes are transactional and lock authoritative Contract/Obligation rows. Terminal writes use an `open` compare-and-set predicate; a competing terminal result fails closed. Exact retry of the same terminal state is idempotent after authorization/scope checks and emits no duplicate service event. Contract-local code uniqueness is database-enforced.

## Automated tests

Affected. `tests/php/enterprise_contract_obligations_p8_001.php` validates schema/version registration, policy bounds, tenant scoping, Contract ownership joins, transactions/locks/CAS, server-derived terminal evidence, no physical delete, service authorization/scope, UUID generation, no REST exposure and global gate wiring. It is wired into `scripts/test-php.sh` and a focused P8-001 workflow.

## Documentation / demo data / onboarding

This Full Impact Review documents the foundation. No demo data or onboarding is added because no end-user surface exists yet.

## CI / build / release / rollback

Affected. Global ESC backend/tenancy, Android/artifact isolation and Flutter gates remain authoritative. A focused P8-001 PHP gate validates the exact new source. No artifact may be called verified from this task unless the normal ESC release requirements are satisfied independently.

## Backward compatibility

Preserved. The change is additive and ESC-only. Existing `safecontracts_contracts`, Contract status, financial logic, P6 Workflow runtime, P7 Approval runtime, Safe Contract `main`, REST contracts and mobile client behavior are untouched.

## Explicit follow-up boundaries

Later P8 tasks should separately define: milestone model/relationship, renewal and notice semantics, recurrence/generation, responsible user/OrgUnit assignment, reminder/escalation scheduling, REST API, admin UI, Flutter UI, reporting/import/export, audit-event integration, feature registry/entitlements and public landing lifecycle.
