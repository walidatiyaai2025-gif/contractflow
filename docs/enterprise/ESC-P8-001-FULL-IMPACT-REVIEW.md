# ESC-P8-001 Full Impact Review — Contract Obligation Foundation

Issue: #476

## Scope decision

P8-001 adds only the tenant-owned Contract Obligation backend foundation: additive schema `1.42.0`, validation/lifecycle policy, tenant-safe repository/service boundaries, adversarial regression and focused CI. REST, WordPress admin, Flutter/mobile presentation, reminders, recurrence, assignments and public marketing are deliberately deferred.

## Mandatory ESC dimensions

- **Tenant isolation — affected.** Every repository path derives the locked tenant from `TenantContextStore`. Contract and Obligation IDs are always combined with tenant/Contract predicates. Persistence mutations revalidate the same tenant-owned Contract; object IDs are not authorization.
- **Schema/migrations — affected.** `Migration0043EnterpriseContractObligations` creates only `safecontracts_contract_obligations`; schema advances from `1.41.0` to `1.42.0`. No ALTER/DROP/destructive legacy change. UUID is unique and `(tenant_id, contract_id, obligation_code)` is unique.
- **Backend/business model — affected.** New `ObligationPolicy`, `ObligationRepository` and `ObligationService` are the only P8 business boundaries. WordPress/plugin remains authoritative.
- **Authorization — affected.** Reads require `ACCESS`; mutations require `EDIT_CONTRACTS`. Tenant role narrowing remains mandatory through `TenantAuthorization`. Contract data scope remains `VIEW_ALL` or own `VIEW_ASSIGNED`. Archived Contracts reject new mutations.
- **API — deferred/N/A for P8-001.** No controller, route or payload contract is introduced. A later issue must design REST separately against these service boundaries.
- **WordPress admin UI — deferred/N/A.** No menu/page/form is added.
- **Flutter — deferred/N/A.** No client model, screen or API integration is added.
- **Android identity/builds — reviewed, unaffected.** No package, Firebase, notification-channel, deep-link, signing or artifact identity changes.
- **Landing/public site — deferred/N/A.** Obligations are not advertised or exposed publicly in this foundation task.
- **Design system — deferred/N/A.** No visual surface is introduced.
- **Feature registry/plans/entitlements — deferred/N/A.** P8-001 deliberately introduces no plan gating or public feature lifecycle claim.
- **Search/reports/import/export — partially affected/deferred.** Repository search is bounded to one authorized Contract and supports status, exact code and contractual due-date ranges. Cross-contract/global reporting, import/export and saved searches are deferred.
- **Notifications — deferred/N/A.** `due_date` is contractual `DATE` only. No reminder scheduling, timezone interpretation, delivery or escalation is introduced.
- **Audit — partially affected.** Creator/updater and completion/cancellation actor/timestamp evidence are stored server-side and domain actions are emitted. A broader immutable audit/history integration, if required for later P8 milestones, is separate work.
- **Documents — deferred/N/A.** No document generation, attachment or notice artifact is created.
- **Localization — reviewed, N/A.** No user-facing UI/copy surface is introduced; domain exception strings remain backend diagnostics.
- **Security — affected.** Unsupported fields/status injection/invalid DATE values fail closed; immutable machine code and terminal records cannot be rewritten through metadata update; terminal transitions use current-tenant/current-Contract/open-state compare-and-set predicates; no physical delete exists.
- **Performance — affected.** Tenant/Contract/status/due-date and tenant/status/due-date indexes support bounded future operational queries. Search limit is capped at 100 and offset at 100000.
- **Tests — affected.** Foundation and adversarial tests cover migration registration, uniqueness, tenant/Contract query predicates, validation, scope denial, archived Contracts, tenant-role narrowing, server terminal evidence, idempotent retry and CAS markers. Tests are wired into the global ESC backend suite.
- **Documentation — affected.** This Full Impact Review records scope, deferred surfaces, security and rollback.
- **CI — affected.** A focused `ESC P8-001 Contract Obligation Gate` runs syntax + P8 tests on PR/push; the global `ESC Foundation Gate` continues to execute the complete backend regression through `scripts/test-php.sh` plus Flutter/Android/artifact isolation.
- **Release/rollback — affected only at source/schema level.** No production artifact is published by this task. Rollback of application behavior is removal/revert of P8 domain code; database rollback is intentionally non-destructive—the additive obligation table may remain unused rather than dropping tenant data. Any future destructive migration requires a separate approved task.

## Lifecycle and concurrency review

The lifecycle is intentionally minimal: `open -> completed` or `open -> cancelled`. Reopening is not present. Metadata writes require the row to remain `open`. Terminal writes also require `open`, current tenant, exact Contract/Obligation identity and a non-archived Contract in the same SQL mutation. An exact retry that observes the already-requested terminal state returns idempotently without another write; a different terminal state fails closed. Actor IDs come from the authenticated server user and terminal timestamps come from database `UTC_TIMESTAMP()`.

## Legacy/P6/P7 non-interference

P8 repository/service code does not update legacy Contract status/financial/payment tables and does not write P6 Workflow instance/history or P7 Approval tables. No P8 route/admin/mobile/public registration is added. Existing P6/P7 tests remain part of the global backend regression.

## Closure evidence required

Before issue #476 closes, the exact final PR head must have both the global ESC Foundation Gate and the focused P8-001 gate green. The PR/issue must record the exact source SHA and run IDs. No release/device/UAT evidence is required because P8-001 creates no mobile/release surface.
