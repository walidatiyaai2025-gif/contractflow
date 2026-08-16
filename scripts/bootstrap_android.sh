#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
MOBILE="$ROOT/mobile"
TEMPLATE="$ROOT/mobile/android-release/app-build.gradle.kts"
FIREBASE_CONFIG="$ROOT/mobile/android-release/google-services.json"
ALKENZY_ICON_SOURCE="$ROOT/mobile/android-release/alkenzy_launcher.png"
MAIN_ACTIVITY_TEMPLATE="$ROOT/mobile/android-release/MainActivity.kt"
ALKENZY_ICON_SHA256="e703241650eeb984791c4715b4243bf96ba5b273b78eb2e25cd3640c188c57c9"

if ! command -v flutter >/dev/null 2>&1; then
  echo "FAIL: flutter is required to bootstrap the Android platform" >&2
  exit 1
fi

for required_source in "$TEMPLATE" "$FIREBASE_CONFIG" "$ALKENZY_ICON_SOURCE" "$MAIN_ACTIVITY_TEMPLATE"; do
  if [[ ! -f "$required_source" ]]; then
    echo "FAIL: committed Android release source is missing: $required_source" >&2
    exit 1
  fi
done

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
# signing, networking, Firebase, notification presentation, and Safe Contracts
# runtime contracts. The launcher icon is the supplied Alkenzy Advertising mark.
rm -rf android
flutter create \
  --platforms=android \
  --org com.safecontracts \
  --project-name safecontracts_mobile \
  .

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

python3 - "$LAUNCHER_ICON" "$ALKENZY_ICON_SHA256" <<'PY'
from hashlib import sha256
from pathlib import Path
import sys

path = Path(sys.argv[1])
expected = sys.argv[2]
content = path.read_bytes()
if not content.startswith(b"\x89PNG\r\n\x1a\n"):
    raise SystemExit("FAIL: Alkenzy launcher icon is not a valid PNG resource")
actual = sha256(content).hexdigest()
if actual != expected:
    raise SystemExit(
        f"FAIL: Alkenzy launcher icon bytes changed (expected {expected}, got {actual})"
    )
PY

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

metadata = '''
        <meta-data
            android:name="com.google.firebase.messaging.default_notification_channel_id"
            android:value="safe_contracts_alerts" />
        <meta-data
            android:name="com.google.firebase.messaging.default_notification_icon"
            android:resource="@android:drawable/ic_dialog_info" />
'''
if 'com.google.firebase.messaging.default_notification_channel_id' not in text:
    match = re.search(r'(?m)^([ \t]*)</application>', text)
    if match is None:
        raise SystemExit("FAIL: AndroidManifest.xml does not contain </application>")
    text = text[:match.start()] + metadata + text[match.start():]

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

echo "Alkenzy ADV Android scaffold bootstrapped with supplied Alkenzy launcher icon, high-importance tray notifications, release signing, INTERNET, notifications, and Firebase contracts."
