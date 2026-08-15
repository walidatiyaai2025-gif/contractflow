<?php

declare(strict_types=1);

namespace SafeContracts\Customers;

use DomainException;
use InvalidArgumentException;
use SafeContracts\Roles\Capabilities;

final class CustomerService
{
    public function __construct(private ?CustomerRepository $repository = null)
    {
        $this->repository ??= new CustomerRepository();
    }

    public function find(int $customerId): ?array
    {
        if (! current_user_can(Capabilities::ACCESS)) {
            throw new DomainException('You do not have access to SafeContracts customers.');
        }
        if ($customerId <= 0) {
            throw new InvalidArgumentException('Customer ID must be positive.');
        }
        return $this->repository->find($customerId);
    }

    public function save(array $input): int
    {
        if (! current_user_can(Capabilities::MANAGE_REFERENCE_DATA)) {
            throw new DomainException('You do not have permission to manage SafeContracts customers.');
        }
        $customerId = max(0, (int) ($input['id'] ?? 0));
        $data = $this->normalize($input);
        if ($customerId > 0) {
            if ($this->repository->find($customerId) === null) {
                throw new InvalidArgumentException('Customer was not found.');
            }
            $this->repository->update($customerId, $data);
            do_action('safecontracts_customer_updated', $customerId, get_current_user_id());
            return $customerId;
        }
        $customerId = $this->repository->create($data, get_current_user_id());
        do_action('safecontracts_customer_created', $customerId, get_current_user_id());
        return $customerId;
    }

    private function normalize(array $input): array
    {
        $name = $this->text($input['name'] ?? '', 191, true, 'Customer name');
        $internalCode = $this->text($input['internal_code'] ?? '', 100, false, 'Internal code');
        $contactName = $this->text($input['contact_name'] ?? '', 191, false, 'Contact name');
        $email = trim((string) ($input['email'] ?? ''));
        if ($email !== '' && (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 191)) {
            throw new InvalidArgumentException('Customer email is invalid.');
        }
        $phone = $this->text($input['phone'] ?? '', 64, false, 'Phone');
        $notes = trim(strip_tags((string) ($input['notes'] ?? '')));
        if (strlen($notes) > 5000) {
            throw new InvalidArgumentException('Customer notes must not exceed 5000 characters.');
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
        if (strlen($value) > $max) {
            throw new InvalidArgumentException("{$label} is too long.");
        }
        return $value;
    }
}
