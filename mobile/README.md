# SafeContracts Mobile

Flutter foundation for the SafeContracts mobile client.

## Architectural boundary

WordPress + the SafeContracts plugin remain the source of truth. This client must not duplicate authoritative contract, payment, permission or financial logic. Server responses and server-enforced scope drive the mobile experience.

## Configuration

Public compile-time configuration is read through Dart defines:

```bash
flutter run --dart-define-from-file=config/local.example.json
```

Supported values:

- `SC_ENV`: `local`, `staging`, `production`
- `SC_API_BASE_URL`: absolute SafeContracts REST base URL

Do not place passwords, tokens, Firebase private credentials or other secrets in these files. Real `config/*.json` files are ignored by Git; only `*.example.json` belongs in the repository.

## Validation

```bash
flutter pub get
dart format --output=none --set-exit-if-changed lib test
flutter analyze
flutter test
```

Platform runners and release signing are intentionally outside this P0 foundation scope and will be added by the relevant mobile/build tasks.

## Alkenzy ADV 0.3.38 — advertising acceptance contract

The public landing screen (without login) displays the owner-provided Main
Banner **directly under the image slider**, and the optional
`ابدأ التسلية` Rewarded button with an in-app completion count.
A completed Google AdMob Rewarded ad grants exactly one in-app XO match against
the computer. A two-minute minimum cooldown starts on `onUserEarnedReward`;
cancellation or ad-load failure earns no game and starts no cooldown.

The signed-in app retains its existing Main/Reports interstitial placements.
Their minimum interval is five minutes; they may be shown **only** at a
completed, user-driven Contracts or Reports → Dashboard transition after
three navigation actions. No ad auto-starts merely because a timer expires.

App Open now supports foreground return on the **public landing** and the
signed-in Dashboard. It needs at least two minutes in the background and at
least 20 minutes since the previous App Open, plus five minutes' separation
from an Interstitial. Neither first launch, login, editing, game dialogs
nor an active Rewarded ad may trigger it.

For the Android production release: versionName `0.3.38`, versionCode `38`,
R8/shrink resources still disabled pending crash diagnosis from build 35.
Test ad units are mandatory for pre-production ad testing. Verify the
exact-head Flutter tests, signed AAB + diagnostic APK, AdMob privacy consent,
and Google Play-installed UAT before promoting this feature to Production.
An unavailable ad must leave the app functional and should never be counted as
a confirmed impression or game.
