# Alkenzy ADV Production Crash Rescue — 0.3.37 / Build 37

## Incident (23 November 2026)

Google Play Production had published **35 (0.3.34)** and the owner reported it crashed.
**36 (0.3.35)** was undergoing Play review, not known safe. The rescue must be
a forward update **37 (0.3.37)**. The app version and versionCode intentionally
share the numeral 37 to avoid confusion.

## Code-level investigation and hypothesis

- Last successful **pre-R8** signed build: **34 (0.3.33)**, commit
  `b7b54ee322152ecac53dcaffc49cef2efcb40ebe`,
  workflow https://github.com/walidatiyaai2025-gif/contractflow/actions/runs/45125103792.
- Build **35 (0.3.34)** enabled Android code minification/obfuscation via
  R8 (`isMinifyEnabled = true`), resource shrinking
  (`isShrinkResources = true`), and `proguard-android-optimize.txt`.
- The Android `MainActivity` and Flutter app-owned AdMob placement source were
  unchanged from build34 to build35. R8 is the **primary regression suspect**.
- Build35's R8 initially failed with three optional AppLovin MAX Amazon
  PrivacyPass references; narrowly scoped `-dontwarn` exclusions allowed
  compiling. A successful R8 compile does **not** prove SDK reflection or
  native startup safety.
- Build36 retains R8 and adds an AndroidX edge-to-edge native entrypoint and
  Force Update runtime, so it is **not** an acceptable untested rescue baseline.

**No crash stack trace is available in this repository; R8 is a hypothesis,
not a verified root cause.** Determine the specific crash in Play Console:
Android vitals -> Crashes & ANRs -> filter versionCode 35 -> affected devices
-> inspect exception / caused-by stack and whether it references
`ClassNotFoundException`, `NoSuchMethodError`, native crash, Flutter engine,
Firebase, AdMob or AppLovin. Use the exact build35
`mapping.txt` to deobfuscate app/library Java/Kotlin frames. Never use
build36's mapping or build37's mapping against build35.

## Rescue delivery (build37)

- Fork exact build34 commit. Change only `mobile/pubspec.yaml` to
  `version: 0.3.37+37`, tests, this note and the isolated CI workflow.
- Preserve the same application ID, production API URL, Google Play upload
  signing certificate, login state isolation and in-app AdMob unit inventory.
- Release Gradle template restores **R8 OFF / resource shrinking OFF**.
- AAB and diagnostic APK are signed from the exact same rescue commit, with
  manifest and SHA-256 checks. No server migrations or WordPress plugin update.
- CI rejects any unapproved runtime source diff from the proven pre-R8 commit.
- Play Console may keep the DEX optimization warning for this emergency
  rescue. **Containment of the production crash takes priority.**
  Reintroduce R8 only after a real stack-trace analysis, targeted keep rules,
  and pre-release Play-installed testing.

## Operational Play rollout

1. Keep **36** from auto-publishing while under review (Managed publishing).
2. Upload **37** to Internal Testing; verify Play-signed cold launch,
   sign-in/home, ads, contract pages, background/resume on Android 13–16.
3. After passing, publish **37** to Production; it updates devices already
   on 35 or 36 because `versionCode=37`.
4. Stop distribution of the crashing build if Play Console offers that action;
   halting never downgrades devices already on build35.
5. Review new Android vitals crash-rate before raising rollout to 100%.
