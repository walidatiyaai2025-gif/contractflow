<?php

declare(strict_types=1);

namespace SafeContracts\Tenancy;

use RuntimeException;
use Throwable;

final class NonCoreTenantOwnershipBackfill
{
    /** @var array<string,string> */
    private const TABLES = [
        'notification_rules' => 'safecontracts_notification_rules',
        'notification_templates' => 'safecontracts_notification_templates',
        'device_tokens' => 'safecontracts_device_tokens',
        'notification_deliveries' => 'safecontracts_notification_deliveries',
        'notification_schedule' => 'safecontracts_notification_schedule',
        'notification_suppressions' => 'safecontracts_notification_suppressions',
        'import_runs' => 'safecontracts_import_runs',
        'import_errors' => 'safecontracts_import_errors',
        'audit_log' => 'safecontracts_audit_log',
    ];

    /** @var array<string,string> */
    private const ROOT_GROUPS = [
        'rules' => 'notification_rules',
        'templates' => 'notification_templates',
        'devices' => 'device_tokens',
        'deliveries' => 'notification_deliveries',
        'imports' => 'import_runs',
        'suppressions' => 'notification_suppressions',
        'audit' => 'audit_log',
    ];

    /** @return list<string> */
    public static function rootGroups(): array
    {
        return array_keys(self::ROOT_GROUPS);
    }

    /**
     * @return array{
     *   unowned:array<string,int>,
     *   mismatches:array<string,int>,
     *   platform_global:array<string,int>,
     *   ready:bool
     * }
     */
    public function report(): array
    {
        global $wpdb;
        $this->assertWpdb($wpdb);

        $unowned = [];
        foreach (self::TABLES as $name => $suffix) {
            if ($name === 'audit_log') {
                continue;
            }
            $table = $wpdb->prefix . $suffix;
            $unowned[$name] = $this->count($wpdb, "SELECT COUNT(*) AS total FROM {$table} WHERE tenant_id IS NULL");
        }

        $audit = $wpdb->prefix . self::TABLES['audit_log'];
        $unowned['audit_tenant_required'] = $this->count(
            $wpdb,
            "SELECT COUNT(*) AS total FROM {$audit} WHERE tenant_id IS NULL AND " . $this->tenantRequiredAuditPredicate()
        );
        $platformGlobal = [
            'audit_rows' => $this->count(
                $wpdb,
                "SELECT COUNT(*) AS total FROM {$audit} WHERE tenant_id IS NULL AND NOT (" . $this->tenantRequiredAuditPredicate() . ')'
            ),
        ];

        $rules = $wpdb->prefix . self::TABLES['notification_rules'];
        $deliveries = $wpdb->prefix . self::TABLES['notification_deliveries'];
        $schedule = $wpdb->prefix . self::TABLES['notification_schedule'];
        $suppressions = $wpdb->prefix . self::TABLES['notification_suppressions'];
        $runs = $wpdb->prefix . self::TABLES['import_runs'];
        $errors = $wpdb->prefix . self::TABLES['import_errors'];
        $payments = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $customers = $wpdb->prefix . 'safecontracts_customers';
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $collections = $wpdb->prefix . 'safecontracts_payment_collections';

        $mismatches = [
            'delivery_payment' => $this->count($wpdb, "SELECT COUNT(*) AS total FROM {$deliveries} d INNER JOIN {$payments} p ON p.id = d.payment_id WHERE d.payment_id IS NOT NULL AND d.tenant_id IS NOT NULL AND p.tenant_id IS NOT NULL AND d.tenant_id <> p.tenant_id"),
            'delivery_rule' => $this->count($wpdb, "SELECT COUNT(*) AS total FROM {$deliveries} d INNER JOIN {$rules} r ON r.id = d.rule_id WHERE d.rule_id IS NOT NULL AND d.tenant_id IS NOT NULL AND r.tenant_id IS NOT NULL AND d.tenant_id <> r.tenant_id"),
            'schedule_payment' => $this->count($wpdb, "SELECT COUNT(*) AS total FROM {$schedule} s INNER JOIN {$payments} p ON p.id = s.payment_id WHERE s.tenant_id IS NOT NULL AND p.tenant_id IS NOT NULL AND s.tenant_id <> p.tenant_id"),
            'schedule_rule' => $this->count($wpdb, "SELECT COUNT(*) AS total FROM {$schedule} s INNER JOIN {$rules} r ON r.id = s.rule_id WHERE s.tenant_id IS NOT NULL AND r.tenant_id IS NOT NULL AND s.tenant_id <> r.tenant_id"),
            'suppression_payment_scope' => $this->count($wpdb, "SELECT COUNT(*) AS total FROM {$suppressions} s INNER JOIN {$payments} p ON p.id = s.scope_id WHERE s.scope_type = 'payment' AND s.tenant_id IS NOT NULL AND p.tenant_id IS NOT NULL AND s.tenant_id <> p.tenant_id"),
            'suppression_contract_scope' => $this->count($wpdb, "SELECT COUNT(*) AS total FROM {$suppressions} s INNER JOIN {$contracts} c ON c.id = s.scope_id WHERE s.scope_type = 'contract' AND s.tenant_id IS NOT NULL AND c.tenant_id IS NOT NULL AND s.tenant_id <> c.tenant_id"),
            'import_error_run' => $this->count($wpdb, "SELECT COUNT(*) AS total FROM {$errors} e INNER JOIN {$runs} r ON r.id = e.import_run_id WHERE e.tenant_id IS NOT NULL AND r.tenant_id IS NOT NULL AND e.tenant_id <> r.tenant_id"),
            'audit_customer' => $this->auditMismatch($wpdb, $audit, $customers, 'customer'),
            'audit_contract' => $this->auditMismatch($wpdb, $audit, $contracts, 'contract'),
            'audit_payment' => $this->auditMismatch($wpdb, $audit, $payments, 'payment'),
            'audit_collection' => $this->auditMismatch($wpdb, $audit, $collections, 'collection'),
            'audit_import_run' => $this->auditMismatch($wpdb, $audit, $runs, 'import_run'),
            'audit_notification_schedule' => $this->auditMismatch($wpdb, $audit, $schedule, 'notification_schedule'),
        ];

        return [
            'unowned' => $unowned,
            'mismatches' => $mismatches,
            'platform_global' => $platformGlobal,
            'ready' => array_sum($unowned) === 0 && array_sum($mismatches) === 0,
        ];
    }

    /**
     * Derive child ownership only from already-owned authoritative parents.
     * No root notification configuration, device, import-run or unresolved direct delivery/audit row is guessed.
     *
     * @return array{unowned:array<string,int>,mismatches:array<string,int>,platform_global:array<string,int>,ready:bool}
     */
    public function deriveDeterministic(): array
    {
        global $wpdb;
        $this->assertWpdb($wpdb);

        $wpdb->query('START TRANSACTION');
        try {
            $this->derive($wpdb);
            $report = $this->report();
            if (array_sum($report['mismatches']) !== 0) {
                $wpdb->query('ROLLBACK');
                throw new RuntimeException('Non-core Enterprise tenant derivation found cross-tenant mismatches; no changes were committed.');
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

    /**
     * Assign only explicitly named legacy root groups to one reviewed tenant, then derive children.
     * Existing tenant ownership is never overwritten. Partial mappings may commit with ready=false.
     *
     * @param list<string> $rootGroups
     * @return array{unowned:array<string,int>,mismatches:array<string,int>,platform_global:array<string,int>,ready:bool}
     */
    public function assignRootsToTenant(int $tenantId, array $rootGroups): array
    {
        if ($tenantId <= 0) {
            throw new RuntimeException('A positive Enterprise tenant id is required for non-core root mapping.');
        }
        if ($rootGroups === []) {
            throw new RuntimeException('At least one reviewed non-core root group is required.');
        }

        $normalized = [];
        foreach ($rootGroups as $group) {
            $group = strtolower(trim($group));
            if (! isset(self::ROOT_GROUPS[$group])) {
                throw new RuntimeException('Unsupported non-core root group: ' . $group);
            }
            $normalized[$group] = true;
        }

        global $wpdb;
        $this->assertWpdb($wpdb);
        $this->assertActiveTenant($wpdb, $tenantId);

        $wpdb->query('START TRANSACTION');
        try {
            foreach (array_keys($normalized) as $group) {
                $name = self::ROOT_GROUPS[$group];
                $table = $wpdb->prefix . self::TABLES[$name];
                if ($group === 'audit') {
                    $wpdb->query($wpdb->prepare(
                        "UPDATE {$table} SET tenant_id = %d WHERE tenant_id IS NULL AND " . $this->tenantRequiredAuditPredicate(),
                        $tenantId
                    ));
                    continue;
                }
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$table} SET tenant_id = %d WHERE tenant_id IS NULL",
                    $tenantId
                ));
            }

            $this->derive($wpdb);
            $report = $this->report();
            if (array_sum($report['mismatches']) !== 0) {
                $wpdb->query('ROLLBACK');
                throw new RuntimeException('Reviewed non-core root mapping produced cross-tenant mismatches; no changes were committed.');
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
            throw new RuntimeException('Non-core Enterprise data is not ready for tenant enforcement.');
        }
    }

    private function derive(object $wpdb): void
    {
        $rules = $wpdb->prefix . self::TABLES['notification_rules'];
        $deliveries = $wpdb->prefix . self::TABLES['notification_deliveries'];
        $schedule = $wpdb->prefix . self::TABLES['notification_schedule'];
        $suppressions = $wpdb->prefix . self::TABLES['notification_suppressions'];
        $runs = $wpdb->prefix . self::TABLES['import_runs'];
        $errors = $wpdb->prefix . self::TABLES['import_errors'];
        $audit = $wpdb->prefix . self::TABLES['audit_log'];
        $payments = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $contracts = $wpdb->prefix . 'safecontracts_contracts';

        $wpdb->query("UPDATE {$schedule} s INNER JOIN {$payments} p ON p.id = s.payment_id SET s.tenant_id = p.tenant_id WHERE s.tenant_id IS NULL AND p.tenant_id IS NOT NULL");
        $wpdb->query("UPDATE {$deliveries} d INNER JOIN {$payments} p ON p.id = d.payment_id SET d.tenant_id = p.tenant_id WHERE d.tenant_id IS NULL AND d.payment_id IS NOT NULL AND p.tenant_id IS NOT NULL");
        $wpdb->query("UPDATE {$deliveries} d INNER JOIN {$rules} r ON r.id = d.rule_id SET d.tenant_id = r.tenant_id WHERE d.tenant_id IS NULL AND d.rule_id IS NOT NULL AND r.tenant_id IS NOT NULL");
        $wpdb->query("UPDATE {$errors} e INNER JOIN {$runs} r ON r.id = e.import_run_id SET e.tenant_id = r.tenant_id WHERE e.tenant_id IS NULL AND r.tenant_id IS NOT NULL");
        $wpdb->query("UPDATE {$suppressions} s INNER JOIN {$payments} p ON p.id = s.scope_id SET s.tenant_id = p.tenant_id WHERE s.tenant_id IS NULL AND s.scope_type = 'payment' AND p.tenant_id IS NOT NULL");
        $wpdb->query("UPDATE {$suppressions} s INNER JOIN {$contracts} c ON c.id = s.scope_id SET s.tenant_id = c.tenant_id WHERE s.tenant_id IS NULL AND s.scope_type = 'contract' AND c.tenant_id IS NOT NULL");

        foreach ($this->auditParentMap($wpdb) as $entityType => $parentTable) {
            $wpdb->query(
                "UPDATE {$audit} a INNER JOIN {$parentTable} p ON p.id = a.entity_id
                 SET a.tenant_id = p.tenant_id
                 WHERE a.tenant_id IS NULL AND a.entity_type = '" . addslashes($entityType) . "'
                   AND a.entity_id IS NOT NULL AND p.tenant_id IS NOT NULL"
            );
        }
    }

    /** @return array<string,string> */
    private function auditParentMap(object $wpdb): array
    {
        return [
            'customer' => $wpdb->prefix . 'safecontracts_customers',
            'contract' => $wpdb->prefix . 'safecontracts_contracts',
            'payment' => $wpdb->prefix . 'safecontracts_scheduled_payments',
            'collection' => $wpdb->prefix . 'safecontracts_payment_collections',
            'import_run' => $wpdb->prefix . self::TABLES['import_runs'],
            'notification_schedule' => $wpdb->prefix . self::TABLES['notification_schedule'],
        ];
    }

    private function auditMismatch(object $wpdb, string $audit, string $parentTable, string $entityType): int
    {
        return $this->count(
            $wpdb,
            "SELECT COUNT(*) AS total FROM {$audit} a INNER JOIN {$parentTable} p ON p.id = a.entity_id
             WHERE a.entity_type = '" . addslashes($entityType) . "'
               AND a.entity_id IS NOT NULL AND a.tenant_id IS NOT NULL AND p.tenant_id IS NOT NULL
               AND a.tenant_id <> p.tenant_id"
        );
    }

    private function tenantRequiredAuditPredicate(): string
    {
        return "NOT (entity_type IN ('payment_method','role','system') OR event_type = 'user_role_changed')";
    }

    private function assertActiveTenant(object $wpdb, int $tenantId): void
    {
        $tenants = $wpdb->prefix . 'safecontracts_tenants';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM {$tenants} WHERE id = %d AND status = 'active' LIMIT 1",
            $tenantId
        ), ARRAY_A);
        if (! is_array($rows) || $rows === []) {
            throw new RuntimeException('Non-core root mapping target must be an active Enterprise tenant.');
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

    private function assertWpdb(mixed $wpdb): void
    {
        if (! is_object($wpdb) || ! isset($wpdb->prefix) || ! method_exists($wpdb, 'prepare') || ! method_exists($wpdb, 'query') || ! method_exists($wpdb, 'get_results')) {
            throw new RuntimeException('Enterprise non-core tenant ownership requires WordPress $wpdb.');
        }
    }
}
