# ALKENZY ADV — PLUGIN UI SCREEN MATRIX

> Frozen governance baseline. Every row has exactly one implementation owner and exactly one controlling locked reference. All list/create/edit/detail/tab/modal/empty/error states rendered by a row's route inherit that row's owner unless a new independent `admin.php?page=` route is introduced and added here first.

**Official implementation foundation:** `main@f671f436d9fd357de1a79089c29ec700d0572e78` on 2026-08-24.

**Current screen count:** 34 logical WordPress Admin screens/states: registered plugin pages, the eight routable grouped-navigation landing states, one conditional migration-recovery page, and the existing `EmailSettingsPage`. `DashboardPage.php` and `DashboardV2Page.php` are implementation variants of the single Dashboard route, not separate screens.

| ID | Screen | Page Slug / Route | PHP Class / Callback | Route Status | Agent Owner | Reference ID | Implementation Status | Visual QA | RTL | Responsive | Functional QA | Screenshot | PR | Approved |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| SC-001 | Dashboard | `safecontracts` | `AdminShell::render() → DashboardV2Page::renderContent()` | REGISTERED | **LEAD** | REF_008 | IN PROGRESS | NO | NO | NO | NO | — | #652 | NO |
| SC-002 | Navigation Group — Parties & Contracts | `safecontracts&safecontracts_group=contracts` | `AdminShell::render() → AdminNavigationGroups::renderRequestedGroup()` | REGISTERED | **LEAD** | REF_002 | IMPLEMENTED | NO | NO | NO | NO | — | #637 | NO |
| SC-003 | Navigation Group — Finance | `safecontracts&safecontracts_group=finance` | `AdminShell::render() → AdminNavigationGroups::renderRequestedGroup()` | REGISTERED | **LEAD** | REF_002 | IMPLEMENTED | NO | NO | NO | NO | — | #637 | NO |
| SC-004 | Navigation Group — Operations | `safecontracts&safecontracts_group=operations` | `AdminShell::render() → AdminNavigationGroups::renderRequestedGroup()` | REGISTERED | **LEAD** | REF_002 | IMPLEMENTED | NO | NO | NO | NO | — | #637 | NO |
| SC-005 | Navigation Group — Notifications | `safecontracts&safecontracts_group=notifications` | `AdminShell::render() → AdminNavigationGroups::renderRequestedGroup()` | REGISTERED | **LEAD** | REF_002 | IMPLEMENTED | NO | NO | NO | NO | — | #637 | NO |
| SC-006 | Navigation Group — Users & Access | `safecontracts&safecontracts_group=access` | `AdminShell::render() → AdminNavigationGroups::renderRequestedGroup()` | REGISTERED | **LEAD** | REF_002 | IMPLEMENTED | NO | NO | NO | NO | — | #637 | NO |
| SC-007 | Navigation Group — Settings & Integrations | `safecontracts&safecontracts_group=system` | `AdminShell::render() → AdminNavigationGroups::renderRequestedGroup()` | REGISTERED | **LEAD** | REF_002 | IMPLEMENTED | NO | NO | NO | NO | — | #637 | NO |
| SC-008 | Navigation Group — User Guide | `safecontracts&safecontracts_group=help` | `AdminShell::render() → AdminNavigationGroups::renderRequestedGroup()` | REGISTERED | **LEAD** | REF_002 | IMPLEMENTED | NO | NO | NO | NO | — | #637 | NO |
| SC-009 | Navigation Group — More / Future Fallback | `safecontracts&safecontracts_group=other` | `AdminShell::render() → AdminNavigationGroups::renderRequestedGroup()` | REGISTERED | **LEAD** | REF_002 | IMPLEMENTED | NO | NO | NO | NO | — | #637 | NO |
| SC-010 | General Settings | `safecontracts-settings` | `GeneralSettingsPage::render()` | REGISTERED | **LEAD** | REF_002 | IMPLEMENTED | NO | NO | NO | NO | — | #637 | NO |
| SC-011 | Runtime Inspector | `safecontracts-runtime-inspector` | `RuntimeInspectorPage::render()` | REGISTERED | **LEAD** | REF_002 | IMPLEMENTED | NO | NO | NO | NO | — | #637 | NO |
| SC-012 | Migration Recovery | `safecontracts-migration-recovery` | `MigrationRecoveryPage::render()` | CONDITIONAL_ON_MIGRATION_FAILURE | **LEAD** | REF_002 | IMPLEMENTED | NO | NO | NO | NO | — | #637 | NO |
| SC-013 | Customers | `safecontracts-customers` | `CustomersPage::render()` | REGISTERED | **WORKER-1** | REF_004 | NOT STARTED | NO | NO | NO | NO | — | — | NO |
| SC-014 | Suppliers | `safecontracts-suppliers` | `SuppliersPage::render()` | REGISTERED | **WORKER-1** | REF_001 | NOT STARTED | NO | NO | NO | NO | — | — | NO |
| SC-015 | Contracts | `safecontracts-contracts` | `ContractsPage::render()` | REGISTERED | **WORKER-1** | REF_001 | NOT STARTED | NO | NO | NO | NO | — | — | NO |
| SC-016 | Archive | `safecontracts-archive` | `ArchivePage::render()` | REGISTERED | **WORKER-1** | REF_002 | NOT STARTED | NO | NO | NO | NO | — | — | NO |
| SC-017 | Payments | `safecontracts-payments` | `PaymentsPage::render()` | REGISTERED | **WORKER-2** | REF_005 | NOT STARTED | NO | NO | NO | NO | — | — | NO |
| SC-018 | Collections / Settlements | `safecontracts-collections` | `CollectionsPage::render()` | REGISTERED | **WORKER-2** | REF_005 | NOT STARTED | NO | NO | NO | NO | — | — | NO |
| SC-019 | Follow-ups | `safecontracts-followups` | `FollowUpsPage::render()` | REGISTERED | **WORKER-2** | REF_001 | NOT STARTED | NO | NO | NO | NO | — | — | NO |
| SC-020 | Finance | `safecontracts-finance` | `FinancePage::render()` | REGISTERED | **WORKER-2** | REF_001 | NOT STARTED | NO | NO | NO | NO | — | — | NO |
| SC-021 | Reports | `safecontracts-reports` | `ReportsPage::render()` | REGISTERED | **WORKER-2** | REF_001 | NOT STARTED | NO | NO | NO | NO | — | — | NO |
| SC-022 | Imports | `safecontracts-imports` | `ImportsPage::render()` | REGISTERED | **WORKER-2** | REF_002 | NOT STARTED | NO | NO | NO | NO | — | — | NO |
| SC-023 | Payment Methods | `safecontracts-payment-methods` | `PaymentMethodsPage::render()` | REGISTERED | **WORKER-2** | REF_005 | NOT STARTED | NO | NO | NO | NO | — | — | NO |
| SC-024 | Notification Center | `safecontracts-notification-center` | `NotificationCenterPage::render()` | REGISTERED | **WORKER-3** | REF_001 | NOT STARTED | NO | NO | NO | NO | — | — | NO |
| SC-025 | Notification Delivery Activity | `safecontracts-notifications` | `NotificationsPage::render()` | REGISTERED | **WORKER-3** | REF_006 | NOT STARTED | NO | NO | NO | NO | — | — | NO |
| SC-026 | Notification Schedule | `safecontracts-notification-schedule` | `NotificationSchedulePage::render()` | REGISTERED | **WORKER-3** | REF_006 | NOT STARTED | NO | NO | NO | NO | — | — | NO |
| SC-027 | Notification Settings | `safecontracts-notification-settings` | `NotificationSettingsPage::render()` | REGISTERED | **WORKER-3** | REF_006 | NOT STARTED | NO | NO | NO | NO | — | — | NO |
| SC-028 | Email Settings | `safecontracts-email-settings` | `EmailSettingsPage::render()` | REGISTERED_BY_LEAD_SHARED_BOOT | **WORKER-3** | REF_006 | NOT STARTED | NO | NO | NO | NO | — | — | NO |
| SC-029 | Active Users | `safecontracts-active-users` | `ActiveUsersPage::render()` | REGISTERED | **WORKER-3** | REF_007 | NOT STARTED | NO | NO | NO | NO | — | — | NO |
| SC-030 | Users & Roles | `safecontracts-users-roles` | `UsersRolesPage::render()` | REGISTERED | **WORKER-3** | REF_007 | NOT STARTED | NO | NO | NO | NO | — | — | NO |
| SC-031 | Firebase Settings | `safecontracts-firebase-settings` | `FirebaseSettingsPage::render()` | REGISTERED | **WORKER-3** | REF_002 | NOT STARTED | NO | NO | NO | NO | — | — | NO |
| SC-032 | Mobile Configuration | `safecontracts-mobile-configuration` | `MobileConfigurationPage::render()` | REGISTERED | **WORKER-3** | REF_002 | NOT STARTED | NO | NO | NO | NO | — | — | NO |
| SC-033 | Translations | `safecontracts-translations` | `TranslationsPage::render()` | REGISTERED | **WORKER-3** | REF_002 | NOT STARTED | NO | NO | NO | NO | — | — | NO |
| SC-034 | User Guide | `safecontracts-user-guide` | `UserGuidePage::render()` | REGISTERED | **WORKER-3** | REF_002 | NOT STARTED | NO | NO | NO | NO | — | — | NO |

## Frozen ownership totals

- **TOTAL SCREENS:** 34
- **LEAD:** 12
- **WORKER-1:** 4
- **WORKER-2:** 7
- **WORKER-3:** 11
- **UNASSIGNED:** 0
- **OVERLAPPING OWNERSHIP:** 0

## State inheritance rule

A worker who owns a route owns every user-visible state rendered inside that route, including list, create, edit, detail, tabs, dialogs, bulk actions, confirmation states, filters, pagination, loading, empty, success and error states. A sub-state is **not** a new owner boundary unless it receives an independent WordPress admin route and is first added to this matrix by the Lead.

`SC-028 Email Settings` remains WORKER-3 visual scope. The LEAD registered the pre-existing `EmailSettingsPage::register()` contract through protected shared `Plugin.php`; this route-boot integration does not transfer screen ownership.

## Status rules

- `NOT STARTED` — implementation untouched under this redesign baseline.
- `IN PROGRESS` — the exact worker branch/PR is recorded.
- `IMPLEMENTED` — visual implementation exists and real business behavior remains intact, but runtime visual acceptance may still be pending.
- `VISUAL QA` — real WordPress screenshots exist and comparison is underway.
- `READY FOR LEAD` — functional, RTL, responsive and visual QA all pass.
- `APPROVED` — Lead accepted runtime evidence against the locked reference.

No screen may be `APPROVED` without a locked Reference ID, a real WordPress runtime screenshot set and the acceptance evidence required by `PLUGIN_REDESIGN_EXECUTION_PLAN.md`.
