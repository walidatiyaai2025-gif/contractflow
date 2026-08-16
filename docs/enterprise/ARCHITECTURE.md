# Enterprise Safe Contracts — Architecture

## Product boundary
Enterprise Safe Contracts (ESC) is a separate product line from the existing client-specific Safe Contract. ESC is developed on `enterprise-safecontracts`; no ESC change flows into Safe Contract without explicit owner instruction.

Official public URL: `https://esc.50sols.com/`.

## Baseline architecture
ESC preserves the repository's current direction:
- WordPress + the custom SafeContracts-derived plugin are the authoritative backend and business source of truth.
- Flutter is a client of versioned REST APIs.
- Authorization, tenant isolation, authoritative financial calculations, workflow transitions and audit policy remain server-side.
- Schema changes use versioned migrations.

## Enterprise layers
1. **Tenant foundation** — Tenant, organization profile, tenant context/resolution, lifecycle/status, locale/timezone/default currency.
2. **Identity and access** — tenant-aware RBAC/capabilities/scopes; departments/teams; later SSO/MFA/SCIM.
3. **Party domain** — customer/vendor/supplier/contractor/agent/individual/government/other party roles.
4. **Contract core** — generic contract entity plus contract types, templates and configurable lifecycle.
5. **Configuration engine** — custom fields, validation, conditional visibility, calculated/reportable metadata.
6. **Workflow/approval** — transitions, sequential/parallel/conditional approval rules, delegation/escalation.
7. **Obligations** — milestones, deliverables, notices, expiries, renewals, guarantees and compliance dates.
8. **Financials** — value, additions, discounts, variations, tax/VAT, retention, penalties, payments, collections and multi-currency.
9. **Documents** — metadata, versions, expiry, access control, retention and storage abstraction.
10. **Notifications** — rules, templates, channels, retry/escalation and delivery logs.
11. **Analytics** — search, reporting, dashboards, import/export and bulk operations.
12. **SaaS control plane** — plans, entitlements, quotas, feature flags, usage and tenant provisioning.
13. **Presentation** — admin shell, Flutter app, landing page, email/report branding, Arabic RTL/English LTR.
14. **Platform** — API/webhooks/integrations, observability, backup/restore, DR, CI/CD and release provenance.

## Architectural guardrails
- IDs are never authorization.
- Every tenant-owned read/write path must resolve and enforce tenant context server-side.
- Prefer tenant-aware repository/service boundaries over ad-hoc SQL filters.
- Avoid per-industry forks and dedicated `ExportContracts`, `RentalContracts`, etc. Use a generic contract core plus configuration/templates.
- Public APIs are versioned and backward compatibility is reviewed on every change.
- Feature state and plan entitlement are centrally registered rather than hidden in UI conditionals.
- Mobile never becomes a competing source of financial or authorization truth.

## Environment model
ESC environments: local/dev, staging, production. Each environment must have explicit API endpoint configuration, secret isolation, logging policy and release identity. Android dev/staging/prod builds must never accidentally connect to the wrong production backend.

## Android product identity
ESC must be installable beside Safe Contract. The production package baseline is `com.safecontracts.enterprise`, distinct from the existing Safe Contract package. Namespace, Firebase app, deep links, signing lineage, local storage namespaces, notification channels and analytics/crash identity must be separate.

## Public web architecture
`https://esc.50sols.com/` is a first-class ESC surface. It consumes the ESC design system and feature registry. Marketing claims must map to feature lifecycle state and product reality.

## Change impact architecture
Every ESC change must evaluate its effect on database/migrations, tenant isolation, authorization, APIs, admin, mobile, public landing, design tokens, reports, imports/exports, notifications, audit, localization, security, tests, CI and release behavior. N/A is valid only after explicit review.
