<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Roles\Capabilities;
use SafeContracts\Roles\RoleRegistrar;
use SafeContracts\Suppliers\SupplierService;
use Throwable;

/**
 * Human-readable select options for internal relationships.
 *
 * End users select business labels while integer IDs stay transport/storage
 * details. Server-side services remain authoritative for authorization and
 * referential validation.
 */
final class AdminLookupOptions
{
    /** @return list<array{ref:string,type:string,id:int,label:string}> */
    public static function counterparties(?AdminReadRepository $read = null): array
    {
        $read ??= new AdminReadRepository();
        $options = [];

        foreach ($read->customerOptions() as $customer) {
            $id = (int) ($customer['id'] ?? 0);
            $name = trim((string) ($customer['name'] ?? ''));
            if ($id > 0 && $name !== '') {
                $options[] = [
                    'ref' => 'customer:' . $id,
                    'type' => 'customer',
                    'id' => $id,
                    'label' => __('Customer', 'safecontracts') . ' — ' . $name,
                ];
            }
        }

        if (current_user_can(Capabilities::VIEW_SUPPLIERS)
            || current_user_can(Capabilities::VIEW_PAYABLES)
            || current_user_can(Capabilities::MANAGE_FINANCE)
            || current_user_can(Capabilities::MANAGE_SUPPLIERS)) {
            try {
                foreach ((new SupplierService())->search('', 500, false) as $supplier) {
                    $id = (int) ($supplier['id'] ?? 0);
                    $name = trim((string) ($supplier['legal_name'] ?? $supplier['trading_name'] ?? ''));
                    if ($id > 0 && $name !== '') {
                        $options[] = [
                            'ref' => 'supplier:' . $id,
                            'type' => 'supplier',
                            'id' => $id,
                            'label' => __('Supplier', 'safecontracts') . ' — ' . $name,
                        ];
                    }
                }
            } catch (Throwable) {
                // Other authorized lookups remain usable if supplier lookup is
                // temporarily unavailable. Saving still validates server-side.
            }
        }

        usort(
            $options,
            static fn (array $left, array $right): int => strcasecmp($left['label'], $right['label'])
        );
        return $options;
    }

    /** @return list<array{id:int,label:string}> */
    public static function accountants(): array
    {
        $users = get_users([
            'role' => RoleRegistrar::ACCOUNTANT,
            'orderby' => 'display_name',
            'order' => 'ASC',
        ]);
        if (! is_array($users)) {
            return [];
        }

        $options = [];
        foreach ($users as $user) {
            $id = (int) ($user->ID ?? 0);
            if ($id <= 0) {
                continue;
            }
            $name = trim((string) ($user->display_name ?? ''));
            $email = trim((string) ($user->user_email ?? ''));
            $label = $name !== '' ? $name : $email;
            if ($label === '') {
                $label = __('Unnamed WordPress user', 'safecontracts');
            } elseif ($name !== '' && $email !== '') {
                $label .= ' — ' . $email;
            }
            $options[] = ['id' => $id, 'label' => $label];
        }
        return $options;
    }

    /** @return list<string> */
    public static function currencies(?AdminReadRepository $read = null, string $selected = ''): array
    {
        $read ??= new AdminReadRepository();
        $currencies = [];
        foreach ($read->contracts() as $contract) {
            $currency = strtoupper(trim((string) ($contract['currency_code'] ?? '')));
            if (preg_match('/^[A-Z]{3}$/', $currency) === 1) {
                $currencies[$currency] = true;
            }
        }

        $selected = strtoupper(trim($selected));
        if (preg_match('/^[A-Z]{3}$/', $selected) === 1) {
            $currencies[$selected] = true;
        }

        $result = array_keys($currencies);
        sort($result, SORT_STRING);
        return array_values($result);
    }

    /** @return array{type:string,id:int}|null */
    public static function parseCounterpartyRef(mixed $reference): ?array
    {
        if (! is_scalar($reference)) {
            return null;
        }
        if (preg_match('/^(customer|supplier):([1-9][0-9]*)$/', trim((string) $reference), $matches) !== 1) {
            return null;
        }
        return ['type' => $matches[1], 'id' => (int) $matches[2]];
    }

    public static function counterpartyRef(string $type, int $id): string
    {
        if (! in_array($type, ['customer', 'supplier'], true) || $id <= 0) {
            return '';
        }
        return $type . ':' . $id;
    }
}
