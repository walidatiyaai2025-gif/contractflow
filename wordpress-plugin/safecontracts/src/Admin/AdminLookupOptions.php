<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Roles\Capabilities;
use SafeContracts\Roles\RoleRegistrar;
use SafeContracts\Suppliers\SupplierService;
use Throwable;

/**
 * Human-readable select options for internal relationships and controlled
 * business codes.
 *
 * End users select business labels while integer IDs stay transport/storage
 * details. Server-side services remain authoritative for authorization and
 * referential validation.
 */
final class AdminLookupOptions
{
    /**
     * Product-supported currency choices. This is intentionally a controlled
     * business list rather than a claim to reproduce the full ISO 4217 table.
     * Existing valid contract currencies are merged in so upgrades never hide
     * production data. Integrations may extend the list through the filter.
     *
     * @var list<string>
     */
    private const BUSINESS_CURRENCIES = [
        'AED', 'AUD', 'AZN', 'BHD', 'BRL', 'CAD', 'CHF', 'CNY', 'CZK', 'DKK',
        'DZD', 'EGP', 'EUR', 'GBP', 'GEL', 'HKD', 'HUF', 'IDR', 'ILS', 'INR',
        'IQD', 'JOD', 'JPY', 'KRW', 'KWD', 'KZT', 'LBP', 'LKR', 'MAD', 'MXN',
        'MYR', 'NOK', 'NZD', 'OMR', 'PHP', 'PKR', 'PLN', 'QAR', 'RON', 'RSD',
        'SAR', 'SEK', 'SGD', 'THB', 'TND', 'TRY', 'UAH', 'USD', 'ZAR',
    ];

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

    /**
     * Currencies currently used by visible contracts. Intended for report and
     * finance filters so users do not see irrelevant choices.
     *
     * @return list<string>
     */
    public static function currencies(?AdminReadRepository $read = null, string $selected = ''): array
    {
        $read ??= new AdminReadRepository();
        $currencies = [];
        foreach ($read->contracts() as $contract) {
            $currency = self::normalizedCurrency($contract['currency_code'] ?? '');
            if ($currency !== '') {
                $currencies[$currency] = true;
            }
        }

        $selected = self::normalizedCurrency($selected);
        if ($selected !== '') {
            $currencies[$selected] = true;
        }

        $result = array_keys($currencies);
        sort($result, SORT_STRING);
        return array_values($result);
    }

    /**
     * Controlled currency choices for create/edit/settings forms.
     *
     * @return list<string>
     */
    public static function currencyChoices(?AdminReadRepository $read = null, string $selected = ''): array
    {
        $currencies = array_fill_keys(self::BUSINESS_CURRENCIES, true);
        $read ??= new AdminReadRepository();
        foreach ($read->contracts() as $contract) {
            $currency = self::normalizedCurrency($contract['currency_code'] ?? '');
            if ($currency !== '') {
                $currencies[$currency] = true;
            }
        }

        $selected = self::normalizedCurrency($selected);
        if ($selected !== '') {
            $currencies[$selected] = true;
        }

        $codes = array_keys($currencies);
        if (function_exists('apply_filters')) {
            $filtered = apply_filters('safecontracts_currency_choices', $codes);
            if (is_array($filtered)) {
                foreach ($filtered as $candidate) {
                    $currency = self::normalizedCurrency($candidate);
                    if ($currency !== '') {
                        $currencies[$currency] = true;
                    }
                }
            }
        }

        $result = array_keys($currencies);
        sort($result, SORT_STRING);
        return array_values($result);
    }

    /**
     * Controlled, translated country choices. The selected legacy value is
     * preserved even when it is outside the built-in business list.
     *
     * @return array<string,string>
     */
    public static function countryChoices(string $selected = ''): array
    {
        $countries = [
            'AE' => __('United Arab Emirates', 'safecontracts'),
            'AU' => __('Australia', 'safecontracts'),
            'BH' => __('Bahrain', 'safecontracts'),
            'CA' => __('Canada', 'safecontracts'),
            'CH' => __('Switzerland', 'safecontracts'),
            'CN' => __('China', 'safecontracts'),
            'DE' => __('Germany', 'safecontracts'),
            'DZ' => __('Algeria', 'safecontracts'),
            'EG' => __('Egypt', 'safecontracts'),
            'ES' => __('Spain', 'safecontracts'),
            'FR' => __('France', 'safecontracts'),
            'GB' => __('United Kingdom', 'safecontracts'),
            'IN' => __('India', 'safecontracts'),
            'IQ' => __('Iraq', 'safecontracts'),
            'IT' => __('Italy', 'safecontracts'),
            'JO' => __('Jordan', 'safecontracts'),
            'JP' => __('Japan', 'safecontracts'),
            'KW' => __('Kuwait', 'safecontracts'),
            'LB' => __('Lebanon', 'safecontracts'),
            'MA' => __('Morocco', 'safecontracts'),
            'MY' => __('Malaysia', 'safecontracts'),
            'NL' => __('Netherlands', 'safecontracts'),
            'NZ' => __('New Zealand', 'safecontracts'),
            'OM' => __('Oman', 'safecontracts'),
            'PK' => __('Pakistan', 'safecontracts'),
            'QA' => __('Qatar', 'safecontracts'),
            'SA' => __('Saudi Arabia', 'safecontracts'),
            'SG' => __('Singapore', 'safecontracts'),
            'TN' => __('Tunisia', 'safecontracts'),
            'TR' => __('Türkiye', 'safecontracts'),
            'US' => __('United States', 'safecontracts'),
            'ZA' => __('South Africa', 'safecontracts'),
        ];

        $selected = strtoupper(trim($selected));
        if (preg_match('/^[A-Z]{2}$/', $selected) === 1 && ! isset($countries[$selected])) {
            $countries[$selected] = $selected;
        }

        if (function_exists('apply_filters')) {
            $filtered = apply_filters('safecontracts_country_choices', $countries);
            if (is_array($filtered)) {
                foreach ($filtered as $code => $label) {
                    $code = strtoupper(trim((string) $code));
                    $label = trim((string) $label);
                    if (preg_match('/^[A-Z]{2}$/', $code) === 1 && $label !== '') {
                        $countries[$code] = $label;
                    }
                }
            }
        }

        asort($countries, SORT_NATURAL | SORT_FLAG_CASE);
        return $countries;
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

    private static function normalizedCurrency(mixed $value): string
    {
        $currency = strtoupper(trim((string) $value));
        return preg_match('/^[A-Z]{3}$/', $currency) === 1 ? $currency : '';
    }
}
