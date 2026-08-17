<?php

declare(strict_types=1);

namespace SafeContracts\Finance;

final class FinanceOverviewService
{
    public function __construct(
        private ?FinanceSummaryRepository $summaryRepository = null,
        private ?FinanceAgingRepository $agingRepository = null,
        private ?FinanceObligationRepository $obligationRepository = null
    ) {
        $this->summaryRepository ??= new FinanceSummaryRepository();
        $this->agingRepository ??= new FinanceAgingRepository();
        $this->obligationRepository ??= new FinanceObligationRepository();
    }

    /** @return array<string,mixed> */
    public function overview(array $input = []): array
    {
        $directions = FinanceReadAccess::authorizedDirections();
        if ($directions === []) {
            return [
                'directions' => [],
                'summary' => [],
                'aging' => [],
                'cash_flow' => [],
                'action_center' => [],
                'work_queue_preview' => [],
            ];
        }

        $summary = $this->summaryRepository->summary($input);
        $aging = $this->agingRepository->aging($input);
        $cashFlow = $this->summaryRepository->cashFlow($input, 90);
        $previewInput = $input;
        $previewInput['limit'] = 25;
        $workQueue = $this->obligationRepository->obligations($previewInput);

        return [
            'directions' => $directions,
            'summary' => $summary,
            'aging' => $aging,
            'cash_flow' => array_map(static function (array $row): array {
                $direction = (string) ($row['financial_direction'] ?? '');
                return [
                    ...$row,
                    'cash_flow_kind' => $direction === FinancialDirection::PAYABLE ? 'outflow' : 'inflow',
                ];
            }, $cashFlow),
            'action_center' => $this->actionCenter($summary),
            'work_queue_preview' => $workQueue,
        ];
    }

    /** @return list<array<string,mixed>> */
    public function obligations(array $input = []): array
    {
        return $this->obligationRepository->obligations($input);
    }

    /** @return list<array<string,mixed>> */
    private function actionCenter(array $summary): array
    {
        $items = [];
        foreach ($summary as $row) {
            $direction = (string) ($row['financial_direction'] ?? '');
            $currency = (string) ($row['currency_code'] ?? 'UNSET');
            $overdueCount = (int) ($row['overdue_count'] ?? 0);
            $dueTodayCount = (int) ($row['due_today_count'] ?? 0);
            $due7Count = (int) ($row['due_7_count'] ?? 0);

            if ($overdueCount > 0) {
                $items[] = [
                    'kind' => 'overdue',
                    'direction' => $direction,
                    'currency_code' => $currency,
                    'count' => $overdueCount,
                    'amount' => (string) ($row['overdue_total'] ?? '0'),
                    'priority' => 'urgent',
                    'drill_down' => [
                        'direction' => $direction,
                        'currency_code' => $currency,
                        'status' => 'overdue',
                    ],
                ];
            }
            if ($dueTodayCount > 0) {
                $items[] = [
                    'kind' => 'due_today',
                    'direction' => $direction,
                    'currency_code' => $currency,
                    'count' => $dueTodayCount,
                    'amount' => (string) ($row['due_today_total'] ?? '0'),
                    'priority' => 'high',
                    'drill_down' => [
                        'direction' => $direction,
                        'currency_code' => $currency,
                        'due_window' => 'today',
                    ],
                ];
            }
            if ($due7Count > 0) {
                $items[] = [
                    'kind' => 'due_7_days',
                    'direction' => $direction,
                    'currency_code' => $currency,
                    'count' => $due7Count,
                    'amount' => (string) ($row['due_7_total'] ?? '0'),
                    'priority' => 'normal',
                    'drill_down' => [
                        'direction' => $direction,
                        'currency_code' => $currency,
                        'due_window' => 'next_7_days',
                    ],
                ];
            }
        }

        usort($items, static function (array $left, array $right): int {
            $rank = ['urgent' => 0, 'high' => 1, 'normal' => 2];
            return ($rank[(string) ($left['priority'] ?? 'normal')] ?? 9)
                <=> ($rank[(string) ($right['priority'] ?? 'normal')] ?? 9);
        });
        return $items;
    }
}
