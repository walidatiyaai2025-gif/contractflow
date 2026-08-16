<?php

declare(strict_types=1);

namespace SafeContracts\Parties;

use DomainException;
use InvalidArgumentException;
use RuntimeException;
use SafeContracts\Roles\Capabilities;

final class PartyService
{
    /** @var list<string> */
    private const MUTATION_FIELDS = [
        'id',
        'party_code',
        'display_name',
        'legal_name',
        'party_kind',
        'country_code',
        'registration_number',
        'tax_number',
        'email',
        'phone',
        'status',
        'metadata',
    ];

    public function __construct(private ?PartyRepository $repository = null)
    {
        $this->repository ??= new PartyRepository();
    }

    public function find(int $partyId): ?array
    {
        $this->requireReadPermission();
        if ($partyId <= 0) {
            throw new InvalidArgumentException('Party ID must be positive.');
        }
        return $this->repository->find($partyId);
    }

    /** @return list<array<string,mixed>> */
    public function search(string $search = '', int $limit = 50, int $offset = 0): array
    {
        $this->requireReadPermission();
        $search = trim(strip_tags($search));
        if (strlen($search) > 191) {
            throw new InvalidArgumentException('Party search must not exceed 191 characters.');
        }
        return $this->repository->search($search, $limit, $offset);
    }

    public function save(array $input): int
    {
        $this->requireWritePermission();
        $this->rejectUnsupportedFields($input);

        $partyId = 0;
        if (array_key_exists('id', $input)) {
            $partyId = (int) $input['id'];
            if ($partyId <= 0) {
                throw new InvalidArgumentException('Party ID must be positive when supplied.');
            }
        }

        $data = $this->normalize($input);
        $actorId = get_current_user_id();

        if ($partyId > 0) {
            if ($this->repository->find($partyId) === null) {
                throw new InvalidArgumentException('Party was not found in the current tenant.');
            }
            $this->repository->update($partyId, $data, $actorId);
            do_action('safecontracts_enterprise_party_updated', $partyId, $actorId);
            return $partyId;
        }

        $partyId = $this->repository->create($data, $this->uuid(), $actorId);
        do_action('safecontracts_enterprise_party_created', $partyId, $actorId);
        return $partyId;
    }

    private function requireReadPermission(): void
    {
        if (! current_user_can(Capabilities::ACCESS)) {
            throw new DomainException('You do not have access to Enterprise parties.');
        }
    }

    private function requireWritePermission(): void
    {
        if (! current_user_can(Capabilities::MANAGE_REFERENCE_DATA)) {
            throw new DomainException('You do not have permission to manage Enterprise parties.');
        }
    }

    private function rejectUnsupportedFields(array $input): void
    {
        foreach (array_keys($input) as $field) {
            if (! is_string($field) || ! in_array($field, self::MUTATION_FIELDS, true)) {
                throw new InvalidArgumentException('Unsupported Party mutation field.');
            }
        }
    }

    /** @return array<string,string> */
    private function normalize(array $input): array
    {
        $displayName = $this->text($input['display_name'] ?? '', 191, true, 'Party display name');
        $legalName = $this->text($input['legal_name'] ?? '', 191, false, 'Party legal name');
        $partyCode = $this->text($input['party_code'] ?? '', 100, false, 'Party code');
        $kind = strtolower($this->text($input['party_kind'] ?? '', 32, true, 'Party kind'));
        if (! PartyPolicy::isKind($kind)) {
            throw new InvalidArgumentException('Party kind is not supported.');
        }

        $status = strtolower($this->text($input['status'] ?? PartyPolicy::STATUS_ACTIVE, 20, true, 'Party status'));
        if (! PartyPolicy::isStatus($status)) {
            throw new InvalidArgumentException('Party status is not supported.');
        }

        $countryCode = strtoupper($this->text($input['country_code'] ?? '', 2, false, 'Country code'));
        if ($countryCode !== '' && preg_match('/^[A-Z]{2}$/', $countryCode) !== 1) {
            throw new InvalidArgumentException('Country code must be a two-letter ISO-style code.');
        }

        $email = trim((string) ($input['email'] ?? ''));
        if ($email !== '' && (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 191)) {
            throw new InvalidArgumentException('Party email is invalid.');
        }

        $metadata = $input['metadata'] ?? [];
        if (! is_array($metadata)) {
            throw new InvalidArgumentException('Party metadata must be an object/array.');
        }
        $metadataJson = $metadata === [] ? '' : json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($metadataJson === false) {
            throw new InvalidArgumentException('Party metadata could not be encoded.');
        }
        if (strlen($metadataJson) > 20000) {
            throw new InvalidArgumentException('Party metadata must not exceed 20000 encoded bytes.');
        }

        return [
            'party_code' => $partyCode,
            'display_name' => $displayName,
            'legal_name' => $legalName,
            'party_kind' => $kind,
            'country_code' => $countryCode,
            'registration_number' => $this->text($input['registration_number'] ?? '', 100, false, 'Registration number'),
            'tax_number' => $this->text($input['tax_number'] ?? '', 100, false, 'Tax number'),
            'email' => $email,
            'phone' => $this->text($input['phone'] ?? '', 64, false, 'Party phone'),
            'status' => $status,
            'metadata_json' => $metadataJson,
        ];
    }

    private function text(mixed $value, int $max, bool $required, string $label): string
    {
        $value = trim(strip_tags((string) $value));
        if ($required && $value === '') {
            throw new InvalidArgumentException("{$label} is required.");
        }
        if (strlen($value) > $max) {
            throw new InvalidArgumentException("{$label} is too long.");
        }
        return $value;
    }

    private function uuid(): string
    {
        if (function_exists('wp_generate_uuid4')) {
            $uuid = (string) wp_generate_uuid4();
            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid) === 1) {
                return strtolower($uuid);
            }
        }

        try {
            $bytes = random_bytes(16);
        } catch (\Throwable $error) {
            throw new RuntimeException('Unable to generate Party UUID.', 0, $error);
        }
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }
}
