# Alkenzy ADV 0.3.38 — optional rewarded XO

Base: signed build 37 source c8b87e91641b4de6d32db35098c9705fc2245b2d (R8 disabled). This feature intentionally does not touch authentication, dashboard data, app IDs, WordPress API, existing in-app banner units, interstitials or app-open ads.

- Entry points: public guest welcome screen and authenticated navigation drawer.
- Owner-authorized additional banner `ca-app-pub-3218037275900725/8818395498` is displayed directly **under the image slider** on public landing (and under the fallback medallion if there are no images). This is a dedicated AdMob `landingSlider` placement, not a substitute for the existing dashboard/footer ad units; available without login. Official Google test-banner ID is used in test mode.
- Arabic primary CTA: «ابدأ التسلية»; count label: «عدد المرات». Both are also localized to English.
- Explicit opt-in dialog before each Rewarded ad, disclosing exactly **one** non-transferable XO game versus the computer.
- AdMob rewarded: ca-app-pub-3218037275900725/1456680066; CI/test: ca-app-pub-3940256099942544/5224354917.
- Reward is granted **only** inside `onUserEarnedReward`; closing or failing an ad grants nothing.
- Ticket is device-local and independent of authentication. The entitlement and completed reward count persist through flutter_secure_storage across application restarts. One ticket cannot be claimed twice; a pending game is accessible without another ad.
- 2-minute cooldown starts when a reward is earned; no automatic ad pop-up. Failed/unavailable ads do not start a cooldown.
- Android native AdMob app ID remains ca-app-pub-3218037275900725~7401372044, and UMP consent/init uses existing SafeContractsMobileAds service.
- One XO popup against computer per earned ticket. Closing it ends the round. There are no in-game purchases, balances or transferable rewards.
- No new API endpoint, WordPress runtime behavior, or server deployment is required. Plugin version header and stable tag are synchronized to 0.3.38 solely to satisfy the repository's unified release-version validation.

QA: test guest (without token), login/logout, consent denied, ad unavailable, skipped ad, earned ad, pending ticket on app restart, exactly one game, count persistence, 2-minute gating, RTL/narrow screens, Android 13–16, Google Play signed Internal Testing. Review AdMob reward-policy disclosure before production rollout.

Release status must remain candidate until exact-head Android signing, AAB/APK CI gates and Play-installed device UAT succeed.

## Automated ad pacing and Guest Landing
- Guest landing below slider: owner Banner `ca-app-pub-3218037275900725/8818395498`, plus optional Rewarded XO `ابدأ التسلية` and local view count. Both require no authentication.
- Interstitial: keep the existing main/reports units. Require at least 5 minutes after app start, 5 minutes after prior Interstitial or App Open, and 3 natural navigation actions, ONLY at the existing completed Contracts/Reports to Dashboard transition. No idle-timer ad; no forced ad while typing, paying or logging in.
- App Open: preload on guest Landing after UMP consent, and on authenticated Dashboard. Show only on a genuine return from background of 2+ minutes, never on initial launch or while a login, modal, Rewarded ad/XO, form or secondary route is active. At least 20 minutes between App Open views and 5 minutes from an Interstitial.
- Existing ad IDs are unchanged; full-screen timestamps survive logout/login within the running process; permission/network/ad-load failure never blocks the application.
- The version 0.3.38 remains a release candidate until exact-head tests, signed artifact verification and Play-installed device testing pass.
