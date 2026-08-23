<?php

declare(strict_types=1);

namespace SafeContracts\Attachments;

use DomainException;
use InvalidArgumentException;
use SafeContracts\Roles\Capabilities;

final class EntityAttachmentService
{
    public const CONTRACT = 'contract';
    public const PAYMENT = 'payment';
    public const COLLECTION = 'collection';

    public function __construct(private ?EntityAttachmentRepository $repository = null)
    {
        $this->repository ??= new EntityAttachmentRepository();
    }

    /** @return list<string> */
    public static function allowedTypes(): array
    {
        return [self::CONTRACT, self::PAYMENT, self::COLLECTION];
    }

    public function assertCanManage(string $entityType, int $entityId): void
    {
        $entityType = $this->normalizeType($entityType);
        $this->assertWritable($entityType, $entityId);
    }

    /**
     * @param list<int> $mediaIds
     * @return list<array{id:int,entity_type:string,entity_id:int,media_id:int,label:string,display_order:int,created_by:?int,created_at:string}>
     */
    public function attachMany(string $entityType, int $entityId, array $mediaIds): array
    {
        $entityType = $this->normalizeType($entityType);
        $this->assertWritable($entityType, $entityId);
        $actorId = get_current_user_id();
        $position = count($this->repository->allFor($entityType, $entityId));
        $seen = [];

        foreach ($mediaIds as $mediaId) {
            $mediaId = (int) $mediaId;
            if ($mediaId <= 0 || isset($seen[$mediaId])) {
                continue;
            }
            $this->assertMedia($mediaId);
            $seen[$mediaId] = true;
            $label = $this->attachmentLabel($mediaId);
            $this->repository->attach($entityType, $entityId, $mediaId, $label, $position++, $actorId);
            if ($entityType === self::CONTRACT) {
                $this->repository->syncLegacyContractAttachment($entityId, $mediaId, $label, $actorId);
            }
            do_action('safecontracts_entity_attachment_added', $entityType, $entityId, $mediaId, $actorId);
        }

        $attachments = $this->repository->allFor($entityType, $entityId);
        if ($entityType === self::COLLECTION) {
            $first = $attachments[0]['media_id'] ?? null;
            $this->repository->setLegacyCollectionProof($entityId, is_int($first) && $first > 0 ? $first : null);
        }
        return $attachments;
    }

    /** @return list<array{id:int,entity_type:string,entity_id:int,media_id:int,label:string,display_order:int,created_by:?int,created_at:string}> */
    public function all(string $entityType, int $entityId): array
    {
        $entityType = $this->normalizeType($entityType);
        $this->assertReadable($entityType, $entityId);
        return $this->repository->allFor($entityType, $entityId);
    }

    public function detach(string $entityType, int $entityId, int $mediaId): void
    {
        $entityType = $this->normalizeType($entityType);
        $this->assertWritable($entityType, $entityId);
        if ($mediaId <= 0) {
            throw new InvalidArgumentException('Attachment media ID must be positive.');
        }
        $this->repository->detach($entityType, $entityId, $mediaId);
        if ($entityType === self::CONTRACT) {
            $this->repository->detachLegacyContractAttachment($entityId, $mediaId);
        } elseif ($entityType === self::COLLECTION) {
            $remaining = $this->repository->allFor($entityType, $entityId);
            $first = $remaining[0]['media_id'] ?? null;
            $this->repository->setLegacyCollectionProof($entityId, is_int($first) && $first > 0 ? $first : null);
        }
        do_action('safecontracts_entity_attachment_removed', $entityType, $entityId, $mediaId, get_current_user_id());
    }

    private function assertReadable(string $entityType, int $entityId): void
    {
        if (! current_user_can(Capabilities::ACCESS)) {
            throw new DomainException('You do not have access to SafeContracts attachments.');
        }
        $this->assertScope($entityType, $entityId, false);
    }

    private function assertWritable(string $entityType, int $entityId): void
    {
        $allowed = match ($entityType) {
            self::CONTRACT => current_user_can(Capabilities::EDIT_CONTRACTS),
            self::PAYMENT => current_user_can(Capabilities::MANAGE_PAYMENTS),
            self::COLLECTION => current_user_can(Capabilities::MANAGE_COLLECTIONS) || current_user_can(Capabilities::MANAGE_FINANCE),
            default => false,
        };
        if (! $allowed) {
            throw new DomainException('You do not have permission to manage attachments for this record.');
        }
        $this->assertScope($entityType, $entityId, true);
    }

    private function assertScope(string $entityType, int $entityId, bool $writable): void
    {
        if ($entityId <= 0) {
            throw new InvalidArgumentException('Attachment entity ID must be positive.');
        }
        $context = $this->repository->entityContext($entityType, $entityId);
        if ($context === null) {
            throw new InvalidArgumentException('Attachment target record was not found.');
        }
        if ($writable && (! empty($context['entity_is_archived']) || ! empty($context['parent_is_archived']))) {
            throw new DomainException('Archived records cannot receive or remove attachments.');
        }
        if (current_user_can(Capabilities::VIEW_ALL)) {
            return;
        }
        $accountantId = $context['accountant_user_id'];
        if (current_user_can(Capabilities::VIEW_ASSIGNED) && $accountantId !== null && $accountantId === get_current_user_id()) {
            return;
        }
        throw new DomainException('Attachment target is outside the current user data scope.');
    }

    private function assertMedia(int $mediaId): void
    {
        if (! function_exists('get_post_type') || get_post_type($mediaId) !== 'attachment') {
            throw new InvalidArgumentException('SafeContracts attachments must reference WordPress Media attachments.');
        }
    }

    private function attachmentLabel(int $mediaId): string
    {
        $title = function_exists('get_the_title') ? trim((string) get_the_title($mediaId)) : '';
        if ($title === '') {
            $title = 'Attachment #' . $mediaId;
        }
        return substr($title, 0, 191);
    }

    private function normalizeType(string $entityType): string
    {
        $entityType = strtolower(trim($entityType));
        if (! in_array($entityType, self::allowedTypes(), true)) {
            throw new InvalidArgumentException('Unsupported SafeContracts attachment entity type.');
        }
        return $entityType;
    }
}
