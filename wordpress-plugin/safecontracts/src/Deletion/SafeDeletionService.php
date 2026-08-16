<?php

declare(strict_types=1);

namespace SafeContracts\Deletion;

use DomainException;
use InvalidArgumentException;
use RuntimeException;
use SafeContracts\Contracts\ContractMoney;
use SafeContracts\Payments\PaymentStatus;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Tenancy\CoreTenantScope;
use Throwable;

final class SafeDeletionService
{
    public function archiveCustomer(int $customerId): void
    {
        $this->requireCapability(Capabilities::MANAGE_REFERENCE_DATA, 'You do not have permission to delete customers.');
        $this->requirePositiveId($customerId, 'Customer');

        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_customers';
        $tenant = $this->tenantCondition();
        $row = $this->firstRow($wpdb->get_results($wpdb->prepare(
            "SELECT id, is_active FROM {$table} WHERE id = %d{$tenant} LIMIT 1",
            $customerId
        ), ARRAY_A));
        if ($row === null) {
            throw new InvalidArgumentException('Customer was not found.');
        }
        if (empty($row['is_active'])) {
            return;
        }

        $sql = $wpdb->prepare(
            "UPDATE {$table} SET is_active = 0, updated_at = UTC_TIMESTAMP() WHERE id = %d{$tenant}",
            $customerId
        );
        $this->execute($wpdb, $sql, 'Unable to archive customer.');
        do_action('safecontracts_customer_archived', $customerId, get_current_user_id());
    }

    public function archivePaymentMethod(int $paymentMethodId): void
    {
        $this->requireCapability(Capabilities::MANAGE_REFERENCE_DATA, 'You do not have permission to delete payment methods.');
        $this->requirePositiveId($paymentMethodId, 'Payment method');

        global $wpdb;
        $this->assertWpdb($wpdb);
        $table = $wpdb->prefix . 'safecontracts_payment_methods';
        $row = $this->firstRow($wpdb->get_results($wpdb->prepare(
            "SELECT id, code, is_active FROM {$table} WHERE id = %d LIMIT 1",
            $paymentMethodId
        ), ARRAY_A));
        if ($row === null) {
            throw new InvalidArgumentException('Payment method was not found.');
        }
        if (empty($row['is_active'])) {
            return;
        }

        $sql = $wpdb->prepare(
            "UPDATE {$table} SET is_active = 0, updated_at = UTC_TIMESTAMP() WHERE id = %d",
            $paymentMethodId
        );
        $this->execute($wpdb, $sql, 'Unable to archive payment method.');
        do_action('safecontracts_payment_method_archived', $paymentMethodId, (string) ($row['code'] ?? ''), get_current_user_id());
    }

    public function archivePayment(int $paymentId): void
    {
        $this->requireCapability(Capabilities::MANAGE_PAYMENTS, 'You do not have permission to delete payments.');
        $this->requirePositiveId($paymentId, 'Payment');

        global $wpdb;
        $this->assertWpdb($wpdb);
        $payments = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $collections = $wpdb->prefix . 'safecontracts_payment_collections';
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $tenantId = CoreTenantScope::tenantId();
        $joinTenant = $tenantId === null
            ? ''
            : ' AND p.tenant_id = ' . $tenantId . ' AND c.tenant_id = ' . $tenantId;
        $collectionTenant = $tenantId === null ? '' : ' AND tenant_id = ' . $tenantId;
        $row = $this->firstRow($wpdb->get_results($wpdb->prepare(
            "SELECT p.id, p.paid_amount, p.status, p.is_archived, c.accountant_user_id
             FROM {$payments} p
             INNER JOIN {$contracts} c ON c.id = p.contract_id
             WHERE p.id = %d{$joinTenant} LIMIT 1",
            $paymentId
        ), ARRAY_A));
        if ($row === null) {
            throw new InvalidArgumentException('Payment was not found.');
        }
        $this->assertScope($row['accountant_user_id'] ?? null);
        if (! empty($row['is_archived'])) {
            return;
        }
        if (ContractMoney::compare((string) ($row['paid_amount'] ?? '0.0000'), '0.0000') !== 0) {
            throw new DomainException('Payments with collected amounts cannot be deleted. Reverse their collections first.');
        }
        $collection = $this->firstRow($wpdb->get_results($wpdb->prepare(
            "SELECT id FROM {$collections} WHERE payment_id = %d AND is_archived = 0{$collectionTenant} LIMIT 1",
            $paymentId
        ), ARRAY_A));
        if ($collection !== null) {
            throw new DomainException('Payments with collection history cannot be deleted. Reverse their collections first.');
        }

        $actorId = get_current_user_id();
        $paymentTenant = $tenantId === null ? '' : ' AND tenant_id = ' . $tenantId;
        $sql = $wpdb->prepare(
            "UPDATE {$payments}
             SET is_archived = 1, archived_by = %d, archived_at = UTC_TIMESTAMP(), updated_by = %d, updated_at = UTC_TIMESTAMP()
             WHERE id = %d{$paymentTenant}",
            $actorId,
            $actorId,
            $paymentId
        );
        $this->execute($wpdb, $sql, 'Unable to archive payment.');
        do_action('safecontracts_payment_archived', $paymentId, (string) ($row['status'] ?? ''), $actorId);
    }

    public function archiveCollection(int $collectionId): void
    {
        $this->requireCapability(Capabilities::MANAGE_COLLECTIONS, 'You do not have permission to delete collections.');
        $this->requirePositiveId($collectionId, 'Collection');

        global $wpdb;
        $this->assertWpdb($wpdb);
        $collections = $wpdb->prefix . 'safecontracts_payment_collections';
        $payments = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $actorId = get_current_user_id();
        $tenantId = CoreTenantScope::tenantId();
        $joinTenant = $tenantId === null
            ? ''
            : ' AND cl.tenant_id = ' . $tenantId . ' AND p.tenant_id = ' . $tenantId . ' AND c.tenant_id = ' . $tenantId;
        $collectionTenant = $tenantId === null ? '' : ' AND tenant_id = ' . $tenantId;
        $paymentTenant = $tenantId === null ? '' : ' AND tenant_id = ' . $tenantId;

        $this->execute($wpdb, 'START TRANSACTION', 'Unable to start collection reversal.');
        try {
            $row = $this->firstRow($wpdb->get_results($wpdb->prepare(
                "SELECT cl.id, cl.payment_id, cl.amount, cl.is_archived,
                        p.original_amount, p.paid_amount, p.remaining_amount, p.status, p.due_date, p.is_archived AS payment_is_archived,
                        c.accountant_user_id
                 FROM {$collections} cl
                 INNER JOIN {$payments} p ON p.id = cl.payment_id
                 INNER JOIN {$contracts} c ON c.id = p.contract_id
                 WHERE cl.id = %d{$joinTenant} LIMIT 1 FOR UPDATE",
                $collectionId
            ), ARRAY_A));
            if ($row === null) {
                throw new InvalidArgumentException('Collection was not found.');
            }
            $this->assertScope($row['accountant_user_id'] ?? null);
            if (! empty($row['is_archived'])) {
                $this->execute($wpdb, 'COMMIT', 'Unable to finalize collection reversal.');
                return;
            }
            if (! empty($row['payment_is_archived'])) {
                throw new DomainException('Collections on archived payments cannot be changed.');
            }

            $before = [
                'paid_amount' => (string) ($row['paid_amount'] ?? '0.0000'),
                'remaining_amount' => (string) ($row['remaining_amount'] ?? '0.0000'),
                'status' => (string) ($row['status'] ?? PaymentStatus::UPCOMING),
            ];

            $archiveSql = $wpdb->prepare(
                "UPDATE {$collections}
                 SET is_archived = 1, archived_by = %d, archived_at = UTC_TIMESTAMP(), updated_by = %d, updated_at = UTC_TIMESTAMP()
                 WHERE id = %d{$collectionTenant}",
                $actorId,
                $actorId,
                $collectionId
            );
            $this->execute($wpdb, $archiveSql, 'Unable to archive collection.');

            $ledger = $this->firstRow($wpdb->get_results($wpdb->prepare(
                "SELECT COALESCE(SUM(amount), 0.0000) AS total
                 FROM {$collections}
                 WHERE payment_id = %d AND is_archived = 0{$collectionTenant}",
                (int) $row['payment_id']
            ), ARRAY_A));
            $newPaid = ContractMoney::normalizeNonNegative((string) ($ledger['total'] ?? '0.0000'));
            $original = ContractMoney::normalizeNonNegative((string) $row['original_amount']);
            if (ContractMoney::compare($newPaid, $original) > 0) {
                throw new DomainException('Collection reversal produced an invalid over-collected balance.');
            }
            $newRemaining = ContractMoney::subtract($original, $newPaid);
            if ($newPaid === '0.0000') {
                $newStatus = PaymentStatus::temporalForDueDate((string) $row['due_date']);
            } else {
                $newStatus = $newRemaining === '0.0000' ? PaymentStatus::PAID : PaymentStatus::PARTIALLY_PAID;
            }

            $settlementSql = $wpdb->prepare(
                "UPDATE {$payments}
                 SET paid_amount = %s, remaining_amount = %s, status = %s, updated_by = %d, updated_at = UTC_TIMESTAMP()
                 WHERE id = %d{$paymentTenant}",
                $newPaid,
                $newRemaining,
                $newStatus,
                $actorId,
                (int) $row['payment_id']
            );
            $this->execute($wpdb, $settlementSql, 'Unable to reconcile payment after collection deletion.');
            $this->execute($wpdb, 'COMMIT', 'Unable to finalize collection reversal.');
        } catch (Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }

        do_action(
            'safecontracts_collection_archived',
            $collectionId,
            (int) $row['payment_id'],
            (string) $row['amount'],
            $actorId,
            $before,
            ['paid_amount' => $newPaid, 'remaining_amount' => $newRemaining, 'status' => $newStatus]
        );
    }

    private function tenantCondition(string $column = 'tenant_id'): string
    {
        $tenantId = CoreTenantScope::tenantId();
        return $tenantId === null ? '' : ' AND ' . $column . ' = ' . $tenantId;
    }

    private function assertScope(mixed $accountantUserId): void
    {
        if (current_user_can(Capabilities::VIEW_ALL)) {
            return;
        }
        if (
            current_user_can(Capabilities::VIEW_ASSIGNED)
            && $accountantUserId !== null
            && (int) $accountantUserId === get_current_user_id()
        ) {
            return;
        }
        throw new DomainException('Record is outside the current user data scope.');
    }

    private function requireCapability(string $capability, string $message): void
    {
        if (! current_user_can($capability)) {
            throw new DomainException($message);
        }
    }

    private function requirePositiveId(int $id, string $label): void
    {
        if ($id <= 0) {
            throw new InvalidArgumentException("{$label} ID must be positive.");
        }
    }

    private function assertWpdb(mixed $wpdb): void
    {
        if (! is_object($wpdb) || ! method_exists($wpdb, 'prepare') || ! method_exists($wpdb, 'query') || ! method_exists($wpdb, 'get_results')) {
            throw new RuntimeException('SafeContracts safe deletion requires WordPress $wpdb.');
        }
    }

    /** @return array<string,mixed>|null */
    private function firstRow(mixed $rows): ?array
    {
        return is_array($rows) && isset($rows[0]) && is_array($rows[0]) ? $rows[0] : null;
    }

    private function execute(object $wpdb, string $sql, string $message): void
    {
        if ($wpdb->query($sql) === false) {
            throw new RuntimeException($message);
        }
    }
}
