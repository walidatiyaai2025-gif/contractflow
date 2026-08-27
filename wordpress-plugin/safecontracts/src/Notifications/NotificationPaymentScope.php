<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

use SafeContracts\Contracts\Counterparty;
use SafeContracts\Payments\FinancialDirection;

/**
 * Canonicalizes notification matching scope from contract-owned business truth.
 *
 * Historical scheduled-payment rows can carry a stale financial_direction after
 * a contract/counterparty migration. Notification rules must not silently skip a
 * supplier merely because that duplicated payment field is stale: customer
 * contracts are receivable and supplier contracts are payable.
 */
final class NotificationPaymentScope
{
    /** @param array<string,mixed> $payment @return array<string,mixed> */
    public static function canonicalize(array $payment): array
    {
        $counterpartyType = strtolower(trim((string) ($payment['counterparty_type'] ?? '')));
        $storedDirection = strtolower(trim((string) ($payment['financial_direction'] ?? '')));

        $effectiveDirection = match ($counterpartyType) {
            Counterparty::CUSTOMER => FinancialDirection::RECEIVABLE,
            Counterparty::SUPPLIER => FinancialDirection::PAYABLE,
            default => $storedDirection,
        };

        if ($effectiveDirection !== '') {
            $payment['financial_direction'] = $effectiveDirection;
        }
        $payment['notification_scope_source'] = in_array(
            $counterpartyType,
            [Counterparty::CUSTOMER, Counterparty::SUPPLIER],
            true
        ) ? 'contract_counterparty' : 'payment_row';

        return $payment;
    }
}
