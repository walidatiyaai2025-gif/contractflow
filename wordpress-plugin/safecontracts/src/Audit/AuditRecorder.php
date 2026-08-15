<?php

declare(strict_types=1);

namespace SafeContracts\Audit;

use Throwable;

final class AuditRecorder
{
    private static bool $registered = false;

    /** @var list<string> */
    private const EVENTS = [
        'safecontracts_contract_base_value_changed',
        'safecontracts_contract_financial_item_added',
        'safecontracts_contract_adjustment_added',
        'safecontracts_payment_settled',
        'safecontracts_contract_customer_assigned',
        'safecontracts_contract_accountant_assigned',
        'safecontracts_contract_status_changed',
        'safecontracts_contract_dates_changed',
        'safecontracts_payment_status_changed',
        'safecontracts_payment_dates_changed',
        'safecontracts_followup_recorded',
        'safecontracts_export_completed',
        'safecontracts_import_completed',
    ];

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;

        foreach (self::EVENTS as $hook) {
            add_action($hook, static function (mixed ...$args) use ($hook): void {
                self::record($hook, $args);
            });
        }
    }

    /** @param list<mixed> $args */
    private static function record(string $hook, array $args): void
    {
        [$entityType, $entityId, $eventType, $actorId, $before, $after, $context] = self::map($hook, $args);
        try {
            (new AuditRepository())->append(
                $entityType,
                $entityId,
                $eventType,
                $actorId > 0 ? $actorId : null,
                $before,
                $after,
                self::sanitize($context)
            );
        } catch (Throwable $error) {
            error_log('SafeContracts audit write failed for ' . $eventType . ': ' . $error->getMessage());
        }
    }

    /**
     * @param list<mixed> $args
     * @return array{0:string,1:?int,2:string,3:int,4:?array,5:?array,6:?array}
     */
    private static function map(string $hook, array $args): array
    {
        return match ($hook) {
            'safecontracts_contract_base_value_changed' => [
                'contract', (int) ($args[0] ?? 0), 'contract_base_value_changed', (int) ($args[2] ?? 0),
                ['base_value' => $args[3] ?? null], ['base_value' => $args[1] ?? null], null,
            ],
            'safecontracts_contract_financial_item_added' => [
                'contract', (int) ($args[0] ?? 0), 'contract_financial_item_added', (int) ($args[3] ?? 0),
                null, ['item_id' => (int) ($args[1] ?? 0), 'amount' => $args[2] ?? null], null,
            ],
            'safecontracts_contract_adjustment_added' => [
                'contract', (int) ($args[0] ?? 0), 'contract_adjustment_added', (int) ($args[4] ?? 0),
                null, ['adjustment_id' => (int) ($args[1] ?? 0), 'type' => $args[2] ?? null, 'amount' => $args[3] ?? null], null,
            ],
            'safecontracts_payment_settled' => [
                'payment', (int) ($args[0] ?? 0), 'payment_settled', (int) ($args[5] ?? 0),
                ['paid_amount' => $args[6] ?? null, 'remaining_amount' => $args[7] ?? null, 'status' => $args[8] ?? null],
                ['paid_amount' => $args[2] ?? null, 'remaining_amount' => $args[3] ?? null, 'status' => $args[4] ?? null],
                ['collection_amount' => $args[1] ?? null],
            ],
            'safecontracts_contract_customer_assigned' => [
                'contract', (int) ($args[0] ?? 0), 'contract_customer_assigned', (int) ($args[2] ?? 0),
                ['customer_id' => $args[3] ?? null], ['customer_id' => $args[1] ?? null], null,
            ],
            'safecontracts_contract_accountant_assigned' => [
                'contract', (int) ($args[0] ?? 0), 'contract_accountant_assigned', (int) ($args[2] ?? 0),
                ['accountant_user_id' => $args[3] ?? null], ['accountant_user_id' => $args[1] ?? null], null,
            ],
            'safecontracts_contract_status_changed' => [
                'contract', (int) ($args[0] ?? 0), 'contract_status_changed', (int) ($args[3] ?? 0),
                ['status' => $args[1] ?? null], ['status' => $args[2] ?? null], null,
            ],
            'safecontracts_contract_dates_changed' => [
                'contract', (int) ($args[0] ?? 0), 'contract_dates_changed', (int) ($args[3] ?? 0),
                ['start_date' => $args[4] ?? null, 'end_date' => $args[5] ?? null],
                ['start_date' => $args[1] ?? null, 'end_date' => $args[2] ?? null], null,
            ],
            'safecontracts_payment_status_changed' => [
                'payment', (int) ($args[0] ?? 0), 'payment_status_changed', (int) ($args[3] ?? 0),
                ['status' => $args[1] ?? null], ['status' => $args[2] ?? null], null,
            ],
            'safecontracts_payment_dates_changed' => [
                'payment', (int) ($args[0] ?? 0), 'payment_dates_changed', (int) ($args[5] ?? 0),
                ['due_date' => $args[1] ?? null, 'expected_payment_date' => $args[3] ?? null],
                ['due_date' => $args[2] ?? null, 'expected_payment_date' => $args[4] ?? null], null,
            ],
            'safecontracts_followup_recorded' => [
                'payment', (int) ($args[1] ?? 0), 'followup_recorded', (int) ($args[3] ?? 0), null,
                ['followup_id' => (int) ($args[0] ?? 0), 'state' => $args[2] ?? null],
                ['promised_date' => $args[4] ?? null, 'deferred_until' => $args[5] ?? null],
            ],
            'safecontracts_export_completed' => self::externalEvent('export', 'export_completed', $args),
            'safecontracts_import_completed' => self::externalEvent('import', 'import_completed', $args),
            default => ['system', null, $hook, get_current_user_id(), null, null, null],
        };
    }

    /** @param list<mixed> $args @return array{0:string,1:?int,2:string,3:int,4:?array,5:?array,6:?array} */
    private static function externalEvent(string $entityType, string $eventType, array $args): array
    {
        $context = is_array($args[0] ?? null) ? $args[0] : [];
        $entityId = isset($context['id']) && (int) $context['id'] > 0 ? (int) $context['id'] : null;
        $actorId = isset($args[1]) ? (int) $args[1] : get_current_user_id();
        return [$entityType, $entityId, $eventType, $actorId, null, null, $context];
    }

    private static function sanitize(?array $context): ?array
    {
        if ($context === null) {
            return null;
        }
        $clean = [];
        foreach ($context as $key => $value) {
            $name = strtolower((string) $key);
            if (preg_match('/token|secret|password|credential|authorization|private[_-]?key|service[_-]?account/', $name)) {
                continue;
            }
            $clean[$key] = is_array($value) ? self::sanitize($value) : $value;
        }
        return $clean;
    }
}
