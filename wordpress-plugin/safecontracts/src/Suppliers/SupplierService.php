<?php

declare(strict_types=1);

namespace SafeContracts\Suppliers;

use DomainException;
use InvalidArgumentException;
use SafeContracts\Finance\CurrencyCode;
use SafeContracts\Roles\Capabilities;

final class SupplierService
{
    public function __construct(private ?SupplierRepository $repository = null)
    {
        $this->repository ??= new SupplierRepository();
    }

    public function find(int $supplierId): ?array
    {
        $this->requireView();
        if ($supplierId <= 0) {
            throw new InvalidArgumentException('Supplier ID must be positive.');
        }
        return $this->repository->find($supplierId);
    }

    /** @return list<array<string,mixed>> */
    public function search(mixed $query = '', mixed $limit = 50, bool $includeArchived = false): array
    {
        $this->requireView();
        $query = trim(strip_tags((string) $query));
        if (strlen($query) > 191) {
            throw new InvalidArgumentException('Supplier search is too long.');
        }
        if ($includeArchived && ! current_user_can(Capabilities::ARCHIVE_SUPPLIERS) && ! current_user_can(Capabilities::VIEW_ALL)) {
            throw new DomainException('You do not have permission to view archived suppliers.');
        }
        return $this->repository->search($query, max(1, min(200, (int) $limit)), $includeArchived);
    }

    public function save(array $input): int
    {
        $supplierId = max(0, (int) ($input['id'] ?? 0));
        $this->requireCapability(
            $supplierId > 0 ? Capabilities::EDIT_SUPPLIERS : Capabilities::CREATE_SUPPLIERS,
            $supplierId > 0
                ? 'You do not have permission to edit SafeContracts suppliers.'
                : 'You do not have permission to create SafeContracts suppliers.'
        );

        if ($supplierId > 0) {
            $existing = $this->repository->find($supplierId);
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
        $this->requireCapability(Capabilities::ARCHIVE_SUPPLIERS, 'You do not have permission to archive suppliers.');
        if ($supplierId <= 0) {
            throw new InvalidArgumentException('Supplier ID must be positive.');
        }
        $supplier = $this->repository->find($supplierId);
        if ($supplier === null) {
            throw new InvalidArgumentException('Supplier was not found.');
        }
        if ($supplier['is_archived']) {
            return;
        }

        // Financial/contract history is intentionally preserved. Archive is the
        // supported lifecycle operation regardless of whether history exists.
        $hasHistory = $this->repository->hasContractHistory($supplierId);
        $actorId = get_current_user_id();
        $this->repository->archive($supplierId, $actorId);
        do_action('safecontracts_supplier_archived', $supplierId, $actorId, $hasHistory);
    }

    /** @return array<string,mixed> */
    private function normalize(array $input): array
    {
        $legalName = $this->text($input['legal_name'] ?? '', 191, true, 'Supplier legal name');
        $email = trim((string) ($input['email'] ?? ''));
        if ($email !== '' && (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 191)) {
            throw new InvalidArgumentException('Supplier email is invalid.');
        }

        $country = strtoupper($this->text($input['country_code'] ?? '', 2, false, 'Country code'));
        if ($country !== '' && ! preg_match('/^[A-Z]{2}$/', $country)) {
            throw new InvalidArgumentException('Supplier country code must use two letters.');
        }

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
            'legal_name' => $legalName,
            'trading_name' => $this->text($input['trading_name'] ?? '', 191, false, 'Trading name'),
            'contact_name' => $this->text($input['contact_name'] ?? '', 191, false, 'Contact name'),
            'phone' => $this->text($input['phone'] ?? '', 64, false, 'Phone'),
            'email' => $email,
            'address' => $address,
            'country_code' => $country,
            'registration_number' => $this->text($input['registration_number'] ?? '', 100, false, 'Registration number'),
            'tax_number' => $this->text($input['tax_number'] ?? '', 100, false, 'Tax number'),
            'default_currency' => CurrencyCode::normalize($input['default_currency'] ?? '', true),
            'payment_terms' => $this->text($input['payment_terms'] ?? '', 191, false, 'Payment terms'),
            'status' => SupplierStatus::normalize($input['status'] ?? SupplierStatus::ACTIVE),
            'notes' => $notes,
        ];
    }

    private function requireView(): void
    {
        if (! current_user_can(Capabilities::VIEW_SUPPLIERS)
            && ! current_user_can(Capabilities::VIEW_ALL)
            && ! current_user_can(Capabilities::MANAGE_REFERENCE_DATA)) {
            throw new DomainException('You do not have permission to view SafeContracts suppliers.');
        }
    }

    private function requireCapability(string $capability, string $message): void
    {
        if (! current_user_can($capability) && ! current_user_can(Capabilities::MANAGE_REFERENCE_DATA)) {
            throw new DomainException($message);
        }
    }

    private function text(mixed $value, int $max, bool $required, string $label): string
    {
        $text = trim(strip_tags((string) $value));
        if ($required && $text === '') {
            throw new InvalidArgumentException("{$label} is required.");
        }
        if (strlen($text) > $max || preg_match('/[\r\n\x00]/', $text)) {
            throw new InvalidArgumentException("{$label} is invalid.");
        }
        return $text;
    }
}
