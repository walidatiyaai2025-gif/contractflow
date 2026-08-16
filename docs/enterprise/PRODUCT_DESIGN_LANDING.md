# Enterprise Safe Contracts — Product, Design System & Landing

Official URL: `https://esc.50sols.com/`

## Product positioning
ESC is a configurable multi-tenant Contract Lifecycle Management platform. It should communicate business outcomes — control, visibility, collections, obligations, approvals, renewal discipline and auditability — rather than present a list of technical modules without context.

## Unified design system
The same visual language must govern:
- public landing pages
- ESC WordPress/admin shell
- Flutter mobile app
- login/authentication surfaces
- branded email notifications
- reports/exports where branding applies
- empty/loading/error/success states

### Design tokens
Define and version:
- primary/secondary/accent palette
- semantic success/warning/error/info colors
- background/surface/text/border colors
- typography scale and weights
- spacing scale
- radius/shadow/elevation
- breakpoints
- iconography
- interaction/focus/disabled states

### Required behavior
- responsive desktop/tablet/mobile
- Arabic RTL and English LTR from day one
- accessibility: focus states, contrast, labels, keyboard behavior on web/admin where applicable
- Light/Dark support where selected for ESC surfaces
- consistent status vocabulary and status chips across admin/mobile/landing demos

## Landing information architecture
The ESC public site must include, as product maturity permits:
1. Hero and primary value proposition.
2. Why contract operations fail and what ESC changes.
3. Contract lifecycle overview.
4. Multi-tenant / organization management.
5. Contract types and industry templates.
6. Dynamic fields and configurable data model.
7. Workflows and approvals.
8. Obligations, milestones, renewal and notice management.
9. Financial tracking, payments/collections and multi-currency.
10. Documents and audit history.
11. Notifications and escalation.
12. Dashboards, reporting, search, import/export.
13. Mobile experience.
14. Security and access control.
15. Localization, timezone and white label.
16. API/webhooks/integrations.
17. Industries/use cases.
18. Plans/enterprise options only when commercially approved.
19. FAQ.
20. Request Demo / Contact.
21. Login entry point.

## Industry content targets
- Agriculture & Export: export contracts, suppliers, importers, shipments, payment schedules, document/expiry dates, multi-currency and obligations.
- Construction: main/subcontracts, variations, retention, milestones, guarantees and approvals.
- Real Estate: leases, sales, deposits, renewal/notice periods and recurring obligations.
- Trading/Procurement: supplier/vendor agreements, deliveries, purchase obligations and payments.
- Manufacturing: supply agreements, quality/delivery milestones and supplier obligations.
- Logistics: freight/warehouse/distribution agreements and delivery milestones.
- IT/SaaS: license, subscription, SLA, support, renewal and notice management.
- Consulting/Professional Services: statements of work, milestones, fees and approvals.
- Maintenance/Facilities: recurring services, SLA, renewals and compliance dates.
- Government: controlled workflows, audit evidence, permissions and approvals.
- General Business: NDA, service, supplier, customer and framework agreements.

## Feature registry linkage
Landing claims must be driven by a controlled feature lifecycle:
- Development
- Internal Preview
- Beta
- Public
- Deprecated

Only Public features are presented as generally available. Beta/preview features require explicit labeling. The registry also records plan entitlement, admin/mobile presence, configuration and documentation state.

## Cross-surface consistency rule
When a feature is added or changed, review all affected surfaces. Example: adding Contract Risk must consider schema, API, permissions, audit, forms, mobile, dashboard/filter/report use, workflow/approval conditions, notifications, feature plan, localization and landing messaging. A local field implementation alone is not complete.
