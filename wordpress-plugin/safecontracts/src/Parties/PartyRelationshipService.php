<?php

declare(strict_types=1);

namespace SafeContracts\Parties;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use SafeContracts\Roles\Capabilities;

final class PartyRelationshipService
{
    public function __construct(
        private ?PartyRepository $parties = null,
        private ?PartyRelationshipRepository $relationships = null
    ) {
        $this->parties ??= new PartyRepository();
        $this->relationships ??= new PartyRelationshipRepository();
    }

    /**
     * @return array{outgoing:list<array<string,mixed>>,incoming:list<array<string,mixed>>}
     */
    public function relationshipsForParty(int $partyId): array
    {
        $this->requireReadPermission();
        $this->requireParty($partyId);

        return [
            'outgoing' => $this->relationships->outgoing($partyId),
            'incoming' => $this->relationships->incoming($partyId),
        ];
    }

    public function assign(
        int $sourcePartyId,
        int $targetPartyId,
        string $relationshipCode,
        array $options = []
    ): void {
        $this->requireWritePermission();
        $relationshipCode = $this->relationshipCode($relationshipCode);
        [$sourcePartyId, $targetPartyId] = $this->endpoints(
            $sourcePartyId,
            $targetPartyId,
            $relationshipCode
        );
        $data = $this->normalizeOptions($options);
        $this->requireParty($sourcePartyId);
        $this->requireParty($targetPartyId);

        $actorId = get_current_user_id();
        $this->relationships->assign(
            $sourcePartyId,
            $targetPartyId,
            $relationshipCode,
            $data['valid_from'],
            $data['valid_to'],
            $data['metadata_json'],
            $actorId
        );
        do_action(
            'safecontracts_enterprise_party_relationship_assigned',
            $sourcePartyId,
            $targetPartyId,
            $relationshipCode,
            $actorId
        );
    }

    public function revoke(int $sourcePartyId, int $targetPartyId, string $relationshipCode): void
    {
        $this->requireWritePermission();
        $relationshipCode = $this->relationshipCode($relationshipCode);
        [$sourcePartyId, $targetPartyId] = $this->endpoints(
            $sourcePartyId,
            $targetPartyId,
            $relationshipCode
        );
        $this->requireParty($sourcePartyId);
        $this->requireParty($targetPartyId);

        $actorId = get_current_user_id();
        $this->relationships->revoke(
            $sourcePartyId,
            $targetPartyId,
            $relationshipCode,
            $actorId
        );
        do_action(
            'safecontracts_enterprise_party_relationship_revoked',
            $sourcePartyId,
            $targetPartyId,
            $relationshipCode,
            $actorId
        );
    }

    private function requireParty(int $partyId): void
    {
        if ($partyId <= 0) {
            throw new InvalidArgumentException('Party ID must be positive.');
        }
        if ($this->parties->find($partyId) === null) {
            throw new InvalidArgumentException('Relationship Party was not found in the current tenant.');
        }
    }

    private function relationshipCode(string $relationshipCode): string
    {
        $relationshipCode = PartyRelationshipPolicy::normalize($relationshipCode);
        if (! PartyRelationshipPolicy::isSupported($relationshipCode)) {
            throw new InvalidArgumentException('Party relationship type is not supported.');
        }
        return $relationshipCode;
    }

    /** @return array{0:int,1:int} */
    private function endpoints(int $sourcePartyId, int $targetPartyId, string $relationshipCode): array
    {
        if ($sourcePartyId <= 0 || $targetPartyId <= 0) {
            throw new InvalidArgumentException('Relationship Party IDs must be positive.');
        }
        if ($sourcePartyId === $targetPartyId) {
            throw new InvalidArgumentException('A Party cannot relate to itself.');
        }

        if (PartyRelationshipPolicy::isSymmetric($relationshipCode) && $sourcePartyId > $targetPartyId) {
            return [$targetPartyId, $sourcePartyId];
        }
        return [$sourcePartyId, $targetPartyId];
    }

    /** @return array{valid_from:string,valid_to:string,metadata_json:string} */
    private function normalizeOptions(array $options): array
    {
        foreach (array_keys($options) as $field) {
            if (! is_string($field) || ! in_array($field, ['valid_from', 'valid_to', 'metadata'], true)) {
                throw new InvalidArgumentException('Unsupported Party relationship option.');
            }
        }

        $validFrom = $this->date($options['valid_from'] ?? '', 'Relationship valid-from date');
        $validTo = $this->date($options['valid_to'] ?? '', 'Relationship valid-to date');
        if ($validFrom !== '' && $validTo !== '' && $validTo < $validFrom) {
            throw new InvalidArgumentException('Relationship valid-to date must not precede valid-from date.');
        }

        $metadata = $options['metadata'] ?? [];
        if (! is_array($metadata)) {
            throw new InvalidArgumentException('Relationship metadata must be an object/array.');
        }
        $metadataJson = $metadata === []
            ? ''
            : json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($metadataJson === false) {
            throw new InvalidArgumentException('Relationship metadata could not be encoded.');
        }
        if (strlen($metadataJson) > 20000) {
            throw new InvalidArgumentException('Relationship metadata must not exceed 20000 encoded bytes.');
        }

        return [
            'valid_from' => $validFrom,
            'valid_to' => $validTo,
            'metadata_json' => $metadataJson,
        ];
    }

    private function date(mixed $value, string $label): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            throw new InvalidArgumentException("{$label} must use YYYY-MM-DD.");
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (! $date instanceof DateTimeImmutable || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException("{$label} is invalid.");
        }
        return $value;
    }

    private function requireReadPermission(): void
    {
        if (! current_user_can(Capabilities::ACCESS)) {
            throw new DomainException('You do not have access to Enterprise Party relationships.');
        }
    }

    private function requireWritePermission(): void
    {
        if (! current_user_can(Capabilities::MANAGE_REFERENCE_DATA)) {
            throw new DomainException('You do not have permission to manage Enterprise Party relationships.');
        }
    }
}
