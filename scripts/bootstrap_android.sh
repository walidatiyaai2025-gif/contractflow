#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
MOBILE="$ROOT/mobile"
TEMPLATE="$ROOT/mobile/android-release/app-build.gradle.kts"

if ! command -v flutter >/dev/null 2>&1; then
  echo "FAIL: flutter is required to bootstrap the Android platform" >&2
  exit 1
fi

if [[ ! -f "$TEMPLATE" ]]; then
  echo "FAIL: committed Android release template is missing: $TEMPLATE" >&2
  exit 1
fi

cd "$MOBILE"

# Flutter owns the platform boilerplate version. Recreate it from the exact
# Flutter stable toolchain used by CI, then replace the app build contract with
# the repository's fail-closed release signing configuration.
rm -rf android
flutter create \
  --platforms=android \
  --org com.safecontracts \
  --project-name safecontracts_mobile \
  .

cp "$TEMPLATE" android/app/build.gradle.kts

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
text = text.replace('android:label="safecontracts_mobile"', 'android:label="SafeContracts"')

permission = 'android.permission.INTERNET'
if permission not in text:
    application = re.search(r'(?m)^([ \t]*)<application\b', text)
    if application is None:
        raise SystemExit("FAIL: AndroidManifest.xml does not contain an <application> element")
    indent = application.group(1)
    permission_line = (
        f'{indent}<uses-permission android:name="{permission}" />\n\n'
    )
    text = text[: application.start()] + permission_line + text[application.start() :]

if permission not in text:
    raise SystemExit("FAIL: Android release manifest is missing INTERNET permission")

path.write_text(text, encoding="utf-8")
PY

for required in \
  android/settings.gradle.kts \
  android/gradlew \
  android/gradle/wrapper/gradle-wrapper.jar \
  android/app/build.gradle.kts \
  android/app/src/main/AndroidManifest.xml; do
  if [[ ! -e "$required" ]]; then
    echo "FAIL: generated Android scaffold missing $required" >&2
    exit 1
  fi
done

grep -Fq 'android.permission.INTERNET' "$MANIFEST" || {
  echo "FAIL: Android release manifest is missing INTERNET permission" >&2
  exit 1
}

echo "SafeContracts Android scaffold bootstrapped with release-signing and INTERNET-permission contracts."
