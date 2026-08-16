<?php

declare(strict_types=1);

namespace SafeContracts\Tenancy;

use RuntimeException;
use Throwable;

final class CoreTenantOwnershipBackfill
{
    /** @var array<string,string> */
    private const TABLES = [
        'customers' => 'safecontracts_customers',
        'contracts' => 'safecontracts_contracts',
        'contract_financial_items' => 'safecontracts_contract_financial_items',
        'contract_adjustments' => 'safecontracts_contract_adjustments',
        'contract_attachments' => 'safecontracts_contract_attachments',
        'contract_history' => 'safecontracts_contract_history',
        'scheduled_payments' => 'safecontracts_scheduled_payments',
        'payment_collections' => 'safecontracts_payment_collections',
        'payment_followups' => 'safecontracts_payment_followups',
    ];

    /** @return array{unowned:array<string,int>,mismatches:array<string,int>,ready:bool} */
    public function report(): array
    {
        global $wpdb;

        $unowned = [];
        foreach (self::TABLES as $name => $suffix) {
            $table = $wpdb->prefix . $suffix;
            $unowned[$name] = $this->count($wpdb, "SELECT COUNT(*) AS total FROM {$table} WHERE tenant_id IS NULL");
        }

        $contracts = $wpdb->prefix . self::TABLES['contracts'];
        $customers = $wpdb->prefix . self::TABLES['customers'];
        $payments = $wpdb->prefix . self::TABLES['scheduled_payments'];
        $collections = $wpdb->prefix . self::TABLES['payment_collections'];
        $followups = $wpdb->prefix . self::TABLES['payment_followups'];
        $items = $wpdb->prefix . self::TABLES['contract_financial_items'];
        $adjustments = $wpdb->prefix . self::TABLES['contract_adjustments'];
        $attachments = $wpdb->prefix . self::TABLES['contract_attachments'];
        $history = $wpdb->prefix . self::TABLES['contract_history'];

        $mismatches = [
            'contract_customer' => $this->count($wpdb, "SELECT COUNT(*) AS total FROM {$contracts} c INNER JOIN {$customers} cu ON cu.id = c.customer_id WHERE c.tenant_id IS NOT NULL AND cu.tenant_id IS NOT NULL AND c.tenant_id <> cu.tenant_id"),
            'payment_contract' => $this->count($wpdb, "SELECT COUNT(*) AS total FROM {$payments} p INNER JOIN {$contracts} c ON c.id = p.contract_id WHERE p.tenant_id IS NOT NULL AND c.tenant_id IS NOT NULL AND p.tenant_id <> c.tenant_id"),
            'collection_payment' => $this->count($wpdb, "SELECT COUNT(*) AS total FROM {$collections} cl INNER JOIN {$payments} p ON p.id = cl.payment_id WHERE cl.tenant_id IS NOT NULL AND p.tenant_id IS NOT NULL AND cl.tenant_id <> p.tenant_id"),
            'followup_payment' => $this->count($wpdb, "SELECT COUNT(*) AS total FROM {$followups} f INNER JOIN {$payments} p ON p.id = f.payment_id WHERE f.tenant_id IS NOT NULL AND p.tenant_id IS NOT NULL AND f.tenant_id <> p.tenant_id"),
            'financial_item_contract' => $this->count($wpdb, "SELECT COUNT(*) AS total FROM {$items} i INNER JOIN {$contracts} c ON c.id = i.contract_id WHERE i.tenant_id IS NOT NULL AND c.tenant_id IS NOT NULL AND i.tenant_id <> c.tenant_id"),
            'adjustment_contract' => $this->count($wpdb, "SELECT COUNT(*) AS total FROM {$adjustments} a INNER JOIN {$contracts} c ON c.id = a.contract_id WHERE a.tenant_id IS NOT NULL AND c.tenant_id IS NOT NULL AND a.tenant_id <> c.tenant_id"),
            'attachment_contract' => $this->count($wpdb, "SELECT COUNT(*) AS total FROM {$attachments} a INNER JOIN {$contracts} c ON c.id = a.contract_id WHERE a.tenant_id IS NOT NULL AND c.tenant_id IS NOT NULL AND a.tenant_id <> c.tenant_id"),
            'history_contract' => $this->count($wpdb, "SELECT COUNT(*) AS total FROM {$history} h INNER JOIN {$contracts} c ON c.id = h.contract_id WHERE h.tenant_id IS NOT NULL AND c.tenant_id IS NOT NULL AND h.tenant_id <> c.tenant_id"),
        ];

        return [
            'unowned' => $unowned,
            'mismatches' => $mismatches,
            'ready' => array_sum($unowned) === 0 && array_sum($mismatches) === 0,
        ];
    }

    /**
     * Assign all remaining unowned legacy customers to one explicitly reviewed tenant,
     * then derive all child ownership from parent relations. Existing ownership is never overwritten.
     *
     * @return array{unowned:array<string,int>,mismatches:array<string,int>,ready:bool}
     */
    public function applyDefaultTenant(int $tenantId): array
    {
        if ($tenantId <= 0) {
            throw new RuntimeException('A positive Enterprise tenant id is required for legacy backfill.');
        }

        global $wpdb;
        $this->assertActiveTenant($wpdb, $tenantId);

        $customers = $wpdb->prefix . self::TABLES['customers'];
        $contracts = $wpdb->prefix . self::TABLES['contracts'];
        $items = $wpdb->prefix . self::TABLES['contract_financial_items'];
        $adjustments = $wpdb->prefix . self::TABLES['contract_adjustments'];
        $attachments = $wpdb->prefix . self::TABLES['contract_attachments'];
        $history = $wpdb->prefix . self::TABLES['contract_history'];
        $payments = $wpdb->prefix . self::TABLES['scheduled_payments'];
        $collections = $wpdb->prefix . self::TABLES['payment_collections'];
        $followups = $wpdb->prefix . self::TABLES['payment_followups'];

        $wpdb->query('START TRANSACTION');
        try {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$customers} SET tenant_id = %d WHERE tenant_id IS NULL",
                $tenantId
            ));
            $wpdb->query("UPDATE {$contracts} c INNER JOIN {$customers} cu ON cu.id = c.customer_id SET c.tenant_id = cu.tenant_id WHERE c.tenant_id IS NULL AND cu.tenant_id IS NOT NULL");
            $wpdb->query("UPDATE {$items} i INNER JOIN {$contracts} c ON c.id = i.contract_id SET i.tenant_id = c.tenant_id WHERE i.tenant_id IS NULL AND c.tenant_id IS NOT NULL");
            $wpdb->query("UPDATE {$adjustments} a INNER JOIN {$contracts} c ON c.id = a.contract_id SET a.tenant_id = c.tenant_id WHERE a.tenant_id IS NULL AND c.tenant_id IS NOT NULL");
            $wpdb->query("UPDATE {$attachments} a INNER JOIN {$contracts} c ON c.id = a.contract_id SET a.tenant_id = c.tenant_id WHERE a.tenant_id IS NULL AND c.tenant_id IS NOT NULL");
            $wpdb->query("UPDATE {$history} h INNER JOIN {$contracts} c ON c.id = h.contract_id SET h.tenant_id = c.tenant_id WHERE h.tenant_id IS NULL AND c.tenant_id IS NOT NULL");
            $wpdb->query("UPDATE {$payments} p INNER JOIN {$contracts} c ON c.id = p.contract_id SET p.tenant_id = c.tenant_id WHERE p.tenant_id IS NULL AND c.tenant_id IS NOT NULL");
            $wpdb->query("UPDATE {$collections} cl INNER JOIN {$payments} p ON p.id = cl.payment_id SET cl.tenant_id = p.tenant_id WHERE cl.tenant_id IS NULL AND p.tenant_id IS NOT NULL");
            $wpdb->query("UPDATE {$followups} f INNER JOIN {$payments} p ON p.id = f.payment_id SET f.tenant_id = p.tenant_id WHERE f.tenant_id IS NULL AND p.tenant_id IS NOT NULL");

            $report = $this->report();
            if (! $report['ready']) {
                $wpdb->query('ROLLBACK');
                throw new RuntimeException('Enterprise tenant ownership backfill left unowned or cross-tenant core records; no changes were committed.');
            }

            $wpdb->query('COMMIT');
            return $report;
        } catch (Throwable $error) {
            if (! str_contains($error->getMessage(), 'no changes were committed')) {
                $wpdb->query('ROLLBACK');
            }
            throw $error;
        }
    }

    public function assertReadyForEnforcement(): void
    {
        $report = $this->report();
        if (! $report['ready']) {
            throw new RuntimeException('Core Enterprise business data is not ready for tenant enforcement.');
        }
    }

    private function assertActiveTenant(object $wpdb, int $tenantId): void
    {
        $tenants = $wpdb->prefix . 'safecontracts_tenants';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM {$tenants} WHERE id = %d AND status = 'active' LIMIT 1",
            $tenantId
        ), ARRAY_A);
        if (! is_array($rows) || $rows === []) {
            throw new RuntimeException('Legacy backfill target must be an active Enterprise tenant.');
        }
    }

    private function count(object $wpdb, string $sql): int
    {
        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (! is_array($rows) || $rows === []) {
            return 0;
        }
        return max(0, (int) ($rows[0]['total'] ?? 0));
    }
}
