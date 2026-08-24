#!/usr/bin/env python3
from pathlib import Path


def replace_once(path: str, old: str, new: str) -> None:
    p = Path(path)
    text = p.read_text()
    count = text.count(old)
    if count != 1:
        raise SystemExit(f'{path}: expected 1 match, got {count}: {old!r}')
    p.write_text(text.replace(old, new, 1))


replace_once(
    'mobile/lib/features/contracts/contracts.dart',
    "      if (counterpartyType != null) 'counterparty_type': counterpartyType!,",
    "      'counterparty_type': ?counterpartyType,",
)

for old, new in [
    ("        if (normalizedNote != null) 'note': normalizedNote,", "        'note': ?normalizedNote,"),
    ("        if (normalizedPromised != null) 'promised_date': normalizedPromised,", "        'promised_date': ?normalizedPromised,"),
    ("        if (normalizedDeferred != null) 'deferred_until': normalizedDeferred,", "        'deferred_until': ?normalizedDeferred,"),
]:
    replace_once('mobile/lib/features/followups/followups.dart', old, new)

for old, new in [
    ("        if (normalizedReference != null) 'reference': normalizedReference,", "        'reference': ?normalizedReference,"),
    ("        if (proofMediaId != null) 'proof_media_id': proofMediaId,", "        'proof_media_id': ?proofMediaId,"),
]:
    replace_once('mobile/lib/features/payments/payments.dart', old, new)

wildcard_files = [
    'mobile/lib/features/contracts/contracts_screen.dart',
    'mobile/lib/features/contracts/premium_contract_details_screen.dart',
    'mobile/lib/features/customers/customers_screen.dart',
    'mobile/lib/features/followups/followups_screen.dart',
    'mobile/lib/features/payments/payments_screen.dart',
    'mobile/lib/features/records/mobile_record_editor_screen.dart',
    'mobile/lib/features/suppliers/suppliers_screen.dart',
    'mobile/lib/features/ui/safecontracts_components.dart',
]
for path in wildcard_files:
    p = Path(path)
    text = p.read_text()
    text = text.replace('(_, __, ___)', '(_, _, _)')
    text = text.replace('(_, __)', '(_, _)')
    p.write_text(text)
