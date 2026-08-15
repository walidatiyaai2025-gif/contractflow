# P9 Mobile customers screen — SC-P9-010

## Implemented slice

SC-P9-010 replaces the authorized **Customers** navigation placeholder with a responsive mobile customer list/detail experience over the existing SafeContracts REST v1 read contract.

## Server-authoritative scope

- Customer list data comes only from `GET /safecontracts/v1/customers`.
- Customer detail comes from `GET /safecontracts/v1/customers/{id}` rather than trusting a client-side list item as an authorization decision.
- The mobile client never sends role names or accountant IDs to widen scope.
- WordPress capability and `VIEW_ALL` / `VIEW_ASSIGNED` enforcement remain authoritative for list and direct-object reads.
- Private customer notes are not modeled or rendered by the mobile feature.

## Bounded paging and sort

- The screen uses the P8 bounded list contract: page `1..5` and configured `per_page` capped at `100`.
- Customer sorting stays server-side using the supported deterministic `name` sort.
- Users can select ascending (`A–Z`) or descending (`Z–A`) order; changing order returns to page 1.
- Previous/next controls honor server `has_more` metadata and the five-page bounded window.
- Response metadata is parsed and validated before presentation (`page`, `per_page`, `sort`, `order`, `bounded_window`, `scope`, `has_more`).

## Safe customer projection

The typed mobile model accepts only the existing customer-safe fields:

- `id`
- `internal_code`
- `name`
- `contact_name`
- `email`
- `phone`
- `is_active`

Unknown response keys are ignored. No passwords, WordPress cookies, tokens, internal customer notes or server credentials are introduced.

## UX and runtime behavior

- Customer data is lazy-loaded only when the Customers destination is opened.
- Initial loading, refresh loading, empty, list error and detail error states are distinct.
- Pull-to-refresh reloads the active bounded page.
- Narrow layouts switch from list to detail with a back action.
- Wide layouts show list and authorized detail side by side.
- The customer controller also fails before a network request when its resolved navigation policy does not permit customer access; the server still rechecks authorization on every request.

## Validation

`mobile/test/mobile_customers_010_test.dart` covers:

- bounded server page/sort query propagation;
- parsing of server paging/scope metadata;
- next-page behavior and server-side order changes;
- direct customer detail reads;
- client-side unauthorized fail-closed behavior with no network request;
- widget lazy-load from customer list into direct detail.

No database migration or REST API contract change is introduced by SC-P9-010.
