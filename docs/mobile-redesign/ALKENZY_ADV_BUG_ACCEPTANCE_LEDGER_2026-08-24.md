# ALKENZY ADV — Final Bug Acceptance QA Ledger

[ALKENZY-ADV-BUG-CLOSURE-LOCK]

REGISTER: `docs/mobile-redesign/ALKENZY_ADV_BUG_UX_REGISTER_2026-08-24.md`  
TOTAL: 101  
MODE: QA ACCEPTANCE ONLY  
NO-PRODUCTION-FEATURE-OWNERSHIP: YES  
NO-WEAKENED-ASSERTIONS: YES  
NO-FAKE-EVIDENCE: YES

## QA authority

- QA may promote only `[READY-FOR-QA] -> [QA-PASS]`.
- Lead alone promotes integrated `[QA-PASS] -> [CLOSED]`.
- No Worker claim, screenshot-only patch, local-only behavior, or stale CI result is sufficient for QA PASS.
- Functional bugs require proof of the real UI/API/data/session path.
- All applicable bugs require Arabic RTL, English LTR, widths `320 / 360 / 375 / 390 / 412 / 430`, Loading/Empty/Error/Retry, state preservation where applicable, no overflow/clipping, screenshot evidence, `flutter analyze` GREEN, and `flutter test` GREEN.

## Initial exact-source checkpoint

- Official closure base: `1984f91b95387934d300a0df4336f6d10a1dbfce`.
- QA branch: `qa/alkenzy-register-acceptance`, created from the exact official closure base.
- At ledger creation, no bugfix PR matching the B001-B101 closure register was available for acceptance.
- `bugfix/alkenzy-register-w3-profile-auth` exists but is identical to the official base (`0` commits ahead).
- `bugfix/alkenzy-register-w5-contracts-lists` exists but is identical to the official base (`0` commits ahead).
- Therefore no bug is promoted to `[READY-FOR-QA]`, `[QA-PASS]`, or `[CLOSED]` at ledger creation.

## P0 acceptance queue

| Bug | Owner | Required acceptance proof | Initial QA state |
|---|---|---|---|
| B001 | W4 | Authoritative overdue/due/upcoming/paid fixtures matching real payment contract and due dates/amounts | [TODO] |
| B002 | W1 | Real customer related-data success/failure/retry through API/plugin permissions mapping | [TODO] |
| B003 | W1 | Real capability-driven Drawer destinations; no empty/placeholder drawer | [TODO] |
| B036 | W3 | Real logout/token/session cleanup, return to Login, no unauthorized back-navigation return | [TODO] |
| B084 | W5 | API-backed `page/pageSize/totalPages` with multiple pages and returned-record verification | [TODO] |

## Acceptance ledger

| ID | Priority | Owner | Reference | Status | QA evidence / return note |
|---|---|---|---|---|---|
| B001 | P0 | W4 | REF-PAYDET-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B002 | P0 | W1 | REF-CUST-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B003 | P0 | W1 | REF-SIDEBAR-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B004 | P1 | W4 | REF-PAYDET-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B005 | P1 | W1 | REF-CUST-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B006 | P1 | W1 | REF-LANDING-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B007 | P1 | W1 | REF-CUST-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B008 | P1 | W1 | REF-CUST-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B009 | P2 | W1 | REF-DASH-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B010 | P2 | W1 | REF-DASH-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B011 | P1 | W4 | REF-PAY-SEQ | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B012 | P1 | W2 | REF-DASH-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B013 | P1 | W1 | REF-SIDEBAR-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B014 | P1 | W2 | REF-DASH-COMPACT | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B015 | P1 | W2 | REF-DASH-LATEST | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B016 | P1 | W2 | REF-DASH-LATEST | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B017 | P1 | W2 | REF-DASH-COMPACT | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B018 | P1 | W2 | REF-DASH-COMPACT | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B019 | P1 | W2 | REF-DASH-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B020 | P1 | W2 | REF-DASH-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B021 | P2 | W2 | REF-DASH-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B022 | P1 | W2 | REF-DASH-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B023 | P1 | W2 | REF-DASH-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B024 | P2 | W2 | REF-DASH-LATEST | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B025 | P2 | W1 | REF-DASH-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B026 | P2 | W1 | REF-DASH-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B027 | P1 | W2 | REF-DASH-COMPACT | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B028 | P1 | W2 | REF-DASH-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B029 | P2 | W2 | REF-DASH-LATEST | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B030 | P1 | W2 | REF-DASH-COMPACT | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B031 | P1 | W3 | REF-PROFILE-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B032 | P1 | W3 | REF-PROFILE-TARGET | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B033 | P1 | W3 | REF-PROFILE-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B034 | P1 | W3 | REF-PROFILE-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B035 | P1 | W3 | REF-PROFILE-TARGET | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B036 | P0 | W3 | REF-PROFILE-TARGET | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B037 | P1 | W3 | REF-PROFILE-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B038 | P1 | W3 | REF-PROFILE-TARGET | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B039 | P1 | W3 | REF-PROFILE-TARGET | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B040 | P2 | W3 | REF-PROFILE-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B041 | P2 | W3 | REF-PROFILE-TARGET | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B042 | P1 | W3 | REF-PROFILE-TARGET | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B043 | P2 | W3 | REF-PROFILE-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B044 | P2 | W3 | REF-PROFILE-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B045 | P2 | W3 | REF-PROFILE-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B046 | P1 | W3 | REF-PROFILE-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B047 | P2 | W3 | REF-PROFILE-TARGET | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B048 | P1 | W3 | REF-PROFILE-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B049 | P1 | W3 | REF-PROFILE-TARGET | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B050 | P1 | W3 | REF-PROFILE-TARGET | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B051 | P2 | W3 | REF-PROFILE-TARGET | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B052 | P1 | W3 | REF-PROFILE-TARGET | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B053 | P1 | W3 | REF-PROFILE-TARGET | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B054 | P1 | W3 | REF-PROFILE-TARGET | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B055 | P1 | W4 | REF-PAYDET-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B056 | P1 | W4 | REF-PAYDET-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B057 | P1 | W4 | REF-PAYDET-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B058 | P1 | W4 | REF-PAYDET-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B059 | P2 | W4 | REF-PAYDET-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B060 | P2 | W4 | REF-PAYDET-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B061 | P1 | W4 | REF-PAYDET-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B062 | P1 | W4 | REF-PAYDET-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B063 | P1 | W4 | REF-PAYDET-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B064 | P2 | W4 | REF-PAYDET-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B065 | P2 | W4 | REF-PAYDET-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B066 | P1 | W4 | REF-PAYDET-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B067 | P2 | W4 | REF-PAYDET-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B068 | P1 | W4 | REF-PAYDET-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B069 | P2 | W4 | REF-PAYDET-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B070 | P1 | W4 | REF-PAYDET-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B071 | P1 | W2 | REF-DASH-TABS | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B072 | P1 | W2 | REF-DASH-TABS | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B073 | P1 | W2 | REF-DASH-TABS | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B074 | P1 | W2 | REF-DASH-TABS | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B075 | P2 | W2 | REF-DASH-TABS | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B076 | P1 | W2 | REF-DASH-TABS | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B077 | P1 | W2 | REF-DASH-TABS | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B078 | P2 | W2 | REF-DASH-TABS | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B079 | P1 | W2 | REF-DASH-TABS | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B080 | P1 | W2 | REF-DASH-TABS | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B081 | P1 | W5 | REF-CONTRACTS-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B082 | P1 | W5 | REF-CONTRACTS-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B083 | P1 | W5 | REF-CONTRACTS-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B084 | P0 | W5 | REF-CONTRACTS-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B085 | P1 | W5 | REF-CONTRACTS-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B086 | P2 | W5 | REF-CONTRACTS-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B087 | P2 | W5 | REF-CONTRACTS-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B088 | P1 | W5 | REF-CONTRACTS-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B089 | P1 | W5 | REF-CONTRACTS-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B090 | P1 | W5 | REF-CONTRACTS-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B091 | P1 | W5 | REF-CONTRACTS-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B092 | P1 | W5 | REF-CONTRACTS-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B093 | P1 | W5 | REF-CONTRACTS-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B094 | P1 | W5 | REF-CONTRACTS-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B095 | P1 | W5 | REF-CONTRACTS-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B096 | P1 | W5 | REF-CONTRACTS-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B097 | P1 | W5 | REF-CONTRACTS-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B098 | P1 | W5 | REF-CONTRACTS-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B099 | P2 | W5 | REF-CONTRACTS-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B100 | P1 | W5 | REF-CONTRACTS-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |
| B101 | P1 | W5 | REF-CONTRACTS-01 | [TODO] | Awaiting owner READY-FOR-QA handoff and exact-head evidence. |

## QA failure template

```text
BUG ID:
REPRODUCTION:
EXPECTED:
ACTUAL:
ROOT/LIKELY LAYER:
EXACT ROUTE:
EXACT FILE IF KNOWN:
SCREEN SIZE:
LANGUAGE:
EVIDENCE:
RETURN TO OWNER:
```

## Running report

QA PASS: 0  
QA FAILED: 0  
READY FOR QA WAITING: 0  
P0 PASS: 0 / 5  
P0 FAILED: 0 / 5  
CURRENT INTEGRATED HEAD TESTED: none beyond closure base initialization  
CI STATUS: not yet attributable to an integrated bugfix candidate  
SCREENSHOT STATUS: no acceptance screenshot claimed at ledger creation  
NEXT BUG: B001 as soon as a testable owner handoff exists; otherwise the first available P0 in order B001, B002, B003, B036, B084.

## Update rule

Every QA result must update the affected row with exact tested head, route, language, width, functional-path evidence, screenshots/artifacts, CI run, and deterministic failure return when applicable. Do not replace evidence with narrative claims.
