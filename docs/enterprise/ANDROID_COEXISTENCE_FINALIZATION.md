# Finalizing ESC Android coexistence UAT evidence

## Boundary

`finalize_esc_android_coexistence_evidence.py` does not perform any device test. It only turns an exact-source `PENDING` draft into a candidate final `PASS` record after the real-device operator has separately completed and referenced every remaining runtime scenario.

The input draft must already contain objective PASS evidence for:

- `dual_install`
- `independent_launch`
- `deep_link_isolation`

The finalizer refuses to invent or overwrite those objective results.

## Required real-device evidence

Before running the finalizer, complete and retain explicit evidence for all of the following on the same release candidate/source SHA:

- session/local-state isolation between Safe Contract and ESC;
- Safe Contract-only push delivery;
- ESC-only push delivery;
- independent app update behavior;
- clear-data/uninstall isolation.

Also retain the approved ESC Firebase Android identity reference and the release-level device, business UAT, coexistence, and Firebase evidence references.

A reference must identify completed evidence. Placeholder values containing terms such as `PENDING`, `REPLACE_WITH`, `TBD`, or `TODO` are rejected.

## Command

```bash
python3 scripts/finalize_esc_android_coexistence_evidence.py \
  --draft /path/to/esc-android-coexistence-draft.json \
  --source-sha <EXACT_40_CHAR_ESC_SHA> \
  --tested-at-utc 2026-08-17T19:00:00Z \
  --session-isolation-evidence '<REFERENCE>' \
  --safe-only-push-evidence '<REFERENCE>' \
  --esc-only-push-evidence '<REFERENCE>' \
  --independent-update-evidence '<REFERENCE>' \
  --clear-data-uninstall-evidence '<REFERENCE>' \
  --esc-firebase-reference '<APPROVED_ESC_FIREBASE_IDENTITY_REFERENCE>' \
  --device-evidence '<DEVICE_EVIDENCE_REFERENCE>' \
  --business-uat-evidence '<BUSINESS_UAT_REFERENCE>' \
  --coexistence-evidence '<COEXISTENCE_EVIDENCE_REFERENCE>' \
  --firebase-evidence '<FIREBASE_DELIVERY_EVIDENCE_REFERENCE>' \
  --output /path/to/esc-android-coexistence-final.json
```

The output is written only after the existing `validate_esc_android_coexistence_evidence.py` validator accepts all eight checks as PASS for the exact source SHA.

## What the finalizer does not prove

The finalizer validates record completeness, identity continuity, source-SHA continuity, explicit evidence references, and compatibility with the release validator. It does not inspect the referenced video, screenshot, device log, Firebase console record, ticket, or business sign-off for truthfulness. The real-device tester and release owner remain responsible for the underlying evidence.

Do not use this tool to convert unexecuted scenarios into PASS. If any runtime scenario is incomplete, keep the draft `PENDING` and do not publish the production release.
