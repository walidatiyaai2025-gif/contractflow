<?php

declare(strict_types=1);

namespace SafeContracts\Contracts;

final class ContractHistoryRecorder
{
    private const EVENTS = [
        'safecontracts_contract_created' => 'created',
        'safecontracts_contract_edited' => 'edited',
        'safecontracts_contract_dates_changed' => 'dates_changed',
        'safecontracts_contract_base_value_changed' => 'base_value_changed',
        'safecontracts_contract_currency_changed' => 'currency_changed',
        'safecontracts_contract_financial_item_added' => 'financial_item_added',
        'safecontracts_contract_adjustment_added' => 'adjustment_added',
        'safecontracts_contract_attachment_added' => 'attachment_added',
        'safecontracts_contract_attachment_removed' => 'attachment_removed',
        'safecontracts_contract_customer_assigned' => 'customer_assigned',
        'safecontracts_contract_counterparty_assigned' => 'counterparty_assigned',
        'safecontracts_contract_accountant_assigned' => 'accountant_assigned',
        'safecontracts_contract_status_changed' => 'status_changed',
    ];

    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;

        foreach (self::EVENTS as $hook => $eventType) {
            add_action($hook, static function (mixed $contractId) use ($eventType): void {
                self::record((int) $contractId, $eventType);
            });
        }
    }

    public static function record(int $contractId, string $eventType): void
    {
        if ($contractId <= 0) {
            return;
        }

        $contract = (new ContractRepository())->find($contractId);
        if ($contract === null) {
            return;
        }

        $actorId = get_current_user_id();
        (new ContractHistoryRepository())->append(
            $contractId,
            $eventType,
            $actorId > 0 ? $actorId : null,
            $contract
        );
    }
}
