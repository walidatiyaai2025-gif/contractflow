# P8 REST validation — SC-P8-018 API conventions & versioning

This validation slice confirms the SafeContracts REST surface remains a single backward-compatible `v1` contract after the read endpoints, mobile support endpoints and list-security work landed.

## Namespace and version source

- `Router::NAMESPACE` remains `safecontracts/v1`.
- `Router::API_VERSION` remains `v1`.
- All registered SafeContracts routes are validated to live under that namespace.
- REST response helpers now use `Router::API_VERSION` as the single source of truth rather than repeating a literal `v1` string.

## Success envelope

Successful responses retain the canonical structure:

```text
{
  data: ...,
  meta: {
    api_version: "v1",
    ...endpoint metadata
  }
}
```

Endpoint-provided metadata cannot override `api_version`; the canonical router version is applied last.

## Error envelope

`ApiResponse::error()` continues to return a WordPress `WP_Error` with the existing stable code/message/status behavior. Its data now also includes `api_version`, so clients can apply the same version contract to both success and failure responses. Existing optional `details` remain additive.

`notFound()` continues to use `safecontracts_not_found` with 404 semantics through the same versioned error helper.

## Route conventions

Regression coverage validates the complete registered REST surface rather than one controller:

- Every route is registered under `safecontracts/v1`.
- Every route defines `methods`, `callback` and `permission_callback`.
- V1 currently exposes bounded readable/creatable method contracts only.
- `/health` remains the intentionally public liveness endpoint.
- All other registered routes retain a non-public permission callback, preserving WordPress capability/scope enforcement.

## Health contract

The health endpoint keeps the service name, plugin version, API version and `ok` status in its data payload. Its envelope metadata is validated to carry the same `Router::API_VERSION` value, preventing payload/envelope version drift.

## Compatibility hardening found during validation

Validation found two version-drift risks and repaired them without changing route paths or existing response status codes:

1. Success metadata previously hard-coded `v1` independently of `Router::API_VERSION`.
2. A controller could previously pass `meta['api_version']` and override the default value; error responses did not carry API version metadata at all.

The response helper now centralizes and locks version metadata to `Router::API_VERSION` for both success and error paths.

## Regression evidence

`tests/php/rest_api_validation_018.php` validates namespace constants, success/error/not-found envelopes, non-overridable version metadata, route registration/method/permission conventions, health payload/envelope consistency and absence of accidental `safecontracts/v2` drift in REST source files.

The suite is wired into `scripts/test-php.sh` and executes after the P8 implementation regressions in Quality Gates.
