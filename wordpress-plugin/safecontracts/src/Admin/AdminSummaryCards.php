<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

final class AdminSummaryCards
{
    /** @param list<array{label:string,value:string|int,detail?:string}> $cards */
    public static function render(array $cards): void
    {
        if ($cards === []) {
            return;
        }
        echo '<div class="safecontracts-summary-cards" role="list">';
        foreach ($cards as $card) {
            echo '<section class="safecontracts-summary-card" role="listitem">';
            echo '<span class="safecontracts-summary-card__label">' . esc_html((string) ($card['label'] ?? '')) . '</span>';
            echo '<strong class="safecontracts-summary-card__value">' . esc_html((string) ($card['value'] ?? '0')) . '</strong>';
            if (isset($card['detail']) && trim((string) $card['detail']) !== '') {
                echo '<small class="safecontracts-summary-card__detail">' . esc_html((string) $card['detail']) . '</small>';
            }
            echo '</section>';
        }
        echo '</div>';
    }
}
