<?php

declare(strict_types=1);

namespace SafeContracts\Settings;

final class MobileLandingContent
{
    public const OPTION = 'safecontracts_mobile_landing_content';

    /**
     * Public mobile landing content. This intentionally exposes only approved
     * company/marketing copy and never reads users, contracts, payments, or
     * private mobile configuration.
     *
     * @return array<string, mixed>
     */
    public function read(): array
    {
        $stored = get_option(self::OPTION, []);
        $stored = is_array($stored) ? $stored : [];
        $defaults = self::defaults();

        return [
            'schema_version' => 1,
            'brand_name' => $this->text($stored['brand_name'] ?? $defaults['brand_name'], 80, $defaults['brand_name']),
            'agency_name' => $this->localizedText($stored['agency_name'] ?? null, $defaults['agency_name'], 120),
            'headline' => $this->localizedText($stored['headline'] ?? null, $defaults['headline'], 160),
            'highlight' => $this->localizedText($stored['highlight'] ?? null, $defaults['highlight'], 180),
            'summary' => $this->localizedText($stored['summary'] ?? null, $defaults['summary'], 700),
            'experience_years' => $this->boundedInt(
                $stored['experience_years'] ?? $defaults['experience_years'],
                0,
                100,
                $defaults['experience_years']
            ),
            'services' => $defaults['services'],
            'contact' => [
                'phones' => $this->phones($stored['phones'] ?? $defaults['contact']['phones'], $defaults['contact']['phones']),
                'office_address' => $this->localizedText(
                    $stored['office_address'] ?? null,
                    $defaults['contact']['office_address'],
                    240
                ),
            ],
            'sign_in_label' => $defaults['sign_in_label'],
            'learn_more_label' => $defaults['learn_more_label'],
        ];
    }

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'brand_name' => 'Alkenzy ADV',
            'agency_name' => [
                'en' => 'Alkenzy Advertising Agency',
                'ar' => 'الكنزي للإعلان',
            ],
            'headline' => [
                'en' => 'Advertising built on experience',
                'ar' => 'خبرة إعلانية تصنع الفرق',
            ],
            'highlight' => [
                'en' => 'Planning, execution, and measurable impact',
                'ar' => 'تخطيط وتنفيذ وتأثير قابل للقياس',
            ],
            'summary' => [
                'en' => 'Alkenzy specializes in advertising strategy, planning, and campaign execution across outdoor media, print, digital channels, social media, internet, and television.',
                'ar' => 'الكنزي شركة متخصصة في الإعلان والتخطيط وتنفيذ الحملات الإعلانية عبر الإعلانات الطرقية والمطبوعات ومواقع التواصل الاجتماعي والإنترنت والتلفزيون.',
            ],
            'experience_years' => 10,
            'services' => [
                [
                    'key' => 'strategy',
                    'title' => ['en' => 'Marketing strategy', 'ar' => 'استراتيجيات تسويقية'],
                    'subtitle' => ['en' => 'Planning and ideas built around each campaign', 'ar' => 'تخطيط وأفكار مصممة لكل حملة'],
                ],
                [
                    'key' => 'outdoor',
                    'title' => ['en' => 'Outdoor & print', 'ar' => 'طرقي ومطبوع'],
                    'subtitle' => ['en' => 'Road advertising and advertising publications', 'ar' => 'إعلانات طرقية ومطبوعات إعلانية'],
                ],
                [
                    'key' => 'digital',
                    'title' => ['en' => 'Digital & social', 'ar' => 'رقمي واجتماعي'],
                    'subtitle' => ['en' => 'Social media and internet campaigns', 'ar' => 'حملات مواقع التواصل والإنترنت'],
                ],
                [
                    'key' => 'television',
                    'title' => ['en' => 'Television campaigns', 'ar' => 'حملات تلفزيونية'],
                    'subtitle' => ['en' => 'Creative campaign planning and execution', 'ar' => 'تخطيط وتنفيذ إبداعي للحملات'],
                ],
            ],
            'contact' => [
                'phones' => ['01000272232', '01017030397'],
                'office_address' => [
                    'en' => '57 Khatam Al-Morselin, Giza',
                    'ar' => '57 خاتم المرسلين، الجيزة',
                ],
            ],
            'sign_in_label' => ['en' => 'Sign in', 'ar' => 'تسجيل الدخول'],
            'learn_more_label' => ['en' => 'Learn more', 'ar' => 'اعرف المزيد'],
        ];
    }

    /**
     * @param mixed $value
     * @param array{en:string,ar:string} $fallback
     * @return array{en:string,ar:string}
     */
    private function localizedText(mixed $value, array $fallback, int $maximum): array
    {
        if (! is_array($value)) {
            return $fallback;
        }

        return [
            'en' => $this->text($value['en'] ?? $fallback['en'], $maximum, $fallback['en']),
            'ar' => $this->text($value['ar'] ?? $fallback['ar'], $maximum, $fallback['ar']),
        ];
    }

    private function text(mixed $value, int $maximum, string $fallback): string
    {
        if (! is_scalar($value) && $value !== null) {
            return $fallback;
        }

        $text = trim(strip_tags((string) $value));
        if ($text === '' || strlen($text) > $maximum || str_contains($text, "\0")) {
            return $fallback;
        }

        return $text;
    }

    private function boundedInt(mixed $value, int $minimum, int $maximum, int $fallback): int
    {
        $parsed = filter_var($value, FILTER_VALIDATE_INT);
        if ($parsed === false || $parsed < $minimum || $parsed > $maximum) {
            return $fallback;
        }

        return (int) $parsed;
    }

    /**
     * @param mixed $value
     * @param list<string> $fallback
     * @return list<string>
     */
    private function phones(mixed $value, array $fallback): array
    {
        if (! is_array($value)) {
            return $fallback;
        }

        $phones = [];
        foreach (array_slice($value, 0, 4) as $phone) {
            if (! is_scalar($phone) && $phone !== null) {
                continue;
            }
            $normalized = preg_replace('/[^0-9+() .-]/', '', trim((string) $phone));
            if (! is_string($normalized) || $normalized === '' || strlen($normalized) > 32) {
                continue;
            }
            $phones[] = $normalized;
        }

        return $phones !== [] ? array_values(array_unique($phones)) : $fallback;
    }
}
