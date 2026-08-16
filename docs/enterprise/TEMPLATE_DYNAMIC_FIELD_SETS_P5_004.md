# ESC-P5-004 — Versioned Template Dynamic Field Set Snapshots

Issue: #462  
Product: Enterprise Safe Contracts (ESC) only  
Branch: `enterprise-safecontracts`

## Purpose

Give each Contract Template Version an explicit, ordered Dynamic Field set that can be authored while the Template Version is a draft and preserved as an immutable historical snapshot after publication.

The primary requirement is to prevent published Contract Templates from silently drifting when P5-001 Dynamic Field definitions are later edited or deactivated.

## Delivered model

Schema `1.30.0` adds the tenant-owned table:

`safecontracts_contract_template_version_fields`

Each row belongs to one exact Template Version and stores:

- tenant ID;
- Template ID;
- Template Version ID;
- source Dynamic Field definition ID;
- deterministic `position_no`;
- field-code snapshot;
- data-type snapshot;
- label snapshot;
- help-text snapshot;
- base required-flag snapshot;
- nullable Template-specific required override;
- options JSON snapshot;
- validation JSON snapshot;
- SHA-256 definition configuration hash;
- actor/timestamps.

Uniqueness is enforced per tenant + Template Version for both definition identity and position.

## Authoring semantics

`TemplateFieldSetService` exposes bounded internal authoring behavior:

- reads require `ACCESS`;
- mutations require `MANAGE_REFERENCE_DATA` with the existing tenant-role ceiling;
- maximum field count is 200;
- input is an ordered list;
- duplicate definition IDs are rejected;
- only active current-tenant definitions belonging to the Template's Contract Type may be attached;
- `required_override` is boolean or null;
- an empty list is valid and means this Template Version has no Dynamic Fields;
- an empty list does not inherit current or future Contract Type definitions.

Published Template Version field sets are read-only and remain historically readable even if the source definitions later change or become inactive.

## Atomic replacement / concurrency impact

Draft field-set replacement is transactional:

1. `START TRANSACTION`.
2. Lock the exact tenant-owned Template Version with `FOR UPDATE`.
3. Revalidate that the Version is still `draft`, the Template is active and the Contract Type is active.
4. Delete only that tenant + Template + Template Version's existing snapshot rows.
5. Reinsert every requested snapshot through `INSERT ... SELECT` from the current Dynamic Field definition.
6. Each insert revalidates at write time that the definition is still active, still belongs to the Template Contract Type and still matches the exact identity/presentation/configuration that was validated by the service.
7. `COMMIT` only after every snapshot succeeds.
8. Any failure performs `ROLLBACK`.

This closes both lifecycle and definition-configuration races between service validation and persistence.

A dedicated adversarial regression forces a snapshot insert to return zero rows after the draft rows were deleted. The repository raises a concurrent-change error, executes `ROLLBACK`, and never executes `COMMIT`, proving that a mid-replacement configuration race cannot leave a partially updated field set.

## Publish-time immutability guard

P4 Template publication is hardened without changing its historical single-table UPDATE contract.

The publish UPDATE still requires the exact tenant + Template + draft Version and still changes only publication state/audit columns. A correlated `EXISTS`/`NOT EXISTS` predicate now additionally requires:

- current Template active;
- current Contract Type active;
- every configured P5-004 snapshot still has a current tenant definition;
- definition still active;
- definition still belongs to the Template Contract Type;
- field code unchanged;
- data type unchanged;
- label unchanged;
- help text unchanged;
- base required flag unchanged;
- options JSON unchanged;
- validation JSON unchanged.

If any configured snapshot is stale, the UPDATE affects zero rows and publication fails closed.

The stored configuration hash is historical evidence; publication deliberately compares the source fields themselves instead of trusting a hash alone.

Template-specific `required_override` is not compared to the live definition because it is authored Template metadata, not definition state.

## Empty-field-set semantics

An empty field set is explicit valid configuration and publishes normally. It does not mean "inherit all active fields from the Contract Type" and therefore cannot acquire fields later through configuration drift.

A newly created Template Version with no snapshot rows has the same effective field-set semantics: zero Dynamic Fields until an author explicitly configures them.

## Tenant / authorization impact

- Core tenant enforcement is mandatory.
- Tenant context must be locked before reads or writes.
- Template and Version lookups are tenant-scoped.
- Definition lookups are tenant-scoped.
- Transactional persistence revalidates tenant ownership in SQL.
- Object IDs are identifiers, never authorization.
- Reads use `ACCESS`.
- Mutations use `MANAGE_REFERENCE_DATA` plus tenant-role narrowing.

No unscoped fallback is introduced.

## Database / migration impact

Migration `Migration0031EnterpriseTemplateFieldSets` is additive and registered at schema version `1.30.0`.

No existing table is altered. In particular:

- `safecontracts_contracts` is unchanged;
- P4 contract bindings are unchanged;
- P5-001 definitions are unchanged;
- P5-002 contract values are unchanged.

The P5-002 historical regression was made forward-compatible so schema `1.29.0` remains required/registered without incorrectly assuming it must remain the latest schema forever.

## Backward compatibility

- Safe Contract behavior is unchanged.
- `main` is unchanged.
- Existing P4 Template Version identity/content/history semantics remain intact.
- Existing P4 publish SQL contract remains a single-table UPDATE with exact tenant/template/draft predicates; P5-004 adds correlated safety predicates rather than changing the observable mutation shape.
- No existing contract is automatically assigned fields.
- No P5-002 value is materialized or rewritten.
- No lifecycle transition outside Template publication is changed.

## API / admin / mobile impact

No REST route, WordPress admin UI, Flutter UI, offline cache model or public mobile surface is introduced in P5-004.

Flutter format/analyze/test remains part of the mandatory ESC Gate even though the task has no Flutter code change.

## Android / release isolation impact

No Android package, namespace, Firebase identity, notification namespace, signing lineage or artifact path changes are introduced.

ESC Android identity and verified-artifact isolation Gates remain mandatory and green.

## Landing / feature-registry / plan impact

P5-004 remains an internal Development capability. It is not marketed as Public and does not alter the ESC landing page, pricing, entitlement or public feature claims.

A later feature-registry phase may expose entitlement/lifecycle metadata when the user-facing surface exists.

## Search / reporting / import / export impact

No search, reporting, import, export or bulk execution is added. The ordered snapshots are structured so later Template rendering/reporting can consume deterministic historical configuration without consulting mutable live definitions.

## Audit / compliance impact

Rows record actor/timestamps and publication retains existing Template publication actor/time fields.

No separate audit event stream is added in this bounded task. A domain action is emitted after successful field-set replacement for later audit/integration consumers.

Historical snapshot preservation improves compliance evidence because published Template interpretation no longer depends on mutable current Dynamic Field configuration.

## Security / privacy impact

- No executable expressions, PHP, JavaScript, SQL fragments, formulas or conditional visibility logic are introduced.
- Input keys and field counts are bounded.
- Cross-tenant and wrong-Contract-Type definitions fail closed.
- Published mutations fail closed.
- Concurrent configuration drift fails closed.
- Transaction rollback prevents partial replacement.
- No new public endpoint or CSRF surface is introduced.

## Localization / RTL / theme impact

No user-facing UI is introduced. Snapshot labels/help text preserve authored content historically, but localization/rendering policy remains a later UI concern. No design-system or RTL/LTR changes are required in this task.

## Automated regression coverage

The backend Gate explicitly executes:

- `tests/php/enterprise_template_field_sets_p5_004.php`
- `tests/php/enterprise_template_field_set_atomic_failure_p5_004.php`

The main P5-004 regression currently passes 70 assertions covering:

- schema/version registration;
- tenant ownership and indexes;
- tenant-context enforcement;
- valid ordered snapshot replacement;
- draft Version row lock;
- active Template/Contract Type guards;
- exact definition identity/presentation/configuration revalidation;
- transaction commit path;
- duplicate definition rejection;
- wrong Contract Type rejection;
- inactive definition rejection;
- post-publication mutation rejection;
- explicit empty field set semantics;
- historical published reads;
- effective required override hydration;
- authorization denial;
- 200-field bound;
- P4 publish integration;
- stale-definition publish predicates;
- absence of executable expression logic.

The atomic-failure regression passes 8 assertions and proves zero-row snapshot persistence causes RuntimeException + `ROLLBACK` with no `COMMIT`.

## CI findings and corrections

The development Gates intentionally surfaced compatibility/test defects before completion:

1. Gate #322 exposed that an initial `UPDATE ... JOIN` publish implementation changed the SQL shape expected by the established P4 regression. The publish implementation was redesigned to preserve the original single-table P4 mutation contract while keeping the P5-004 stale-snapshot guard inside correlated predicates.
2. Gate #323 reached P5-004 and exposed a test-fixture omission for the valid empty-field-set path: the fake DB queue did not return the expected `FOR UPDATE` lock row. The fixture was corrected without weakening production locking.
3. Gate #324 passed the 70-assertion P5-004 regression and all existing regressions on head `23b559a601b62b0bc7241239fa2965ab2418f254`.
4. A stronger atomic rollback regression was then added and wired. Final implementation Gate #326 passed on head `029cca8755d8d08365f42d1f8e88215a0cc7d2e8`, with P5-004 70/70 plus rollback 8/8, all backend/tenancy checks, Android/artifact isolation and Flutter format/analyze/test green.

## Full Impact Review checklist

- Business/domain requirement: implemented — Template Version-specific historical Dynamic Field sets.
- Tenant model/isolation: reviewed and enforced in service/repository/SQL.
- Database/migrations/indexes: additive schema `1.30.0`, tenant-first uniqueness/indexes.
- Backend business logic: implemented.
- Authorization/scopes/roles: ACCESS reads; MANAGE_REFERENCE_DATA mutations with tenant-role narrowing.
- REST/API compatibility: no route added.
- WordPress/admin UI: N/A in this task.
- Flutter/mobile UI/offline: N/A in this task; Gate remains green.
- Android identity/build environments: unchanged; isolation Gate green.
- Landing/public messaging: no public claim.
- Theme/design system: N/A.
- Feature registry/plans: remains internal Development capability.
- Search/filter/sort/bulk: no execution surface; deterministic snapshot order provided.
- Reports/import/export: N/A.
- Notifications/escalation: N/A.
- Audit/compliance: historical snapshots and actor timestamps preserved; no new audit stream.
- Documents/storage: N/A.
- Localization/RTL/timezone/currency: no UI/runtime localization change.
- Security/privacy/rate limits: no new external endpoint; bounded input and fail-closed tenant/config validation.
- Performance/concurrency/idempotency: max 200 fields; transaction + Version row lock + atomic definition predicates + rollback proof.
- Automated tests: P5-004 main and atomic-failure regressions explicitly wired.
- Documentation/demo/onboarding: this Full Impact Review added; no public/demo exposure.
- CI/build/release/rollback: implementation Gate #326 green; rollback is removal of additive P5-004 code/table before production adoption, with no legacy contract mutation to reverse.
- Backward compatibility: P4 SQL behavior, Safe Contract/main, existing contracts/bindings/values remain unchanged.

## Explicit non-goals / follow-up boundary

P5-004 does not materialize Template fields into contract values, block contract lifecycle transitions, implement formulas/calculations, implement conditional visibility, expose REST/admin/Flutter UI, or make a public feature claim.
