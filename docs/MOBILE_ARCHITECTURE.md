# SafeContracts Mobile Architecture

## Technology baseline

The SafeContracts mobile client uses **Flutter/Dart**. This choice applies to the native mobile client while WordPress + the SafeContracts custom plugin remain the complete backend and single source of truth.

## Boundary

Mobile is intentionally a thin client. It may own presentation state, navigation, input validation for UX and temporary/cache state, but it must not become an authoritative source for:

- contract or payment status rules;
- financial totals, balances or reconciliation;
- role/capability decisions;
- Accountant data scope;
- notification rules;
- payment-method/reference data;
- Excel report generation;
- editable system/mobile configuration.

Those values come from versioned SafeContracts WordPress APIs and are revalidated server-side on writes.

## Initial source layout

```text
mobile/
  lib/
    app/                 application shell/theme/routing foundation
    core/
      config/            non-secret runtime/build configuration
      network/           API namespace/path primitives
    features/            feature-owned presentation/application code
  test/                  mobile unit/widget tests
```

Feature modules will be introduced by P9 tasks. Cross-feature business shortcuts are not permitted; reusable contracts belong under `core` only when they are truly infrastructure-level.

## Configuration

The WordPress site base URL is non-secret deployment configuration and is provided through Dart defines. Credentials, Firebase server keys, WordPress secrets and database credentials must never be compiled into the mobile app.

The server later supplies dynamic branding, labels, reference data and feature flags through authorized APIs as defined in the master plan.

## Networking direction

The canonical namespace is:

`/wp-json/safecontracts/v1`

The foundation deliberately does not choose a third-party HTTP/state-management package yet. That keeps P0 dependency-light; API/auth implementation tasks will introduce dependencies only when their requirements and tests justify them.
