# Mobile UI Screen Matrix

> The canonical matrix was absent when Worker #3 resumed. This file intentionally contains **only Worker #3-owned rows**. No status is asserted for other workers.

| Worker | Feature / screen | Data authority | RTL / narrow-phone QA | Reference review | Status |
|---|---|---|---|---|---|
| 3 | Payments list | `GET payments` | Complete | Complete | COMPLETE |
| 3 | Payment states: due / paid / overdue / future | Server payment status | Complete | Complete | COMPLETE |
| 3 | Payment direction: receivable / payable | `financial_direction` | Complete | Complete | COMPLETE |
| 3 | Payment filters / scoped paging | Existing `DashboardFilters` + server paging | Complete | Complete | COMPLETE |
| 3 | Payment details | `GET payments/{id}` | Complete | Complete | COMPLETE |
| 3 | Expected payment date edit | `PATCH payments/{id}/expected-date` | Complete | Complete | COMPLETE |
| 3 | Customer collection | `collections/record` + server payment methods | Complete | Complete | COMPLETE |
| 3 | Supplier payable presentation | Server payable record; no unsupported mutation invented | Complete | Complete | COMPLETE |
| 3 | Payments loading / empty / error / retry | Existing repository/controller state | Complete | Complete | COMPLETE |
| 3 | Finance overview | `finance/overview` | Complete | Complete | COMPLETE |
| 3 | Finance AP / AR separation | Server direction + capabilities | Complete | Complete | COMPLETE |
| 3 | Finance filters | FinanceController server-bound filters | Complete | Complete | COMPLETE |
| 3 | Finance summary cards | Server summary rows | Complete | Complete | COMPLETE |
| 3 | Finance action center | Server action-center rows | Complete | Complete | COMPLETE |
| 3 | Finance aging | Server aging rows | Complete | Complete | COMPLETE |
| 3 | Finance cash-flow summary / chart | Server cash-flow rows; visual scaling only | Complete | Complete | COMPLETE |
| 3 | Finance obligations / work queue | `finance/obligations` | Complete | Complete | COMPLETE |
| 3 | Finance loading / empty / error / stale refresh | Existing FinanceController state | Complete | Complete | COMPLETE |
| 3 | Follow-up queue | Existing follow-up queue endpoint | Complete | Complete | COMPLETE |
| 3 | Follow-up urgency presentation | Real payment/follow-up state | Complete | Complete | COMPLETE |
| 3 | Follow-up history | Existing history endpoint | Complete | Complete | COMPLETE |
| 3 | Follow-up add / record | Supported `note/promise/issue/defer/escalate` operations | Complete | Complete | COMPLETE |
| 3 | Follow-up loading / empty / error / retry | Existing repository state | Complete | Complete | COMPLETE |
| 3 | Notification center | Current-user notifications endpoint | Complete | Complete | COMPLETE |
| 3 | Notification unread / read filters | Current page + persisted read state | Complete | Complete | COMPLETE |
| 3 | Notification payment-due filter | Shown only when returned template codes identify payment due | Complete | Complete | COMPLETE |
| 3 | Notification overdue severity/filter | Shown only when returned template codes identify overdue | Complete | Complete | COMPLETE |
| 3 | Notification deep links | Existing validated deep-link model/controller | Complete | Complete | COMPLETE |
| 3 | Notification loading / empty / error / retry | NotificationsController state | Complete | Complete | COMPLETE |
| 3 | Export / reports | `reports/excel` | Complete | Complete | COMPLETE |
| 3 | Export filter summary | Current authorized dashboard filters | Complete | Complete | COMPLETE |
| 3 | Export loading / success / failure | MobileExcelExportController state | Complete | Complete | COMPLETE |
| 3 | Profile identity | Real session user ID / scope | Complete | Complete | COMPLETE |
| 3 | Profile permissions summary | Real enabled capability flags | Complete | Complete | COMPLETE |
| 3 | Profile language | Supported Arabic / English preference | Complete | Complete | COMPLETE |
| 3 | Profile notification / device controls | Existing push/device controllers | Complete | Complete | COMPLETE |
| 3 | Profile support / app/session information | Existing mobile config + session | Complete | Complete | COMPLETE |
| 3 | Logout / clear local session | Existing session flow | Complete | Complete | COMPLETE |
| 3 | Help / User Guide | Permission-aware mobile destinations | Complete | Complete | COMPLETE |
| 3 | Settings surfaces exposed through Profile | Only real language/push/device/support/session settings | Complete | Complete | COMPLETE |

## Worker #3 QA coverage

- Arabic RTL reviewed across financial figures, arrows, paging, cards, chips, forms and guide actions.
- English LTR reviewed against the same layouts.
- Narrow-phone design review covered approximately 320 / 360 / 375 / 390 / 412 / 430 px widths using responsive constraints already present in the Worker #3 surfaces.
- Financial values, statuses, permissions, payment methods, exports and notification destinations remain sourced from existing backend/controller models.
- Locked-reference comparison was completed against the supplied Alkenzy ADV reference sheets available to the worker. The repository reference folder itself was absent.
- Executable pixel screenshot persistence is unavailable in this worker environment; this is recorded in `MOBILE_UI_PROGRESS.md` and is not an implementation-status gap.

**Worker #3 remaining IN PROGRESS rows: 0**
