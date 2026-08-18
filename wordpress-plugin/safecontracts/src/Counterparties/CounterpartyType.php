<?php

declare(strict_types=1);

namespace SafeContracts\Counterparties;

use SafeContracts\Contracts\Counterparty;

final class CounterpartyType
{
    public const CUSTOMER = Counterparty::CUSTOMER;
    public const SUPPLIER = Counterparty::SUPPLIER;

    public static function normalize(mixed $value): string
    {
        return Counterparty::normalize($value);
    }
}
