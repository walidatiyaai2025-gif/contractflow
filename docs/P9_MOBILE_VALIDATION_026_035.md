# P9 mobile validation — SC-P9-026..035

This validation slice hardens the first ten SafeContracts mobile workstreams without duplicating the team's sequential feature implementation. It validates the already-delivered P9-001..010 behavior and keeps WordPress REST as the authority for permissions, accountant scope, financial state and status calculations.

## SC-P9-026 — App architecture & API client — Validate

- API endpoints are constrained to the configured SafeContracts base scheme/host/port/path.
- Embedded credentials, query/fragment base URLs, absolute endpoint URLs and path traversal are rejected.
- The API client retains `get()` and adds typed JSON `post()` / `patch()` / generic request support for later light-edit work.
- JSON request bodies are capped at 256 KiB; IO responses remain capped at 2 MiB.
- Session-supplied headers cannot override canonical JSON `Accept`/`Content-Type`, and CR/LF header injection is rejected.
- Malformed non-2xx bodies map to a safe API exception instead of exposing parser details.
- Responses advertising a different API version fail closed.

## SC-P9-027 — Authentication/session — Validate

- A successful mobile session requires explicit `authenticated: true`.
- User ID remains positive and scope remains `all`, `assigned` or `none`.
- Capability names are bounded SafeContracts capability identifiers, values must be booleans and the capability map is bounded.
- Forbidden/unauthenticated/error bootstrap continues to clear local session state.
- Reset removes session/error state without creating a second credential store.

## SC-P9-028 — Dynamic configuration bootstrap — Validate

- Missing or malformed feature maps fall back to disabled feature flags.
- Page size keeps a safe 25 default and a mobile bound of 10..100.
- Support text is bounded; malformed/oversized text falls back to empty.
- Unknown fields, including secret-looking server settings, are not represented by the mobile config model.

## SC-P9-029 — Role-aware navigation — Validate

- Navigation remains capability + server scope + safe feature-flag driven; no role-name branching is introduced.
- `none` scope cannot expose business-data destinations even if capability booleans are present.
- Export and collection mutation actions still require their explicit capability/feature combinations.

## SC-P9-030 — Dashboard KPIs — Validate

- KPI financial values remain exact server fixed-point strings; the mobile client does not recompute them from floating-point numbers.
- Contract count is non-negative.
- Numeric/malformed/negative financial KPI payloads fail closed instead of being normalized silently.

## SC-P9-031 — Customer dropdown — Validate

- Customer option IDs must be positive, names non-empty and IDs unique.
- Unknown customer selections are rejected before network access.
- The client only sends IDs that came from the current server-authorized option set.

## SC-P9-032 — Dependent contract dropdown — Validate

- Dependent contract option IDs are positive/unique and must belong to the selected customer.
- Changing customer clears the previous contract selection and immediately clears stale dependent options/data while the new scoped request is loading.
- Failed dependent lookups cannot leave the prior customer's options visible.

## SC-P9-033 — Dashboard filtered lists — Validate

- Dashboard filters validate positive IDs, supported statuses, real ISO dates and non-reversed date ranges before requests.
- Filter changes clear stale overview/list data before the new scoped read completes.
- Server list IDs, dates and fixed-point money fields are validated; numeric money is not silently reinterpreted by Flutter.
- Paging remains bounded by the existing server/mobile contract.

## SC-P9-034 — Mobile Excel export — Validate

- Export remains capability-gated before any request.
- Filter payload is validated before download.
- Base64/XLSX payloads are bounded and require the ZIP local-file header used by XLSX packages.
- Mobile retains only the known filter/count metadata; accountant IDs, storage paths, tokens and unknown internal metadata are discarded.
- Controller failures distinguish local unauthorized, server forbidden, validation, network, invalid payload, storage and generic server errors.

## SC-P9-035 — Customers screen — Validate

- Customer pages remain bounded to page/per-page/window metadata, reject duplicate IDs and accept only `all`/`assigned` scope metadata.
- Direct customer IDs must be positive before network access.
- Invalid ID and authorization failures are separate local states.
- Private/unknown customer response fields remain outside the mobile model.

## Regression evidence

`mobile/test/mobile_validation_026_035_test.dart` provides deterministic behavioral coverage for every task ID in this slice. Existing P9 implementation suites remain unchanged and run alongside it through the repository Quality Gates (`dart format`, `flutter analyze`, `flutter test`, PHP regressions and repository standards).

The final merge candidate is revalidated after the team's SC-P9-012 contract-details implementation landed on `main`, so the validation slice and the protected contract-detail workflow are tested together before merge.
