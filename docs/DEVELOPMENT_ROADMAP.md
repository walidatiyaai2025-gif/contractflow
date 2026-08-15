# SafeContracts — Development Roadmap

The execution baseline is **11 phases / 284 production tasks**. Each task uses an ID in the form `SC-P{phase}-{NNN}` and is represented by a GitHub Issue after plan sync.

| Phase | Scope | Planned Tasks | Primary outcome |
|---|---|---:|---|
| P0 | Foundation | 16 | Plugin/mobile foundations, environments, CI, coding standards, migrations framework |
| P1 | Master Data | 13 | Customers, payment methods, reference data, role/capability primitives |
| P2 | Contracts | 23 | Contract CRUD, assignment, financial lines, additions/discounts, attachments/history |
| P3 | Payments & Collections | 24 | Payment schedules, collections, mandatory payment methods, partial/full payment logic |
| P4 | Follow-up & Audit | 15 | Accountant follow-up workflow, status history, activity/audit trails |
| P5 | Notifications & Firebase | 26 | Rule engine, 10-day default reminder, role targeting, Firebase push, delivery logs |
| P6 | Admin UI & Reports | 40 | SafeContracts white-label WordPress shell, 13 WP screens, dashboards, filters, reports/Excel |
| P7 | Import | 17 | Excel upload, mapping, preview, validation, duplicate strategy, results/audit |
| P8 | REST API | 28 | Versioned mobile APIs, auth, scopes, filters, config, export endpoints, hardening |
| P9 | Mobile | 50 | 11 screens, role-aware dashboard, dependent filters, light edits, notifications, Excel download |
| P10 | Hardening & UAT | 32 | Security, performance, backup/recovery, test matrix, UAT, production release readiness |
| **Total** |  | **284** | Production V1 |

## Phase gates

### P0 — Foundation
- SafeContracts plugin bootstrap and lifecycle
- Custom-table migration/versioning framework
- Base roles/capability registration
- REST namespace foundation
- Mobile project structure
- CI/lint/test skeleton
- Secrets/environment conventions

### P1 — Master Data
- Customer/entity model
- Optional internal customer code
- Payment-method table and defaults: Cash, Bank Transfer, Wallet
- Active/inactive/order management
- Reference-data APIs
- Server-side access primitives

### P2 — Contracts
- Contract lifecycle and validation
- Customer/accountant assignment
- One-currency financial model
- Base value + financial lines + additions + discounts
- Reconciliation/net value
- Due-date mutability under capability control
- Notes/attachments/history

### P3 — Payments & Collections
- Scheduled receivable instalments
- Upcoming/Due Soon/Due/Overdue/Partially Paid/Paid lifecycle
- Expected payment date
- Partial/full collections
- Mandatory payment method
- Optional proof attachment
- Remaining-balance calculations and integrity checks

### P4 — Follow-up & Audit
- Accountant notes and operational follow-up states
- Promise-to-pay / issue / deferred-style tracking
- Material-change audit trail
- Assignment/status/value/date audit
- Export/import/settings audit hooks

### P5 — Notifications & Firebase
- Configurable rule engine in WordPress
- Default 10-days-before-due rule
- Accountant + Manager default recipients
- Due-day/overdue/repeat/escalation options
- Firebase configuration through SafeContracts settings
- Device tokens, push delivery, retry/failure logs

### P6 — Admin UI & Reports
- Full SafeContracts admin identity
- Hide irrelevant WordPress menus for operational roles
- SafeContracts login/header/footer/menu shell
- Role-aware dashboard
- Customers/contracts/payments/collections/follow-up/notifications/reports/settings screens
- Filters by customer/contract/accountant/status/date
- Server-generated Excel reports

### P7 — Import
- Import supplied Excel structure
- Column mapping and preview
- Validation and error handling
- Duplicate handling
- Import execution/report/audit

### P8 — REST API
- Versioned endpoints
- Authentication/session/device flows
- Capability + Accountant-scope enforcement
- Customer -> dependent contract filtering
- Dashboard KPIs and lists
- Contract/payment/follow-up/collection light-write endpoints
- Dynamic mobile config and reference data
- Server-side Excel export request/download

### P9 — Mobile
- Login/session
- Role-aware dashboard
- Customer dropdown
- Dependent contract dropdown with All Contracts
- Contract list/details
- Payment list/details
- Follow-up updates
- Collection entry
- Notifications/deep links
- Excel export/download/share flow
- Profile/session/device state

### P10 — Hardening & UAT
- Permission penetration tests
- Financial calculation regression suite
- API validation/security tests
- Performance/index review
- Notification reliability tests
- Import/export verification
- RTL/responsive/accessibility pass
- Backup/restore and migration testing
- UAT checklist and production release package

## Task status contract

The live table is generated from GitHub Issues:

- `open` + no assignee → **To Do**
- `open` + assignee → **In Progress**
- `closed` → **Done**

Plan sync ensures missing planned issue IDs are created. Status sync updates `docs/PROJECT_STATUS.md` from GitHub issue state.
