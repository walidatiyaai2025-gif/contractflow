<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Support\MoneyFormatter;

/**
 * SafeContracts Admin compatibility shim for legacy presentation helpers.
 *
 * PHP resolves unqualified function calls in a namespace before falling back to
 * the global function. Existing Admin pages that call number_format(..., 2)
 * therefore use the same centralized money presentation rule without changing
 * their calculations, queries or DECIMAL storage precision.
 */
function number_format(
    float $num,
    int $decimals = 0,
    ?string $decimal_separator = '.',
    ?string $thousands_separator = ','
): string {
    return MoneyFormatter::formatNumber(
        (string) $num,
        $decimals,
        $decimal_separator ?? '.',
        $thousands_separator ?? ','
    );
}
