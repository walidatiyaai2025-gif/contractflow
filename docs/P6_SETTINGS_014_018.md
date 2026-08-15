# P6 Settings — SC-P6-014..018

This slice implements the SafeContracts WordPress administration settings boundary while preserving the locked architecture decisions.

## SC-P6-014 — SafeContracts settings

- Stores non-secret operational preferences in `safecontracts_general_settings`.
- Supports organization name, one configured currency code for V1, and admin page size.
- Writes require `safecontracts_manage_system`.
- Settings cannot disable authorization, assignment scope, financial reconciliation, due-date authority, audit, or collection rules.

## SC-P6-015 — Payment-method settings

- Moves payment-method administration under the SafeContracts shell.
- Reuses `PaymentMethodRepository`; no presentation-layer SQL is added.
- Existing method codes are stable during edit; name, display order, and active state remain editable.
- Collection entry continues to accept only active methods through the collection domain service.

## SC-P6-016 — Notification settings

- Adds an editor backed by `NotificationRuleService` and active templates from `NotificationTemplateRepository`.
- Supports trigger timing, repeat cadence, role recipients, escalation roles, assigned-Accountant targeting, template selection, and active state.
- Existing rule codes are stable during edit.
- Settled-payment suppression and contractual due-date matching stay authoritative in the notification engine.

## SC-P6-017 — Firebase settings UI

- Reuses `FirebaseSettings` for public Firebase metadata.
- Stores only a credential **reference identifier** in WordPress.
- Raw service-account JSON, private keys, OAuth/access tokens, and credential content are never accepted by the UI contract.
- The UI reports Ready only when public metadata and the credential reference are configured.

## SC-P6-018 — Mobile configuration UI

- Stores non-secret mobile bootstrap values in `safecontracts_mobile_configuration`.
- Supports support/footer text, default page size, and explicit feature-availability flags.
- Future-facing feature flags default off.
- This task intentionally does not add REST endpoints; P8 owns the Dynamic mobile config API.

## UX and security

All five screens are SafeContracts submenus and inherit the corporate/FinTech admin identity. A settings stylesheet is layered after the existing responsive/RTL admin styles. Menu visibility is UX only; every write remains capability-gated server-side.

## Validation

`tests/php/admin_ui_014_018.php` covers capability gates, normalization, stable identifiers, active-template selection, secret rejection, plugin lifecycle registration, absence of presentation-layer SQL, and the P8 REST boundary.
