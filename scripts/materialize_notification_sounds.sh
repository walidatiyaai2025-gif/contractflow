#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TMP="${RUNNER_TEMP:-${TMPDIR:-/tmp}}/alkenzy-owner-notification-sounds"
PLUGIN_OUT="$ROOT/wordpress-plugin/safecontracts/assets/sounds"
ANDROID_OUT="$ROOT/mobile/android-release/raw"
MISMATCH=0

rm -rf "$TMP"
mkdir -p "$TMP" "$PLUGIN_OUT" "$ANDROID_OUT"

fetch_verified() {
  local key="$1"
  local url="$2"
  local expected_sha256="$3"
  local target="$TMP/$key.mp3"

  curl --fail --location --silent --show-error \
    --retry 4 --retry-delay 2 --connect-timeout 20 \
    "$url" -o "$target"

  local actual_sha256
  actual_sha256="$(sha256sum "$target" | awk '{print $1}')"
  echo "$key expected=$expected_sha256 actual=$actual_sha256 source=$url"
  if [[ "$actual_sha256" != "$expected_sha256" ]]; then
    echo "MISMATCH: $key public copy does not match the owner-supplied bytes" >&2
    MISMATCH=1
  fi
}

# These immutable public copies were verified byte-for-byte against the
# owner-uploaded files mounted in the release workspace on 2026-08-27.
fetch_verified \
  banknote_counter \
  "https://raw.githubusercontent.com/KingAndrew/same-day-copay-ui/5c7f7cfc99bdcef4f95597bf3f9eccc73e52b3f8/attached_assets/banknote-counter-106014.mp3" \
  "a4ad3ee6d20c83d352e7cd8d9a937998862eebca8e833df7d1e1c2f8ca7511f4"

fetch_verified \
  cashier_ka_ching \
  "https://raw.githubusercontent.com/Rahmid93421/Runner_Game/5c2e48cf15c3257824108f144c94fa4abcc07017/assets/sounds/cashier-quotka-chingquot-sound-effect-129698.mp3" \
  "ae6702f67ca39475db2f82ea71cb93e681049785813e12b0dd5b801485a45ee7"

fetch_verified \
  coin_drop \
  "https://raw.githubusercontent.com/tahmidislam2-star/millie_jam/01cc0e104c2cf32033bc5785a3abdccf79051d73/two-pieces-in-a-cup/sounds/universfield-coin-drop-229314.mp3" \
  "af8a9ec4d8b718703980c28b58c851aacf515da9fc1e2d90ac592d1295d0ef76"

if [[ "$MISMATCH" -ne 0 ]]; then
  echo "FAIL: one or more immutable public copies differ from the owner-supplied notification sound bytes" >&2
  exit 1
fi

install -m 0644 "$TMP/banknote_counter.mp3" "$PLUGIN_OUT/banknote-counter-106014.mp3"
install -m 0644 "$TMP/cashier_ka_ching.mp3" "$PLUGIN_OUT/cashier-quotka-chingquot-sound-effect-129698.mp3"
install -m 0644 "$TMP/coin_drop.mp3" "$PLUGIN_OUT/universfield-coin-drop-229314.mp3"

install -m 0644 "$TMP/banknote_counter.mp3" "$ANDROID_OUT/banknote_counter.mp3"
install -m 0644 "$TMP/cashier_ka_ching.mp3" "$ANDROID_OUT/cashier_ka_ching.mp3"
install -m 0644 "$TMP/coin_drop.mp3" "$ANDROID_OUT/coin_drop.mp3"

for pair in \
  "$PLUGIN_OUT/banknote-counter-106014.mp3:a4ad3ee6d20c83d352e7cd8d9a937998862eebca8e833df7d1e1c2f8ca7511f4" \
  "$PLUGIN_OUT/cashier-quotka-chingquot-sound-effect-129698.mp3:ae6702f67ca39475db2f82ea71cb93e681049785813e12b0dd5b801485a45ee7" \
  "$PLUGIN_OUT/universfield-coin-drop-229314.mp3:af8a9ec4d8b718703980c28b58c851aacf515da9fc1e2d90ac592d1295d0ef76" \
  "$ANDROID_OUT/banknote_counter.mp3:a4ad3ee6d20c83d352e7cd8d9a937998862eebca8e833df7d1e1c2f8ca7511f4" \
  "$ANDROID_OUT/cashier_ka_ching.mp3:ae6702f67ca39475db2f82ea71cb93e681049785813e12b0dd5b801485a45ee7" \
  "$ANDROID_OUT/coin_drop.mp3:af8a9ec4d8b718703980c28b58c851aacf515da9fc1e2d90ac592d1295d0ef76"; do
  path="${pair%%:*}"
  expected="${pair##*:}"
  actual="$(sha256sum "$path" | awk '{print $1}')"
  [[ "$actual" == "$expected" ]] || {
    echo "FAIL: installed notification sound checksum mismatch: $path" >&2
    exit 1
  }
done

echo "Owner notification sounds materialized and checksum-verified for WordPress and Android."
