# ESC product-line boundary

This document is authoritative for Enterprise Safe Contracts packaging and test handoff.

## Enterprise Safe Contracts (ESC)

- Integration branch: `enterprise-safecontracts`
- Test/package branch family: branches based only on `enterprise-safecontracts`
- Production/test API host: `esc.50sols.com`
- REST base: `https://esc.50sols.com/wp-json/safecontracts/v1/`
- Android application ID: `com.safecontracts.enterprise`
- ESC plugin/theme/APK artifacts must never be sourced from `main`.

## SafeContracts / Alkenzy ADV

- Product: SafeContracts
- Mobile product name: `Alkenzy ADV`
- Main product branch: `main`
- API host: `cms.50sols.com`
- ESC packaging must never publish an APK or config containing this host.

## Packaging rule

When the requested product is ESC, handoff artifacts must be built only from the ESC branch lineage and must be explicitly verified against the ESC API host and Android application ID before delivery. SafeContracts/Alkenzy ADV artifacts are not valid substitutes for ESC artifacts.
