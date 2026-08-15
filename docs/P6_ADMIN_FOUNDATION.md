# SafeContracts P6 Admin Foundation

This slice completes `SC-P5-025..026` validation and implements `SC-P6-001..003`.

## Admin shell

- `SafeContracts` is the top-level WordPress admin entry point.
- The shell requires `safecontracts_access` and contains no direct database/business logic.
- SafeContracts admin pages share a navy/blue + green identity stylesheet with RTL and responsive behavior.
- Existing specialized pages, including Payment Methods, remain server-authorized and can inherit the shared identity without being duplicated.

## Login branding

- WordPress authentication/session handling is unchanged.
- SafeContracts only changes login visual identity, logo destination, and accessible header text.
- No credential, authentication, cookie, nonce, or session bypass is introduced.

## Navigation cleanup

- Operational SafeContracts users (`safecontracts_access` without `safecontracts_manage_system`) have irrelevant native WordPress menus hidden.
- `safecontracts_manage_system` users are exempt from the cleanup.
- Menu hiding is UX only. Protected actions must continue to enforce SafeContracts capabilities and assignment scope server-side.
- The hidden-menu list is filterable through `safecontracts_hidden_admin_menus`, while the SafeContracts shell itself is protected from removal by this component.

## P5 final validation

- Delivery retries remain bounded to three retries with deterministic backoff.
- Each transport attempt is append-only and auditable without raw device tokens.
- Paid or zero-remaining payments are suppressed before template lookup and transport planning.
- Positive partial balances remain eligible when the notification rule matches.

## Automated evidence

- `tests/php/notifications_validation_025_026.php`
- `tests/php/admin_ui_001_003.php`
- Both suites run through `scripts/test-php.sh` with the complete existing regression suite.
