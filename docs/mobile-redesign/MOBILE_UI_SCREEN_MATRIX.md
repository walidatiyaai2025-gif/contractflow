# Mobile UI Screen Matrix

This matrix is additive. Workers should update only rows inside their assigned scope and preserve other agents' entries when they appear.

| Area | Screen / surface | Owner | Status | Functional behavior | Visual / responsive evidence |
| --- | --- | --- | --- | --- | --- |
| Foundation | Central design tokens + theme | Worker 1 | Implemented | Existing Alkenzy ADV brand palette preserved; Cairo/Inter, controls, form, navigation and status styling centralized | Automated component coverage added for 320/360/375/390/412/430 widths; locked reference directory is not present on `main` as of 2026-08-24 |
| Foundation | Shared UI components | Worker 1 | Implemented | Reusable scaffold/header/section/card/button/status/filter/search/amount/navigation/drawer/sheet/empty/loading/error components | Responsive bottom navigation regression test covers 320/360/375/390/412/430 |
| Foundation | Global form language | Worker 1 | Implemented | Shared text/email/phone/password/dropdown/date/money/textarea/file-picker presentation; no backend capability added | Theme + field primitives use centralized spacing/radii/control tokens |
| Auth | Bootstrap / splash | Worker 1 | Implemented | Real bootstrap/retry logic preserved; no fake loading | Premium Alkenzy navy/rose-gold branded splash |
| Welcome | Landing / welcome | Worker 1 | Implemented | WordPress-fed landing content, fallback behavior, refresh, sign-in and learn-more behavior preserved | Narrow/wide widget coverage at 320 and 430 in Arabic and English |
| Auth | Login | Worker 1 | Implemented | Existing auth API, validation, remember-me, password visibility, errors and post-login bootstrap preserved | Narrow/wide widget coverage at 320 and 430 in Arabic and English |
| Auth | Auth errors / unauthorized presentation | Worker 1 | Implemented for existing capabilities | 401/403/login errors and bootstrap error/retry remain server-driven | Shared error/splash language; password reset/verification not invented because no existing backend capability was found in owned auth flow |
| Navigation | App shell | Worker 1 | Implemented | Existing destination permissions, live refresh, deep links and business controllers preserved | Premium compact shell/header and responsive bottom navigation |
| Navigation | Drawer / More | Worker 1 | Implemented | Drawer shows only policy-authorized destinations; logout uses existing session clear flow with confirmation | Premium deep-navy drawer; More opens the same permission-aware destination list without duplicate routes |
| Navigation | Quick Add presentation | Worker 1 | Implemented | Existing `availableMobileQuickAdds(session)` permissions and existing feature forms preserved | Premium permission-aware bottom sheet; no feature form rewrite |
| Navigation | Logout confirmation | Worker 1 | Implemented | Confirms before invoking existing `onClearSession` flow | Arabic/English dialog presentation |

## Visual-reference availability note

The task names `docs/mobile-redesign/MOBILE_UI_REFERENCE.md` and `assets/design/mobile_redesign/reference/` as locked sources. Neither path exists on the repository `main` branch at the start of Worker 1 implementation on 2026-08-24. Worker 1 therefore preserved and systematized the already-approved Alkenzy ADV navy / cream / rose-gold visual language present in the existing mobile source and did not fabricate replacement reference assets.
