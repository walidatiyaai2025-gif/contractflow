# Device UI Regression Closure — 2026-08-25

Source: real-device screenshots supplied after the final integration build.

Targeted production fixes only:

- Drawer: restore readable unselected/selected icon and label contrast on the navy drawer background.
- App bar: scale the combined Alkenzy ADV / current-route title down instead of truncating it.
- Quick Add: move the floating action away from the center bottom-navigation label area.
- Dashboard KPI summary: use a responsive 2-column mobile layout and 4-column wide layout instead of compressing four KPI cards into one narrow row.
- KPI labels: improve minimum readable size after the responsive card-width fix.

No business rules, API requests, authorization, accounting calculations, pagination, or server-authoritative behavior are changed.

Acceptance requires Flutter format/analyze/test GREEN and a new release candidate built from the exact accepted head.
