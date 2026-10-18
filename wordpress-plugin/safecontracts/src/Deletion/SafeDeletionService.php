<?php

declare(strict_types=1);

namespace SafeContracts\Deletion;

use DomainException;
use InvalidArgumentException;
use RuntimeException;
use SafeContracts\Contracts\ContractMoney;
use SafeContracts\Diagnostics\RuntimeInspector;
use SafeContracts\Payments\PaymentStatus;
use SafeContracts\Roles\Capabilities;
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
        $row = $this->firstRow($wpdb->get_results($wpdb->prepare(
            "SELECT id, is_active FROM {$table} WHERE id = %d LIMIT 1",
            $customerId
        ), ARRAY_A));
        if ($row === null) {
            throw new InvalidArgumentException('Customer was not found.');
        }
        if (empty($row['is_active'])) {
            return;
        }

        $sql = $wpdb->prepare(
            "UPDATE {$table} SET is_active = 0, updated_at = UTC_TIMESTAMP() WHERE id = %d",
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
        RuntimeInspector::begin('payment.archive', ['payment_id' => $paymentId]);
        try {
            $this->archivePaymentTraced($paymentId);
        } catch (Throwable $error) {
            RuntimeInspector::capture($error);
            throw $error;
        } finally {
            RuntimeInspector::finish();
        }
    }

    private function archivePaymentTraced(int $paymentId): void
    {
        RuntimeInspector::stage('payment.archive.authorization', ['payment_id' => $paymentId]);
        $this->requireCapability(Capabilities::MANAGE_PAYMENTS, 'You do not have permission to delete payments.');

        RuntimeInspector::stage('payment.archive.id', ['payment_id' => $paymentId]);
        $this->requirePositiveId($paymentId, 'Payment');

        global $wpdb;
        RuntimeInspector::stage('payment.archive.database.ready', ['payment_id' => $paymentId]);
        $this->assertWpdb($wpdb);
        $payments = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $collections = $wpdb->prefix . 'safecontracts_payment_collections';
        $contracts = $wpdb->prefix . 'safecontracts_contracts';

        RuntimeInspector::stage('payment.archive.load', ['payment_id' => $paymentId]);
        $row = $this->firstRow($wpdb->get_results($wpdb->prepare(
            "SELECT p.id, p.paid_amount, p.status, p.is_archived, c.accountant_user_id
             FROM {$payments} p
             INNER JOIN {$contracts} c ON c.id = p.contract_id
             WHERE p.id = %d LIMIT 1",
            $paymentId
        ), ARRAY_A));
        if ($row === null) {
            throw new InvalidArgumentException('Payment was not found.');
        }

        RuntimeInspector::stage('payment.archive.scope', [
            'payment_id' => $paymentId,
            'accountant_user_id' => $row['accountant_user_id'] ?? null,
        ]);
        $this->assertScope($row['accountant_user_id'] ?? null);

        RuntimeInspector::stage('payment.archive.state', [
            'payment_id' => $paymentId,
            'status' => (string) ($row['status'] ?? ''),
            'paid_amount' => (string) ($row['paid_amount'] ?? '0.0000'),
            'is_archived' => ! empty($row['is_archived']),
        ]);
        if (! empty($row['is_archived'])) {
            return;
        }
        if (ContractMoney::compare((string) ($row['paid_amount'] ?? '0.0000'), '0.0000') !== 0) {
            throw new DomainException('Payments with collected amounts cannot be deleted. Reverse their collections first.');
        }

        RuntimeInspector::stage('payment.archive.collection_history', ['payment_id' => $paymentId]);
        $collection = $this->firstRow($wpdb->get_results($wpdb->prepare(
            "SELECT id FROM {$collections} WHERE payment_id = %d AND is_archived = 0 LIMIT 1",
            $paymentId
        ), ARRAY_A));
        if ($collection !== null) {
            RuntimeInspector::stage('payment.archive.collection_history.blocked', [
                'payment_id' => $paymentId,
                'collection_id' => (int) ($collection['id'] ?? 0),
            ]);
            throw new DomainException('Payments with collection history cannot be deleted. Reverse their collections first.');
        }

        $actorId = get_current_user_id();
        RuntimeInspector::stage('payment.archive.database.update', [
            'payment_id' => $paymentId,
            'actor_user_id' => $actorId,
        ]);
        $sql = $wpdb->prepare(
            "UPDATE {$payments}
             SET is_archived = 1, archived_by = %d, archived_at = UTC_TIMESTAMP(), updated_by = %d, updated_at = UTC_TIMESTAMP()
             WHERE id = %d",
            $actorId,
            $actorId,
            $paymentId
        );
        $this->execute($wpdb, $sql, 'Unable to archive payment.');

        RuntimeInspector::stage('payment.archive.events', ['payment_id' => $paymentId]);
        do_action('safecontracts_payment_archived', $paymentId, (string) ($row['status'] ?? ''), $actorId);
    }

    public function archiveCollection(int $collectionId): void
    {
        RuntimeInspector::begin('collection.archive', ['collection_id' => $collectionId]);
        try {
            $this->archiveCollectionTraced($collectionId);
        } catch (Throwable $error) {
            RuntimeInspector::capture($error);
            throw $error;
        } finally {
            RuntimeInspector::finish();
        }
    }

    private function archiveCollectionTraced(int $collectionId): void
    {
        RuntimeInspector::stage('collection.archive.authorization', ['collection_id' => $collectionId]);
        $this->requireCapability(Capabilities::MANAGE_COLLECTIONS, 'You do not have permission to delete collections.');

        RuntimeInspector::stage('collection.archive.id', ['collection_id' => $collectionId]);
        $this->requirePositiveId($collectionId, 'Collection');

        global $wpdb;
        RuntimeInspector::stage('collection.archive.database.ready', ['collection_id' => $collectionId]);
        $this->assertWpdb($wpdb);
        $collections = $wpdb->prefix . 'safecontracts_payment_collections';
        $payments = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $contracts = $wpdb->prefix . 'safecontracts_contracts';
        $actorId = get_current_user_id();

        RuntimeInspector::stage('collection.archive.transaction.begin', ['collection_id' => $collectionId]);
        $this->execute($wpdb, 'START TRANSACTION', 'Unable to start collection reversal.');
        try {
            RuntimeInspector::stage('collection.archive.load', ['collection_id' => $collectionId]);
            $row = $this->firstRow($wpdb->get_results($wpdb->prepare(
                "SELECT cl.id, cl.payment_id, cl.amount, cl.is_archived,
                        p.original_amount, p.paid_amount, p.remaining_amount, p.status, p.due_date, p.is_archived AS payment_is_archived,
                        c.accountant_user_id
                 FROM {$collections} cl
                 INNER JOIN {$payments} p ON p.id = cl.payment_id
                 INNER JOIN {$contracts} c ON c.id = p.contract_id
                 WHERE cl.id = %d LIMIT 1 FOR UPDATE",
                $collectionId
            ), ARRAY_A));
            if ($row === null) {
                throw new InvalidArgumentException('Collection was not found.');
            }

            RuntimeInspector::stage('collection.archive.scope', [
                'collection_id' => $collectionId,
                'payment_id' => (int) ($row['payment_id'] ?? 0),
                'accountant_user_id' => $row['accountant_user_id'] ?? null,
            ]);
            $this->assertScope($row['accountant_user_id'] ?? null);

            RuntimeInspector::stage('collection.archive.state', [
                'collection_id' => $collectionId,
                'payment_id' => (int) ($row['payment_id'] ?? 0),
                'is_archived' => ! empty($row['is_archived']),
                'payment_is_archived' => ! empty($row['payment_is_archived']),
                'amount' => (string) ($row['amount'] ?? '0.0000'),
            ]);
            if (! empty($row['is_archived'])) {
                RuntimeInspector::stage('collection.archive.transaction.commit_already_archived', ['collection_id' => $collectionId]);
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

            RuntimeInspector::stage('collection.archive.database.archive', [
                'collection_id' => $collectionId,
                'payment_id' => (int) ($row['payment_id'] ?? 0),
            ]);
            $archiveSql = $wpdb->prepare(
                "UPDATE {$collections}
                 SET is_archived = 1, archived_by = %d, archived_at = UTC_TIMESTAMP(), updated_by = %d, updated_at = UTC_TIMESTAMP()
                 WHERE id = %d",
                $actorId,
                $actorId,
                $collectionId
            );
            $this->execute($wpdb, $archiveSql, 'Unable to archive collection.');

            RuntimeInspector::stage('collection.archive.ledger.recalculate', [
                'collection_id' => $collectionId,
                'payment_id' => (int) $row['payment_id'],
            ]);
            $ledger = $this->firstRow($wpdb->get_results($wpdb->prepare(
                "SELECT COALESCE(SUM(amount), 0.0000) AS total
                 FROM {$collections}
                 WHERE payment_id = %d AND is_archived = 0",
                (int) $row['payment_id']
            ), ARRAY_A));
            $newPaid = ContractMoney::normalizeNonNegative((string) ($ledger['total'] ?? '0.0000'));
            $original = ContractMoney::normalizeNonNegative((string) $row['original_amount']);
            RuntimeInspector::stage('collection.archive.ledger.integrity', [
                'collection_id' => $collectionId,
                'payment_id' => (int) $row['payment_id'],
                'original_amount' => $original,
                'new_paid_amount' => $newPaid,
            ]);
            if (ContractMoney::compare($newPaid, $original) > 0) {
                throw new DomainException('Collection reversal produced an invalid over-collected balance.');
            }
            $newRemaining = ContractMoney::subtract($original, $newPaid);
            if ($newPaid === '0.0000') {
                $newStatus = PaymentStatus::temporalForDueDate((string) $row['due_date']);
            } else {
                $newStatus = $newRemaining === '0.0000' ? PaymentStatus::PAID : PaymentStatus::PARTIALLY_PAID;
            }

            RuntimeInspector::stage('collection.archive.payment.reconcile', [
                'collection_id' => $collectionId,
                'payment_id' => (int) $row['payment_id'],
                'new_paid_amount' => $newPaid,
                'new_remaining_amount' => $newRemaining,
                'new_status' => $newStatus,
            ]);
            $settlementSql = $wpdb->prepare(
                "UPDATE {$payments}
                 SET paid_amount = %s, remaining_amount = %s, status = %s, updated_by = %d, updated_at = UTC_TIMESTAMP()
                 WHERE id = %d",
                $newPaid,
                $newRemaining,
                $newStatus,
                $actorId,
                (int) $row['payment_id']
            );
            $this->execute($wpdb, $settlementSql, 'Unable to reconcile payment after collection deletion.');

            RuntimeInspector::stage('collection.archive.transaction.commit', [
                'collection_id' => $collectionId,
                'payment_id' => (int) $row['payment_id'],
            ]);
            $this->execute($wpdb, 'COMMIT', 'Unable to finalize collection reversal.');
        } catch (Throwable $error) {
            RuntimeInspector::stage('collection.archive.transaction.rollback', ['collection_id' => $collectionId]);
            $wpdb->query('ROLLBACK');
            throw $error;
        }

        RuntimeInspector::stage('collection.archive.events', [
            'collection_id' => $collectionId,
            'payment_id' => (int) $row['payment_id'],
        ]);
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
