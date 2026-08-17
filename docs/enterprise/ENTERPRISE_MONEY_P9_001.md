# ESC-P9-001 — Enterprise Money and Currency Foundation

Issue: #490

## Decision

ESC-P9 starts with a pure monetary value boundary before any Enterprise financial schema is introduced. Existing SafeContracts contract values, financial items, adjustments and payments are stored as `DECIMAL(20,4)` without a per-record currency column, while the existing General Settings currency remains the legacy display context. Retrofitting those rows in the first P9 increment would invent historical currency identity and could change established financial meaning.

P9-001 therefore adds only `SafeContracts\Finance\CurrencyCode` and `SafeContracts\Finance\Money`.

`CurrencyCode` canonicalizes a syntactically valid three-letter ASCII code to uppercase. This task does not bundle or query a live ISO-4217 registry, so it intentionally makes no claim that every syntactically valid code is currently issued by a monetary authority.

`Money` binds an explicit currency to a signed fixed-scale amount. Its canonical scale is four decimal places and its absolute capacity matches `DECIMAL(20,4)`: at most sixteen whole digits and four fractional digits. Construction accepts integers or plain-decimal strings only. Binary floats, scientific notation, locale separators and over-precision are rejected.

Arithmetic is exact string-digit arithmetic. Same-currency `compare`, `add`, `subtract` and `negate` are available. Any cross-currency comparison or arithmetic fails closed. There is no exchange-rate lookup, inferred rate, default conversion currency or automatic revaluation.

## Legacy financial compatibility

P9-001 does not modify:

- `Contracts\ContractMoney`;
- `Contracts\ContractService`;
- `Payments\PaymentService`;
- legacy Contract/financial-item/adjustment/payment migrations;
- `Settings\GeneralSettings` currency semantics;
- Dashboard/reporting calculations;
- historical stored monetary values.

No existing amount receives an inferred currency. No existing dashboard amount is converted. The established `admin_safe_deletion_currency_400.php` regression continues to require display-only currency context and no exchange-rate recalculation.

## Full Impact Review

### Tenant isolation

N/A for data access. These are pure immutable value objects and read no tenant context, database row, request or user identity. A later persisted P9 financial model must be tenant-owned and enforce server-side tenant scope independently; P9-001 does not pre-authorize any such model.

### Schema / migrations

No change. `Migrator::LATEST_VERSION` remains `1.46.0` and Migration0048 is intentionally unconsumed. This preserves the next migration number for the first P9 feature that actually requires persistence.

### Backend / business authority

Affected. The authoritative implementation lives in the WordPress plugin under `SafeContracts\Finance`. The value objects are infrastructure-independent and are suitable for later server-side financial services. Flutter is not given duplicate financial authority.

### Authorization

N/A. P9-001 performs no data read or mutation and exposes no service/controller. Authorization must be added at the later service boundary that consumes these values.

### API

No change. No REST route, request field, response field or public serialization contract is registered.

### Admin UI

No change. Existing SafeContracts currency display and financial screens remain unchanged.

### Flutter

No change. No model, API DTO, UI, state management or local calculation is added.

### Android identity / builds

No change. No package identity, Firebase, channel, deep-link, signing or artifact behavior is touched.

### Landing page

No change. P9-001 is an internal foundation and is not marketed as an available multi-currency product feature.

### Design system

N/A. No user-visible surface is introduced.

### Feature registry / plans / entitlements

No change. A pure foundation is not a customer entitlement and does not create a generally available feature claim.

### Search / reports / import / export

No change. Existing reports/import/export remain on legacy monetary semantics. No grouping, conversion, aggregation or export of Enterprise Money is introduced.

### Notifications

N/A. Monetary value construction and arithmetic emit no notification and schedule no work.

### Audit

N/A. Pure value operations do not persist or mutate business state. Future financial mutations must add auditable server-side events at their service/repository boundary.

### Documents

No change. No document rendering, clause generation, versioning or monetary formatting is added.

### Localization

No user-visible formatting is introduced. Currency is stored only as canonical uppercase code; locale-specific symbols, decimal separators and presentation rules are deliberately deferred to presentation-layer work.

### Security

Affected and fail-closed:

- only exactly three ASCII currency letters are accepted;
- money input is integer or plain-decimal string only;
- floats/scientific/locale-formatted inputs are rejected;
- scale and capacity are bounded before use;
- arithmetic overflow throws instead of wrapping/truncating;
- cross-currency arithmetic/comparison throws instead of guessing a conversion;
- there is no network, filesystem write, database, clock or external rate-provider dependency;
- immutable objects prevent mutation of amount/currency after validation.

### Performance

Bounded. Each monetary value is limited to twenty scaled decimal digits. Arithmetic is linear in that fixed digit count, so operations are effectively constant-bounded and allocate no unbounded graph/list/query workload.

### Concurrency

N/A. Value objects contain no shared mutable state, lock, cache or persistence operation.

### Tests

`tests/php/enterprise_money_p9_001.php` covers:

- schema non-change;
- currency canonicalization and malformed input rejection;
- exact four-decimal amount normalization;
- signed and negative-zero semantics;
- same-currency arithmetic and ordering;
- boundary and overflow behavior;
- cross-currency failure;
- absence of DB/network/clock/FX/float helpers;
- legacy financial-source compatibility;
- absence of REST/plugin execution exposure;
- explicit global backend-gate wiring.

The existing legacy currency regression remains in the global gate as an independent compatibility guard.

### Documentation

This Full Impact Review records the foundation boundary and deferred responsibilities. Issue #490 is the task-level source of acceptance requirements.

### CI

`.github/workflows/esc-p9-001.yml` runs PHP 8.1 syntax validation and the focused adversarial regression for pull requests to `enterprise-safecontracts` and integration-branch pushes. `scripts/test-php.sh` also invokes the P9-001 regression so the normal ESC backend foundation gate cannot bypass it.

### Release / rollback

No production data migration or backfill exists, so rollback is code-only: revert the Finance value objects, focused regression/workflow and global-gate invocation together. There is no database downgrade, currency rewrite or financial data repair step. P9-001 must not be described as a released multi-currency engine by itself.

## Explicitly deferred P9 capabilities

Later P9 tasks must separately design and review persistence and tenant ownership before use. Deferred capabilities include exchange-rate quote/provider semantics, tenant base/reporting currency, contract financial currency assignment, Enterprise ledger/accounting records, conversion/revaluation, FX gain/loss, tax/invoice/journal/billing/payment-allocation behavior, API/admin/mobile surfaces and reporting.

No later capability may bypass the P9-001 rule that cross-currency arithmetic requires an explicit, auditable conversion boundary.
