<?php

declare(strict_types=1);

namespace SafeContracts\Reports;

use SafeContracts\Admin\AdminReadRepository;
use SafeContracts\Admin\DashboardFilters;
use SafeContracts\FollowUps\FollowUpService;
use SafeContracts\Roles\Capabilities;

final class ReportExportService
{
    public function __construct(
        private ?AdminReadRepository $read = null,
        private ?FollowUpService $followUps = null,
        private ?XlsxWorkbook $workbook = null
    ) {
        $this->read ??= new AdminReadRepository();
        $this->followUps ??= new FollowUpService();
        $this->workbook ??= new XlsxWorkbook();
    }

    /** @param array<string,mixed> $input */
    public function generate(array $input): array
    {
        if (! current_user_can(Capabilities::EXPORT_REPORTS)) {
            throw new \DomainException('You do not have permission to export SafeContracts reports.');
        }

        $filters = DashboardFilters::normalize($input);
        if (! empty($filters['date_range_error'])) {
            throw new \InvalidArgumentException('Report period is invalid.');
        }
        $summary = $this->read->reportSummary($filters);
        $customers = $this->read->customers($filters);
        $contracts = $this->read->contracts($filters);
        $payments = $this->read->payments($filters);
        $collections = $this->read->collections($filters);
        $followUps = $this->filterFollowUps(
            $this->followUps->queue(500, $filters['date_from'], $filters['date_to']),
            $filters
        );

        $sheets = [
            'Summary' => $this->summaryRows($summary, $filters),
            'Customers' => $this->rows($customers, ['id','internal_code','name','contact_name','email','phone','is_active','created_at']),
            'Contracts' => $this->rows($contracts, ['id','contract_number','customer_id','customer_name','accountant_user_id','status','start_date','end_date','base_value','is_archived','created_at']),
            'Payments' => $this->rows($payments, ['id','contract_id','contract_number','customer_id','customer_name','accountant_user_id','sequence_no','reference','due_date','expected_payment_date','original_amount','paid_amount','remaining_amount','status']),
            'Collections' => $this->rows($collections, ['id','payment_id','contract_id','contract_number','customer_id','customer_name','accountant_user_id','collection_date','amount','payment_method_name','reference','proof_media_id','created_by','created_at']),
            'Follow-up Queue' => $this->rows($followUps, ['payment_id','contract_id','customer_id','accountant_user_id','contract_status','reference','due_date','expected_payment_date','original_amount','paid_amount','remaining_amount','status','followup_state']),
        ];

        $binary = $this->workbook->build($sheets);
        $date = function_exists('wp_date') ? wp_date('Y-m-d') : gmdate('Y-m-d');
        $filename = 'SafeContracts-report-' . $date . '.xlsx';
        $counts = [
            'customers' => count($customers),
            'contracts' => count($contracts),
            'payments' => count($payments),
            'collections' => count($collections),
            'followups' => count($followUps),
        ];

        do_action('safecontracts_export_completed', [
            'type' => 'admin_report_xlsx',
            'filename' => $filename,
            'filters' => $filters,
            'row_counts' => $counts,
        ], get_current_user_id());

        return [
            'filename' => $filename,
            'content_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'content' => $binary,
            'filters' => $filters,
            'row_counts' => $counts,
        ];
    }

    /** @param array<string,mixed> $summary @param array<string,mixed> $filters @return list<array<int,scalar|null>> */
    private function summaryRows(array $summary, array $filters): array
    {
        $rows = [['Metric', 'Value']];
        foreach ([
            'contract_count','scheduled_total','remaining_total','overdue_exposure','collected_total',
            'collection_transactions','collection_ledger_total','followup_events','followed_up_payments',
        ] as $key) {
            $rows[] = [$key, $summary[$key] ?? ''];
        }
        $rows[] = ['', ''];
        $rows[] = ['Filter', 'Value'];
        foreach (['customer_id','contract_id','accountant_user_id','status','date_from','date_to'] as $key) {
            $rows[] = [$key, $filters[$key] ?? ''];
        }
        $rows[] = ['period_semantics', 'payments=due_date; collections=collection_date; followup_metrics=event_created_at; followup_queue=due_date; contracts=start_or_created; customers=created_at'];
        return $rows;
    }

    /** @param list<array<string,mixed>> $items @param list<string> $columns @return list<array<int,scalar|null>> */
    private function rows(array $items, array $columns): array
    {
        $rows = [$columns];
        foreach ($items as $item) {
            $row = [];
            foreach ($columns as $column) {
                $value = $item[$column] ?? '';
                $row[] = is_scalar($value) || $value === null ? $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            $rows[] = $row;
        }
        return $rows;
    }

    /** @param list<array<string,mixed>> $rows @param array<string,mixed> $filters @return list<array<string,mixed>> */
    private function filterFollowUps(array $rows, array $filters): array
    {
        $paymentStatuses = ['upcoming', 'due_soon', 'due', 'overdue', 'partially_paid', 'paid'];
        return array_values(array_filter($rows, static function (array $row) use ($filters, $paymentStatuses): bool {
            if (($filters['customer_id'] ?? 0) > 0 && (int) ($row['customer_id'] ?? 0) !== (int) $filters['customer_id']) {
                return false;
            }
            if (($filters['contract_id'] ?? 0) > 0 && (int) ($row['contract_id'] ?? 0) !== (int) $filters['contract_id']) {
                return false;
            }
            if (($filters['accountant_user_id'] ?? 0) > 0 && current_user_can(Capabilities::VIEW_ALL)
                && (int) ($row['accountant_user_id'] ?? 0) !== (int) $filters['accountant_user_id']) {
                return false;
            }
            $status = (string) ($filters['status'] ?? '');
            if ($status !== '') {
                $rowStatus = in_array($status, $paymentStatuses, true)
                    ? (string) ($row['status'] ?? '')
                    : (string) ($row['contract_status'] ?? '');
                if ($rowStatus !== $status) {
                    return false;
                }
            }
            return true;
        }));
    }
}
