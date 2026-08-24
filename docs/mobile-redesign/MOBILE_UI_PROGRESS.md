# Alkenzy ADV Mobile Redesign Progress

## Current redesign status

Active stacked implementation branch: `feat/alkenzy-mobile-reference-redesign`.

Base: PR #624 branch `feat/alkenzy-0.3.2-premium-final` so the locked-reference work extends rather than overwrites the active premium 0.3.2 implementation.

Tracking issue: #626.

## Completed screens / foundation

- Audited repository governance and active overlapping PR work.
- Audited the Flutter feature tree and current design authority.
- Added `alkenzy_reference_components.dart` with centralized reference geometry, card, pill, header, page and CTA primitives that reuse `SafeContractsVisual` colors.
- Rebuilt `PremiumCompactWelcomeScreen` around the locked REF_01 cream/navy onboarding composition while preserving `MobileLandingController`, language switching, Learn More content and the existing sign-in entry point.
- Created reference documentation and a complete working screen matrix for the currently reachable feature families.

## Partially completed / inherited premium screens

The stacked base already contains premium implementations for login, shell/navigation, dashboard 2, contracts, payments, help and related backend/plugin support. They are not automatically considered locked-reference complete; each still needs screenshot comparison against REF_01–REF_06.

## Remaining screens

See `MOBILE_UI_SCREEN_MATRIX.md`. Highest-priority remaining visual passes:

1. auth/login + bootstrap states;
2. authenticated shell/bottom navigation/drawer/Quick Add;
3. dashboard/dashboard filters;
4. customers and suppliers;
5. contracts including all detail tabs and image fallback;
6. payments/collections/finance;
7. follow-ups/notifications;
8. export/profile/settings/help;
9. final RTL/English/responsive/non-happy-state comparison pass.

## Current visual decisions

- Warm cream/off-white is the default content canvas.
- Deep navy is the navigation/header authority.
- Rose-gold/copper is an accent, not a full-page competing primary.
- Cards remain compact with 18 px reference radius, thin warm borders and controlled soft shadows.
- Arabic uses Cairo and English uses Inter through the existing app theme.
- Existing backend models, permissions and financial calculations are preserved.

## Known issues / blockers

- The six original reference PNGs are supplied to the implementation session and canonical filenames are reserved, but the connected GitHub write interface currently accepts text contents/blobs rather than mounted local binary files directly. Do not mark reference-asset ingestion complete until the actual PNG binaries are committed under `assets/design/mobile_redesign/reference/`.
- Flutter SDK/emulator execution is not available inside the GitHub connector. CI must provide formatter/analyze/test evidence; screenshot capture requires a Flutter-capable runner/emulator or local device.

## Testing status

- Source changes are committed to the implementation branch.
- Dedicated reference-component widget tests are being added in this branch.
- Full `flutter analyze` / `flutter test` remains required through CI before QA completion.

## Latest screenshots

No implementation screenshot is claimed yet. Reference-comparison evidence must be real; it must not be fabricated from the supplied mockups.

## Next exact task

Finish REF_01 by aligning the real login/bootstrap states, then move to the authenticated navigation shell and dashboard composition from REF_02.

---

## Worker 2 — Customers / Suppliers / Contracts

Branch: `mobile-redesign/customers-suppliers-contracts`.

### Implemented presentation and integration

- Rebuilt the customer directory with compact premium cards, current-page search, alphabetical sort, empty/loading/error states and permission-aware create/edit actions.
- Added authorized customer create/edit integration through the existing mobile customer mutation routes. Customer edit deliberately does not send `notes` because the customer read projection does not expose the existing note value; this prevents an edit from erasing unseen server data.
- Rebuilt customer detail as a business workspace showing real counterparty contracts, receivables, per-currency server financial summary and recent settlement activity. No cross-currency client total is created.
- Rebuilt the supplier directory/detail/create-edit surfaces in the same entity family while keeping payable semantics visually and textually distinct from customer receivables.
- Supplier detail consumes real supplier contracts, payables, upcoming payments and settlement activity; archive/create/edit continue through existing supplier permissions and endpoints.
- Rebuilt the contract directory with real `counterparty_type` customer/supplier filtering, real contract statuses, server sort, current-page search, image-led cards, date-term progress and uploaded-image/company-logo/neutral-placeholder fallback behavior.
- Added permission-aware contract creation through the canonical `POST /contracts` contract route using only fields accepted by the server. Customer/supplier type remains server-authoritative for receivable/payable direction.
- Rebuilt contract edit around the existing light-edit and accountant-assignment endpoints. Financial values/status are not moved into Flutter mutation logic.
- Rebuilt `PremiumContractDetailsScreen` with four real-data tabs: Summary, Payments, Attachments and Details (`الملخص`, `الدفعات`, `المرفقات`, `التفاصيل`).
- The contract Summary tab consumes server `finance/summary` data; the Payments tab consumes the existing authoritative payment repository; the Attachments tab uses real contract media metadata and does not fabricate attachment size because the API does not expose it.
- Money rendering in these Worker 2 surfaces removes unnecessary trailing zero decimals without changing server values.
- Shared bootstrap changes are limited to exposing existing customer/contract mutation capability flags to these feature controllers.
- Worker branch was reconciled with the updated redesign foundation after it advanced by 18 commits. The foundation changes did not overlap Worker 2 feature files, and the merge preserves both histories.

### Backend/product boundaries confirmed

- The mobile contract create endpoint currently exposes contract number, counterparty, base value, optional currency/accountant/notes. Start/end dates are therefore not fabricated into create; existing light edit remains the supported date-edit path.
- Current contract REST media is read-oriented for the mobile client. The Worker 2 create/edit UI does not invent an unsupported media-upload endpoint.
- Contract `overdue` is not invented as a contract status. Overdue remains a payment/obligation state from authoritative backend data.
- Attachment file size is omitted because it is not present in the current contract-media projection.

### Reference / runtime QA status

- `MOBILE_UI_REFERENCE.md`, the screen matrix and the shared reference component file are now present and were reviewed after the foundation branch advanced.
- `assets/design/mobile_redesign/reference/` currently contains only its `README.md`; the actual `REF_03_Customers_Suppliers.png` and `REF_04_Contracts.png` binaries are still absent from the repository, so screenshot-to-reference comparison cannot truthfully be marked complete.
- Flutter SDK/emulator execution is unavailable through the current connector session. CI is being used for formatter/analyze/test evidence; runtime Arabic/English width checks and screenshot comparison remain pending real executable/reference access.

### Worker 2 next validation step

Run the focused PR through repository CI, fix all formatter/analyzer/tests, then leave it open for Lead/Integration ownership. Do not self-merge.
