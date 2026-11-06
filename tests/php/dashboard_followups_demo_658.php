<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

function sc_658_source(string $path): string
{
    $source = file_get_contents($path);
    if (! is_string($source)) {
        throw new RuntimeException('Unable to read ' . $path);
    }
    return $source;
}

function sc_658_assert(bool $condition, string $message): void
{
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$dashboard = sc_658_source($root . '/wordpress-plugin/safecontracts/src/Admin/DashboardV2Page.php');
$monthly = sc_658_source($root . '/wordpress-plugin/safecontracts/src/Admin/DashboardMonthlyFlowRepository.php');
$followups = sc_658_source($root . '/wordpress-plugin/safecontracts/src/FollowUps/FollowUpRepository.php');
$service = sc_658_source($root . '/wordpress-plugin/safecontracts/src/FollowUps/FollowUpService.php');
$page = sc_658_source($root . '/wordpress-plugin/safecontracts/src/Admin/FollowUpsPage.php');
$demo = sc_658_source($root . '/wordpress-plugin/safecontracts/src/Support/DemoDataService.php');
$controller = sc_658_source($root . '/wordpress-plugin/safecontracts/src/Admin/DemoDataController.php');
$plugin = sc_658_source($root . '/wordpress-plugin/safecontracts/src/Plugin.php');
$enhancement = sc_658_source($root . '/wordpress-plugin/safecontracts/src/Admin/AdminPremiumDashboardEnhancements.php');
$runtimeQa = sc_658_source($root . '/tests/plugin-redesign-visual-qa/demo-data-runtime.php');
$collectionsPage = sc_658_source($root . '/wordpress-plugin/safecontracts/src/Admin/CollectionsPage.php');
$paymentsPage = sc_658_source($root . '/wordpress-plugin/safecontracts/src/Admin/PaymentsPage.php');
$financePage = sc_658_source($root . '/wordpress-plugin/safecontracts/src/Admin/FinancePage.php');
$reportsPage = sc_658_source($root . '/wordpress-plugin/safecontracts/src/Admin/ReportsPage.php');
$importsPage = sc_658_source($root . '/wordpress-plugin/safecontracts/src/Admin/ImportsPage.php');
$paymentMethodsPage = sc_658_source($root . '/wordpress-plugin/safecontracts/src/Admin/PaymentMethodsPage.php');
$worker2States = sc_658_source($root . '/wordpress-plugin/safecontracts/assets/admin/plugin-redesign/worker-2/route-states.css');
$visualWorkflow = sc_658_source($root . '/.github/workflows/plugin-redesign-visual-qa.yml');

sc_658_assert(str_contains($dashboard, 'DashboardMonthlyFlowRepository') && str_contains($dashboard, 'safecontracts-dashboard-monthly-flow'), 'Dashboard renders the real monthly financial-flow surface');
sc_658_assert(str_contains($dashboard, '(new FollowUpService())->recent') && str_contains($dashboard, 'Latest employee payment notes'), 'Dashboard surfaces recent employee follow-up notes');
sc_658_assert(str_contains($dashboard, 'DemoDataController::renderControls'), 'Dashboard exposes controlled demo-data actions');
sc_658_assert(! str_contains($enhancement, 'safecontracts-premium-chart__bars'), 'legacy count-bar chart is removed instead of duplicating the monthly chart');

sc_658_assert(str_contains($monthly, 'safecontracts_payment_collections') && str_contains($monthly, 'SUM(cl.amount)') && str_contains($monthly, 'MONTH(cl.collection_date)'), 'monthly flow is aggregated from the settlement ledger by month');
sc_658_assert(str_contains($monthly, 'currency_code') && str_contains($monthly, 'financial_direction'), 'monthly flow keeps currency and AP/AR direction independent');
sc_658_assert(str_contains($monthly, 'Capabilities::VIEW_ALL') && str_contains($monthly, 'Capabilities::VIEW_ASSIGNED'), 'monthly flow preserves server-side user scope');

sc_658_assert(str_contains($followups, 'COALESCE(NULLIF(u.display_name') && str_contains($followups, 'author_name'), 'follow-up reads expose the employee display name');
sc_658_assert(str_contains($service, 'public function recent') && str_contains($service, 'get_current_user_id()'), 'recent follow-up service applies assigned-user scope');
sc_658_assert(str_contains($page, 'Employee payment notes') && str_contains($page, "['author_name']"), 'Follow-ups page visibly lists employee-authored mobile notes');

sc_658_assert(str_contains($demo, 'public const ROWS_PER_TABLE = 500'), 'demo batch size is exactly 500 rows per table');
sc_658_assert(substr_count($demo, "'safecontracts_") >= 22, 'demo service covers every current SafeContracts plugin table');
sc_658_assert(str_contains($demo, "'START TRANSACTION'") && str_contains($demo, "'ROLLBACK'") && str_contains($demo, "'COMMIT'"), 'demo create/delete operations are transactional');
sc_658_assert(str_contains($demo, 'REGISTRY_OPTION') && str_contains($demo, 'tables' . "' => \$tables"), 'demo rows are recorded by exact primary-key registry');
sc_658_assert(str_contains($demo, 'WHERE id IN') && ! str_contains(strtoupper($demo), 'TRUNCATE TABLE'), 'demo deletion uses exact IDs and never truncates tables');
sc_658_assert(str_contains($demo, "'batches' => \$valid") && str_contains($demo, "'batch_count' => count(\$valid)"), 'demo registry accumulates repeatable 500-row batches');
sc_658_assert(! str_contains($demo, 'Delete it before creating another batch') && str_contains($demo, 'GET_LOCK'), 'demo creation supports sequential batches while serializing concurrent mutations');
sc_658_assert(str_contains($controller, 'Add 500 rows per table') && str_contains($controller, 'Delete all demo data'), 'dashboard keeps both repeatable-create and delete-all actions visible');
sc_658_assert(str_contains($controller, 'screenLinks') && str_contains($controller, 'Technical reason:'), 'dashboard links directly to visible demo screens and explains a failed transaction');
sc_658_assert(str_contains($runtimeQa, 'Repeated demo creation') && str_contains($runtimeQa, 'real admin read model') && str_contains($runtimeQa, 'exact pre-demo row count'), 'real WordPress QA proves repeated creation, screen visibility and exact deletion');
sc_658_assert(str_contains($visualWorkflow, 'demo-data-runtime.php'), 'visual QA executes the demo lifecycle against real WordPress and MySQL');
sc_658_assert(str_contains($collectionsPage, 'safecontracts-w2-table-scroll'), 'collection controls remain inside the responsive table scroller when demo rows are visible');
sc_658_assert(str_contains($paymentsPage, 'safecontracts-w2-table-scroll') && str_contains($worker2States, 'position: static'), 'payment actions remain reachable in the normal table flow at narrow RTL widths');
sc_658_assert(substr_count($financePage, 'safecontracts-w2-table-scroll') === 3 && substr_count($reportsPage, 'safecontracts-w2-table-scroll') === 2 && str_contains($importsPage, 'safecontracts-w2-table-scroll') && str_contains($paymentMethodsPage, 'safecontracts-w2-table-scroll'), 'all visible worker-two data tables own a deterministic RTL scroll boundary');
sc_658_assert(str_contains($controller, 'Capabilities::MANAGE_SYSTEM') && str_contains($controller, 'check_admin_referer'), 'demo actions require system capability and nonces');
sc_658_assert(str_contains($plugin, 'DemoDataController::CREATE_ACTION') && str_contains($plugin, 'DemoDataController::DELETE_ACTION'), 'demo admin-post handlers are registered');

$manifest = json_decode(sc_658_source($root . '/assets/design/plugin-redesign/reference/REFERENCE_MANIFEST.json'), true, 512, JSON_THROW_ON_ERROR);
$ref = array_values(array_filter($manifest['references'] ?? [], static fn (array $row): bool => ($row['id'] ?? '') === 'REF_008'));
sc_658_assert(count($ref) === 1 && ($ref[0]['sha256'] ?? '') === '0108f21b5a73cbe0c45b733d31d30964a13d1847675c725bd3b92d28ad62942e', 'owner-approved Dashboard reference is locked as REF_008');

echo "Dashboard follow-ups/demo validation passed.\n";
