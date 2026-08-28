<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

use SafeContracts\Contracts\Counterparty;
use SafeContracts\Payments\FinancialDirection;

/**
 * Normalizes notification scope without silently rewriting a valid scheduled
 * payment obligation.
 *
 * `financial_direction` is duplicated on contracts and scheduled payments.
 * Once a scheduled payment has a valid receivable/payable value, that payment
 * row is the authoritative obligation for notification matching. The owning
 * contract remains authoritative for counterparty identity and is used only as
 * a direction fallback when the payment row has no valid direction.
 *
 * This matters for historical production data where a payable Supplier payment
 * can coexist with a stale receivable value on the owning contract. Replacing
 * the payment direction from that stale contract value makes every
 * supplier/payable notification rule disappear before schedule materialization.
 */
final class NotificationPaymentScope
{
    /** @param array<string,mixed> $payment @return array<string,mixed> */
    public static function canonicalize(array $payment): array
    {
        $contractId = (int) ($payment['contract_id'] ?? 0);
        if ($contractId <= 0) {
            return self::apply($payment, null);
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

        $scopes = $contractIds === [] ? [] : self::contractScopes(array_values($contractIds));
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
        $paymentDirection = self::validDirection($payment['financial_direction'] ?? null);
        $contractDirection = $scope === null ? null : self::validDirection($scope['financial_direction'] ?? null);

        if ($paymentDirection !== null) {
            $payment['financial_direction'] = $paymentDirection;
            $payment['notification_direction_source'] = 'payment_row';
        } elseif ($contractDirection !== null) {
            $payment['financial_direction'] = $contractDirection;
            $payment['notification_direction_source'] = 'contract_fallback';
        } else {
            $payment['notification_direction_source'] = 'unresolved';
        }

        if ($scope !== null) {
            $counterpartyType = strtolower(trim((string) ($scope['counterparty_type'] ?? '')));
            if (in_array($counterpartyType, [Counterparty::CUSTOMER, Counterparty::SUPPLIER], true)) {
                $payment['counterparty_type'] = $counterpartyType;
            }
        }

        $payment['notification_contract_direction'] = $contractDirection;
        $payment['notification_direction_mismatch'] = $paymentDirection !== null
            && $contractDirection !== null
            && $paymentDirection !== $contractDirection;
        $payment['notification_scope_source'] = $scope === null
            ? 'payment_row'
            : ($paymentDirection !== null ? 'payment_direction_contract_counterparty' : 'contract_fallback');

        return $payment;
    }

    private static function validDirection(mixed $value): ?string
    {
        $direction = strtolower(trim((string) $value));
        return in_array($direction, [FinancialDirection::RECEIVABLE, FinancialDirection::PAYABLE], true)
            ? $direction
            : null;
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
            if (! in_array($counterpartyType, [Counterparty::CUSTOMER, Counterparty::SUPPLIER], true)) {
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
