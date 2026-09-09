<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

use SafeContracts\Contracts\ContractRepository;
use SafeContracts\Payments\PaymentRepository;
use Throwable;

/**
 * Sends immediate operational notifications to the accountant currently
 * responsible for a contract whenever that contract or one of its financial
 * obligations changes.
 *
 * The dispatcher subscribes to domain events instead of being called from
 * admin/REST controllers, so the same notification contract applies to every
 * mutation path (WordPress admin, mobile API and future integrations).
 */
final class ContractActivityNotificationDispatcher
{
    /** @var array<string, array{ar:string,en:string}> */
    private const CONTRACT_EVENTS = [
        'safecontracts_contract_created' => ['ar' => 'تم إنشاء العقد', 'en' => 'Contract created'],
        'safecontracts_contract_edited' => ['ar' => 'تم تعديل بيانات العقد', 'en' => 'Contract details updated'],
        'safecontracts_contract_dates_changed' => ['ar' => 'تم تعديل تواريخ العقد', 'en' => 'Contract dates updated'],
        'safecontracts_contract_base_value_changed' => ['ar' => 'تم تعديل قيمة العقد', 'en' => 'Contract value updated'],
        'safecontracts_contract_currency_changed' => ['ar' => 'تم تعديل عملة العقد', 'en' => 'Contract currency updated'],
        'safecontracts_contract_financial_item_added' => ['ar' => 'تمت إضافة بند مالي للعقد', 'en' => 'Contract financial item added'],
        'safecontracts_contract_adjustment_added' => ['ar' => 'تمت إضافة تسوية على العقد', 'en' => 'Contract adjustment added'],
        'safecontracts_contract_attachment_added' => ['ar' => 'تمت إضافة مرفق للعقد', 'en' => 'Contract attachment added'],
        'safecontracts_contract_attachment_removed' => ['ar' => 'تم حذف مرفق من العقد', 'en' => 'Contract attachment removed'],
        'safecontracts_contract_customer_assigned' => ['ar' => 'تم تغيير عميل العقد', 'en' => 'Contract customer changed'],
        'safecontracts_contract_counterparty_assigned' => ['ar' => 'تم تغيير طرف العقد', 'en' => 'Contract counterparty changed'],
        'safecontracts_contract_accountant_assigned' => ['ar' => 'تم تحديث مسؤول العقد', 'en' => 'Contract responsibility updated'],
        'safecontracts_contract_status_changed' => ['ar' => 'تم تغيير حالة العقد', 'en' => 'Contract status changed'],
        'safecontracts_contract_archived' => ['ar' => 'تمت أرشفة العقد', 'en' => 'Contract archived'],
    ];

    /** @var array<string, array{ar:string,en:string}> */
    private const PAYMENT_EVENTS = [
        'safecontracts_payment_created' => ['ar' => 'تمت إضافة دفعة للعقد', 'en' => 'Payment added to contract'],
        'safecontracts_payment_details_changed' => ['ar' => 'تم تعديل بيانات دفعة', 'en' => 'Payment details updated'],
        'safecontracts_payment_dates_changed' => ['ar' => 'تم تعديل تاريخ دفعة', 'en' => 'Payment dates updated'],
        'safecontracts_payment_status_changed' => ['ar' => 'تم تغيير حالة دفعة', 'en' => 'Payment status changed'],
        'safecontracts_payment_archived' => ['ar' => 'تمت أرشفة دفعة', 'en' => 'Payment archived'],
    ];

    private static ?self $instance = null;

    /** @var array<int, true> */
    private array $paymentDetailsHandled = [];

    public function __construct(
        private ?ContractRepository $contracts = null,
        private ?PaymentRepository $payments = null,
        private ?DirectNotificationService $notifications = null
    ) {
        $this->contracts ??= new ContractRepository();
        $this->payments ??= new PaymentRepository();
        $this->notifications ??= new DirectNotificationService();
    }

    public static function register(): void
    {
        $dispatcher = self::$instance ??= new self();

        foreach (self::CONTRACT_EVENTS as $hook => $label) {
            add_action($hook, static function (mixed ...$args) use ($dispatcher, $hook, $label): void {
                $dispatcher->handleContractEvent($hook, $label, $args);
            }, 20, 12);
        }

        foreach (self::PAYMENT_EVENTS as $hook => $label) {
            add_action($hook, static function (mixed ...$args) use ($dispatcher, $hook, $label): void {
                $dispatcher->handlePaymentEvent($hook, $label, $args);
            }, 20, 12);
        }

        // Settlement/collection emits several compatibility hooks for one
        // business mutation. Listen only to the canonical directional event to
        // avoid duplicate notifications.
        add_action('safecontracts_financial_settlement_recorded', static function (mixed ...$args) use ($dispatcher): void {
            $dispatcher->handlePaymentAtIndex(
                'financial_settlement_recorded',
                ['ar' => 'تم تسجيل سداد أو تحصيل على دفعة', 'en' => 'Payment settlement recorded'],
                $args,
                1
            );
        }, 20, 12);

        add_action('safecontracts_collection_archived', static function (mixed ...$args) use ($dispatcher): void {
            $dispatcher->handlePaymentAtIndex(
                'collection_archived',
                ['ar' => 'تم إلغاء سداد أو تحصيل من دفعة', 'en' => 'Payment settlement reversed'],
                $args,
                1
            );
        }, 20, 12);

        add_action('safecontracts_followup_recorded', static function (mixed ...$args) use ($dispatcher): void {
            $dispatcher->handlePaymentAtIndex(
                'followup_recorded',
                ['ar' => 'تم تسجيل متابعة على دفعة', 'en' => 'Payment follow-up recorded'],
                $args,
                1,
                'followup'
            );
        }, 20, 12);

        add_action('safecontracts_entity_attachment_added', static function (mixed ...$args) use ($dispatcher): void {
            $dispatcher->handleEntityAttachment(
                'attachment_added',
                ['ar' => 'تمت إضافة مرفق', 'en' => 'Attachment added'],
                $args
            );
        }, 20, 12);
        add_action('safecontracts_entity_attachment_removed', static function (mixed ...$args) use ($dispatcher): void {
            $dispatcher->handleEntityAttachment(
                'attachment_removed',
                ['ar' => 'تم حذف مرفق', 'en' => 'Attachment removed'],
                $args
            );
        }, 20, 12);
    }

    /** @param array{ar:string,en:string} $label @param list<mixed> $args */
    private function handleContractEvent(string $hook, array $label, array $args): void
    {
        $contractId = (int) ($args[0] ?? 0);
        if ($contractId <= 0) {
            return;
        }

        // Contract creation also emits the base-value event with an old value
        // of zero. The creation notification already represents that action.
        if ($hook === 'safecontracts_contract_base_value_changed') {
            $oldValue = isset($args[3]) ? (string) $args[3] : '';
            if ($oldValue === '0.0000') {
                return;
            }
        }

        $this->notifyContract($contractId, $this->eventCode($hook), $label, 'contract', $contractId, 0);
    }

    /** @param array{ar:string,en:string} $label @param list<mixed> $args */
    private function handlePaymentEvent(string $hook, array $label, array $args): void
    {
        $paymentId = (int) ($args[0] ?? 0);
        if ($paymentId <= 0) {
            return;
        }

        // Archived payments are intentionally excluded by PaymentRepository::find().
        // Resolve their parent directly and deep-link to the still-available
        // contract instead of dropping the archive activity notification.
        if ($hook === 'safecontracts_payment_archived') {
            $contractId = $this->contractIdForAnyPayment($paymentId);
            if ($contractId > 0) {
                $this->notifyContract($contractId, $this->eventCode($hook), $label, 'contract', $contractId, $paymentId);
            }
            return;
        }

        // updateEditable() deliberately emits both details_changed and
        // dates_changed for integration compatibility. Send only one user
        // notification for that single save action.
        if ($hook === 'safecontracts_payment_details_changed') {
            $this->paymentDetailsHandled[$paymentId] = true;
        } elseif ($hook === 'safecontracts_payment_dates_changed' && isset($this->paymentDetailsHandled[$paymentId])) {
            return;
        }

        $this->notifyPayment($paymentId, $this->eventCode($hook), $label, 'payment');
    }

    /** @param array{ar:string,en:string} $label @param list<mixed> $args */
    private function handlePaymentAtIndex(
        string $eventCode,
        array $label,
        array $args,
        int $paymentIndex,
        string $resourceType = 'payment'
    ): void {
        $paymentId = (int) ($args[$paymentIndex] ?? 0);
        if ($paymentId <= 0) {
            return;
        }
        $this->notifyPayment($paymentId, $eventCode, $label, $resourceType);
    }

    /** @param array{ar:string,en:string} $label @param list<mixed> $args */
    private function handleEntityAttachment(string $eventCode, array $label, array $args): void
    {
        $entityType = strtolower(trim((string) ($args[0] ?? '')));
        $entityId = (int) ($args[1] ?? 0);
        if ($entityId <= 0) {
            return;
        }

        if ($entityType === 'contract') {
            $this->notifyContract($entityId, 'contract_' . $eventCode, $label, 'contract', $entityId, 0);
            return;
        }
        if ($entityType === 'payment') {
            $this->notifyPayment($entityId, 'payment_' . $eventCode, $label, 'payment');
            return;
        }
        if ($entityType === 'collection') {
            $paymentId = $this->paymentIdForCollection($entityId);
            if ($paymentId > 0) {
                $this->notifyPayment($paymentId, 'collection_' . $eventCode, $label, 'payment');
            }
        }
    }

    /** @param array{ar:string,en:string} $label */
    private function notifyPayment(int $paymentId, string $eventCode, array $label, string $resourceType): void
    {
        try {
            $payment = $this->payments->find($paymentId);
            if ($payment === null) {
                do_action('safecontracts_contract_activity_notification_suppressed', $eventCode, 0, $paymentId, 'payment_unavailable');
                return;
            }
            $contractId = (int) ($payment['contract_id'] ?? 0);
            if ($contractId <= 0) {
                return;
            }
            $this->notifyContract($contractId, $eventCode, $label, $resourceType, $paymentId, $paymentId);
        } catch (Throwable $error) {
            $this->recordFailure($eventCode, 0, $paymentId, $error);
        }
    }

    /** @param array{ar:string,en:string} $label */
    private function notifyContract(
        int $contractId,
        string $eventCode,
        array $label,
        string $resourceType,
        int $resourceId,
        int $paymentId
    ): void {
        try {
            $contract = $this->contracts->find($contractId);
            if ($contract === null) {
                do_action('safecontracts_contract_activity_notification_suppressed', $eventCode, $contractId, $paymentId, 'contract_unavailable');
                return;
            }

            $accountantUserId = (int) ($contract['accountant_user_id'] ?? 0);
            if ($accountantUserId <= 0) {
                do_action('safecontracts_contract_activity_notification_suppressed', $eventCode, $contractId, $paymentId, 'no_responsible_accountant');
                return;
            }

            $contractNumber = trim((string) ($contract['contract_number'] ?? ''));
            $isArabic = function_exists('get_locale') && str_starts_with(strtolower((string) get_locale()), 'ar');
            $title = $isArabic ? 'تحديث على العقد' : 'Contract activity';
            $eventLabel = $isArabic ? $label['ar'] : $label['en'];
            $body = $contractNumber !== ''
                ? ($isArabic ? "{$eventLabel} — العقد {$contractNumber}" : "{$eventLabel} — {$contractNumber}")
                : $eventLabel;

            $result = $this->notifications->send(
                $accountantUserId,
                $title,
                $body,
                true,
                false,
                $resourceType === 'payment' || $resourceType === 'followup' ? 'payment' : 'safe_contracts',
                [
                    'event_code' => $eventCode,
                    'template_code' => $eventCode,
                    'contract_id' => $contractId,
                    'payment_id' => $paymentId,
                    'resource_type' => $resourceType,
                    'resource_id' => $resourceId,
                ]
            );

            do_action(
                'safecontracts_contract_activity_notification_dispatched',
                $eventCode,
                $contractId,
                $paymentId,
                $accountantUserId,
                $result
            );
        } catch (Throwable $error) {
            $this->recordFailure($eventCode, $contractId, $paymentId, $error);
        }
    }

    private function contractIdForAnyPayment(int $paymentId): int
    {
        global $wpdb;
        if (! is_object($wpdb) || ! method_exists($wpdb, 'get_var') || ! method_exists($wpdb, 'prepare')) {
            return 0;
        }
        $table = $wpdb->prefix . 'safecontracts_scheduled_payments';
        $contractId = $wpdb->get_var($wpdb->prepare(
            "SELECT contract_id FROM {$table} WHERE id = %d LIMIT 1",
            $paymentId
        ));
        return max(0, (int) $contractId);
    }

    private function paymentIdForCollection(int $collectionId): int
    {
        global $wpdb;
        if (! is_object($wpdb) || ! method_exists($wpdb, 'get_var') || ! method_exists($wpdb, 'prepare')) {
            return 0;
        }
        $table = $wpdb->prefix . 'safecontracts_payment_collections';
        $paymentId = $wpdb->get_var($wpdb->prepare(
            "SELECT payment_id FROM {$table} WHERE id = %d LIMIT 1",
            $collectionId
        ));
        return max(0, (int) $paymentId);
    }

    private function recordFailure(string $eventCode, int $contractId, int $paymentId, Throwable $error): void
    {
        do_action(
            'safecontracts_contract_activity_notification_failed',
            $eventCode,
            $contractId,
            $paymentId,
            sanitize_key($error->getMessage())
        );
        error_log('SafeContracts activity notification failed: ' . $error->getMessage());
    }

    private function eventCode(string $hook): string
    {
        return str_starts_with($hook, 'safecontracts_') ? substr($hook, strlen('safecontracts_')) : $hook;
    }
}
