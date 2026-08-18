# Alkenzy ADV / SafeContracts Production Change Safety Standard

Scope: `main` / production SafeContracts line only. ESC is explicitly out of scope.

## 1. Database migration contract
Every new migration must be additive-first, idempotent and paired with a written migration plan under `docs/migrations/`.

A migration plan must state:
- source and target schema versions;
- preconditions and preflight queries/checks;
- backup/restore checkpoint requirements;
- forward steps;
- data backfill strategy, including batching and restart safety;
- post-migration invariants;
- rollback trigger and exact rollback procedure;
- code/plugin versions compatible with both pre- and post-migration schemas;
- whether DDL is transactional on the production database;
- compensating action when DDL cannot be rolled back transactionally.

### Expand → migrate → verify → contract
Destructive schema changes are not allowed in the same release that first stops reading the legacy shape.

1. Expand: add new nullable/compatible structures while preserving old structures.
2. Migrate: copy/backfill data idempotently.
3. Verify: prove counts, relationships and business invariants.
4. Contract: remove old structures only in a later release after rollback no longer requires them.

`DROP TABLE`, `DROP COLUMN`, `TRUNCATE`, destructive rename, lossy type conversion and irreversible data deletion are blocked by default. An exception requires explicit production-owner approval, backup/restore evidence, tested recovery steps and a documented compatibility window.

## 2. Failed/partial migration behavior
The plugin must fail closed when a migration is incomplete. Do not continue normal writes against an unknown schema state.

Migration execution must record enough state to distinguish:
- not started;
- running;
- completed;
- failed/needs operator action.

A retry must be safe and must not duplicate data or corrupt balances.

## 3. Production deployment decision points
Before deployment:
- exact source candidate is green in SafeContracts Quality Gates;
- database backup exists and restore procedure has been verified for the environment;
- currently installed plugin package is retained for rollback;
- migration preflight passes;
- production API/WordPress health is green;
- release operator knows the rollback decision point and maximum acceptable outage.

After deployment:
- schema/version check;
- login/session smoke test;
- customer/supplier/contract read smoke tests;
- receivable/payable/settlement totals compared to pre-release baseline;
- create/edit workflow smoke tests using controlled test data;
- notification/report smoke tests when affected;
- error logs checked before declaring success.

## 4. Rollback rules
Rollback is three separate concerns and must never be described as a single vague action.

### Code/plugin rollback
Restore the previous verified plugin package.

### Database rollback
Use the migration-specific rollback/compensating procedure. Restoring the plugin alone is insufficient when the schema/data shape changed.

### Configuration rollback
Restore changed production settings/reference data separately when a release modifies configuration.

After rollback, run the same health and financial-invariant checks used after deployment.

## 5. Translation completeness
Any new end-user feature must include Arabic and English coverage for:
- menu/navigation labels;
- page titles and section headings;
- field labels and placeholders;
- option/status labels;
- validation and error messages;
- empty states;
- success notices;
- confirmation prompts;
- help/user-guide text;
- permission names and descriptions.

Raw internal keys, enum values, database field names, API names and capability codes must never be rendered as the end-user label.

## 6. Lookup-first input rule
Users must not type system identifiers.

If a field represents a user, customer, supplier, contract, payment method, role, status, currency, reference list or other bounded entity, the UI must present a select/autocomplete/search picker with a human-readable label. The canonical ID/code remains an internal submitted value only.

Numeric inputs are allowed only for real business quantities such as money, percentage, days, counts or durations, with explicit range/unit validation.

Free text is allowed only for genuinely free-form business content.

Server-side validation must re-check that the selected option exists, is active where required and is permitted for the current user.

## 7. End-user permission presentation
Authorization continues to use stable internal capability codes. Those codes are not an end-user interface.

Permission screens must show:
- business group;
- localized business name;
- plain-language description of what becomes available.

Do not show capability code strings to normal administrators/users.

## 8. System user guide
Every major page/workflow must have contextual guidance that answers:
- What is this page for?
- Who can use it?
- What must exist first?
- What should I do step by step?
- What happens after I save/submit?
- Where do I go next?
- What should I check if I cannot see or perform an action?

Guidance must use visible navigation names rather than technical slugs/codes and must respect permissions. Do not instruct a user to visit a screen they cannot access.

Example: “To review overdue customer balances, go to Finance → Receivables → Overdue.”

## 9. Definition of done for new features
A change is incomplete until all affected layers are covered:
- business rules and server-side validation;
- database migration + rollback when schema/data changes;
- REST/API contract where applicable;
- admin/mobile UI;
- lookup/reference-data UX;
- permissions with human-readable presentation;
- Arabic/English translations;
- contextual help/user guide;
- tests and release/rollback notes.
