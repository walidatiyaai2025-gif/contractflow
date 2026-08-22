#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
MOBILE="$ROOT/mobile"
TEMPLATE="$ROOT/mobile/android-release/app-build.gradle.kts"
FIREBASE_CONFIG="$ROOT/mobile/android-release/google-services.json"
ALKENZY_APP_ASSET="$ROOT/mobile/assets/brand/alkenzy_adv.png"
ALKENZY_ICON_SOURCE="$ROOT/mobile/android-release/alkenzy_launcher.png"
MAIN_ACTIVITY_TEMPLATE="$ROOT/mobile/android-release/MainActivity.kt"
ADMOB_TEST_APP_ID="ca-app-pub-3940256099942544~3347511713"
ADMOB_PRODUCTION_APP_ID="ca-app-pub-3218037275900725~7401372044"

if [[ "${SC_REQUIRE_PRODUCTION_ADMOB:-0}" == "1" ]]; then
  ADMOB_APP_ID="${SC_ADMOB_APP_ID:-$ADMOB_PRODUCTION_APP_ID}"
else
  ADMOB_APP_ID="${SC_ADMOB_APP_ID:-$ADMOB_TEST_APP_ID}"
fi

if ! command -v flutter >/dev/null 2>&1; then
  echo "FAIL: flutter is required to bootstrap the Android platform" >&2
  exit 1
fi

for required_source in "$TEMPLATE" "$FIREBASE_CONFIG" "$ALKENZY_APP_ASSET" "$ALKENZY_ICON_SOURCE" "$MAIN_ACTIVITY_TEMPLATE"; do
  if [[ ! -f "$required_source" ]]; then
    echo "FAIL: committed Android release source is missing: $required_source" >&2
    exit 1
  fi
done

python3 - "$ADMOB_APP_ID" "$ADMOB_TEST_APP_ID" "${SC_REQUIRE_PRODUCTION_ADMOB:-0}" <<'PY'
import re
import sys

app_id, test_id, require_production = sys.argv[1:]
if not re.fullmatch(r"ca-app-pub-\d{16}~\d{10}", app_id):
    raise SystemExit("FAIL: AdMob App ID must use ca-app-pub-XXXXXXXXXXXXXXXX~YYYYYYYYYY format")
if require_production == "1" and app_id == test_id:
    raise SystemExit("FAIL: Google Play build requires a production AdMob App ID")
PY

cmp -s "$ALKENZY_APP_ASSET" "$ALKENZY_ICON_SOURCE" || {
  echo "FAIL: in-app and launcher Alkenzy identities must use the same supplied logo bytes" >&2
  exit 1
}

python3 - "$ALKENZY_ICON_SOURCE" <<'PY'
from pathlib import Path
import sys

path = Path(sys.argv[1])
content = path.read_bytes()
if len(content) < 4096 or not content.startswith(b"\x89PNG\r\n\x1a\n"):
    raise SystemExit("FAIL: Alkenzy launcher icon is not a valid PNG resource")
PY

python3 - "$FIREBASE_CONFIG" <<'PY'
import json
from pathlib import Path
import sys

path = Path(sys.argv[1])
data = json.loads(path.read_text(encoding="utf-8"))
if data.get("project_info", {}).get("project_id") != "safecontract-13846":
    raise SystemExit("FAIL: Firebase project ID does not match SafeContracts production")
if data.get("project_info", {}).get("project_number") != "744938686052":
    raise SystemExit("FAIL: Firebase sender/project number does not match SafeContracts production")
clients = data.get("client") or []
packages = {
    client.get("client_info", {}).get("android_client_info", {}).get("package_name")
    for client in clients if isinstance(client, dict)
}
if "com.safecontracts.safecontracts_mobile" not in packages:
    raise SystemExit("FAIL: Firebase Android package does not match SafeContracts applicationId")
PY

cd "$MOBILE"

# Flutter owns the platform boilerplate version. Recreate it from the exact
# Flutter stable toolchain used by CI, then restore the repository's release
# signing, networking, Firebase, notification presentation, advertising, and
# Safe Contracts runtime contracts. The launcher icon is the supplied Alkenzy
# Advertising mark.
rm -rf android
flutter create \
  --platforms=android \
  --org com.safecontracts \
  --project-name safecontracts_mobile \
  .

# AppLovin MAX Flutter 4.6.4 still declares Android compileSdk 31 in its
# plugin Gradle file. Its current AndroidX dependencies require compileSdk 34+
# and Alkenzy itself targets API 36. Patch only that dependency's build-time
# compileSdk in the local Pub cache; no runtime/provider behavior is changed.
APPLOVIN_ANDROID_BUILD="$(python3 - <<'PY'
import json
from pathlib import Path
from urllib.parse import unquote, urlparse

config = json.loads(Path('.dart_tool/package_config.json').read_text(encoding='utf-8'))
for package in config.get('packages', []):
    if package.get('name') != 'applovin_max':
        continue
    uri = urlparse(package['rootUri'])
    if uri.scheme != 'file':
        raise SystemExit('FAIL: applovin_max package root is not a local file URI')
    root = Path(unquote(uri.path))
    for filename in ('build.gradle', 'build.gradle.kts'):
        candidate = root / 'android' / filename
        if candidate.is_file():
            print(candidate)
            raise SystemExit(0)
    raise SystemExit('FAIL: applovin_max Android Gradle file was not found')
raise SystemExit('FAIL: applovin_max package was not resolved')
PY
)"
python3 - "$APPLOVIN_ANDROID_BUILD" <<'PY'
from pathlib import Path
import re
import sys

path = Path(sys.argv[1])
text = path.read_text(encoding='utf-8')
original = text
patterns = (
    (r'compileSdkVersion\s*=\s*\d+', 'compileSdkVersion = 36'),
    (r'compileSdkVersion\s+\d+', 'compileSdkVersion 36'),
    (r'compileSdk\s*=\s*\d+', 'compileSdk = 36'),
)
for pattern, replacement in patterns:
    text, count = re.subn(pattern, replacement, text, count=1)
    if count:
        break
if text == original:
    if not re.search(r'(?:compileSdkVersion|compileSdk)\D+36\b', text):
        raise SystemExit('FAIL: unable to enforce compileSdk 36 for applovin_max')
else:
    path.write_text(text, encoding='utf-8')
if not re.search(r'(?:compileSdkVersion|compileSdk)\D+36\b', text):
    raise SystemExit('FAIL: applovin_max compileSdk 36 verification failed')
print(f'Patched {path} to compileSdk 36 for AndroidX compatibility.')
PY

cp "$TEMPLATE" android/app/build.gradle.kts
cp "$FIREBASE_CONFIG" android/app/google-services.json
MAIN_ACTIVITY_TARGET="android/app/src/main/kotlin/com/safecontracts/safecontracts_mobile/MainActivity.kt"
mkdir -p "$(dirname "$MAIN_ACTIVITY_TARGET")"
cp "$MAIN_ACTIVITY_TEMPLATE" "$MAIN_ACTIVITY_TARGET"

SETTINGS="android/settings.gradle.kts"
python3 - "$SETTINGS" <<'PY'
from pathlib import Path
import re
import sys

path = Path(sys.argv[1])
text = path.read_text(encoding="utf-8")
plugin = 'id("com.google.gms.google-services") version "4.4.4" apply false'
if plugin not in text:
    match = re.search(r'(?m)^plugins\s*\{\s*$', text)
    if match is None:
        raise SystemExit("FAIL: generated settings.gradle.kts has no plugins block")
    text = text[:match.end()] + "\n    " + plugin + text[match.end():]
path.write_text(text, encoding="utf-8")
PY

LAUNCHER_ICON="android/app/src/main/res/drawable/alkenzy_launcher.png"
mkdir -p "$(dirname "$LAUNCHER_ICON")"
cp "$ALKENZY_ICON_SOURCE" "$LAUNCHER_ICON"

cmp -s "$ALKENZY_ICON_SOURCE" "$LAUNCHER_ICON" || {
  echo "FAIL: generated Android launcher does not match the supplied Alkenzy logo" >&2
  exit 1
}

MANIFEST="android/app/src/main/AndroidManifest.xml"
if [[ ! -f "$MANIFEST" ]]; then
  echo "FAIL: Flutter did not generate AndroidManifest.xml" >&2
  exit 1
fi
python3 - "$MANIFEST" "$ADMOB_APP_ID" <<'PY'
from pathlib import Path
import re
import sys

path = Path(sys.argv[1])
admob_app_id = sys.argv[2]
text = path.read_text(encoding="utf-8")
text = text.replace('android:label="safecontracts_mobile"', 'android:label="Alkenzy ADV"')
text = re.sub(
    r'android:icon="@[^"]+"',
    'android:icon="@drawable/alkenzy_launcher"',
    text,
    count=1,
)
if 'android:roundIcon=' in text:
    text = re.sub(
        r'android:roundIcon="@[^"]+"',
        'android:roundIcon="@drawable/alkenzy_launcher"',
        text,
        count=1,
    )
else:
    text = text.replace(
        'android:icon="@drawable/alkenzy_launcher"',
        'android:icon="@drawable/alkenzy_launcher"\n        android:roundIcon="@drawable/alkenzy_launcher"',
        1,
    )

permissions = [
    'android.permission.INTERNET',
    'android.permission.POST_NOTIFICATIONS',
]
for permission in permissions:
    if permission in text:
        continue
    application = re.search(r'(?m)^([ \t]*)<application\b', text)
    if application is None:
        raise SystemExit("FAIL: AndroidManifest.xml does not contain an <application> element")
    indent = application.group(1)
    permission_line = f'{indent}<uses-permission android:name="{permission}" />\n'
    text = text[: application.start()] + permission_line + text[application.start() :]

metadata = f'''
        <meta-data
            android:name="com.google.firebase.messaging.default_notification_channel_id"
            android:value="safe_contracts_alerts" />
        <meta-data
            android:name="com.google.firebase.messaging.default_notification_icon"
            android:resource="@android:drawable/ic_dialog_info" />
        <meta-data
            android:name="com.google.android.gms.ads.APPLICATION_ID"
            android:value="{admob_app_id}" />
'''
if 'com.google.firebase.messaging.default_notification_channel_id' not in text:
    match = re.search(r'(?m)^([ \t]*)</application>', text)
    if match is None:
        raise SystemExit("FAIL: AndroidManifest.xml does not contain </application>")
    text = text[:match.start()] + metadata + text[match.start():]
elif 'com.google.android.gms.ads.APPLICATION_ID' not in text:
    match = re.search(r'(?m)^([ \t]*)</application>', text)
    if match is None:
        raise SystemExit("FAIL: AndroidManifest.xml does not contain </application>")
    ads_metadata = f'''        <meta-data
            android:name="com.google.android.gms.ads.APPLICATION_ID"
            android:value="{admob_app_id}" />
'''
    text = text[:match.start()] + ads_metadata + text[match.start():]

for permission in permissions:
    if permission not in text:
        raise SystemExit(f"FAIL: Android release manifest is missing {permission}")
if 'android:label="Alkenzy ADV"' not in text:
    raise SystemExit("FAIL: Android release manifest is missing Alkenzy ADV label")
if 'android:icon="@drawable/alkenzy_launcher"' not in text:
    raise SystemExit("FAIL: Android release manifest is missing Alkenzy launcher icon")
if 'android:roundIcon="@drawable/alkenzy_launcher"' not in text:
    raise SystemExit("FAIL: Android release manifest is missing Alkenzy round launcher icon")
if 'safe_contracts_alerts' not in text:
    raise SystemExit("FAIL: Android release manifest is missing Safe Contracts notification channel metadata")
if 'com.google.android.gms.ads.APPLICATION_ID' not in text or admob_app_id not in text:
    raise SystemExit("FAIL: Android release manifest is missing the configured AdMob application ID")

path.write_text(text, encoding="utf-8")
PY

for required in \
  android/settings.gradle.kts \
  android/gradlew \
  android/gradle/wrapper/gradle-wrapper.jar \
  android/app/build.gradle.kts \
  android/app/google-services.json \
  android/app/src/main/AndroidManifest.xml \
  "$MAIN_ACTIVITY_TARGET" \
  "$LAUNCHER_ICON"; do
  if [[ ! -e "$required" ]]; then
    echo "FAIL: generated Android scaffold missing $required" >&2
    exit 1
  fi
done

grep -Fq 'android.permission.INTERNET' "$MANIFEST" || {
  echo "FAIL: Android release manifest is missing INTERNET permission" >&2
  exit 1
}
grep -Fq 'android.permission.POST_NOTIFICATIONS' "$MANIFEST" || {
  echo "FAIL: Android release manifest is missing POST_NOTIFICATIONS permission" >&2
  exit 1
}
grep -Fq 'android:label="Alkenzy ADV"' "$MANIFEST" || {
  echo "FAIL: Android release manifest is missing Alkenzy ADV label" >&2
  exit 1
}
grep -Fq 'android:icon="@drawable/alkenzy_launcher"' "$MANIFEST" || {
  echo "FAIL: Android release manifest is missing Alkenzy launcher icon" >&2
  exit 1
}
grep -Fq 'android:roundIcon="@drawable/alkenzy_launcher"' "$MANIFEST" || {
  echo "FAIL: Android release manifest is missing Alkenzy round launcher icon" >&2
  exit 1
}
grep -Fq 'safe_contracts_alerts' "$MANIFEST" || {
  echo "FAIL: Android release manifest is missing high-importance notification channel metadata" >&2
  exit 1
}
grep -Fq 'com.google.android.gms.ads.APPLICATION_ID' "$MANIFEST" || {
  echo "FAIL: Android release manifest is missing AdMob application metadata" >&2
  exit 1
}
grep -Fq "$ADMOB_APP_ID" "$MANIFEST" || {
  echo "FAIL: Android release manifest AdMob application ID does not match the configured build value" >&2
  exit 1
}
grep -Fq 'safecontracts/notifications' "$MAIN_ACTIVITY_TARGET" || {
  echo "FAIL: Android release activity is missing foreground notification bridge" >&2
  exit 1
}
grep -Fq 'id("com.google.gms.google-services")' android/app/build.gradle.kts || {
  echo "FAIL: Android app does not apply Google Services Gradle plugin" >&2
  exit 1
}
grep -Fq 'id("com.google.gms.google-services") version "4.4.4" apply false' "$SETTINGS" || {
  echo "FAIL: Android settings do not declare Google Services Gradle plugin" >&2
  exit 1
}

echo "Alkenzy ADV Android scaffold bootstrapped with supplied Alkenzy launcher icon, high-importance tray notifications, release signing, INTERNET, Firebase, and AdMob contracts."
