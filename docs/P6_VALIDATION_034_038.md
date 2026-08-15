# P6 validation — SC-P6-034..038

This slice validates the SafeContracts settings administration boundaries after the initial P6 implementation. It keeps secret material server-side and does not pull P7/P8 work forward.

## SC-P6-034 — SafeContracts settings
- General settings remain `MANAGE_SYSTEM` + nonce gated.
- Organization/currency/page-size values are sanitized and normalized by `GeneralSettings`.
- Malformed settings fail closed.
- General settings accept no Firebase/service-account/access-token material.

## SC-P6-035 — Payment-method settings
- Administration remains `MANAGE_REFERENCE_DATA` + nonce gated.
- Existing method codes remain stable; name/order/active state stay editable through `PaymentMethodRepository`.
- Collection entry continues to depend on active server-side methods.
- No direct SQL or hard-delete path exists in the presentation layer.

## SC-P6-036 — Notification settings
- Administration remains `MANAGE_NOTIFICATIONS` + nonce gated.
- Trigger values use the domain allow-list; recipient/escalation role values are normalized.
- Rule editing uses stable codes and active notification templates.
- Contractual due-date and settled-payment suppression semantics remain backend-authoritative.

## SC-P6-037 — Firebase settings UI
- The UI accepts only Firebase public metadata plus a credential-reference identifier.
- Raw service-account JSON, private keys and access tokens have no POST input contract.
- Secret-like credential-reference values are rejected by `FirebaseSettings`.
- Readiness rendering uses a safe summary and escaped reference value.

## SC-P6-038 — Mobile configuration UI
- Administration remains `MANAGE_SYSTEM` + nonce gated.
- Only support text, bounded page size and explicit feature flags are stored.
- Server credentials and authorization/financial rules are not configurable from this client-bootstrap page.
- No P8 REST route is introduced early.

## Regression evidence

`tests/php/admin_ui_validation_034_038.php` independently validates the capability, nonce, normalization, secret-isolation and presentation-layer boundaries above. It is wired into `scripts/test-php.sh` and therefore runs in the repository Quality Gates workflow.
