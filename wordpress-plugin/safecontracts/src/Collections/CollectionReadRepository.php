<?php

declare(strict_types=1);

namespace SafeContracts\Collections;

use RuntimeException;
use SafeContracts\Tenancy\CoreTenantScope;

final class CollectionReadRepository
{
    /** @return array<string,mixed>|null */
    public function find(int $collectionId): ?array
    {
        global $wpdb;
        if (! is_object($wpdb) || ! method_exists($wpdb, 'prepare') || ! method_exists($wpdb, 'get_results')) {
            throw new RuntimeException('SafeContracts collection reads require WordPress $wpdb.');
        }
        if ($collectionId <= 0) {
            return null;
        }

        $collections = $wpdb->prefix . 'safecontracts_payment_collections';
        $payments = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $methods = $wpdb->prefix . 'safecontracts_payment_methods';
        $tenantId = CoreTenantScope::tenantId();
        $tenant = $tenantId === null
            ? ''
            : ' AND cl.tenant_id = ' . $tenantId . ' AND p.tenant_id = ' . $tenantId . ' AND c.tenant_id = ' . $tenantId;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT cl.id, cl.payment_id, cl.amount, cl.collection_date, cl.payment_method_id,
                        pm.name AS payment_method_name, cl.reference, cl.proof_media_id, cl.created_by,
                        cl.created_at, cl.updated_at, p.contract_id, c.accountant_user_id
                 FROM {$collections} cl
                 INNER JOIN {$payments} p ON p.id = cl.payment_id
                 INNER JOIN {$contracts} c ON c.id = p.contract_id
                 INNER JOIN {$methods} pm ON pm.id = cl.payment_method_id
                 WHERE cl.id = %d AND cl.is_archived = 0 AND p.is_archived = 0{$tenant}
                 LIMIT 1",
                $collectionId
            ),
            ARRAY_A
        );

        if (! is_array($rows) || $rows === [] || ! is_array($rows[0])) {
            return null;
        }

        $row = $rows[0];
        return [
            'id' => (int) ($row['id'] ?? 0),
            'payment_id' => (int) ($row['payment_id'] ?? 0),
            'contract_id' => (int) ($row['contract_id'] ?? 0),
            'accountant_user_id' => isset($row['accountant_user_id']) && $row['accountant_user_id'] !== null
                ? (int) $row['accountant_user_id']
                : null,
            'amount' => (string) ($row['amount'] ?? '0.0000'),
            'collection_date' => (string) ($row['collection_date'] ?? ''),
            'payment_method_id' => (int) ($row['payment_method_id'] ?? 0),
            'payment_method_name' => (string) ($row['payment_method_name'] ?? ''),
            'reference' => isset($row['reference']) && $row['reference'] !== null ? (string) $row['reference'] : null,
            'proof_media_id' => isset($row['proof_media_id']) && $row['proof_media_id'] !== null ? (int) $row['proof_media_id'] : null,
            'created_by' => isset($row['created_by']) && $row['created_by'] !== null ? (int) $row['created_by'] : null,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }
}
