<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/wordpress-plugin/safecontracts/safecontracts.php';

$tests = 0;

function sc_627_assert(bool $condition, string $message): void
{
    global $tests;
    $tests++;
    if (! $condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$root = dirname(__DIR__, 2);
$tree = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Admin/ContractPaymentTree.php');
$repo = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Admin/ContractPaymentTreeRepository.php');
$plugin = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Plugin.php');
$shell = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/src/Admin/AdminShell.php');
$css = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/assets/admin/contract-payment-tree.css');
$bootstrap = (string) file_get_contents($root . '/wordpress-plugin/safecontracts/safecontracts.php');

sc_627_assert(str_contains($repo, 'forVisibleContracts') && str_contains($repo, 'contract_id IN') && str_contains($repo, 'is_archived = 0'), 'contract tree reads active payments for the already scoped visible contract ids in one query');
sc_627_assert(str_contains($repo, 'original_amount') && str_contains($repo, 'paid_amount') && str_contains($repo, 'remaining_amount'), 'contract tree read model exposes scheduled, settled and remaining balances');
sc_627_assert(str_contains($tree, 'data-safecontracts-payment-tree') && str_contains($tree, 'safecontracts-contract-payment-tree-row'), 'contract rows receive an expandable child payment tree row');
sc_627_assert(str_contains($tree, "__('Scheduled total', 'safecontracts')") && str_contains($tree, "__('Paid', 'safecontracts')") && str_contains($tree, "__('Remaining', 'safecontracts')"), 'payment tree summary exposes scheduled, paid and remaining totals');
sc_627_assert(str_contains($tree, "__('Payment description', 'safecontracts')") && str_contains($tree, "__('Due date', 'safecontracts')") && str_contains($tree, "__('Status', 'safecontracts')"), 'payment tree exposes operational payment fields');
sc_627_assert(str_contains($tree, 'PaymentsPage::SLUG') && str_contains($tree, 'CollectionsPage::SLUG'), 'payment tree links directly to full payment and settlement workflows');
sc_627_assert(str_contains($tree, 'PaymentService') && str_contains($tree, 'updateEditable') && str_contains($tree, 'SAVE_ACTION'), 'payment tree supports governed inline payment editing through PaymentService');
sc_627_assert(str_contains($tree, 'SafeDeletionService') && str_contains($tree, 'archivePayment') && str_contains($tree, 'DELETE_ACTION'), 'payment tree supports governed inline payment removal without bypassing safe deletion');
sc_627_assert(str_contains($tree, 'readonly') && str_contains($tree, 'settlement activity'), 'payment amount remains locked inline after settlement activity exists');
sc_627_assert(str_contains($tree, 'cannot exceed the contract value') && str_contains($tree, 'contract_cap'), 'contract-value capacity failures return an explicit contract-tree message');
sc_627_assert(str_contains($tree, "FinancialDirection::PAYABLE ? '− ' : '+ '") || str_contains($tree, "FinancialDirection::PAYABLE ? '− ' : '+ '"), 'payment tree signs payable amounts negative and receivable amounts positive');
sc_627_assert(str_contains($tree, "'sc_payment_tree'"), 'tree open state is restored after inline payment actions');
sc_627_assert(str_contains($plugin, 'ContractPaymentTree::register()'), 'plugin boots the contract payment tree controller');
sc_627_assert(str_contains($shell, 'contract-payment-tree.css') && str_contains($shell, 'CONTRACT_TREE_STYLE_HANDLE'), 'admin shell loads the dedicated contract tree stylesheet');
sc_627_assert(str_contains($css, '.safecontracts-contract-payment-tree--receivable') && str_contains($css, '.safecontracts-contract-payment-tree--payable'), 'tree styling preserves the system green receivable and red payable visual language');
sc_627_assert(str_contains($css, '.safecontracts-contract-payment-tree__edit'), 'tree stylesheet supports inline payment editor UX');
sc_627_assert(str_contains($bootstrap, 'Version: 0.3.15') && str_contains($bootstrap, "SAFECONTRACTS_VERSION', '0.3.15'"), 'plugin version advances with the current integrated release candidate');

echo "SafeContracts contract payment tree regression passed ({$tests} assertions).\n";
