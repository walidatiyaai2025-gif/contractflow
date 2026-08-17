<?php

declare(strict_types=1);

namespace SafeContracts\Suppliers;

use DomainException;
use InvalidArgumentException;
use SafeContracts\Payments\CurrencyCode;
use SafeContracts\Roles\Capabilities;

final class SupplierService
{
    public function __construct(private ?SupplierRepository $repository = null)
    {
        $this->repository ??= new SupplierRepository();
    }

    /** @return array<string,mixed>|null */
    public function find(int $supplierId, bool $includeArchived = false): ?array
    {
        $this->requireView();
        if ($supplierId <= 0) {
            throw new InvalidArgumentException('Supplier ID must be positive.');
        }
        return $this->repository->find($supplierId, $includeArchived);
    }

    /** @return list<array<string,mixed>> */
    public function active(int $limit = 500): array
    {
        $this->requireView();
        return $this->repository->active($limit);
    }

    /** @return list<array<string,mixed>> */
    public function search(mixed $query = '', mixed $limit = 50, bool $includeArchived = false): array
    {
        $this->requireView();
        $query = trim(strip_tags((string) $query));
        if (strlen($query) > 191) {
            throw new InvalidArgumentException('Supplier search must not exceed 191 characters.');
        }
        return $this->repository->search($query, max(1, min(200, (int) $limit)), $includeArchived);
    }

    public function save(array $input): int
    {
        $supplierId = max(0, (int) ($input['id'] ?? 0));
        $required = $supplierId > 0 ? Capabilities::EDIT_SUPPLIERS : Capabilities::CREATE_SUPPLIERS;
        $this->requireAny([$required, Capabilities::MANAGE_SUPPLIERS, Capabilities::MANAGE_REFERENCE_DATA], $supplierId > 0
            ? 'You do not have permission to edit SafeContracts suppliers.'
            : 'You do not have permission to create SafeContracts suppliers.');

        $existing = null;
        if ($supplierId > 0) {
            $existing = $this->repository->find($supplierId, true);
            if ($existing === null) {
                throw new InvalidArgumentException('Supplier was not found.');
            }
            if ($existing['is_archived']) {
                throw new DomainException('Archived suppliers cannot be edited.');
            }
        }

        $data = $this->normalize($input);
        $duplicate = $this->repository->duplicateId($data, $supplierId > 0 ? $supplierId : null);
        if ($duplicate !== null) {
            throw new InvalidArgumentException('Supplier code, registration number or tax number already belongs to another supplier.');
        }

        $actorId = get_current_user_id();
        if ($supplierId > 0) {
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
        $this->requireAny(
            [Capabilities::EDIT_SUPPLIERS, Capabilities::MANAGE_SUPPLIERS, Capabilities::MANAGE_REFERENCE_DATA],
            'You do not have permission to archive SafeContracts suppliers.'
        );
        if ($supplierId <= 0) {
            throw new InvalidArgumentException('Supplier ID must be positive.');
        }
        $supplier = $this->repository->find($supplierId, true);
        if ($supplier === null) {
            throw new InvalidArgumentException('Supplier was not found.');
        }
        if ($supplier['is_archived']) {
            return;
        }
        // Archive is the only deletion path. Historical contracts and financial
        // obligations intentionally remain linked to this Supplier ID.
        $actorId = get_current_user_id();
        $this->repository->archive($supplierId, $actorId);
        do_action('safecontracts_supplier_archived', $supplierId, $actorId);
    }

    /** @return array<string,mixed> */
    private function normalize(array $input): array
    {
        // `name` remains an accepted legacy alias; all new writes persist both
        // name and legal_name through the repository.
        $legalNameInput = array_key_exists('legal_name', $input) ? $input['legal_name'] : ($input['name'] ?? '');
        $legalName = $this->text($legalNameInput, 191, true, 'Supplier legal name');
        $email = trim((string) ($input['email'] ?? ''));
        if ($email !== '' && (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 191)) {
            throw new InvalidArgumentException('Supplier email is invalid.');
        }

        $country = strtoupper($this->text($input['country_code'] ?? '', 2, false, 'Country code'));
        if ($country !== '' && ! preg_match('/^[A-Z]{2}$/', $country)) {
            throw new InvalidArgumentException('Supplier country code must use two letters.');
        }
        $currency = strtoupper(trim((string) ($input['default_currency'] ?? '')));
        if ($currency !== '') {
            $currency = CurrencyCode::normalize($currency);
        }

        $statusInput = $input['status'] ?? null;
        if ($statusInput === null || trim((string) $statusInput) === '') {
            $statusInput = array_key_exists('is_active', $input) && ! (bool) $input['is_active']
                ? SupplierStatus::INACTIVE
                : SupplierStatus::ACTIVE;
        }
        $status = SupplierStatus::normalize($statusInput);

        $notes = trim(strip_tags((string) ($input['notes'] ?? '')));
        if (strlen($notes) > 10000) {
            throw new InvalidArgumentException('Supplier notes must not exceed 10000 characters.');
        }
        $address = trim(strip_tags((string) ($input['address'] ?? '')));
        if (strlen($address) > 2000) {
            throw new InvalidArgumentException('Supplier address must not exceed 2000 characters.');
        }

        return [
            'internal_code' => $this->text($input['internal_code'] ?? '', 100, false, 'Supplier code'),
            'name' => $legalName,
            'legal_name' => $legalName,
            'trading_name' => $this->text($input['trading_name'] ?? '', 191, false, 'Trading name'),
            'contact_name' => $this->text($input['contact_name'] ?? '', 191, false, 'Contact name'),
            'email' => $email,
            'phone' => $this->text($input['phone'] ?? '', 64, false, 'Phone'),
            'address' => $address,
            'country_code' => $country,
            'registration_number' => $this->text($input['registration_number'] ?? '', 100, false, 'Registration number'),
            'tax_number' => $this->text($input['tax_number'] ?? '', 100, false, 'Tax number'),
            'default_currency' => $currency,
            'payment_terms' => $this->text($input['payment_terms'] ?? '', 191, false, 'Payment terms'),
            'status' => $status,
            'notes' => $notes,
            'is_active' => SupplierStatus::isActive($status),
        ];
    }

    private function requireView(): void
    {
        $this->requireAny(
            [Capabilities::VIEW_SUPPLIERS, Capabilities::MANAGE_SUPPLIERS, Capabilities::MANAGE_REFERENCE_DATA],
            'You do not have access to SafeContracts suppliers.'
        );
    }

    private function text(mixed $value, int $max, bool $required, string $label): string
    {
        $value = trim(strip_tags((string) $value));
        if ($required && $value === '') {
            throw new InvalidArgumentException("{$label} is required.");
        }
        if (strlen($value) > $max || preg_match('/[\r\n\x00]/', $value)) {
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
