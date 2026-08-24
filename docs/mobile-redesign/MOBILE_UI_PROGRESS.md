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
