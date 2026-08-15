#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

find "$ROOT/wordpress-plugin/safecontracts" "$ROOT/tests/php" -name '*.php' -print0 \
  | xargs -0 -n1 php -l >/dev/null

php "$ROOT/tests/php/run.php"
php "$ROOT/tests/php/contracts_schema.php"
php "$ROOT/tests/php/contracts_workflow.php"
php "$ROOT/tests/php/contracts_financials.php"
php "$ROOT/tests/php/contracts_history.php"
php "$ROOT/tests/php/contracts_validation_017_021.php"
