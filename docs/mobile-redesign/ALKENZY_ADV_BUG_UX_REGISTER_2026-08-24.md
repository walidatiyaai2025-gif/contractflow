# ALKENZY ADV — Consolidated QA & UX Bug Register

**Canonical operational register — 24 August 2026**

This Markdown file is the repository-authoritative transcription of the supplied QA document `ALKENZY_ADV_Bug_UX_Register_2026-08-24(1).pdf` for the current ALKENZY ADV mobile closure pass.

- Total: **101** items.
- Priority distribution: **P0 = 5, P1 = 71, P2 = 25**.
- Latest approved design direction wins when it conflicts with an older suggestion.
- P0 = data correctness / real execution blocker.
- P1 = major functional or UX issue that must close before release.
- P2 = polish / accessibility / consistency.

## Mandatory acceptance rule

A functional item MUST NOT be closed with a visual-only patch. Closure requires proof of the real path from UI through service/API/data, plus Arabic RTL, English LTR, responsive behavior, Loading/Empty/Error/Retry as applicable, automated tests, and screenshot evidence.

## Authoritative bug list

| ID | Priority | Area | Required closure | Reference |
|---|---|---|---|---|
| B001 | P0 | Payments | Fix payment status so overdue/due/upcoming/paid comes from one authoritative engine and matches actual due date and amounts. A payment due 2026-08-01 must not remain Upcoming after it is overdue. | REF-PAYDET-01 |
| B002 | P0 | Customers | Trace and fix the real customer-related business-data loading failure across mobile API/plugin permissions mapping; do not hide the error. | REF-CUST-01 |
| B003 | P0 | Navigation | Fix Hamburger/Sidebar so the Drawer always renders the real capability-authorized navigation destinations instead of an almost-empty area. | REF-SIDEBAR-01 |
| B004 | P1 | Payment Details | Fix Payment Details title/back-arrow contrast by using the approved AppBar treatment consistently. | REF-PAYDET-01 |
| B005 | P1 | Customers | Provide correct Loading/Empty/Error states and a visible Retry that performs a real new request. | REF-CUST-01 |
| B006 | P1 | Landing | Fix PageView/carousel indicators so they track real page changes, or remove the indicator when content is static. | REF-LANDING-01 |
| B007 | P1 | RTL / Phone | Use bidi isolation/LTR phone formatting so phone numbers render correctly inside Arabic RTL UI. | REF-CUST-01 |
| B008 | P1 | Localization | Remove hard-coded English strings such as Edit from Arabic UI and complete Arabic/English localization. | REF-CUST-01 |
| B009 | P2 | Responsive UI | Prevent important titles/text from being clipped or replaced by ellipsis where the information is required. | REF-DASH-01 |
| B010 | P2 | Bottom Navigation | Reduce nav text/icon size and spacing so labels do not crowd or clip on small screens. | REF-DASH-01 |
| B011 | P1 | Loading UX | Reduce long skeleton/blank transitions; prevent duplicate requests, use caching only where justified, and standardize loading states. | REF-PAY-SEQ |
| B012 | P1 | Dashboard | Replace the old crowded banner/large-card composition with the approved compact Summary + KPI row + compact filters layout. | REF-DASH-01 |
| B013 | P1 | Sidebar | Raise text/icon contrast for normal/selected/disabled states, targeting WCAG AA where practical. | REF-SIDEBAR-01 |
| B014 | P1 | Dashboard Layout | Recompose the dashboard so core summary data fits in the viewport with minimal vertical scrolling. | REF-DASH-COMPACT |
| B015 | P1 | Dashboard Summary | Remove wasteful explanatory copy; keep the donut/chart and Total Account Balance/value together in one compact Summary card. | REF-DASH-LATEST |
| B016 | P1 | KPI Cards | Show Total/Scheduled/Collected/Remaining as four compact KPI cards in one row. | REF-DASH-LATEST |
| B017 | P1 | Quick Filters | Replace multi-line quick-filter chips with a compact dropdown. | REF-DASH-COMPACT |
| B018 | P1 | Payment Filters | Move repeated payment filters into an on-demand dropdown/bottom sheet instead of permanently lengthening the page. | REF-DASH-COMPACT |
| B019 | P1 | Typography | Reduce Dashboard Heading/KPI/Labels/Filters/Nav typography using a consistent scale. | REF-DASH-01 |
| B020 | P1 | Hero Title | Reduce/reflow Financial performance title so it does not clip or ellipsize incorrectly. | REF-DASH-01 |
| B021 | P2 | Hero Copy | Remove or drastically shorten nonessential hero explanatory text. | REF-DASH-01 |
| B022 | P1 | Horizontal Overflow | Prevent clipped/overflowing controls at every supported width. | REF-DASH-01 |
| B023 | P1 | Information Density | Reduce large gaps/padding while preserving touch targets and readability. | REF-DASH-01 |
| B024 | P2 | KPI Cards | Put each KPI icon beside the amount/currency and materially reduce card height. | REF-DASH-LATEST |
| B025 | P2 | Bottom Navigation | Reduce Bottom Nav label/icon scale and normalize spacing. | REF-DASH-01 |
| B026 | P2 | FAB | Reduce the centered + FAB and position it correctly relative to Bottom Navigation. | REF-DASH-01 |
| B027 | P1 | Filter Hierarchy | Show one primary filter level and expose advanced filters only on demand. | REF-DASH-COMPACT |
| B028 | P1 | Viewport | Use viewport-aware responsive layout rather than fixed dimensions. | REF-DASH-01 |
| B029 | P2 | Visual Balance | Keep the top Summary compact: chart + balance, without excess copy. | REF-DASH-LATEST |
| B030 | P1 | Dashboard UX | Put KPI row + Summary + primary filter above the fold where practical. | REF-DASH-COMPACT |
| B031 | P1 | Profile UI | Rebuild Profile as a simple focused Premium Profile rather than a long Settings page. | REF-PROFILE-01 |
| B032 | P1 | Profile Layout | Recompose core profile elements to fit without vertical scrolling on the reference device. | REF-PROFILE-TARGET |
| B033 | P1 | Profile | Remove Account Information from the visible Profile UI. | REF-PROFILE-01 |
| B034 | P1 | Profile | Remove History/Session History from the visible Profile UI. | REF-PROFILE-01 |
| B035 | P1 | Session Action | Replace Remove/Delete Session concepts with an end-user Log out action. | REF-PROFILE-TARGET |
| B036 | P0 | Authentication | Logout must execute the real session/token termination path, clear local auth state, and return to Login; UI-only logout is forbidden. | REF-PROFILE-TARGET |
| B037 | P1 | Profile | Remove Currency from Profile. | REF-PROFILE-01 |
| B038 | P1 | Language | Replace large language dropdown with compact Arabic \| English segmented toggle/slider. | REF-PROFILE-TARGET |
| B039 | P1 | Runtime Localization | Apply language immediately with correct RTL/LTR and persist choice after reopening the app. | REF-PROFILE-TARGET |
| B040 | P2 | User Guide | Remove the oversized top User Guide card. | REF-PROFILE-01 |
| B041 | P2 | User Guide | Keep User Guide as a small secondary icon/action near the end of Profile. | REF-PROFILE-TARGET |
| B042 | P1 | Profile Hero | Reduce Profile hero while keeping only avatar/image, name, and understandable account description. | REF-PROFILE-TARGET |
| B043 | P2 | Permissions | Hide/simplify technical enabled-permission counts unless directly useful to the user. | REF-PROFILE-01 |
| B044 | P2 | Scope | Hide or replace technical “Full scope” wording with understandable account-type language where needed. | REF-PROFILE-01 |
| B045 | P2 | Session Status | Remove redundant “Active session” status from the hero. | REF-PROFILE-01 |
| B046 | P1 | Profile Typography | Apply the compact typography scale to My profile/Preferences and the whole page. | REF-PROFILE-01 |
| B047 | P2 | Profile Spacing | Reduce vertical spacing/card padding while preserving clarity. | REF-PROFILE-TARGET |
| B048 | P1 | Profile + Bottom Nav | Reserve correct SafeArea so content does not collide with Bottom Nav/FAB. | REF-PROFILE-01 |
| B049 | P1 | Profile Responsive | Make Profile adapt to short and narrow devices. | REF-PROFILE-TARGET |
| B050 | P1 | Premium Visual System | Normalize spacing/typography/icons/shadows/radius and approved colors. | REF-PROFILE-TARGET |
| B051 | P2 | Avatar | Support a real avatar with approved logo fallback when no image exists. | REF-PROFILE-TARGET |
| B052 | P1 | Visual Noise | Focus Profile on identity + Language + Logout + User Guide; remove unnecessary technical noise. | REF-PROFILE-TARGET |
| B053 | P1 | Profile Interaction | Do not add content carousel/slides in Profile; the language segmented toggle is the only allowed segmented interaction. | REF-PROFILE-TARGET |
| B054 | P1 | No-scroll Acceptance | Core Profile content must fit on the reference device without vertical scrolling. | REF-PROFILE-TARGET |
| B055 | P1 | Global Typography | Create a centralized typography scale and reduce app-wide text sizes. | REF-PAYDET-01 |
| B056 | P1 | Section Titles | Reduce oversized section headings such as Due information while keeping clear hierarchy. | REF-PAYDET-01 |
| B057 | P1 | Hero Title | Reduce payment hero title font size and line height. | REF-PAYDET-01 |
| B058 | P1 | KPI Amount | Reduce oversized primary amount text while keeping it more prominent than labels. | REF-PAYDET-01 |
| B059 | P2 | Labels | Reduce Expected payment date / Contractual due date label sizes. | REF-PAYDET-01 |
| B060 | P2 | Values | Use a consistent medium size for dates and detail values. | REF-PAYDET-01 |
| B061 | P1 | Vertical Density | Reduce font size + line height + vertical padding together so rows are not excessively tall. | REF-PAYDET-01 |
| B062 | P1 | App-wide Scroll | Re-measure screens after typography reduction and remove unnecessary scrolling created by oversized type. | REF-PAYDET-01 |
| B063 | P1 | Typography Consistency | Centralize display/title/section/body/caption/KPI tokens across Dashboard/Profile/Payments/Customers. | REF-PAYDET-01 |
| B064 | P2 | Arabic/English Metrics | Balance font metrics and weights between Arabic and English. | REF-PAYDET-01 |
| B065 | P2 | Currency Typography | Make the currency symbol smaller and visually aligned with the amount. | REF-PAYDET-01 |
| B066 | P1 | Responsive Text | Use bounded responsive typography min/max values so text works on small devices. | REF-PAYDET-01 |
| B067 | P2 | AppBar Typography | Reduce and globally normalize AppBar title typography. | REF-PAYDET-01 |
| B068 | P1 | Detail Card | Convert very tall Due information content into compact readable/tappable rows. | REF-PAYDET-01 |
| B069 | P2 | Supporting Copy | Reduce/shorten supporting server-authoritative copy so it does not compete with primary content. | REF-PAYDET-01 |
| B070 | P1 | Design Tokens | Fix Global TextTheme/Design Tokens first, then use screen-specific exceptions only where justified. | REF-PAYDET-01 |
| B071 | P1 | Dashboard IA | Use Tabs to separate information instead of stacking every data type in one view. | REF-DASH-TABS |
| B072 | P1 | Dashboard Tabs | Add real Overview / Payments / Contracts / Collections tabs backed by actual data. | REF-DASH-TABS |
| B073 | P1 | Tab Content | Render only the active tab’s data; use a data slide/carousel only where needed to avoid excessive page length. | REF-DASH-TABS |
| B074 | P1 | Tab State | Preserve selected filters/date range/loaded state while switching tabs. | REF-DASH-TABS |
| B075 | P2 | Tab Count | Keep 3–4 primary tabs or use a scrolling tab bar only when necessary. | REF-DASH-TABS |
| B076 | P1 | Filter Scope | Year/month are global; tab-specific filters belong inside the relevant tab. | REF-DASH-TABS |
| B077 | P1 | Chart Placement | Keep one financial chart/trend in Overview or one financial tab; do not duplicate it. | REF-DASH-TABS |
| B078 | P2 | Tab Indicator | Selected tab must have strong contrast and unselected state must remain clear. | REF-DASH-TABS |
| B079 | P1 | Responsive Tabs | Use short labels, smaller font, and responsive layout so tab titles do not clip. | REF-DASH-TABS |
| B080 | P1 | RTL Tabs | Support correct Arabic RTL order and swipe direction. | REF-DASH-TABS |
| B081 | P1 | Global Pagination | Create one reusable Pagination component for the app. | REF-CONTRACTS-01 |
| B082 | P1 | Pagination Layout | Show Previous \| page indicator \| Next in one compact row. | REF-CONTRACTS-01 |
| B083 | P1 | Pagination State | Previous disabled only on first page; Next disabled only on last page; states must be visually clear. | REF-CONTRACTS-01 |
| B084 | P0 | Pagination Logic | Connect page/pageSize/totalPages to the backend and verify returned records; UI-only pagination is forbidden. | REF-CONTRACTS-01 |
| B085 | P1 | Pagination Consistency | Reuse the same pagination behavior across Contracts/Customers/Suppliers/Payments/Notifications and other lists. | REF-CONTRACTS-01 |
| B086 | P2 | Pagination Size | Reduce pagination button height/font/padding. | REF-CONTRACTS-01 |
| B087 | P2 | Page Counter | Prefer a clear `1 / N` hierarchy with result total as secondary information. | REF-CONTRACTS-01 |
| B088 | P1 | Single Page | Hide pagination when totalPages = 1, or show only a small indicator. | REF-CONTRACTS-01 |
| B089 | P1 | Pagination Loading | Disable pagination controls during loading and prevent double requests. | REF-CONTRACTS-01 |
| B090 | P1 | List State | Preserve Page + Search + Filters + Sort when returning from details. | REF-CONTRACTS-01 |
| B091 | P1 | Quick Filters | Replace multi-row quick-filter chips with one dropdown. | REF-CONTRACTS-01 |
| B092 | P1 | Filter + Sort | Place Quick Filter dropdown beside Sort in the same compact row. | REF-CONTRACTS-01 |
| B093 | P1 | Contract Type Filter | Move All/Customers/Suppliers from three chips to a compact dropdown/filter. | REF-CONTRACTS-01 |
| B094 | P1 | Status Filter | Move All statuses/Draft/Active/Completed/Cancelled to a dropdown. | REF-CONTRACTS-01 |
| B095 | P1 | Filter Density | Compress Search + Filter + Sort into a small toolbar so filters do not dominate the results area. | REF-CONTRACTS-01 |
| B096 | P1 | Filter State | Treat Search/Filter/Sort/Pagination as one coherent state so one action does not silently reset another. | REF-CONTRACTS-01 |
| B097 | P1 | Reset vs Refresh | Separate data Refresh from filter Clear/Reset and label them unambiguously. | REF-CONTRACTS-01 |
| B098 | P1 | Applied Filter | Show the selected filter value inside the dropdown/button. | REF-CONTRACTS-01 |
| B099 | P2 | Filter Badge | Show `Filters (N)` when advanced filters are active. | REF-CONTRACTS-01 |
| B100 | P1 | Global List Toolbar | Create a reusable Search \| Filter \| Sort list toolbar for all applicable lists. | REF-CONTRACTS-01 |
| B101 | P1 | Contracts Tabs | Activate all visible contract tabs and connect them to real content/API with selected/loading/empty/error states; visible unexplained disabled tabs are forbidden. | REF-CONTRACTS-01 |

## Definition of Done for every bug

Each item must have all applicable evidence before `[CLOSED]`:

1. Clear reproduction before the fix and identified root cause.
2. Real UI → service/controller → API → backend/data proof for functional items.
3. Arabic RTL verified.
4. English LTR verified.
5. Responsive verification on supported small/medium/large phones; no overflow/clipping.
6. Correct Loading / Empty / Error / Retry behavior.
7. Preserve page/filter/tab state when applicable.
8. `dart format lib test` clean.
9. `flutter analyze` GREEN.
10. `flutter test` GREEN.
11. Screenshot/video acceptance evidence against the relevant reference.
12. No visible no-op/disabled control without a real capability gate or clear reason.

## Required references

- `REF-DASH-01` — actual Dashboard before redesign.
- `REF-DASH-LATEST` — latest Dashboard direction: four KPI cards in one row, icon beside amount, compact Summary with chart + Total Account Balance.
- `REF-DASH-COMPACT` — compact no-crowding Dashboard target.
- `REF-DASH-TABS` — Dashboard Tabs / data-slide target.
- `REF-PROFILE-01` — actual Profile before redesign.
- `REF-PROFILE-TARGET` — simplified Premium Profile without History / Account Info / Currency.
- `REF-PAYDET-01` — Payment details/status/typography/AppBar reference.
- `REF-PAY-SEQ` — payment/loading sequence reference.
- `REF-CONTRACTS-01` — Contracts/filter/sort/pagination/tabs reference.
- `REF-CUST-01` — Customer/related data/RTL/localization reference.
- `REF-SIDEBAR-01` — Drawer/Sidebar/contrast reference.
- `REF-LANDING-01` — Landing/PageView/indicator reference.
