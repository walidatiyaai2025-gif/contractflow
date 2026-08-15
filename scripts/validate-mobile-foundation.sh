#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

required_paths=(
  "mobile/pubspec.yaml"
  "mobile/analysis_options.yaml"
  "mobile/lib/main.dart"
  "mobile/lib/app/safe_contracts_app.dart"
  "mobile/lib/core/config/app_config.dart"
  "mobile/lib/core/network/api_paths.dart"
  "mobile/test/app_config_test.dart"
)

for path in "${required_paths[@]}"; do
  if [[ ! -f "$path" ]]; then
    echo "Mobile foundation violation: missing $path" >&2
    exit 1
  fi
done

if ! grep -q 'SAFECONTRACTS_API_BASE_URL' mobile/lib/core/config/app_config.dart; then
  echo "Mobile foundation violation: API URL must come from deployment configuration." >&2
  exit 1
fi

if grep -R -n -E 'https?://[A-Za-z0-9]' mobile/lib >/dev/null; then
  echo "Mobile foundation violation: hard-coded server URL found in mobile source." >&2
  exit 1
fi

if grep -R -n -Ei '\b(mysql|mysqli|wpdb)\b' mobile/lib >/dev/null; then
  echo "Mobile foundation violation: mobile must not access WordPress/MySQL internals." >&2
  exit 1
fi

if ! grep -q "wp-json/safecontracts/v1" mobile/lib/core/network/api_paths.dart; then
  echo "Mobile foundation violation: canonical SafeContracts REST namespace missing." >&2
  exit 1
fi

echo "SafeContracts mobile foundation validation passed."
