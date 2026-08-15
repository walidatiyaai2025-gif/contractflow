# SafeContracts — Screen Inventory

V1 baseline: **24 logical screens** — 13 WordPress + 11 Mobile.

## WordPress / SafeContracts Admin — 13 screens

| # | Screen | Main purpose |
|---:|---|---|
| W01 | SafeContracts Dashboard | Role-aware KPIs, due/overdue/collections overview, global filters and quick actions |
| W02 | Customers | Customer list, search, filters, create/edit/archive and customer summaries |
| W03 | Customer Details | Customer profile, optional code, contacts, contracts and receivables summary |
| W04 | Contracts | Contract list with customer/accountant/status/date filters and create action by capability |
| W05 | Contract Editor / Details | Contract financial model, dates, assignment, additions/discounts, notes, attachments and history |
| W06 | Payments | Scheduled instalments list, status/due filters and bulk operational visibility |
| W07 | Payment Details | Due/expected dates, amount, paid/remaining, collections, follow-up and history |
| W08 | Collections | Collection ledger, mandatory payment method, filters, attachment visibility and reconciliation |
| W09 | Follow-up | Accountant work queue, promises/issues/deferred notes and assigned-contract follow-up |
| W10 | Notifications | Notification inbox/delivery view and operational notification visibility |
| W11 | Reports & Excel | Customer/contract/accountant/status/date reporting and server-generated Excel exports |
| W12 | Users, Roles & Capabilities | User assignment, four roles, capability grants and data-scope administration |
| W13 | SafeContracts Settings | Identity, payment methods, notification rules, Firebase, mobile config, imports and system settings sections |

### WordPress admin shell

The SafeContracts white-label shell is applied across these screens and is **not counted as an additional logical screen**. It replaces the normal operational WordPress dashboard/navigation experience, hides irrelevant menus for operational roles, and applies SafeContracts identity to login/admin/header/footer/menu surfaces.

## Mobile — 11 screens

| # | Screen | Main purpose |
|---:|---|---|
| M01 | Login / Session | Authentication and session bootstrap from WordPress |
| M02 | Dashboard | Role-scoped KPIs, client dropdown, dependent contract dropdown, all/single contract filter and Excel export |
| M03 | Customers | Accessible customer list/search according to server scope |
| M04 | Contracts | Accessible contracts list with filters/search |
| M05 | Contract Details | Contract summary, financial lines, payment schedule, status and permitted light edits |
| M06 | Payments | Payment work list with due/status/search filters |
| M07 | Payment Details | Financial status, due/expected dates, collections, follow-up and permitted updates |
| M08 | Add Collection | Amount, mandatory payment method, date, reference and optional proof attachment |
| M09 | Follow-up | Add/update operational follow-up notes/states for accessible assigned work |
| M10 | Notifications | Push/in-app notification history and deep links to relevant contract/payment |
| M11 | Profile / Settings | User/session/device information and server-delivered app configuration/support details |

## Mobile Dashboard behavior

1. API returns customers visible to the current user.
2. User selects a customer or All Customers when permitted.
3. Contract dropdown is refreshed from WordPress for that customer and user scope.
4. User selects All Contracts or one contract.
5. Dashboard KPI cards and lists refresh using the same server filter context.
6. Excel export requests the authoritative filtered export from WordPress.

An Accountant never gains broader visibility because of a client-side dropdown; scope is enforced in every backend query/API endpoint.
