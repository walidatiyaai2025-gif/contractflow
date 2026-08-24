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
            'services' => $this->services($stored['services'] ?? null, $defaults['services']),
            'contact' => [
                'phones' => $this->phones($stored['phones'] ?? $defaults['contact']['phones'], $defaults['contact']['phones']),
                'office_address' => $this->localizedText(
                    $stored['office_address'] ?? null,
                    $defaults['contact']['office_address'],
                    240
                ),
            ],
            'images' => $this->publicImages($stored['image_ids'] ?? []),
            'sign_in_label' => $this->localizedText($stored['sign_in_label'] ?? null, $defaults['sign_in_label'], 60),
            'learn_more_label' => $this->localizedText($stored['learn_more_label'] ?? null, $defaults['learn_more_label'], 60),
        ];
    }

    /**
     * Persist only the public, bounded marketing fields used by the mobile
     * landing endpoint. This method intentionally accepts no URLs, secrets,
     * user IDs, financial values, or protected application configuration.
     *
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function save(array $input): array
    {
        $defaults = self::defaults();
        $normalized = [
            'brand_name' => $this->text($input['brand_name'] ?? null, 80, $defaults['brand_name']),
            'agency_name' => $this->localizedText($input['agency_name'] ?? null, $defaults['agency_name'], 120),
            'headline' => $this->localizedText($input['headline'] ?? null, $defaults['headline'], 160),
            'highlight' => $this->localizedText($input['highlight'] ?? null, $defaults['highlight'], 180),
            'summary' => $this->localizedText($input['summary'] ?? null, $defaults['summary'], 700),
            'experience_years' => $this->boundedInt($input['experience_years'] ?? null, 0, 100, $defaults['experience_years']),
            'services' => $this->services($input['services'] ?? null, $defaults['services']),
            'phones' => $this->phones($input['phones'] ?? null, $defaults['contact']['phones']),
            'office_address' => $this->localizedText($input['office_address'] ?? null, $defaults['contact']['office_address'], 240),
            'image_ids' => $this->imageIds($input['image_ids'] ?? []),
            'sign_in_label' => $this->localizedText($input['sign_in_label'] ?? null, $defaults['sign_in_label'], 60),
            'learn_more_label' => $this->localizedText($input['learn_more_label'] ?? null, $defaults['learn_more_label'], 60),
        ];

        update_option(self::OPTION, $normalized, false);
        return $this->read();
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
            'image_ids' => [],
            'sign_in_label' => ['en' => 'Sign in', 'ar' => 'تسجيل الدخول'],
            'learn_more_label' => ['en' => 'Learn more', 'ar' => 'اعرف المزيد'],
        ];
    }

    /** @return list<int> */
    public function selectedImageIds(): array
    {
        $stored = get_option(self::OPTION, []);
        $stored = is_array($stored) ? $stored : [];
        return $this->imageIds($stored['image_ids'] ?? []);
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
     * @param list<array{key:string,title:array{en:string,ar:string},subtitle:array{en:string,ar:string}}> $fallback
     * @return list<array{key:string,title:array{en:string,ar:string},subtitle:array{en:string,ar:string}}>
     */
    private function services(mixed $value, array $fallback): array
    {
        if (! is_array($value)) {
            return $fallback;
        }

        $byKey = [];
        foreach ($value as $service) {
            if (! is_array($service)) {
                continue;
            }
            $key = isset($service['key']) && is_scalar($service['key'])
                ? preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $service['key']))
                : '';
            if (! is_string($key) || $key === '') {
                continue;
            }
            $byKey[$key] = $service;
        }

        $services = [];
        foreach ($fallback as $default) {
            $stored = $byKey[$default['key']] ?? [];
            $services[] = [
                'key' => $default['key'],
                'title' => $this->localizedText($stored['title'] ?? null, $default['title'], 100),
                'subtitle' => $this->localizedText($stored['subtitle'] ?? null, $default['subtitle'], 180),
            ];
        }
        return $services;
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

    /** @return list<int> */
    private function imageIds(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }
        if (! is_array($value)) {
            return [];
        }

        $imageIds = [];
        foreach (array_slice($value, 0, 12) as $candidate) {
            if (! is_scalar($candidate)) {
                continue;
            }
            $id = filter_var($candidate, FILTER_VALIDATE_INT);
            if ($id === false || $id < 1 || in_array((int) $id, $imageIds, true)) {
                continue;
            }
            if (function_exists('wp_attachment_is_image') && ! wp_attachment_is_image((int) $id)) {
                continue;
            }
            $imageIds[] = (int) $id;
            if (count($imageIds) === 6) {
                break;
            }
        }

        return $imageIds;
    }

    /**
     * Resolve only public image presentation metadata. Attachment IDs remain
     * server-authoritative and deleted/non-image media automatically disappear.
     *
     * @param mixed $value
     * @return list<array{id:int,url:string,alt:string,width:int,height:int}>
     */
    private function publicImages(mixed $value): array
    {
        if (! function_exists('wp_get_attachment_image_src')) {
            return [];
        }

        $images = [];
        foreach ($this->imageIds($value) as $id) {
            $source = wp_get_attachment_image_src($id, 'large');
            if (! is_array($source) || ! isset($source[0]) || ! is_scalar($source[0])) {
                continue;
            }
            $url = function_exists('esc_url_raw') ? esc_url_raw((string) $source[0]) : trim((string) $source[0]);
            if ($url === '') {
                continue;
            }
            $alt = function_exists('get_post_meta') ? get_post_meta($id, '_wp_attachment_image_alt', true) : '';
            $alt = is_scalar($alt) ? trim(strip_tags((string) $alt)) : '';
            if ($alt === '' && function_exists('wp_get_attachment_caption')) {
                $caption = wp_get_attachment_caption($id);
                $alt = is_scalar($caption) ? trim(strip_tags((string) $caption)) : '';
            }
            $images[] = [
                'id' => $id,
                'url' => $url,
                'alt' => strlen($alt) <= 180 ? $alt : '',
                'width' => isset($source[1]) ? max(0, (int) $source[1]) : 0,
                'height' => isset($source[2]) ? max(0, (int) $source[2]) : 0,
            ];
        }

        return $images;
    }
}
