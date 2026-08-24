# Alkenzy ADV Mobile UI Screen Matrix

Legend: `DONE` = implemented on this redesign branch; `BASE` = existing implementation inherited from the stacked premium branch but still requires locked-reference comparison; `TODO` = redesign/comparison remains; `N/A` = backend/app does not currently expose the state.

| ID | Feature | Existing screen/state | Route / entry point | Reference | Required redesign | Status | RTL | Responsive | Empty | Loading | Error | Screenshot | QA |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| AUTH-01 | Welcome | `PremiumCompactWelcomeScreen` | app entry before sign-in | REF_01 | cream onboarding composition + feature tiles + CTA | DONE | pending | pending | N/A | yes | N/A | pending | pending |
| AUTH-02 | Login | `SafeContractsLoginScreen` | welcome → sign in | REF_01 | compact premium login | BASE | pending | pending | N/A | yes | yes | pending | pending |
| AUTH-03 | Bootstrap | blocking bootstrap splash | post-login | REF_01 | branded splash/loading | BASE | pending | pending | N/A | yes | yes | pending | pending |
| AUTH-04 | Session | expired/auth error states | API/session controller | REF_01 | premium recovery state | TODO | pending | pending | N/A | N/A | required | pending | pending |
| DASH-01 | Dashboard | `DashboardContextScreen` / dashboard | primary nav | REF_02 | executive KPI hierarchy, filters, activity | BASE | pending | pending | required | required | required | pending | pending |
| DASH-02 | Dashboard 2 | `DashboardTwoScreen` | drawer/more | REF_02 | alternate executive dashboard | BASE | pending | pending | required | required | required | pending | pending |
| DASH-03 | Filters | year/month filter states | dashboard | REF_02 | year-only, month+year, reset, visible state | BASE | pending | pending | N/A | N/A | N/A | pending | pending |
| NAV-01 | Shell | `SafeContractsShell` | authenticated root | REF_02 | locked bottom navigation hierarchy | BASE | pending | pending | N/A | N/A | N/A | pending | pending |
| NAV-02 | Drawer | authenticated drawer | hamburger | REF_02 | navy premium drawer | BASE | pending | pending | N/A | N/A | N/A | pending | pending |
| NAV-03 | Quick Add | `MobileQuickAddScreen` | central add action | REF_02 | compact premium bottom sheet/actions | BASE | pending | pending | permission-aware | N/A | N/A | pending | pending |
| CUS-01 | Customers | `CustomersScreen` list/search/filter | customers nav | REF_03 | compact business cards | BASE | pending | pending | required | required | required | pending | pending |
| CUS-02 | Customer detail | customer detail state within feature | customer tap | REF_03 | financial summary, contracts, communication | BASE | pending | pending | required | required | required | pending | pending |
| CUS-03 | Customer add/edit | customer form | quick add/detail | REF_03 | reference form system | BASE | pending | pending | N/A | N/A | required | pending | pending |
| SUP-01 | Suppliers | `SuppliersScreen` list/search/filter | suppliers nav | REF_03 | shared entity family + payable distinction | BASE | pending | pending | required | required | required | pending | pending |
| SUP-02 | Supplier detail | supplier detail state | supplier tap | REF_03 | payable summary, contracts, actions | BASE | pending | pending | required | required | required | pending | pending |
| SUP-03 | Supplier add/edit | supplier form | quick add/detail | REF_03 | reference form system | BASE | pending | pending | N/A | N/A | required | pending | pending |
| CON-01 | Contracts | `ContractsScreen` | contracts nav | REF_04 | image-led compact contract cards + filters | BASE | pending | pending | required | required | required | pending | pending |
| CON-02 | Contract details | `PremiumContractDetailsScreen` / details | contract tap | REF_04 | hero image, finance summary, progress, tabs | BASE | pending | pending | required | required | required | pending | pending |
| CON-03 | Contract create/edit | `ContractEditScreen` | quick add/detail | REF_04 | reference form + image upload | BASE | pending | pending | N/A | N/A | required | pending | pending |
| CON-04 | Contract summary tab | details tab | contract details | REF_04 | real system summary | BASE | pending | pending | required | required | required | pending | pending |
| CON-05 | Contract payments tab | details tab | contract details | REF_04 | payment schedule/status chips | BASE | pending | pending | required | required | required | pending | pending |
| CON-06 | Contract attachments tab | details tab | contract details | REF_04 | attachment rows/actions | BASE | pending | pending | required | required | required | pending | pending |
| PAY-01 | Payments | `PaymentsScreen` | payments nav | REF_05 | due/paid/overdue tabs + compact rows | BASE | pending | pending | required | required | required | pending | pending |
| PAY-02 | Add payment | payment flow | quick add/payments | REF_05 | supplier/customer mode + reference form | BASE | pending | pending | N/A | N/A | required | pending | pending |
| PAY-03 | Collections | collection/payment mode | payments/finance | REF_05 | receivable collection flow | BASE | pending | pending | required | required | required | pending | pending |
| FIN-01 | Finance | `FinanceScreen` | finance destination | REF_05 | cash-flow/receivable/payable executive view | BASE | pending | pending | required | required | required | pending | pending |
| FUP-01 | Follow-ups | `FollowupsScreen` | follow-ups destination | REF_05 | urgency filters/actions | BASE | pending | pending | required | required | required | pending | pending |
| NOT-01 | Notification center | `NotificationsScreen` | notifications | REF_06 | urgency groups, read/unread hierarchy | BASE | pending | pending | required | required | required | pending | pending |
| NOT-02 | Notification detail | deep-link/detail state | notification tap | REF_06 | compact warning/detail/action layout | BASE | pending | pending | N/A | required | required | pending | pending |
| EXP-01 | Export | `MobileExcelExportScreen` | reports/export | REF_06 | date range, formats, options, feedback | BASE | pending | pending | N/A | required | required | pending | pending |
| PRO-01 | Profile | `ProfileScreen` | profile/more | REF_06 | avatar/company/permissions/shortcuts | BASE | pending | pending | required | required | required | pending | pending |
| SET-01 | Settings | settings/account states | more/profile | REF_06 | language, notifications, email, support | TODO | pending | pending | N/A | N/A | required | pending | pending |
| HLP-01 | Help | `MobileUserGuideScreen` | help | REF_06 | same visual family, no legacy styling | BASE | pending | pending | required | required | required | pending | pending |

## Completion rule

No row may be marked QA complete until real API/business integration is preserved, Arabic RTL + English are verified, practical mobile widths are checked, non-happy states are designed where applicable, a screenshot is captured, and the screenshot is compared to the mapped locked reference.
