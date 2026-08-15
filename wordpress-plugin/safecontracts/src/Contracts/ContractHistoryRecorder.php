<?php

declare(strict_types=1);

namespace SafeContracts\Contracts;

use Throwable;

final class ContractHistoryRecorder
{
    private static ?ContractHistoryRepository $repository = null;

    public static function register(): void
    {
        add_action('safecontracts_contract_created', static function (mixed $contractId, mixed $actorId, mixed $customerId, mixed $accountantUserId): void {
            self::record((int) $contractId, 'created', (int) $actorId, [
                'customer_id' => (int) $customerId,
                'accountant_user_id' => $accountantUserId === null ? null : (int) $accountantUserId,
            ]);
        }, 10, 4);

        add_action('safecontracts_contract_edited', static function (mixed $contractId, mixed $actorId): void {
            self::record((int) $contractId, 'edited', (int) $actorId);
        }, 10, 2);

        add_action('safecontracts_contract_dates_changed', static function (mixed $contractId, mixed $start, mixed $end, mixed $actorId): void {
            self::record((int) $contractId, 'dates_changed', (int) $actorId, [
                'start_date' => $start === null ? null : (string) $start,
                'end_date' => $end === null ? null : (string) $end,
            ]);
        }, 10, 4);

        add_action('safecontracts_contract_base_value_changed', static function (mixed $contractId, mixed $amount, mixed $actorId): void {
            self::record((int) $contractId, 'base_value_changed', (int) $actorId, ['amount' => (string) $amount]);
        }, 10, 3);

        add_action('safecontracts_contract_financial_item_added', static function (mixed $contractId, mixed $itemId, mixed $amount, mixed $actorId): void {
            self::record((int) $contractId, 'financial_item_added', (int) $actorId, [
                'item_id' => (int) $itemId,
                'amount' => (string) $amount,
            ]);
        }, 10, 4);

        add_action('safecontracts_contract_adjustment_added', static function (mixed $contractId, mixed $adjustmentId, mixed $type, mixed $amount, mixed $actorId): void {
            self::record((int) $contractId, 'adjustment_added', (int) $actorId, [
                'adjustment_id' => (int) $adjustmentId,
                'adjustment_type' => (string) $type,
                'amount' => (string) $amount,
            ]);
        }, 10, 5);

        add_action('safecontracts_contract_attachment_added', static function (mixed $contractId, mixed $mediaId, mixed $actorId): void {
            self::record((int) $contractId, 'attachment_added', (int) $actorId, ['media_id' => (int) $mediaId]);
        }, 10, 3);

        add_action('safecontracts_contract_attachment_removed', static function (mixed $contractId, mixed $mediaId, mixed $actorId): void {
            self::record((int) $contractId, 'attachment_removed', (int) $actorId, ['media_id' => (int) $mediaId]);
        }, 10, 3);

        add_action('safecontracts_contract_customer_assigned', static function (mixed $contractId, mixed $customerId, mixed $actorId): void {
            self::record((int) $contractId, 'customer_assigned', (int) $actorId, ['customer_id' => (int) $customerId]);
        }, 10, 3);

        add_action('safecontracts_contract_accountant_assigned', static function (mixed $contractId, mixed $accountantUserId, mixed $actorId): void {
            self::record((int) $contractId, 'accountant_assigned', (int) $actorId, [
                'accountant_user_id' => $accountantUserId === null ? null : (int) $accountantUserId,
            ]);
        }, 10, 3);

        add_action('safecontracts_contract_status_changed', static function (mixed $contractId, mixed $from, mixed $to, mixed $actorId): void {
            self::record((int) $contractId, 'status_changed', (int) $actorId, [
                'from' => (string) $from,
                'to' => (string) $to,
            ]);
        }, 10, 4);
    }

    /** @param array<string, mixed> $context */
    private static function record(int $contractId, string $action, int $actorId, array $context = []): void
    {
        if ($contractId <= 0) {
            return;
        }

        try {
            self::$repository ??= new ContractHistoryRepository();
            self::$repository->record($contractId, $action, $actorId > 0 ? $actorId : null, $context);
        } catch (Throwable $error) {
            do_action('safecontracts_contract_history_failed', $contractId, $action, $error);
        }
    }
}
