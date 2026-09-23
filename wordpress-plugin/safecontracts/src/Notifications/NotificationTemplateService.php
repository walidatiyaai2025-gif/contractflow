<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

use DomainException;
use InvalidArgumentException;
use SafeContracts\Roles\Capabilities;

final class NotificationTemplateService
{
    /** @var array<string,string> */
    private const COMPATIBLE_TEMPLATE_FALLBACKS = [
        'supplier_payment_due_soon' => 'payment_due_soon',
    ];

    public function __construct(private ?NotificationTemplateRepository $repository = null)
    {
        $this->repository ??= new NotificationTemplateRepository();
    }

    /** @return array<string,mixed> */
    public function save(array $input): array
    {
        $this->requireManage();
        $template = NotificationTemplate::normalizeInput($input);
        $actorId = get_current_user_id();
        $this->repository->save($template, $actorId);
        do_action('safecontracts_notification_template_saved', $template['code'], $actorId);
        return $template;
    }

    /** @return array{title:string,body:string,email_subject:string,email_body:string,icon_key:string} */
    public function render(string $code, array $context): array
    {
        $normalizedCode = NotificationRule::normalizeCode($code);
        $template = $this->repository->findActiveByCode($normalizedCode);

        if ($template === null) {
            // Respect an explicitly persisted inactive template. Compatibility
            // fallback is only for historical/owner rule aliases that never had
            // a dedicated template row, so they can still materialize schedules.
            $storedTemplate = $this->repository->findByCode($normalizedCode);
            if ($storedTemplate !== null) {
                throw new InvalidArgumentException('Notification template was not found or is inactive.');
            }

            $fallbackCode = self::COMPATIBLE_TEMPLATE_FALLBACKS[$normalizedCode] ?? null;
            if ($fallbackCode !== null) {
                $template = $this->repository->findActiveByCode($fallbackCode);
            }
        }

        if ($template === null) {
            throw new InvalidArgumentException('Notification template was not found or is inactive.');
        }
        return NotificationTemplate::render($template, $context);
    }

    private function requireManage(): void
    {
        if (! current_user_can(Capabilities::MANAGE_NOTIFICATIONS)) {
            throw new DomainException('You do not have permission to manage SafeContracts notification templates.');
        }
    }
}
