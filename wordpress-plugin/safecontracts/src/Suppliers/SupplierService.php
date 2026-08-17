<?php

declare(strict_types=1);

namespace SafeContracts\Suppliers;

use DomainException;
use InvalidArgumentException;
use SafeContracts\Roles\Capabilities;

final class SupplierService
{
    public function __construct(private ?SupplierRepository $repository = null)
    {
        $this->repository ??= new SupplierRepository();
    }

    /** @return array<string,mixed>|null */
    public function find(int $supplierId): ?array
    {
        $this->requireAny([Capabilities::VIEW_SUPPLIERS, Capabilities::MANAGE_SUPPLIERS], 'You do not have access to SafeContracts suppliers.');
        if ($supplierId <= 0) {
            throw new InvalidArgumentException('Supplier ID must be positive.');
        }
        return $this->repository->find($supplierId);
    }

    /** @return list<array<string,mixed>> */
    public function active(int $limit = 500): array
    {
        $this->requireAny([Capabilities::VIEW_SUPPLIERS, Capabilities::MANAGE_SUPPLIERS], 'You do not have access to SafeContracts suppliers.');
        return $this->repository->active($limit);
    }

    public function save(array $input): int
    {
        $supplierId = max(0, (int) ($input['id'] ?? 0));
        $required = $supplierId > 0 ? Capabilities::EDIT_SUPPLIERS : Capabilities::CREATE_SUPPLIERS;
        $this->requireAny([$required, Capabilities::MANAGE_SUPPLIERS], $supplierId > 0
            ? 'You do not have permission to edit SafeContracts suppliers.'
            : 'You do not have permission to create SafeContracts suppliers.');

        $data = $this->normalize($input);
        $actorId = get_current_user_id();
        if ($supplierId > 0) {
            if ($this->repository->find($supplierId) === null) {
                throw new InvalidArgumentException('Supplier was not found.');
            }
            $this->repository->update($supplierId, $data, $actorId);
            do_action('safecontracts_supplier_updated', $supplierId, $actorId);
            return $supplierId;
        }

        $supplierId = $this->repository->create($data, $actorId);
        do_action('safecontracts_supplier_created', $supplierId, $actorId);
        return $supplierId;
    }

    public function archive(int $supplierId): void
    {
        $this->requireAny([Capabilities::EDIT_SUPPLIERS, Capabilities::MANAGE_SUPPLIERS], 'You do not have permission to archive SafeContracts suppliers.');
        if ($supplierId <= 0 || $this->repository->find($supplierId) === null) {
            throw new InvalidArgumentException('Supplier was not found.');
        }
        $actorId = get_current_user_id();
        $this->repository->archive($supplierId, $actorId);
        do_action('safecontracts_supplier_archived', $supplierId, $actorId);
    }

    /** @return array{internal_code:string,name:string,contact_name:string,email:string,phone:string,notes:string,is_active:bool} */
    private function normalize(array $input): array
    {
        $name = $this->text($input['name'] ?? '', 191, true, 'Supplier name');
        $internalCode = $this->text($input['internal_code'] ?? '', 100, false, 'Internal code');
        $contactName = $this->text($input['contact_name'] ?? '', 191, false, 'Contact name');
        $email = trim((string) ($input['email'] ?? ''));
        if ($email !== '' && (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 191)) {
            throw new InvalidArgumentException('Supplier email is invalid.');
        }
        $phone = $this->text($input['phone'] ?? '', 64, false, 'Phone');
        $notes = trim(strip_tags((string) ($input['notes'] ?? '')));
        if (strlen($notes) > 5000) {
            throw new InvalidArgumentException('Supplier notes must not exceed 5000 characters.');
        }
        return [
            'internal_code' => $internalCode,
            'name' => $name,
            'contact_name' => $contactName,
            'email' => $email,
            'phone' => $phone,
            'notes' => $notes,
            'is_active' => ! array_key_exists('is_active', $input) || (bool) $input['is_active'],
        ];
    }

    private function text(mixed $value, int $max, bool $required, string $label): string
    {
        $value = trim(strip_tags((string) $value));
        if ($required && $value === '') {
            throw new InvalidArgumentException("{$label} is required.");
        }
        if (strlen($value) > $max || preg_match('/[\x00]/', $value)) {
            throw new InvalidArgumentException("{$label} is invalid.");
        }
        return $value;
    }

    /** @param list<string> $capabilities */
    private function requireAny(array $capabilities, string $message): void
    {
        foreach ($capabilities as $capability) {
            if (current_user_can($capability)) {
                return;
            }
        }
        throw new DomainException($message);
    }
}
