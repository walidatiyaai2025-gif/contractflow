from pathlib import Path


def replace_once(path: str, old: str, new: str) -> None:
    p = Path(path)
    text = p.read_text()
    count = text.count(old)
    if count != 1:
        raise SystemExit(f"{path}: expected one match, got {count}: {old[:120]!r}")
    p.write_text(text.replace(old, new, 1))


def insert_before(path: str, marker: str, content: str) -> None:
    p = Path(path)
    text = p.read_text()
    if text.count(marker) != 1:
        raise SystemExit(f"{path}: marker mismatch: {marker!r}")
    p.write_text(text.replace(marker, content + marker, 1))


status = 'wordpress-plugin/safecontracts/src/Payments/PaymentStatus.php'
replace_once(
    status,
    "use InvalidArgumentException;\n",
    "use InvalidArgumentException;\nuse SafeContracts\\Contracts\\ContractMoney;\n",
)
insert_before(
    status,
    "    public static function isDueSoon(mixed $dueDate, ?DateTimeImmutable $today = null, int $dueSoonDays = 10): bool\n",
    r'''    /**
     * One read-time status engine for mobile/API presentation.
     * Financial settlement state is derived from authoritative stored amounts;
     * otherwise the contractual due date determines the temporal state.
     */
    public static function authoritative(
        mixed $dueDate,
        mixed $paidAmount,
        mixed $remainingAmount,
        ?DateTimeImmutable $today = null,
        int $dueSoonDays = 10
    ): string {
        $paid = ContractMoney::normalizeNonNegative($paidAmount);
        $remaining = ContractMoney::normalizeNonNegative($remainingAmount);
        if ($remaining === '0.0000') {
            return self::PAID;
        }
        if (ContractMoney::compare($paid, '0.0000') > 0) {
            return self::PARTIALLY_PAID;
        }
        return self::temporalForDueDate($dueDate, $today, $dueSoonDays);
    }

''',
)

repo = 'wordpress-plugin/safecontracts/src/Payments/PaymentRepository.php'
replace_once(
    repo,
    "            'status' => (string) ($row['status'] ?? PaymentStatus::UPCOMING),\n",
    "            'status' => PaymentStatus::authoritative(\n                $row['due_date'] ?? '',\n                $row['paid_amount'] ?? '0.0000',\n                $row['remaining_amount'] ?? '0.0000'\n            ),\n",
)

counter = 'wordpress-plugin/safecontracts/src/Counterparties/CounterpartyReadRepository.php'
replace_once(
    counter,
    "use SafeContracts\\Roles\\Capabilities;\n",
    "use SafeContracts\\Payments\\PaymentStatus;\nuse SafeContracts\\Roles\\Capabilities;\n",
)
replace_once(
    counter,
    """        return $this->rows($wpdb->get_results($sql, ARRAY_A));
    }

    /** @return list<array<string,mixed>> */
    public function settlements(array $filters = []): array
""",
    """        return $this->authoritativePaymentRows(
            $this->rows($wpdb->get_results($sql, ARRAY_A)),
            (string) ($f['status'] ?? '')
        );
    }

    /** @return list<array<string,mixed>> */
    public function settlements(array $filters = []): array
""",
)
replace_once(
    counter,
    """                       p.status AS payment_status, p.remaining_amount, c.id AS contract_id, c.contract_number,
""",
    """                       p.status AS payment_status, p.paid_amount, p.remaining_amount, c.id AS contract_id, c.contract_number,
""",
)
# Replace the settlements return (the second plain rows return after settlements SQL).
p = Path(counter)
text = p.read_text()
settle_start = text.index('    public function settlements(array $filters = []): array')
needle = '        return $this->rows($wpdb->get_results($sql, ARRAY_A));\n    }\n'
pos = text.find(needle, settle_start)
if pos < 0:
    raise SystemExit('settlements return marker missing')
replacement = """        return $this->authoritativeSettlementRows(
            $this->rows($wpdb->get_results($sql, ARRAY_A)),
            (string) ($f['status'] ?? '')
        );
    }
"""
text = text[:pos] + replacement + text[pos + len(needle):]
p.write_text(text)
# Do not filter payment statuses by stale p.status in SQL. Contract statuses still use SQL.
replace_once(
    counter,
    """        if ($filters['status'] !== '') {
            $paymentStatuses = ['upcoming', 'due_soon', 'due', 'overdue', 'partially_paid', 'paid'];
            $alias = $paymentAlias !== null && in_array($filters['status'], $paymentStatuses, true)
                ? $paymentAlias
                : $contractAlias;
            $where[] = $alias . ".status = '" . addslashes($filters['status']) . "'";
        }
""",
    """        if ($filters['status'] !== '') {
            $paymentStatuses = ['upcoming', 'due_soon', 'due', 'overdue', 'partially_paid', 'paid'];
            if ($paymentAlias === null || ! in_array($filters['status'], $paymentStatuses, true)) {
                $where[] = $contractAlias . ".status = '" . addslashes($filters['status']) . "'";
            }
        }
""",
)
insert_before(
    counter,
    "    /** @return list<array<string,mixed>> */\n    private function rows(mixed $rows): array\n",
    r'''    /** @param list<array<string,mixed>> $rows @return list<array<string,mixed>> */
    private function authoritativePaymentRows(array $rows, string $statusFilter): array
    {
        $paymentStatuses = PaymentStatus::all();
        $filter = in_array($statusFilter, $paymentStatuses, true) ? $statusFilter : '';
        $result = [];
        foreach ($rows as $row) {
            $row['status'] = PaymentStatus::authoritative(
                $row['due_date'] ?? '',
                $row['paid_amount'] ?? '0.0000',
                $row['remaining_amount'] ?? '0.0000'
            );
            if ($filter === '' || $row['status'] === $filter) {
                $result[] = $row;
            }
        }
        return $result;
    }

    /** @param list<array<string,mixed>> $rows @return list<array<string,mixed>> */
    private function authoritativeSettlementRows(array $rows, string $statusFilter): array
    {
        $paymentStatuses = PaymentStatus::all();
        $filter = in_array($statusFilter, $paymentStatuses, true) ? $statusFilter : '';
        $result = [];
        foreach ($rows as $row) {
            $row['payment_status'] = PaymentStatus::authoritative(
                $row['due_date'] ?? '',
                $row['paid_amount'] ?? '0.0000',
                $row['remaining_amount'] ?? '0.0000'
            );
            if ($filter === '' || $row['payment_status'] === $filter) {
                unset($row['paid_amount']);
                $result[] = $row;
            }
        }
        return $result;
    }

''',
)

# Extend existing P3 regression test with B001 read-time authority checks.
test = 'tests/php/payments_due_collections.php'
replace_once(
    test,
    "use SafeContracts\\Database\\Migrator;\n",
    "use SafeContracts\\Database\\Migrator;\nuse SafeContracts\\Rest\\DataController;\n",
)
marker = "$paymentService = new PaymentService();\n"
insert_before(
    test,
    marker,
    r'''// B001: stored temporal status is not a read authority. Due date + settlement
// amounts are authoritative for every API/mobile projection.
sc_dc_assert(
    PaymentStatus::authoritative('2026-08-01', '0.0000', '500.0000', $today) === PaymentStatus::OVERDUE,
    'B001 stale upcoming state is recomputed as overdue from contractual due date'
);
sc_dc_assert(
    PaymentStatus::authoritative('2026-09-30', '125.0000', '375.0000', $today) === PaymentStatus::PARTIALLY_PAID,
    'B001 actual paid/remaining amounts take precedence over future temporal state'
);
sc_dc_assert(
    PaymentStatus::authoritative('2026-09-30', '500.0000', '0.0000', $today) === PaymentStatus::PAID,
    'B001 zero remaining balance is paid regardless of stored temporal status'
);

$GLOBALS['sc_test_current_caps'] = [Capabilities::ACCESS=>true, Capabilities::VIEW_ALL=>true];
$GLOBALS['sc_test_result_queue'] = [[sc_dc_payment([
    'due_date'=>'2020-08-01',
    'status'=>'upcoming',
    'contract_number'=>'SC-501',
    'contract_is_archived'=>'0',
])]];
$listResponse = DataController::payments(new WP_REST_Request([
    'page'=>'1', 'per_page'=>'50', 'sort'=>'due_date', 'order'=>'asc'
]));
sc_dc_assert(
    ($listResponse->data['data'][0]['status'] ?? null) === PaymentStatus::OVERDUE,
    'B001 payments API list returns authoritative overdue status instead of stale stored upcoming'
);

$GLOBALS['sc_test_result_queue'] = [[sc_dc_payment([
    'due_date'=>'2020-08-01',
    'status'=>'upcoming',
    'contract_is_archived'=>'0',
])]];
$detailRequest = new WP_REST_Request([]);
$detailRequest->set_url_params(['id'=>'7001']);
$detailResponse = DataController::payment($detailRequest);
sc_dc_assert(
    ($detailResponse->data['data']['status'] ?? null) === PaymentStatus::OVERDUE,
    'B001 payment detail API returns the same authoritative overdue status as list API'
);

$GLOBALS['sc_test_result_queue'] = [[
    sc_dc_payment(['id'=>'7001','due_date'=>'2020-08-01','status'=>'upcoming','contract_number'=>'SC-501']),
    sc_dc_payment(['id'=>'7002','due_date'=>'2099-08-01','status'=>'upcoming','contract_number'=>'SC-502']),
]];
$filteredResponse = DataController::payments(new WP_REST_Request([
    'page'=>'1', 'per_page'=>'50', 'sort'=>'due_date', 'order'=>'asc', 'status'=>'overdue'
]));
sc_dc_assert(
    count($filteredResponse->data['data']) === 1
        && ($filteredResponse->data['data'][0]['id'] ?? null) === '7001',
    'B001 payment status filter uses authoritative recomputed status rather than stale database status'
);

''',
)

mobile = Path('mobile/test/alkenzy_w4_b001_payment_status_test.dart')
mobile.write_text(r'''import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/features/payments/payments.dart';

void main() {
  test('B001 Flutter consumes server-authoritative payment status unchanged', () {
    final payment = SafeContractsPayment.fromData(<String, Object?>{
      'id': 7001,
      'contract_id': 501,
      'contract_number': 'SC-501',
      'customer_id': 7,
      'customer_name': 'Customer',
      'counterparty_type': 'customer',
      'counterparty_id': 7,
      'counterparty_name': 'Customer',
      'financial_direction': 'receivable',
      'currency_code': 'KWD',
      'sequence_no': 1,
      'reference': 'P-001',
      'due_date': '2020-08-01',
      'expected_payment_date': '2099-08-01',
      'original_amount': '500.0000',
      'paid_amount': '0.0000',
      'remaining_amount': '500.0000',
      'status': 'overdue',
      'contract_is_archived': false,
    });

    expect(payment.status, 'overdue');
    expect(payment.dueDate, '2020-08-01');
    expect(payment.expectedPaymentDate, '2099-08-01');
    expect(payment.remainingAmount, '500.0000');
  });
}
''')

print('W4 B001 authoritative payment status patch applied')
