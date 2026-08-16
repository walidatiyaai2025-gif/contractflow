# ESC-P4-003 — Contract Configuration Bindings

## Purpose
P4-003 introduces an Enterprise-only configuration binding between an existing tenant-owned contract and the P4 Contract Type / published Contract Template Version foundations.

The binding is intentionally stored outside `safecontracts_contracts`. This preserves the Safe Contract/legacy contract schema and behavior while allowing ESC to attach configurable enterprise metadata without a destructive retrofit.

## Domain boundary
- One ESC contract may have at most one configuration binding per tenant.
- `contract_type_id` is mandatory once a binding exists.
- `template_id` and `template_version_id` are optional only as a pair.
- A bound Template must belong to the selected Contract Type.
- A bound Template Version must belong to that Template and must already be `published`.
- Draft Template Versions are never bindable.
- Exact rebinding is idempotent.
- Binding changes are permitted only while the contract remains an unarchived `draft`.
- Once the contract leaves `draft`, the binding is historical configuration and is immutable.
- Type/Template deactivation after binding does not erase historical binding reads.

## Full Impact Review

### Business requirement / domain model
Implemented as a separate one-to-one Enterprise binding record. No automatic contract creation, template rendering, custom-field materialization, workflow binding or approval binding is included.

### Tenant model / isolation
Every binding is tenant-owned. Contract, Contract Type, Template and Template Version lookups all use the locked `TenantContextStore` tenant. There is no unscoped fallback and object IDs do not grant authorization.

### Database / migrations / indexes
Schema `1.27.0` adds `safecontracts_contract_configuration_bindings` only. It does not alter `safecontracts_contracts`. The table has unique `(tenant_id, contract_id)` plus tenant-first type and template-version indexes. Existing contracts are not backfilled.

### Backend business logic
The service validates contract lifecycle/archive state, Contract Type activity, Template→Type consistency and published Version→Template consistency before persistence. Persistence independently revalidates those same invariants atomically against current tenant-owned rows. Historical reads do not require referenced configuration records to remain active.

### Authorization / data scope
Reads require `ACCESS`; mutations require `EDIT_CONTRACTS`. Existing contract data-scope semantics are preserved: `VIEW_ALL`, or `VIEW_ASSIGNED` only for the current user's assigned contract.

### API compatibility
No REST endpoint is added in P4-003. Existing APIs and contract payloads are unchanged.

### WordPress / admin UI
N/A in this task. No admin surface is exposed yet.

### Flutter / mobile / offline state
N/A in this task. Existing Flutter behavior remains unchanged and mobile CI remains mandatory.

### Android identity / environments
N/A functionally. ESC Android identity and release-artifact isolation gates remain mandatory and must stay green.

### Landing / public messaging / feature registry
No public availability claim is made. P4-003 remains an internal foundation capability until later feature-registry/public-surface work.

### Design system / theme
N/A; no UI is introduced.

### Search / filter / bulk / reports / import / export
Not exposed in this foundation task. Later consumers should resolve configuration through the tenant-owned binding table rather than denormalizing legacy contract rows.

### Notifications / escalation
N/A.

### Audit / compliance
Binding records retain created/updated actors and timestamps; a domain action hook is fired on mutation. Dedicated audit-event integration can be added when the ESC audit surface is expanded.

### Documents / storage
N/A.

### Localization / timezone / currency
N/A; the binding contains identifiers only.

### Security / privacy
Cross-tenant contract/type/template/version IDs fail closed. Draft versions cannot be bound. One-sided Template/Version inputs fail before mutation. Legacy contract rows are never rewritten by this feature.

### Performance / concurrency / idempotency
The unique tenant+contract key gives one binding per contract. Exact bindings return without a write and replacements use an atomic upsert. Persistence uses `INSERT … SELECT` with tenant-scoped joins that atomically revalidate all mutable preconditions at write time: the contract must still be an unarchived `draft`, the Contract Type must still be active, and—when present—the Template must still be active and belong to that Type while the selected Version must still be a published version of that Template. Therefore a concurrent lifecycle/archive transition or configuration deactivation cannot race the service-level validation. A zero-row write fails closed.

### Automated tests
`tests/php/enterprise_contract_configuration_bindings_p4_003.php` covers schema registration, tenant predicates, authorization/data scope, cross-tenant references, inactive/mismatched configuration, draft-version rejection, exact idempotency, type-only binding, post-draft/archive rejection and atomic DB revalidation of contract/type/template/version invariants. The regression is explicitly wired into `scripts/test-php.sh`.

### Release / rollback / backward compatibility
Rollback can leave the additive binding table unused without changing legacy contract behavior. No Safe Contract/main change, merge or backport is created. Existing contracts without a binding continue to work exactly as before.

## Deferred work
- REST/admin/Flutter configuration surfaces.
- Automatic contract creation from templates.
- Dynamic field materialization.
- Workflow/approval configuration binding.
- Rendering/PDF generation.
- Optional controlled backfill/import for existing Enterprise contracts.
