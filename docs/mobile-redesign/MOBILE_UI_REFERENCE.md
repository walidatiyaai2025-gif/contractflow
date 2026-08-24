# Alkenzy ADV Mobile — Locked Visual Reference

## Status

The six user-supplied mobile boards are the approved visual baseline for the Alkenzy ADV Flutter application. The redesign is presentation-layer work only: WordPress + SafeContracts remain the business and financial source of truth.

## Brand direction

- Product: **Alkenzy ADV**
- Personality: premium, corporate, modern, elegant, financial, trustworthy, Arabic-first.
- Information density: compact executive/business UI rather than generic spacious Material cards.
- Primary composition: warm cream/off-white content surfaces, deep navy headers/navigation, subtle copper/rose-gold accents, controlled shadows, thin borders and rounded cards.

## Reference mapping

| Reference | Source board | Primary implementation targets |
|---|---|---|
| `REF_01_Auth_Onboarding.png` | auth/onboarding board | Splash, onboarding/welcome, login, create-account/OTP when supported |
| `REF_02_Dashboard_Navigation.png` | dashboard/navigation board | Dashboard, reporting overview, drawer, Quick Add, upcoming dues |
| `REF_03_Customers_Suppliers.png` | customers/suppliers board | Customer list/details/add-edit, supplier list/details/add-edit |
| `REF_04_Contracts.png` | contracts board | Contract list/details/create-edit, summary/payments/attachments tabs |
| `REF_05_Payments_Finance.png` | payments/finance board | Payments, collections, finance overview, follow-ups |
| `REF_06_Notifications_Profile_Settings.png` | account/operations board | Notifications, notification detail, export, profile, settings |

## Central visual authority

Existing `SafeContractsVisual` remains the color authority. New locked-reference geometry and component primitives are centralized in `mobile/lib/features/ui/alkenzy_reference_components.dart`; screens must reuse those primitives or equivalent shared components instead of scattering local magic values.

### Current palette family

- Deep navy: `SafeContractsVisual.navyDeep`
- Navy: `SafeContractsVisual.navy`
- Raised navy: `SafeContractsVisual.navyRaised`
- Warm cream/background: `SafeContractsVisual.background`
- Off-white surface: `SafeContractsVisual.surface`
- Warm surface: `SafeContractsVisual.surfaceWarm`
- Rose gold/copper: `SafeContractsVisual.roseGold` / `roseGoldDark`
- Success: `SafeContractsVisual.green`
- Warning: `SafeContractsVisual.amber`
- Overdue/error: `SafeContractsVisual.red`

No feature may introduce a separate ungoverned palette.

## Typography

- Arabic: Cairo through the existing app theme.
- English: Inter through the existing app theme.
- Headings: strong 800–900 weights with tight hierarchy.
- Monetary values: visually dominant, compact, and formatted by shared business helpers; unnecessary `.00` must not be introduced.

## Geometry

Reference-aligned values are centralized in `AlkenzyReferenceTokens`:

- page padding: 18 px normal / 14 px compact;
- primary card radius: 18 px;
- compact radius: 14 px;
- primary bottom action height: 52 px;
- compact section gaps rather than oversized whitespace;
- thin warm borders and soft shadows.

## RTL contract

Arabic is first-class. Every redesigned screen must be exercised under RTL with long Arabic entity names and financial values. Directional APIs (`EdgeInsetsDirectional`, `PositionedDirectional`, `AlignmentDirectional`) are preferred where direction matters. Back/forward icons must remain semantically correct.

## Responsive contract

Required practical widths: 320, 360, 375, 390, 412, 430 plus representative Android proportions. No RenderFlex overflow, clipped CTA, inaccessible bottom action or hard-coded iPhone-only layout is accepted.

## Product boundary

The reference images control look, feel, hierarchy and composition. Repository/backend contracts control data, permissions, validation, financial meaning and feature availability. When they conflict, preserve business correctness and match the reference visually around it.

## Reference asset ingestion

Target repository location:

`assets/design/mobile_redesign/reference/`

The exact source images were supplied in the ChatGPT work session. This branch reserves the canonical filenames above. Binary ingestion must preserve enough source resolution for implementation comparison; do not substitute recreated mockups for the original references.
