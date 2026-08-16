#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
MOBILE="$ROOT/mobile"
TEMPLATE="$ROOT/mobile/android-release/app-build.gradle.kts"
ICON_SOURCE="$ROOT/mobile/android-release/enterprise-launcher.xml"
ICON_FOREGROUND_SOURCE="$ROOT/mobile/android-release/enterprise-launcher-foreground.xml"
ICON_BACKGROUND_SOURCE="$ROOT/mobile/android-release/enterprise-launcher-background.xml"
ADAPTIVE_ICON_SOURCE="$ROOT/mobile/android-release/enterprise-launcher-adaptive.xml"
SPLASH_SOURCE="$ROOT/mobile/android-release/enterprise-splash.xml"
MAIN_ACTIVITY_TEMPLATE="$ROOT/mobile/android-release/MainActivity.kt"

FIREBASE_DEV="${ESC_FIREBASE_ANDROID_CONFIG_DEV:-}"
FIREBASE_STAGING="${ESC_FIREBASE_ANDROID_CONFIG_STAGING:-}"
FIREBASE_PRODUCTION="${ESC_FIREBASE_ANDROID_CONFIG_PRODUCTION:-}"

if ! command -v flutter >/dev/null 2>&1; then
  echo "FAIL: flutter is required to bootstrap the ESC Android platform" >&2
  exit 1
fi

for required_source in \
  "$TEMPLATE" \
  "$ICON_SOURCE" \
  "$ICON_FOREGROUND_SOURCE" \
  "$ICON_BACKGROUND_SOURCE" \
  "$ADAPTIVE_ICON_SOURCE" \
  "$SPLASH_SOURCE" \
  "$MAIN_ACTIVITY_TEMPLATE"; do
  if [[ ! -f "$required_source" ]]; then
    echo "FAIL: committed ESC Android release source is missing: $required_source" >&2
    exit 1
  fi
done

for name in ESC_FIREBASE_ANDROID_CONFIG_DEV ESC_FIREBASE_ANDROID_CONFIG_STAGING ESC_FIREBASE_ANDROID_CONFIG_PRODUCTION; do
  value="${!name:-}"
  if [[ -z "$value" || ! -f "$value" ]]; then
    echo "FAIL: $name must point to a local, uncommitted ESC Firebase google-services.json" >&2
    exit 1
  fi
done

python3 - "$FIREBASE_DEV" "$FIREBASE_STAGING" "$FIREBASE_PRODUCTION" <<'PY'
import json
from pathlib import Path
import sys

configs = [
    (Path(sys.argv[1]), "com.safecontracts.enterprise.dev", "dev"),
    (Path(sys.argv[2]), "com.safecontracts.enterprise.staging", "staging"),
    (Path(sys.argv[3]), "com.safecontracts.enterprise", "production"),
]
legacy_package = "com.safecontracts.safecontracts_mobile"
legacy_app_id = "1:744938686052:android:1710fdbe24fe02cbc00171"
seen_app_ids = set()

for path, expected_package, flavor in configs:
    try:
        data = json.loads(path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as exc:
        raise SystemExit(f"FAIL: invalid ESC Firebase config for {flavor}: {exc}")
    clients = data.get("client") or []
    match = None
    all_packages = set()
    for client in clients:
        if not isinstance(client, dict):
            continue
        info = client.get("client_info", {})
        package = info.get("android_client_info", {}).get("package_name")
        if package:
            all_packages.add(package)
        if package == expected_package:
            match = client
    if match is None:
        raise SystemExit(
            f"FAIL: {flavor} Firebase config must contain Android app {expected_package}; "
            f"found {sorted(all_packages)}"
        )
    app_id = str(match.get("client_info", {}).get("mobilesdk_app_id", "")).strip()
    if not app_id:
        raise SystemExit(f"FAIL: {flavor} Firebase config has no mobilesdk_app_id")
    if app_id == legacy_app_id:
        raise SystemExit(f"FAIL: {flavor} ESC Firebase app reuses the Safe Contract mobile app id")
    if app_id in seen_app_ids:
        raise SystemExit("FAIL: ESC dev/staging/production must use distinct Firebase Android app registrations")
    seen_app_ids.add(app_id)
    if expected_package == legacy_package:
        raise SystemExit("FAIL: ESC Firebase config must never target the Safe Contract package")

print("ESC Firebase Android identities validated")
PY

cd "$MOBILE"
rm -rf android
flutter create \
  --platforms=android \
  --org com.safecontracts \
  --project-name safecontracts_mobile \
  .

cp "$TEMPLATE" android/app/build.gradle.kts

for flavor in dev staging production; do
  mkdir -p "android/app/src/$flavor"
done
cp "$FIREBASE_DEV" android/app/src/dev/google-services.json
cp "$FIREBASE_STAGING" android/app/src/staging/google-services.json
cp "$FIREBASE_PRODUCTION" android/app/src/production/google-services.json

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

rm -rf android/app/src/main/kotlin/com/safecontracts/safecontracts_mobile
MAIN_ACTIVITY_TARGET="android/app/src/main/kotlin/com/safecontracts/enterprise/MainActivity.kt"
mkdir -p "$(dirname "$MAIN_ACTIVITY_TARGET")"
cp "$MAIN_ACTIVITY_TEMPLATE" "$MAIN_ACTIVITY_TARGET"

ICON_TARGET="android/app/src/main/res/drawable/enterprise_safe_contracts_launcher.xml"
ICON_FOREGROUND_TARGET="android/app/src/main/res/drawable/enterprise_safe_contracts_launcher_foreground.xml"
ICON_BACKGROUND_TARGET="android/app/src/main/res/drawable/enterprise_safe_contracts_launcher_background.xml"
SPLASH_TARGET="android/app/src/main/res/drawable/enterprise_safe_contracts_splash.xml"
LEGACY_MIPMAP_TARGET="android/app/src/main/res/mipmap-anydpi/ic_launcher_enterprise.xml"
ADAPTIVE_MIPMAP_TARGET="android/app/src/main/res/mipmap-anydpi-v26/ic_launcher_enterprise.xml"
mkdir -p \
  "$(dirname "$ICON_TARGET")" \
  "$(dirname "$LEGACY_MIPMAP_TARGET")" \
  "$(dirname "$ADAPTIVE_MIPMAP_TARGET")"
cp "$ICON_SOURCE" "$ICON_TARGET"
cp "$ICON_FOREGROUND_SOURCE" "$ICON_FOREGROUND_TARGET"
cp "$ICON_BACKGROUND_SOURCE" "$ICON_BACKGROUND_TARGET"
cp "$SPLASH_SOURCE" "$SPLASH_TARGET"
cp "$ICON_SOURCE" "$LEGACY_MIPMAP_TARGET"
cp "$ADAPTIVE_ICON_SOURCE" "$ADAPTIVE_MIPMAP_TARGET"

MANIFEST="android/app/src/main/AndroidManifest.xml"
if [[ ! -f "$MANIFEST" ]]; then
  echo "FAIL: Flutter did not generate AndroidManifest.xml" >&2
  exit 1
fi
python3 - "$MANIFEST" <<'PY'
from pathlib import Path
import re
import sys

path = Path(sys.argv[1])
text = path.read_text(encoding="utf-8")
text = re.sub(r'android:label="[^"]+"', 'android:label="@string/app_name"', text, count=1)
text = re.sub(r'android:icon="@[^"]+"', 'android:icon="@mipmap/ic_launcher_enterprise"', text, count=1)
text = re.sub(r'android:roundIcon="@[^"]+"', 'android:roundIcon="@mipmap/ic_launcher_enterprise"', text, count=1)

permissions = ["android.permission.INTERNET", "android.permission.POST_NOTIFICATIONS"]
for permission in permissions:
    if permission not in text:
        application = re.search(r'(?m)^([ \t]*)<application\b', text)
        if application is None:
            raise SystemExit("FAIL: AndroidManifest.xml does not contain an <application> element")
        indent = application.group(1)
        text = text[:application.start()] + f'{indent}<uses-permission android:name="{permission}" />\n' + text[application.start():]

metadata = '''
        <meta-data
            android:name="com.google.firebase.messaging.default_notification_channel_id"
            android:value="enterprise_safe_contracts_alerts" />
        <meta-data
            android:name="com.google.firebase.messaging.default_notification_icon"
            android:resource="@drawable/enterprise_safe_contracts_launcher_foreground" />
'''
if "com.google.firebase.messaging.default_notification_channel_id" not in text:
    match = re.search(r'(?m)^([ \t]*)</application>', text)
    if match is None:
        raise SystemExit("FAIL: AndroidManifest.xml does not contain </application>")
    text = text[:match.start()] + metadata + text[match.start():]

# Reserve an ESC-only custom deep-link scheme. It cannot collide with Safe Contract.
if 'android:scheme="esc-safecontracts"' not in text:
    activity_close = re.search(r'(?m)^([ \t]*)</activity>', text)
    if activity_close is None:
        raise SystemExit("FAIL: AndroidManifest.xml does not contain </activity>")
    intent = '''
            <intent-filter>
                <action android:name="android.intent.action.VIEW" />
                <category android:name="android.intent.category.DEFAULT" />
                <category android:name="android.intent.category.BROWSABLE" />
                <data android:scheme="esc-safecontracts" />
            </intent-filter>
'''
    text = text[:activity_close.start()] + intent + text[activity_close.start():]

path.write_text(text, encoding="utf-8")
PY

# Replace Flutter's generic launch background with an explicit ESC splash resource.
# Android 12+ uses the application's isolated adaptive launcher identity when its
# platform splash theme does not override the animated icon.
python3 - "android/app/src/main/res" <<'PY'
from pathlib import Path
import sys

res_root = Path(sys.argv[1])
style_files = sorted(res_root.glob("values*/styles.xml"))
if not style_files:
    raise SystemExit("FAIL: generated Android resources contain no styles.xml")

replaced = 0
for path in style_files:
    text = path.read_text(encoding="utf-8")
    if "@drawable/launch_background" in text:
        text = text.replace(
            "@drawable/launch_background",
            "@drawable/enterprise_safe_contracts_splash",
        )
        path.write_text(text, encoding="utf-8")
        replaced += 1

if replaced == 0:
    raise SystemExit("FAIL: generated LaunchTheme has no replaceable launch background")
PY

for required in \
  android/settings.gradle.kts \
  android/gradlew \
  android/gradle/wrapper/gradle-wrapper.jar \
  android/app/build.gradle.kts \
  android/app/src/dev/google-services.json \
  android/app/src/staging/google-services.json \
  android/app/src/production/google-services.json \
  android/app/src/main/AndroidManifest.xml \
  "$MAIN_ACTIVITY_TARGET" \
  "$ICON_TARGET" \
  "$ICON_FOREGROUND_TARGET" \
  "$ICON_BACKGROUND_TARGET" \
  "$SPLASH_TARGET" \
  "$LEGACY_MIPMAP_TARGET" \
  "$ADAPTIVE_MIPMAP_TARGET"; do
  if [[ ! -e "$required" ]]; then
    echo "FAIL: generated ESC Android scaffold missing $required" >&2
    exit 1
  fi
done

grep -Fq 'applicationId = "com.safecontracts.enterprise"' android/app/build.gradle.kts
grep -Fq 'applicationIdSuffix = ".dev"' android/app/build.gradle.kts
grep -Fq 'applicationIdSuffix = ".staging"' android/app/build.gradle.kts
grep -Fq 'android:label="@string/app_name"' "$MANIFEST"
grep -Fq 'android:icon="@mipmap/ic_launcher_enterprise"' "$MANIFEST"
grep -Fq 'enterprise_safe_contracts_alerts' "$MANIFEST"
grep -Fq 'esc-safecontracts' "$MANIFEST"
grep -Fq 'enterprise_safecontracts/notifications' "$MAIN_ACTIVITY_TARGET"
grep -Fq '<adaptive-icon' "$ADAPTIVE_MIPMAP_TARGET"
grep -R -Fq '@drawable/enterprise_safe_contracts_splash' android/app/src/main/res/values* || {
  echo "FAIL: ESC LaunchTheme does not use the isolated splash resource" >&2
  exit 1
}
grep -Fq 'id("com.google.gms.google-services") version "4.4.4" apply false' "$SETTINGS"

echo "Enterprise Safe Contracts Android scaffold bootstrapped with isolated package/flavors, Firebase apps, adaptive launcher/splash, notifications, deep links and signing namespace."
