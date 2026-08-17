<?php

declare(strict_types=1);

namespace SafeContracts\Finance;

use InvalidArgumentException;
use RuntimeException;
use SafeContracts\Tenancy\TenantContext;
use UnexpectedValueException;

final class TenantBaseCurrencyRepository
{
    public function resolve(TenantContext $context): CurrencyCode
    {
        $tenantId = $context->requireTenantId();

        global $wpdb;
        $tenants = $wpdb->prefix . 'safecontracts_tenants';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, default_currency
             FROM {$tenants}
             WHERE id = %d AND status = 'active'
             LIMIT 1",
            $tenantId
        ), ARRAY_A);

        if (! is_array($rows) || count($rows) !== 1 || ! is_array($rows[0])) {
            throw new RuntimeException('Active Enterprise tenant base currency could not be resolved.');
        }

        $row = $rows[0];
        if ((int) ($row['id'] ?? 0) !== $tenantId) {
            throw new RuntimeException('Enterprise tenant base currency lookup returned a mismatched tenant.');
        }

        try {
            return CurrencyCode::from($row['default_currency'] ?? null);
        } catch (InvalidArgumentException $error) {
            throw new UnexpectedValueException(
                'Enterprise tenant has an invalid financial base currency.',
                0,
                $error
            );
        }
    }
}
