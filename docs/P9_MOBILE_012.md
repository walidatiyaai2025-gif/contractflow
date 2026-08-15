# P9 Mobile contract details — SC-P9-012

## Implemented slice

SC-P9-012 replaces the SC-P9-011 contract-detail handoff placeholder with a protected mobile detail read over the existing REST v1 contract endpoint.

## Protected direct read

- Selecting a contract still hands off only its server identifier from the list.
- `ContractDetailsScreen` then performs a fresh `GET /safecontracts/v1/contracts/{id}` request.
- The list row is never treated as authorization for the detail view.
- WordPress remains authoritative for SafeContracts access and Accountant scope on the direct-object read.
- A controller that is not authorized for the Contracts destination fails closed before making a network request.

## Exact safe projection

The detail view reuses the existing typed safe contract projection and displays only:

- `id`
- `contract_number`
- `customer_id`
- `customer_name`
- `accountant_user_id`
- `status`
- `start_date`
- `end_date`
- `base_value`
- `is_archived`

Unknown/private response fields remain ignored. Contract status and `base_value` are displayed exactly as returned by the server. Flutter does not calculate lifecycle state, receivables, overdue state, totals or derived financial values.

## Error states

Direct contract reads preserve distinct states:

- HTTP `404` → contract not found in the current authorized read surface;
- HTTP `403` → forbidden contract read;
- other API/transport/format failures → generic error state;
- loading and ready states remain separate.

Each server/error state is rendered explicitly and supports a direct-read retry without widening scope.

## Capability-aware actions

- The bootstrap resolves `safecontracts_edit_contracts` from the authenticated server session capability map.
- Edit UI is offered only when the Contracts destination is authorized **and** that edit capability is granted.
- SC-P9-012 performs no mutation. The edit action hands the contract ID to the SC-P9-013 boundary only.
- Actual supported edit fields, REST mutation, validation, conflict handling and audit behavior remain exclusively owned by SC-P9-013.

## Validation

`mobile/test/mobile_contract_details_012_test.dart` covers:

- direct `/contracts/{id}` reads;
- exact preservation of server-provided status and base value;
- safe-field projection while ignoring an injected private field;
- distinct `404`, `403` and generic API error states;
- unauthorized fail-closed behavior with no network request;
- capability-gated Edit action;
- explicit not-found UI and retry surface.

The pre-existing SC-P9-011 contracts-list regression suite remains active and now sets its edit capability explicitly.

No database migration or REST API contract change is introduced by SC-P9-012.
