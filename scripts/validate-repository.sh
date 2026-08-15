#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

required_paths=(
  ".editorconfig"
  ".gitattributes"
  ".gitignore"
  "README.md"
  "docs/MASTER_PLAN.md"
  "docs/DEVELOPMENT_STANDARDS.md"
  "docs/DECISIONS.md"
  "wordpress-plugin/safecontracts/safecontracts.php"
  "mobile/pubspec.yaml"
  "scripts/test-php.sh"
)

for path in "${required_paths[@]}"; do
  if [[ ! -e "$path" ]]; then
    echo "Repository standard violation: missing $path" >&2
    exit 1
  fi
done

if git ls-files | grep -E '(^|/)(\.env($|\.)|node_modules/|vendor/|\.idea/|\.vscode/)' >/dev/null; then
  echo "Repository standard violation: generated/local environment content is tracked." >&2
  exit 1
fi

if git ls-files | grep -Ei '\.(pem|p12|pfx|key)$|service[-_]?account.*\.json$' >/dev/null; then
  echo "Repository standard violation: secret/key material filename is tracked." >&2
  exit 1
fi

if git grep -I -n -E -- '-----BEGIN ([A-Z0-9]+ )?PRIVATE KEY-----' -- . >/dev/null; then
  echo "Repository standard violation: private-key material detected." >&2
  exit 1
fi

if ! grep -q "defined('ABSPATH')" wordpress-plugin/safecontracts/safecontracts.php; then
  echo "Repository standard violation: WordPress plugin entrypoint lacks direct-access guard." >&2
  exit 1
fi

if ! grep -q 'safecontracts/v1' docs/DEVELOPMENT_STANDARDS.md; then
  echo "Repository standard violation: versioned REST namespace is not documented." >&2
  exit 1
fi

echo "SafeContracts repository policy validation passed."
