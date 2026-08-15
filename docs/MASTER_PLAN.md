# SafeContracts — Master Product & Functional Plan

**Baseline:** V1.2 approved scope  
**System name:** SafeContracts  
**Backend:** WordPress Custom Plugin  
**Client:** Mobile application  

## 1. Product goal

SafeContracts tracks advertising-company contracts with customers/entities and the financial instalments due to the company under those contracts. The primary objective is to prevent missed receivables by giving accountants and management a controlled workflow for contracts, payment schedules, collections, follow-up and notifications.

The default reminder is 10 days before a due date, while actual reminder timing, recipients, channels and rules are centrally configurable in WordPress.

## 2. Source-of-truth architecture

WordPress and the SafeContracts plugin are the **single source of truth** for:

- Customers/entities
- Contracts
- Contract financial lines, additions and discounts
- Scheduled payments/installments
- Collections and collection status
- Follow-up records
- Payment methods
- Users, roles, capabilities and data scope
- Notifications and notification rules
- Firebase/mobile push configuration
- Mobile application configuration
- Dynamic header/footer/text/content values exposed to mobile
- Reports and exports
- Audit history
- Import mapping

The mobile application consumes SafeContracts REST APIs. It must not own independent business rules or a competing business database.

## 3. User roles and access model

### System Administrator

Full system administration including system settings, users, capabilities, identity, Firebase, notification rules, reference data, imports and technical controls.

### Manager

Sees all customers, contracts, payments, collections and follow-up records. Can filter across the entire accessible portfolio. Manager can modify operational data according to granted capabilities.

### Accountant

Contract creation is a core Accountant function. Each Accountant sees only the contracts/payments assigned to them and operates within that server-enforced scope. Accountants update contract/payment status, follow-up, collections, expected dates and operational notes according to capabilities.

### Viewer

Read-only role within the data scope/capabilities granted to the user.

### Capability rule

Contract editing is not permanently tied to one role. It is an explicit capability that may be granted or removed from any role. Hiding a WordPress menu is only a UX measure; authorization and scope are enforced server-side.

## 4. Customer/entity management

Customer records include core identity/contact details plus an optional internal customer code. The code is not mandatory. Customers are used as the primary filter dimension in WordPress, reports and mobile dashboards.

Functions:

- Create/edit/archive customer
- Optional internal code
- Contact and notes fields
- View customer contracts
- Filter/report by customer
- Customer-level receivables summary

## 5. Contract management

A contract belongs to a customer/entity and is assigned to a responsible Accountant. Core functions:

- Contract create/read/update/archive
- Contract number/reference
- Customer relation
- Responsible Accountant
- Start/end dates
- Contract status
- Base value
- One system currency
- Financial line items
- Additions
- Discounts
- Net contractual value
- Notes and attachments
- Change history
- Ability to modify due dates and other contract data when the user has the required capability

The contract total does **not** have to equal a simple sum of scheduled payments because separate additions, discounts or financial line items may exist. The system must show a transparent reconciliation.

## 6. Payment schedule / instalments

Each contract can contain one or many scheduled receivable payments.

Each payment supports:

- Sequence/reference
- Contract relation
- Due date
- Original amount
- Current expected payment date when changed operationally
- Paid amount
- Remaining amount
- Status
- Responsible Accountant via contract scope
- Follow-up notes
- Collection records
- Notification state
- Audit history

Suggested lifecycle:

- Upcoming
- Due Soon
- Due
- Overdue
- Partially Paid
- Paid

Operational follow-up states may additionally represent contacted, promised payment, issue, deferred or similar business states without destroying the financial status.

## 7. Collections

A payment may be collected in full or partially. Every collection transaction stores:

- Amount
- Collection date
- **Payment method — mandatory**
- Reference/details
- Optional attachment/proof
- User who recorded it
- Created/updated timestamps

Attachment/proof is optional. Payment method is mandatory.

## 8. Payment methods

Payment methods are maintained in a WordPress-managed reference table rather than hard-coded in mobile.

Initial defaults:

- Cash
- Bank Transfer
- Wallet

Administrators can add, rename, order, activate or deactivate methods. Mobile reads the active list from WordPress.

## 9. Notifications

SafeContracts contains a complete WordPress notification-rules settings area.

Default business rule:

- Notify **Accountant + Manager** 10 days before a payment due date.

But administrators can configure:

- Days before due date
- Due-day notification
- After-due/overdue reminders
- Repetition/cadence
- Recipient roles
- Assigned-accountant targeting
- Channels
- Active/inactive rules
- Message templates
- Escalation behavior

Notification logic runs server-side and respects current payment status so settled items do not continue receiving irrelevant reminders.

## 10. Firebase and mobile push

Firebase/mobile push configuration is managed from WordPress SafeContracts settings. Secrets must be stored safely and never exposed unnecessarily to the mobile client.

Functions include:

- Firebase project/config settings
- Device-token registration
- Token revocation/refresh
- Role/scoped targeting
- Push templates
- Delivery logging
- Failure/retry visibility

## 11. WordPress administration experience

The plugin replaces the normal operational WordPress experience with a **SafeContracts white-label admin shell**.

Required identity controls:

- SafeContracts name/logo
- Login branding
- Primary/secondary colors
- Favicon/icon
- Admin header/footer identity
- Simplified navigation

Operational users should not see irrelevant WordPress menus such as Posts, Comments, Appearance, Plugins, Tools or other unrelated areas. System Administrator retains required technical/system access.

The visible core navigation is built around:

- Dashboard
- Customers
- Contracts
- Payments
- Collections
- Follow-up
- Notifications
- Reports
- Users/permissions where authorized
- SafeContracts Settings

## 12. Mobile application

Mobile is designed for monitoring and light operational changes. All data and configurable values originate from WordPress.

Core functions:

- Authentication/session
- Role-aware home/dashboard
- Customer filter
- Dependent contract filter
- All-contracts option
- Contract details
- Payment details
- Follow-up updates
- Collection entry
- Notification inbox/deep links
- Search/filter
- Excel export request/download
- Profile/session/device settings

## 13. Mobile dashboard and Excel export

The mobile dashboard is role/scoped.

Filter flow:

1. User selects customer from a dropdown.
2. WordPress API returns contracts available to that user for that customer.
3. User selects **All Contracts** or one specific contract.
4. KPIs, lists, overdue amounts, upcoming amounts and other dashboard values refresh from the server.

An Accountant can only see/export their assigned scope. A Manager can see all contracts and filter them.

Excel export must be generated by WordPress from the same server-side filters and authorization rules used by the dashboard. The mobile application requests and downloads the export; it must not reconstruct authoritative financial reports locally.

Export actions should be auditable.

## 14. Reports and dashboards

Dashboard/report dimensions include:

- Customer
- Contract
- Accountant
- Contract status
- Payment status
- Due-date range
- Collection-date range

Core KPIs include:

- Active contracts
- Total contractual/net value
- Upcoming receivables
- Due soon
- Due today
- Overdue
- Partially paid
- Collected in selected period
- Remaining receivables

Reports support Excel export and access control.

## 15. Audit and history

Financial/operational changes require traceability. Audit log should capture material changes such as:

- Contract create/update/status/date/value changes
- Payment amount/date/status changes
- Collection create/update/reversal where allowed
- Assignment changes
- Permission changes
- Notification-rule changes
- Export actions
- Import operations

Audit records store actor, timestamp, entity, action and before/after context where appropriate.

## 16. Import from Excel

The initial Excel import must support the fields present in the supplied business workbook and provide a mapping/validation workflow rather than assuming perfect input.

Functions:

- Upload workbook
- Column mapping
- Preview
- Validation
- Duplicate handling strategy
- Import execution
- Per-row error result
- Import summary
- Audit record

## 17. Mobile dynamic configuration

Administrators can manage appropriate mobile-facing configurable values through WordPress, including:

- Branding colors/logos where supported
- Header/footer text
- Support/contact text
- Select labels/content blocks
- Feature flags
- API-driven reference lists
- Notification configuration

UX layout and security-critical behavior remain application-controlled; WordPress configuration must not turn the native app into an unsafe arbitrary-rendering engine.

## 18. Data model direction

Use dedicated plugin database tables for high-volume/financial workflow data, with WordPress users/capabilities integrated for identity and authorization.

Expected logical entities:

- customers
- contracts
- contract_financial_items
- scheduled_payments
- collections
- payment_methods
- followups
- attachments/document references
- notification_rules
- notifications/delivery log
- device_tokens
- audit_log
- import_runs/import_errors
- mobile/system settings

Schema is created/upgraded by the plugin using versioned migrations.

## 19. API principles

- Versioned REST namespace
- Authentication/session policy
- Server-side authorization on every endpoint
- Server-side Accountant scope enforcement
- Validation/sanitization
- Pagination/filter/sort conventions
- Consistent error envelope
- Rate/abuse controls where needed
- No unnecessary secret exposure
- Idempotency/duplicate protection for sensitive writes where appropriate

## 20. Non-functional requirements

- Simple, professional WordPress settings UX
- RTL/Arabic-ready UI with English compatibility
- Responsive admin screens
- Mobile-first mobile UI
- Secure input validation/escaping
- Database indexes for reporting/filtering
- Backup-friendly data model
- Observable notification failures
- Production logging without secret leakage
- Automated tests for financial calculations, permissions and API scopes
- CI quality gates before merge

## 21. Screen baseline

V1 contains **24 logical screens**:

- WordPress: **13**
- Mobile: **11**

See `docs/SCREENS.md` for the maintained inventory.

## 22. Delivery baseline

Implementation is split into **11 phases / 284 production tasks**. GitHub Issues are the execution source of truth and `docs/PROJECT_STATUS.md` is regenerated from their live state.

See `docs/DEVELOPMENT_ROADMAP.md`.

## 23. Visual identity

Product name: **SafeContracts**.

Visual direction:

- Modern corporate / FinTech administration product
- Navy and blue primary system colors
- Green for positive/paid/tracked states
- Contract + clipboard + checkmark as core visual metaphor
- Clean cards, data tables and restrained status colors
- No cryptocurrency imagery
- No visible WordPress brand in the operational product shell

See `docs/design/BRAND_GUIDE.md`.
