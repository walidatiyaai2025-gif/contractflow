# SafeContracts — Locked Decisions

This file records decisions that implementation must treat as baseline unless an explicit later decision updates them.

| ID | Decision | Status |
|---|---|---|
| DEC-001 | Product name is **SafeContracts**. | Locked |
| DEC-002 | WordPress Custom Plugin is the complete backend and single source of truth. | Locked |
| DEC-003 | Mobile receives business data/configuration from WordPress APIs and does not maintain competing business logic/database. | Locked |
| DEC-004 | Roles are System Administrator, Manager, Accountant and Viewer. | Locked |
| DEC-005 | Accountant sees only assigned contracts/payments; Manager sees all and can filter. | Locked |
| DEC-006 | Contract creation is core Accountant work. | Locked |
| DEC-007 | Contract editing is capability-based and can be granted to any role. | Locked |
| DEC-008 | Due dates and other contract data may be edited by authorized users; changes must be audited. | Locked |
| DEC-009 | Collection proof attachment is optional. | Locked |
| DEC-010 | Payment method is mandatory for collection entry. | Locked |
| DEC-011 | Payment methods are an admin-managed table; initial defaults: Cash, Bank Transfer, Wallet. | Locked |
| DEC-012 | V1 uses one currency. | Locked |
| DEC-013 | Contracts may contain financial line items, additions and discounts; net value is reconciled transparently. | Locked |
| DEC-014 | Notification platform is configurable by timing, role/recipient, channel and rule state. | Locked |
| DEC-015 | Default notification is 10 days before due date to assigned Accountant + Manager. | Locked |
| DEC-016 | Firebase settings are managed in WordPress. | Locked |
| DEC-017 | Mobile-facing editable header/footer/text/reference/config values are managed from WordPress where appropriate. | Locked |
| DEC-018 | WordPress operational admin is fully white-labelled with SafeContracts identity. | Locked |
| DEC-019 | Irrelevant WordPress menus are hidden for operational roles, but security always relies on server-side capabilities/scope rather than menu hiding. | Locked |
| DEC-020 | Mobile Dashboard has customer dropdown -> dependent contract dropdown -> All/one contract selection. | Locked |
| DEC-021 | Mobile Excel export uses the current authorized dashboard filters and is generated server-side by WordPress. | Locked |
| DEC-022 | Customer/entity internal code exists but is optional. | Locked |
| DEC-023 | Initial Excel import supports the fields from the supplied workbook with mapping/validation. | Locked |
| DEC-024 | V1 screen baseline is 13 WordPress + 11 Mobile = 24 logical screens. | Locked |
| DEC-025 | Visual direction is corporate/FinTech: navy/blue + green success, Contract/Clipboard/Checkmark motif. | Locked |
| DEC-026 | Production visuals exclude cryptocurrency/Ethereum imagery and visible WordPress branding. | Locked |
| DEC-027 | Implementation baseline is 11 phases / 284 production tasks tracked in GitHub Issues. | Locked |
