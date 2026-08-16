---
name: Enterprise Safe Contracts task
about: Track an ESC task with mandatory cross-surface impact review
title: "ESC-Px-NNN — "
labels: ""
assignees: ""
---

## Goal

## Acceptance criteria
- [ ] Business behavior defined and implemented

## Full Impact Review
Mark each item complete or explicitly write `N/A — <reason>`.

- [ ] Tenant ownership/isolation reviewed
- [ ] Cross-tenant negative tests reviewed/updated
- [ ] Database migrations/indexes/constraints reviewed
- [ ] Backend business logic reviewed
- [ ] Authorization/capabilities/scopes reviewed
- [ ] REST/API/version compatibility reviewed
- [ ] WordPress/Admin UI reviewed
- [ ] Flutter/mobile impact reviewed
- [ ] Android identity/build/environment impact reviewed
- [ ] Landing/public feature messaging reviewed
- [ ] Design-system/theme impact reviewed
- [ ] Feature registry/plan/feature-flag impact reviewed
- [ ] Search/filter/sort/bulk actions reviewed
- [ ] Reports/import/export reviewed
- [ ] Notifications/escalation reviewed
- [ ] Audit/compliance reviewed
- [ ] Documents/storage reviewed
- [ ] Localization/RTL/LTR/timezone/currency reviewed
- [ ] Security/privacy/rate limiting reviewed
- [ ] Performance/concurrency/idempotency reviewed
- [ ] Automated tests updated
- [ ] Documentation/demo/onboarding updated
- [ ] CI/build/release/rollback impact reviewed
- [ ] Safe Contract separation verified — no unintended `main`/client change

## Evidence
- Branch:
- Commit(s):
- Tests/CI:
- Screenshots/UAT if applicable:

## ESC separation declaration
This task belongs to Enterprise Safe Contracts. Do not merge/port it into Safe Contract unless the product owner explicitly requests that specific transfer.
