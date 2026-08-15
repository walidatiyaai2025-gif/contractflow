# SafeContracts

SafeContracts is a contract receivables tracking platform for an advertising company. WordPress + the SafeContracts custom plugin are the backend and single source of truth; the mobile application is an API client for monitoring and light operational updates.

## Repository structure

- `wordpress-plugin/safecontracts/` — WordPress backend plugin.
- `mobile/` — mobile application (introduced by its P0 foundation task).
- `tests/` — automated/contract tests.
- `scripts/` — validation helpers.
- `docs/` — approved specifications, roadmap, architecture and standards.
- `assets/` — approved visual identity references.

## Foundation validation

Requires PHP 8.1+:

```bash
./scripts/test-php.sh
```

The foundation suite syntax-checks plugin/test PHP and validates plugin lifecycle registration, versioned migration behavior, role/capability defaults, Accountant/Manager scope primitives and REST namespace registration.

## Delivery tracking

GitHub Issues with stable `SC-Px-NNN` IDs are authoritative. `docs/PROJECT_STATUS.md` is regenerated from live issue state/assignment.
