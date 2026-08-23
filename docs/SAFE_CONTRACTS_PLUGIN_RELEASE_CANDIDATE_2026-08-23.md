# Safe Contracts plugin release candidate — 2026-08-23

This branch is the consolidated WordPress plugin candidate to be completed, packaged, deployed, and accepted before any further mobile product work.

Included scope:
- notification recipient legacy-role repair and stale FCM token retirement;
- mandatory positive contract base value with new contracts active on creation;
- optional multi-file contract attachments;
- multi-file scheduled-payment and collection/settlement evidence;
- customer receivable / supplier payable direction kept server-authoritative;
- Payments contract filter and selected-contract financial summary;
- governed payment editing with settlement-safe amount locking;
- Arabic admin defaults for the new plugin surfaces.

Release rule: do not start new mobile product changes until this plugin candidate is green against current `main`, merged, packaged, deployed, and accepted. Mobile CI may still run as a regression gate only.
