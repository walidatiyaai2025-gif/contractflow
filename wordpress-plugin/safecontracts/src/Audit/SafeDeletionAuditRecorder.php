<?php

declare(strict_types=1);

namespace SafeContracts\Audit;

use Throwable;

final class SafeDeletionAuditRecorder
{
    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;

        add_action('safecontracts_customer_archived', [self::class, 'customer'], 10, 2);
        add_action('safecontracts_payment_archived', [self::class, 'payment'], 10, 3);
        add_action('safecontracts_collection_archived', [self::class, 'collection'], 10, 6);
        add_action('safecontracts_payment_method_archived', [self::class, 'paymentMethod'], 10, 3);
    }

    public static function customer(int $customerId, int $actorId): void
    {
        self::append('customer', $customerId, 'customer_archived', $actorId, ['is_active' => true], ['is_active' => false]);
    }

    public static function payment(int $paymentId, string $status, int $actorId): void
    {
        self::append('payment', $paymentId, 'payment_archived', $actorId, ['is_archived' => false, 'status' => $status], ['is_archived' => true, 'status' => $status]);
    }

    /** @param array<string,mixed> $before @param array<string,mixed> $after */
    public static function collection(int $collectionId, int $paymentId, string $amount, int $actorId, array $before, array $after): void
    {
        self::append(
            'collection',
            $collectionId,
            'collection_archived',
            $actorId,
            ['is_archived' => false, 'payment_id' => $paymentId, 'amount' => $amount, 'payment' => $before],
            ['is_archived' => true, 'payment_id' => $paymentId, 'amount' => $amount, 'payment' => $after]
        );
    }

    public static function paymentMethod(int $paymentMethodId, string $code, int $actorId): void
    {
        self::append('payment_method', $paymentMethodId, 'payment_method_archived', $actorId, ['is_active' => true, 'code' => $code], ['is_active' => false, 'code' => $code]);
    }

    /** @param array<string,mixed>|null $before @param array<string,mixed>|null $after */
    private static function append(string $entityType, int $entityId, string $eventType, int $actorId, ?array $before, ?array $after): void
    {
        try {
            (new AuditRepository())->append($entityType, $entityId, $eventType, $actorId > 0 ? $actorId : null, $before, $after, null);
        } catch (Throwable $error) {
            error_log('SafeContracts safe-deletion audit write failed for ' . $eventType . ': ' . $error->getMessage());
        }
    }
}
