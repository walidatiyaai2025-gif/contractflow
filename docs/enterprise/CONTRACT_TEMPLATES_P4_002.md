# ESC-P4-002 — Versioned Contract Template foundation

## Purpose

P4-002 adds tenant-owned reusable Contract Templates on top of P4-001 Contract Types. It establishes durable template identity and explicit content-version history without binding current contracts or changing their lifecycle.

This task is ESC-only and does not alter Safe Contract/main.

## Model boundary

- **Contract Type** identifies the tenant-owned category/configuration anchor.
- **Contract Template** is a stable tenant-owned reusable template identity linked to one Contract Type.
- **Template Version** is a numbered content/configuration snapshot belonging to one Template.
- **Existing Contract** remains unbound to Contract Types/Templates in P4-002.

Template identity and version content are intentionally separate so published history is never rewritten when display metadata changes.

## Schema

Schema `1.26.0` adds:

### `safecontracts_contract_templates`

Mandatory tenant ownership, server UUIDv4, current-tenant Contract Type ID, stable tenant-local `template_code`, name/description, active/inactive status, actors and timestamps.

Unique `(tenant_id, template_code)` prevents duplicate stable codes inside one tenant. Tenant-first indexes cover Contract Type/status and status/name listing.

### `safecontracts_contract_template_versions`

Mandatory tenant ownership, Template ID, server-controlled `version_no`, `draft`/`published` state, structured `definition_json`, optional notes, actors, publication actor/timestamp and audit timestamps.

Unique `(tenant_id, template_id, version_no)` protects version identity. Published rows remain historical records; no delete/unpublish path exists in the foundation.

## Authoring and publication rules

- New Template creation requires an existing **active Contract Type in the locked tenant**.
- Template code and Contract Type binding are immutable in this foundation.
- Draft creation requires both the Template and its Contract Type to remain active.
- The next version number is calculated server-side from the current maximum for the locked tenant/template.
- DB uniqueness is the final concurrency guard. If two concurrent writers race for the same next number, one fails closed and may retry; history is never renumbered or overwritten.
- Draft versions may update definition/notes only while their DB state is `draft`.
- Publish changes state/publication metadata only; it does not rewrite definition, notes or version number.
- Published versions cannot be edited, unpublished or destructively deleted through this service.
- Deactivating a Template prevents new authoring/publishing but does not hide/destroy existing version history.

## Definition safety

`definition` is inert structured data, not executable PHP/JavaScript. The service accepts JSON-compatible arrays only and recursively rejects objects, resources, closures and non-finite floats.

Limits:

- maximum encoded definition size: 100,000 bytes;
- maximum nesting depth: 32;
- version notes: 5,000 characters.

Strings are stored as data. Any future HTML/PDF/UI renderer must apply context-appropriate escaping/sanitization; P4-002 introduces no executable renderer.

## Tenant and authorization boundary

All repositories fail closed without Enterprise core tenant enforcement and a locked `TenantContextStore` tenant.

Every Template/Version predicate contains tenant ownership; version reads/writes also bind `template_id`, so a numeric version ID from another template/tenant is never sufficient authorization.

Reads require global `ACCESS` plus tenant-role capability ceiling. Authoring/publishing requires global `MANAGE_REFERENCE_DATA` plus tenant-role ceiling.

Caller cannot provide tenant ownership, UUID or version number.

## Backward compatibility

P4-002 does not:

- add `contract_type_id`, `template_id` or `template_version_id` to `safecontracts_contracts`;
- change `ContractService` create/edit behavior;
- change inherited `ContractStatus` transitions;
- create contracts from templates;
- bind custom fields/workflows/approvals;
- render HTML/PDF documents.

Those are later bounded P4/P5/P6 tasks.

## Full Impact Review

- Business/domain: reusable Template identity + version history only; no industry code fork.
- Tenant/isolation: Template, Contract Type and every Version resolved in locked tenant; version predicates include tenant+template.
- Database/migrations: additive `1.26.0`, two new tables, tenant-first indexes, stable code/version uniqueness, no existing-table rewrite.
- Backend: template create/find/search/update metadata/deactivate; version create/edit/publish/find/list.
- Authorization: `ACCESS` reads and `MANAGE_REFERENCE_DATA` mutations, narrowed by tenant-role policy.
- REST/admin/Flutter: N/A; no external UI/API surface in foundation.
- Android/release identity: N/A.
- Landing/public messaging: N/A; do not claim template availability publicly from this foundation alone.
- Design system/theme: N/A.
- Feature registry/plans: no public lifecycle/entitlement claim introduced.
- Search: Template search tenant-scoped and bounded to 100 rows; version history bounded similarly.
- Reports/import/export/bulk: N/A.
- Notifications/escalation: N/A.
- Audit/compliance: actor/timestamps, publisher metadata and domain hooks; published snapshots immutable.
- Documents/storage: definitions remain DB configuration, not rendered documents/files.
- Localization/timezone/currency: definition strings are inert UTF-8 data; no locale/currency behavior introduced.
- Security/privacy: structured JSON compatibility checks, byte/depth bounds, immutable publication, tenant/template ID binding.
- Performance/concurrency: tenant-first indexes; bounded reads; DB uniqueness protects duplicate codes/version numbers; concurrent version allocation fails closed rather than rewriting history.
- Idempotency: Template deactivate is idempotent. Draft edit/publish uses draft-only predicates and fails on concurrent state changes. Publication is intentionally one-way.
- Automated tests: migration registration, schema, code normalization, definition bounds/types, Contract Type ownership/status, template immutability, server version allocation, draft editing, published immutability, publish no-content-rewrite, foreign IDs, list bounds, capabilities and legacy Contract/lifecycle isolation.
- CI/build/release: P4-002 regression must be explicitly invoked by `scripts/test-php.sh`; exact-source ESC Foundation Gate required.
- Backward compatibility: existing contracts/lifecycle untouched.
- Rollback: additive schema/code; no destructive automatic rollback or removal of historical versions.

## Deferred work

Contract-to-Type/Template binding and snapshot provenance on newly created contracts should be a separate P4 task. Existing-contract migration/backfill requires its own compatibility analysis. Dynamic fields and workflow/approval bindings belong to later roadmap phases.
