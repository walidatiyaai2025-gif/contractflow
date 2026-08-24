# Alkenzy ADV Mobile UI Acceptance

A screen is not accepted because it compiles. It is accepted only after business behavior and visual comparison both pass.

## Per-screen gate

- [ ] Real API/business integration preserved.
- [ ] Correct locked reference identified.
- [ ] Reference-aligned UI implemented.
- [ ] No working field/action/filter/tab/permission silently removed.
- [ ] Arabic RTL checked with realistic long names.
- [ ] English checked.
- [ ] Widths 320 / 360 / 375 / 390 / 412 / 430 checked where practical.
- [ ] Representative Android proportion checked.
- [ ] No overflow, clipping or inaccessible bottom action.
- [ ] Loading state designed.
- [ ] Empty state designed where data may be empty.
- [ ] Error/retry state designed where an API can fail.
- [ ] Money uses the shared formatter and does not invent unnecessary decimals.
- [ ] Screenshot captured from the running implementation.
- [ ] Screenshot compared to the mapped reference for spacing, hierarchy, typography, card size, color, icons, headers and navigation.
- [ ] Formatter/analyze/tests pass for the exact candidate.

## Reference asset gate

The following files must physically exist before overall redesign completion is declared:

- `assets/design/mobile_redesign/reference/REF_01_Auth_Onboarding.png`
- `assets/design/mobile_redesign/reference/REF_02_Dashboard_Navigation.png`
- `assets/design/mobile_redesign/reference/REF_03_Customers_Suppliers.png`
- `assets/design/mobile_redesign/reference/REF_04_Contracts.png`
- `assets/design/mobile_redesign/reference/REF_05_Payments_Finance.png`
- `assets/design/mobile_redesign/reference/REF_06_Notifications_Profile_Settings.png`

## Overall definition of done

- [ ] All references committed.
- [x] Reference documentation exists.
- [x] Screen matrix exists.
- [x] Shared locked-reference presentation foundation exists.
- [ ] Every reachable mobile destination is redesigned and compared.
- [ ] Every form/detail/modal/bottom-sheet flow uses the same design language.
- [ ] RTL and English verified.
- [ ] Responsive checks complete.
- [ ] No major legacy visual islands remain.
- [ ] Major screen screenshots stored under `docs/mobile-redesign/screenshots/`.
- [ ] Flutter analyze passes.
- [ ] Flutter tests pass.
- [ ] Matrix contains no undocumented reachable mobile screens.
- [ ] Progress document reflects the exact final state.

The final completion marker may only be used when all unchecked items above have real evidence:

`ALKENZY ADV MOBILE REDESIGN COMPLETE — ALL MOBILE SCREENS VISUALLY ALIGNED WITH LOCKED REFERENCES, FUNCTIONAL, TESTED, AND READY FOR RELEASE QA.`
