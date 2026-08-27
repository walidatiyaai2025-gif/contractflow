<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

use SafeContracts\Contracts\Counterparty;
use SafeContracts\Payments\FinancialDirection;

/**
 * Reconciles duplicated payment scope with its owning contract before a
 * notification rule is matched.
 *
 * Financial direction is an independent accounting dimension: a customer may
 * legitimately be payable and a supplier may legitimately be receivable. The
 * owning contract is therefore authoritative; counterparty type must never be
 * used to invent a direction. Historical scheduled-payment rows can retain a
 * stale copy after migrations, which previously made supplier rules disappear
 * from the notification schedule.
 */
final class NotificationPaymentScope
{
    /** @param array<string,mixed> $payment @return array<string,mixed> */
    public static function canonicalize(array $payment): array
    {
        $contractId = (int) ($payment['contract_id'] ?? 0);
        if ($contractId <= 0) {
            return $payment;
        }

        $scopes = self::contractScopes([$contractId]);
        return self::apply($payment, $scopes[$contractId] ?? null);
    }

    /**
     * @param list<array<string,mixed>> $payments
     * @return list<array<string,mixed>>
     */
    public static function canonicalizeMany(array $payments): array
    {
        $contractIds = [];
        foreach ($payments as $payment) {
            $contractId = (int) ($payment['contract_id'] ?? 0);
            if ($contractId > 0) {
                $contractIds[$contractId] = $contractId;
            }
        }
        if ($contractIds === []) {
            return array_values($payments);
        }

        $scopes = self::contractScopes(array_values($contractIds));
        $normalized = [];
        foreach ($payments as $payment) {
            $contractId = (int) ($payment['contract_id'] ?? 0);
            $normalized[] = self::apply($payment, $scopes[$contractId] ?? null);
        }
        return $normalized;
    }

    /**
     * @param array{financial_direction:string,counterparty_type:string}|null $scope
     * @param array<string,mixed> $payment
     * @return array<string,mixed>
     */
    private static function apply(array $payment, ?array $scope): array
    {
        if ($scope === null) {
            $payment['notification_scope_source'] = 'payment_row';
            return $payment;
        }

        $direction = strtolower(trim($scope['financial_direction']));
        if (in_array($direction, [FinancialDirection::RECEIVABLE, FinancialDirection::PAYABLE], true)) {
            $payment['financial_direction'] = $direction;
        }

        $counterpartyType = strtolower(trim($scope['counterparty_type']));
        if (in_array($counterpartyType, [Counterparty::CUSTOMER, Counterparty::SUPPLIER], true)) {
            $payment['counterparty_type'] = $counterpartyType;
        }

        $payment['notification_scope_source'] = 'contract';
        return $payment;
    }

    /**
     * @param list<int> $contractIds
     * @return array<int,array{financial_direction:string,counterparty_type:string}>
     */
    private static function contractScopes(array $contractIds): array
    {
        global $wpdb;
        if (! is_object($wpdb) || $contractIds === []) {
            return [];
        }

        $contractIds = array_values(array_unique(array_filter(array_map('intval', $contractIds), static fn (int $id): bool => $id > 0)));
        if ($contractIds === []) {
            return [];
        }

        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $placeholders = implode(',', array_fill(0, count($contractIds), '%d'));
        $sql = $wpdb->prepare(
            "SELECT id, financial_direction, counterparty_type
             FROM {$contracts}
             WHERE id IN ({$placeholders}) AND is_archived = 0",
            ...$contractIds
        );
        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (! is_array($rows)) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $direction = strtolower(trim((string) ($row['financial_direction'] ?? '')));
            $counterpartyType = strtolower(trim((string) ($row['counterparty_type'] ?? '')));
            if (! in_array($direction, [FinancialDirection::RECEIVABLE, FinancialDirection::PAYABLE], true)
                || ! in_array($counterpartyType, [Counterparty::CUSTOMER, Counterparty::SUPPLIER], true)) {
                continue;
            }
            $result[$id] = [
                'financial_direction' => $direction,
                'counterparty_type' => $counterpartyType,
            ];
        }
        return $result;
    }
}
