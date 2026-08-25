# ALKENZY ADV — LOCKED DESIGN REFERENCE CONSTITUTION

## P0 GOVERNANCE RULE — MANDATORY FOR ALL CONTRIBUTORS

The approved Alkenzy ADV WordPress Plugin redesign images are **PROJECT CONSTITUTION ASSETS**.

They are not temporary inspiration.

They are the permanent visual source of truth for the Premium Plugin Redesign.

Every human developer, AI agent, Codex worker, reviewer, QA worker, and future contributor MUST continue from these references.

---

# 1. CANONICAL REFERENCE LOCATION

All approved UI reference images MUST be stored permanently under:

```text
assets/design/plugin-redesign/reference/
```

Never overwrite an approved reference silently.

---

# 2. THESE FILES ARE LOCKED

Once a reference is approved and committed:

> IT IS IMMUTABLE BY DEFAULT.

No Agent may delete, replace, crop, recolor, overwrite, move, silently supersede, or ignore a locked reference because another style is preferred. A reference may only be superseded when the project owner explicitly approves a new visual baseline.

---

# 3. REFERENCE MANIFEST

The authoritative manifest is:

```text
assets/design/plugin-redesign/reference/REFERENCE_MANIFEST.json
```

Every locked image MUST have a SHA-256 checksum so future Agents and CI can detect accidental replacement.

---

# 4. CONSTITUTION DOCUMENT

This document is authoritative for approved design direction, locked references, reference-to-screen mapping, design tokens, typography, spacing, card/navigation/table/form/status/chart language, RTL, WordPress constraints, responsive behavior, acceptance and change control.

---

# 5. AGENTS.md MUST REFERENCE THE CONSTITUTION

Before modifying any Safe Contracts / Alkenzy ADV WordPress Admin UI, every contributor MUST read:

```text
docs/plugin-redesign/PLUGIN_UI_CONSTITUTION.md
docs/plugin-redesign/PLUGIN_REDESIGN_EXECUTION_PLAN.md
docs/plugin-redesign/PLUGIN_UI_SCREEN_MATRIX.md
docs/plugin-redesign/PLUGIN_UI_PROGRESS.md
assets/design/plugin-redesign/reference/REFERENCE_MANIFEST.json
```

The visual files under `assets/design/plugin-redesign/reference/` are the approved and locked design source of truth. Do not introduce a competing design language. Continue from the current implementation checkpoint rather than restarting completed work.

---

# 6. REQUIRED STARTUP PROCEDURE FOR EVERY AGENT

At the beginning of EVERY work session:

```text
1. Fetch latest main.
2. Read AGENTS.md.
3. Read PLUGIN_UI_CONSTITUTION.md.
4. Read PLUGIN_REDESIGN_EXECUTION_PLAN.md.
5. Read PLUGIN_UI_SCREEN_MATRIX.md.
6. Read PLUGIN_UI_PROGRESS.md.
7. Read REFERENCE_MANIFEST.json.
8. Verify locked reference files exist and validation passes.
9. Inspect latest screenshots and merged redesign PRs.
10. Determine the next unfinished screen inside your exact ownership.
```

Do not rely on chat memory. GitHub repository state is authoritative.

---

# 7. CONTINUE FROM LAST CHECKPOINT

Every future Agent MUST reconstruct:

```text
LAST APPROVED SCREEN
LAST IMPLEMENTED SCREEN
LAST VISUAL QA SCREEN
CURRENT IN-PROGRESS SCREEN
NEXT DEPENDENCY-SAFE SCREEN
```

It is forbidden to restart the redesign from Dashboard simply because the Agent is new.

---

# 8. PROGRESS CHECKPOINT

Continuously maintain:

```text
docs/plugin-redesign/PLUGIN_UI_PROGRESS.md
```

It records governance version, current scope, completed/ready/in-progress/not-started/blocked states, latest screenshots, mismatches, responsive/RTL issues and next exact task.

---

# 9. SCREEN MATRIX

The authoritative matrix is:

```text
docs/plugin-redesign/PLUGIN_UI_SCREEN_MATRIX.md
```

Every real plugin screen/state in redesign scope must have an ID, route/slug, PHP class/callback, exactly one owner, one Reference ID, implementation status, Visual QA, RTL, Responsive, Functional QA, Screenshot, PR and Approved fields.

Allowed implementation statuses are:

```text
NOT STARTED
IN PROGRESS
IMPLEMENTED
VISUAL QA
READY FOR LEAD
APPROVED
```

---

# 10. REFERENCE MAPPING IS MANDATORY

Every redesigned screen MUST state which locked Reference ID controls it. A screen cannot reach `APPROVED` without a Reference ID.

---

# 11. IMPLEMENTATION SCREENSHOTS

Store real implementation evidence under:

```text
docs/plugin-redesign/screenshots/
```

Each approved screen must have its controlling reference identified, real implementation screenshots and visual QA notes.

---

# 12. VISUAL ACCEPTANCE LOOP

Every screen follows:

```text
LOCKED REFERENCE
      ↓
IMPLEMENTATION
      ↓
REAL WORDPRESS RUNTIME
      ↓
SCREENSHOT
      ↓
SIDE-BY-SIDE COMPARISON
      ↓
FIX DIFFERENCES
      ↓
SECOND SCREENSHOT
      ↓
LEAD APPROVAL
```

No visual approval from code inspection alone.

---

# 13. LOCKED DESIGN SYSTEM

Agents may improve implementation quality but may NOT change the visual language. Constitution-level decisions are:

```text
Deep Navy Navigation
Warm Cream Background
White / Warm Surface Cards
Copper / Rose-Gold Accent
Green Positive State
Amber Warning
Red Overdue/Error
Rounded Premium Cards
Subtle Shadows
Compact Business Density
Arabic RTL First
Clean Financial Tables
Premium Form Sections
```

---

# 14. WORDPRESS CONSTITUTION

The references define appearance. WordPress defines runtime architecture. The redesign MUST remain a WordPress Admin Plugin and preserve:

```text
admin.php?page=
admin-post.php
wp_nonce
current_user_can()
admin_menu
admin_enqueue_scripts
WP Admin Bar
WordPress notices
WordPress accessibility behavior
RTL admin behavior
```

Do not transform the plugin into a disconnected frontend application merely to imitate the mockups.

---

# 15. DESIGN REFERENCE OVERRIDES PERSONAL PREFERENCE

An Agent may believe another UI is cleaner, more modern or easier. That is irrelevant unless the locked direction cannot technically work. The locked reference wins.

---

# 16. BUSINESS TRUTH OVERRIDES MOCK DATA

References control visual hierarchy, spacing, layout, colors, component styling, information density and navigation language.

Repository/backend controls real values, business rules, permissions, statuses, fields, actions and security.

Never copy fake values from a reference into production.

---

# 17. REFERENCE CHANGE CONTROL

A reference can change only when:

```text
1. Project owner explicitly requests a visual change.
2. Lead records the decision.
3. New file receives a new Reference ID.
4. REFERENCE_MANIFEST.json is updated.
5. SHA-256 is generated.
6. PLUGIN_UI_CONSTITUTION.md is updated.
7. SCREEN_MATRIX mapping is updated.
8. Impacted approved screens are marked for re-review.
```

Do not silently reuse an existing Reference ID for a different image.

---

# 18. CI GOVERNANCE

`scripts/validate-plugin-design-references.py` must verify the manifest, every locked file, SHA-256 checksums, duplicate IDs, required governance docs and exactly-one-owner matrix invariants. CI must fail if a locked reference disappears or changes unexpectedly.

---

# 19. STRONGER PROTECTION

Where repository governance permits, reference assets and constitution/execution governance should require Lead/owner review.

---

# 20. AGENT HANDOFF CONTRACT

Before any Agent stops work, update `PLUGIN_UI_PROGRESS.md` with:

```text
LAST COMPLETED
CURRENT STATE
FILES CHANGED
TESTS
SCREENSHOTS
KNOWN DIFFERENCES
BLOCKERS
NEXT EXACT TASK
```

The next Agent starts from that exact point.

---

# 21. NO CHAT-ONLY DECISIONS

Any visual decision important enough to affect future implementation must be committed to the repository. Do not leave governing decisions only in ChatGPT/Codex/Slack/prompt history/Agent memory.

---

# 22. LEAD RESPONSIBILITY

The Lead ensures references remain locked, manifest stays valid, correct references are used, no competing design system appears, progress/matrix remain current, screenshots exist, ownership stays exclusive, protected shared files remain coordinated and approved screens are not unintentionally redesigned.

---

# 23. WORKER RESPONSIBILITY

Each Worker must read the Constitution/Execution Plan first, use assigned Reference IDs, continue from last checkpoint, avoid another owner's or approved screens, store screenshots, update its matrix/progress evidence, open a focused PR and never self-merge.

---

# 24. DEFINITION OF CONTINUITY

The project has successful continuity when a completely new Agent can enter with zero previous chat context and determine within minutes:

```text
What design is approved?
Where are the images?
What screens exist?
Who owns each screen?
What is finished?
What is not finished?
Which reference controls each screen?
What was the last checkpoint?
What should I work on next?
```

If the Agent must ask “What design should I use?” then project governance has failed.

---

# 25. P0 RULE

No Premium Plugin Redesign implementation begins until the following are committed to `main` together:

```text
[ ] 7 Locked images
[ ] Reference manifest
[ ] PLUGIN_UI_CONSTITUTION.md
[ ] PLUGIN_REDESIGN_EXECUTION_PLAN.md
[ ] PLUGIN_UI_SCREEN_MATRIX.md
[ ] PLUGIN_UI_PROGRESS.md
[ ] AGENTS.md governance rule
[ ] reference validation script
[ ] validation PASS
[ ] unassigned screens = 0
[ ] overlapping ownership = 0
```

Only after this foundation lands may Worker #1, #2 and #3 begin parallel implementation from the exact foundation `main` SHA.

---

# 26. APPROVED RELEASE BASELINE AND VERSION CONTROL

The project owner approves the integrated PR `#652` source as the forward-only ALKENZY ADV baseline:

```text
APPROVED PRODUCT RELEASE: 0.3.6+10
APPROVED PLUGIN VERSION: 0.3.6
APPROVED FUNCTIONAL SOURCE: 9171f1c357822f9118eb8058aab6fb145c475fc3
IMMUTABLE BASELINE BRANCH: release/alkenzy-adv-mobile-0.3.6
PREVIOUS APPROVED MOBILE SOURCE: 458e3580d07eb182224c3652bb18d3c82b87adbd
PREVIOUS BASELINE ANCESTOR VERIFIED: YES
```

Every future plugin, mobile, design or bug-fix implementation MUST start from `release/alkenzy-adv-mobile-0.3.6` or a commit proven to be its descendant. Starting from an older branch, stale PR head, abandoned worker line or historical release snapshot is forbidden. Accepted visible changes, server-authoritative behavior, B084 pagination fields and prior approved mobile fixes must not disappear during conflict resolution.

Every later user-facing production change MUST increment the unified semantic product version before merge. `wordpress-plugin/safecontracts/safecontracts.php`, its readme stable tag, `mobile/pubspec.yaml`, footer output, CI artifact names and release metadata must agree. The mobile build number must also increase. The default next release is at least `0.3.7+11`; reuse of `0.3.6+10` is forbidden.

Use semantic versioning: PATCH for backward-compatible fixes, MINOR for backward-compatible features and MAJOR for breaking changes. CI MUST run `python3 scripts/validate-release-version.py` and fail a production-code PR that does not move forward from its base version.

The WordPress admin footer on SafeContracts pages MUST show the canonical plugin version so the approved build can be verified visually in the delivered UI.

---

# IMPLEMENTED BASELINE APPENDIX — 2026-08-24

| Reference | File | Primary role |
|---|---|---|
| REF_001 | `REF_001_Premium_Module_Masterboard.png` | Primary premium module masterboard |
| REF_002 | `REF_002_WordPress_Plugin_Masterboard_DesignSystem.png` | WordPress masterboard + design system |
| REF_003 | `REF_003_WordPress_Dashboard.png` | Historical detailed Dashboard baseline |
| REF_004 | `REF_004_WordPress_Customers.png` | Detailed Customers baseline |
| REF_005 | `REF_005_WordPress_Payments.png` | Detailed Payments baseline |
| REF_006 | `REF_006_WordPress_Notification_Settings.png` | Detailed Notification Settings baseline |
| REF_007 | `REF_007_WordPress_Active_Users.png` | Detailed Active Users baseline |
| REF_008 | `REF_008_WordPress_Dashboard_Monthly_Flow.jpg` | Owner-approved Dashboard baseline with monthly financial-flow composition |

The SHA-256 values in `REFERENCE_MANIFEST.json` are authoritative. These files must be committed byte-for-byte from the approved uploads; they must not be cropped, recolored, resized, recompressed or regenerated.

## Baseline precedence

1. REF_008 controls Dashboard SC-001 and supersedes REF_003 for that screen only. REF_003 remains immutable historical evidence.
2. The remaining detailed page references (REF_004 through REF_007) control their named pages.
3. For pages without a detailed page reference, REF_001 controls premium visual language and module composition.
4. REF_002 controls WordPress-admin integration cues, sidebar language, page framing and the visible design-system direction.
5. Real repository data, permissions, fields, WordPress behavior and business rules override mock values in the images.
6. Any visual conflict that cannot be resolved by this precedence must be recorded for Lead/owner approval rather than silently reinterpreted.

**ALKENZY ADV DESIGN REFERENCES LOCKED**
