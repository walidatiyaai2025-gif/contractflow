# P9 Mobile contracts list — SC-P9-011

## Implemented slice

SC-P9-011 replaces the authorized **Contracts** navigation placeholder with a server-backed mobile contracts list while keeping contract detail semantics in the dedicated SC-P9-012 task.

## Server-authoritative data and scope

- The list reads only `GET /safecontracts/v1/contracts`.
- WordPress remains authoritative for capability checks and `VIEW_ALL` / `VIEW_ASSIGNED` accountant scope.
- Mobile never submits an accountant ID to widen scope.
- The typed mobile projection accepts only the existing REST-safe contract fields: `id`, `contract_number`, `customer_id`, `customer_name`, `accountant_user_id`, `status`, `start_date`, `end_date`, `base_value`, `is_archived`.
- Unknown/private response keys are ignored.
- Contract status is displayed exactly as returned by the server. Flutter does not recalculate lifecycle, payment or overdue state.

## Filters, sort and bounded paging

- Customer filtering uses customer IDs from the already server-authorized dashboard customer options.
- Contract-status filtering sends only the selected supported contract status to the REST endpoint; it does not derive status locally.
- Sorting remains server-side and uses the endpoint allowlist: default `id desc`, plus `contract_number asc`, `start_date desc`, or `end_date desc`.
- Paging preserves the P8 bounded contract: page `1..5`, configured `per_page <= 100`, server `has_more` metadata and bounded window validation.
- Filter or sort changes reset to page 1.

## UX states

- The contracts controller is created at bootstrap but performs no contracts request until the Contracts destination opens.
- Initial loading, refresh loading, empty state and errors are distinct.
- Pull-to-refresh reloads the active bounded page.
- Contract rows expose customer, dates, base value, archive marker and the server-returned status.

## Contract detail navigation boundary

Selecting a contract passes only its server identifier into a dedicated details route handoff. SC-P9-011 intentionally does not fetch or render the direct contract-detail endpoint, so SC-P9-012 can implement that read, error handling and detail presentation without duplicated or premature business semantics.

## Validation

`mobile/test/mobile_contracts_011_test.dart` covers:

- bounded page and default sort propagation;
- customer/status/filter/sort/page query propagation;
- exact preservation of a server-provided contract status string;
- safe-field parsing while ignoring an injected private field;
- fail-closed unauthorized execution with no network request;
- widget lazy-load and contract-ID navigation handoff.

No database migration or REST API contract change is introduced by SC-P9-011.
