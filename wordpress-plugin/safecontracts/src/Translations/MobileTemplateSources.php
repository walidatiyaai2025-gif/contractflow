<?php

declare(strict_types=1);

namespace SafeContracts\Translations;

/**
 * Source-only catalog hints for dynamic mobile strings.
 *
 * TranslationCatalog discovers these literal SafeContracts gettext calls while
 * building the dashboard editor. The method is intentionally not executed at
 * runtime; Flutter receives only saved overrides through mobile-config.
 */
final class MobileTemplateSources
{
    /** @return list<string> */
    public static function sources(): array
    {
        return [
            __('Page {page} • {count} shown', 'safecontracts'),
            __('Page {page}', 'safecontracts'),
            __('Payment #{id}', 'safecontracts'),
            __('Customer #{id}', 'safecontracts'),
            __('Contract #{id}', 'safecontracts'),
            __('Collection #{id} recorded.', 'safecontracts'),
            __('Follow-up #{id} recorded.', 'safecontracts'),
            __('Loading customer #{id}…', 'safecontracts'),
        ];
    }
}
