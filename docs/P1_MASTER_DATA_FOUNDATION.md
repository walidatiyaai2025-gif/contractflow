# P1 Master Data Foundation

This document records the database baseline introduced by `SC-P1-001..004`.

## Customers

Table: `{$wpdb->prefix}safecontracts_customers`

The customer/entity master stores:

- required display name;
- optional `internal_code` used only when the business supplies one;
- optional contact name, email, phone and notes;
- active/inactive state;
- creator reference and timestamps.

`internal_code` is nullable and uniquely indexed. Multiple customers may therefore omit a code, while any supplied code must remain unique.

## Payment methods

Table: `{$wpdb->prefix}safecontracts_payment_methods`

Payment methods are reference data rather than mobile constants. Each row has a stable code, editable display name, display order, active flag and timestamps.

The initial defaults are:

| Code | Name | Order |
|---|---|---:|
| `cash` | Cash | 10 |
| `bank_transfer` | Bank Transfer | 20 |
| `wallet` | Wallet | 30 |

The seed uses an idempotent upsert so migration recovery cannot create duplicate default rows. Future administration and APIs must read this table instead of hard-coding payment methods in clients.

## Migration

`Migration0002MasterData` advances the SafeContracts schema version to `1.1.0`. The migration remains prefix-aware, uses `dbDelta` for table creation/update, and only advances the stored migration version after the migration completes.
