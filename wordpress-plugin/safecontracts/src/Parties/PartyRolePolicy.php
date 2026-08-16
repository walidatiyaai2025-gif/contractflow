<?php

declare(strict_types=1);

namespace SafeContracts\Parties;

final class PartyRolePolicy
{
    public const CUSTOMER = 'customer';
    public const SUPPLIER = 'supplier';
    public const VENDOR = 'vendor';
    public const CONTRACTOR = 'contractor';
    public const SUBCONTRACTOR = 'subcontractor';
    public const AGENT = 'agent';
    public const CONSULTANT = 'consultant';
    public const LANDLORD = 'landlord';
    public const LESSEE = 'lessee';
    public const BUYER = 'buyer';
    public const SELLER = 'seller';
    public const OTHER = 'other';

    /** @return list<string> */
    public static function roles(): array
    {
        return [
            self::CUSTOMER,
            self::SUPPLIER,
            self::VENDOR,
            self::CONTRACTOR,
            self::SUBCONTRACTOR,
            self::AGENT,
            self::CONSULTANT,
            self::LANDLORD,
            self::LESSEE,
            self::BUYER,
            self::SELLER,
            self::OTHER,
        ];
    }

    public static function normalize(string $role): string
    {
        return strtolower(trim($role));
    }

    public static function isSupported(string $role): bool
    {
        return in_array(self::normalize($role), self::roles(), true);
    }
}
