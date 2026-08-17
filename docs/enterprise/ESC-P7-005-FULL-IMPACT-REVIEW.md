# ESC-P7-005 — Approval REST API Full Impact Review

Issue: #474  
Track: Enterprise Safe Contracts (`enterprise-safecontracts`)  
Scope: REST exposure of the existing P7-002/P7-003/P7-004 Approval services only.

## Delivered boundary

The Approval engine is exposed under the existing `safecontracts/v1` namespace through three route families and six operations:

- `GET/POST /contracts/{contract_id}/approval-requests`
- `GET/POST /approval-requests/{request_id}/decisions`
- `GET/POST /approval-requests/{request_id}/release`

Controllers remain service-only. They do not access approval tables, `$wpdb`, P6 transition repositories, or transition execution directly. Write bodies are strict JSON objects with allowlisted keys only. Existing Approval Request, Decision and Release idempotency policy remains authoritative; REST does not add a header-based identity convention.

## Security and tenancy review

- Routes register only while Enterprise core tenant enforcement is enabled.
- Approval permission callbacks resolve and lock tenant context through `TenantRequestContext::resolve(..., true)` before capability evaluation.
- `Permission::capability()` preserves the WordPress capability requirement and the Enterprise tenant-role narrowing ceiling.
- The global `CoreTenantRestGuard` classifies both contract-scoped Approval Request routes and request-scoped Decision/Release routes as core business routes.
- Reads require `ACCESS`; writes require `EDIT_CONTRACTS`.
- Services continue to enforce current-tenant repository ownership plus existing contract data scope (`VIEW_ALL` or own `VIEW_ASSIGNED`).
- Caller-supplied tenant identity is not accepted in route or JSON payloads.
- Foreign/cross-tenant object identifiers continue to fail closed inside tenant-scoped services/repositories.
- Internal SHA-256 idempotency hashes remain repository/service internals and are not emitted by the controller contract.
- Unexpected server errors are masked by the v1 error envelope.

## Full impact matrix

| Dimension | Review/result |
|---|---|
| Business/domain | No new Approval semantics. REST exposes already-authoritative P7 Request, Decision and Release commands/reads. |
| Tenant isolation | Affected. Locked tenant resolution is mandatory in both global route guard and permission callbacks; request-scoped Approval routes were added to the core route classifier. |
| Database/migrations | N/A. No schema change; P7-002..004 tables remain authoritative. |
| Backend business logic | No duplication. Controller delegates only to the established Approval services. |
| Authorization/scopes/roles | Affected. `ACCESS` for reads, `EDIT_CONTRACTS` for writes, with tenant-role ceiling and service data-scope enforcement. |
| REST/version compatibility | Affected. Additive `safecontracts/v1` endpoints only; no existing endpoint changed. |
| WordPress/admin UI | N/A for this task. |
| Flutter/mobile | N/A for this task; API is ready for a later Approval UI task. |
| Android identity/build | N/A; no mobile/build identity change. |
| Landing/public catalog | N/A; no public availability claim. |
| Design system/theme | N/A. |
| Feature registry/plans | N/A; no entitlement/public lifecycle change. |
| Search/filter/bulk | N/A; only bounded Approval list pagination is added. |
| Reports/import/export | N/A. |
| Notifications/escalation | N/A; existing P7 domain hooks remain unchanged. |
| Audit/compliance | Existing immutable P7/P6 evidence remains authoritative; REST introduces no alternate mutation path. |
| Documents/storage | N/A. |
| Localization/RTL/timezone/currency | N/A; API-only task. |
| Security/privacy/rate limits | Existing REST protection/rate controls remain in path; strict request shapes and masked 5xx responses reduce leakage. |
| Performance/concurrency/idempotency | Existing bounded service/repository limits and transactional/idempotent P7 semantics are reused. Pagination is capped at 100 items. |
| Automated tests | `enterprise_approval_rest_p7_005.php` verifies route shape, runtime permission-helper existence, tenant route classification, strict payloads, service-only delegation, hidden hashes and status semantics. It is wired into `scripts/test-php.sh`. |
| Documentation/onboarding | This impact review documents the REST contract and non-goals. |
| CI/build/release/rollback | Global ESC Foundation Gate runs the wired regression. Dedicated `ESC P7-005 Approval REST Gate` lints and executes the P7-005 boundary test. Rollback is additive: revert controller/router/guard wiring without schema rollback. |
| Backward compatibility | Safe Contract `main` is untouched. ESC P6/P7 service contracts and direct no-route P6 behavior are unchanged. |

## Non-goals

No admin Approval UI, Flutter Approval UI, bulk decisions/releases, automatic release, escalation/reminder implementation, workflow-version migration, public landing claim, legacy `ContractStatus` synchronization, or Safe Contract/main transfer is included.

## Validation requirement

Issue #474 must remain open until the exact final source head passes both the global ESC Foundation Gate and the dedicated P7-005 gate. Completion evidence should record the final commit and Actions run IDs before closure.
