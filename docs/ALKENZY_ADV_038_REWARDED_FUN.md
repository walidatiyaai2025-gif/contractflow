# Alkenzy ADV 0.3.38 — optional rewarded XO

Base: signed build 37 source c8b87e91641b4de6d32db35098c9705fc2245b2d (R8 disabled). This feature intentionally does not touch authentication, dashboard data, app IDs, WordPress API, existing banners, interstitials or app-open ads.

- Entry points: public guest welcome screen and authenticated navigation drawer.
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
