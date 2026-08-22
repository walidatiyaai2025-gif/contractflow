<?php

declare(strict_types=1);

namespace SafeContracts\Settings;

use DomainException;
use InvalidArgumentException;
use SafeContracts\Roles\Capabilities;
use SafeContracts\Support\Input;

final class MobileConfiguration
{
    public const OPTION = 'safecontracts_mobile_configuration';
    public const AD_PROVIDER_ADMOB = 'admob';
    public const AD_PROVIDER_APPLOVIN = 'applovin';

    /** @return array<string,mixed> */
    public function read(): array
    {
        $stored = get_option(self::OPTION, []);
        $stored = is_array($stored) ? $stored : [];
        $defaults = self::defaults();
        $legacyAdMobUnit = $stored['ads_admob_banner_unit_id'] ?? $stored['ads_banner_unit_id'] ?? $defaults['ads_admob_banner_unit_id'];
        $adMobUnit = $this->readAdMobUnitId($legacyAdMobUnit);

        return [
            'support_text' => $this->readText($stored['support_text'] ?? $defaults['support_text']),
            'default_page_size' => $this->readPageSize($stored['default_page_size'] ?? $defaults['default_page_size']),
            'excel_export_enabled' => $this->readBool($stored['excel_export_enabled'] ?? $defaults['excel_export_enabled']),
            'push_notifications_enabled' => $this->readBool($stored['push_notifications_enabled'] ?? $defaults['push_notifications_enabled']),
            'collection_entry_enabled' => $this->readBool($stored['collection_entry_enabled'] ?? $defaults['collection_entry_enabled']),
            'ads_enabled' => $this->readBool($stored['ads_enabled'] ?? $defaults['ads_enabled']),
            'ads_test_mode' => $this->readBool($stored['ads_test_mode'] ?? $defaults['ads_test_mode']),
            'ads_banner_enabled' => $this->readBool($stored['ads_banner_enabled'] ?? $defaults['ads_banner_enabled']),
            'ads_provider' => $this->readProvider($stored['ads_provider'] ?? $defaults['ads_provider']),
            // Keep the legacy key readable for older clients while the new provider-aware contract rolls out.
            'ads_banner_unit_id' => $adMobUnit,
            'ads_admob_banner_unit_id' => $adMobUnit,
            'ads_applovin_sdk_key' => $this->readAppLovinToken($stored['ads_applovin_sdk_key'] ?? $defaults['ads_applovin_sdk_key'], 20, 256),
            'ads_applovin_banner_unit_id' => $this->readAppLovinToken($stored['ads_applovin_banner_unit_id'] ?? $defaults['ads_applovin_banner_unit_id'], 8, 128),
        ];
    }

    /** @return array<string,mixed> */
    public function save(array $input): array
    {
        $this->requireManage();
        $provider = $this->normalizeProvider($input['ads_provider'] ?? self::AD_PROVIDER_ADMOB);
        $adMobUnit = $this->normalizeAdMobUnitId($input['ads_admob_banner_unit_id'] ?? $input['ads_banner_unit_id'] ?? '');
        $appLovinSdkKey = $this->normalizeAppLovinToken($input['ads_applovin_sdk_key'] ?? '', 'AppLovin SDK key', 20, 256);
        $appLovinBannerUnit = $this->normalizeAppLovinToken($input['ads_applovin_banner_unit_id'] ?? '', 'AppLovin banner ad unit ID', 8, 128);

        $config = [
            'support_text' => $this->normalizeText($input['support_text'] ?? ''),
            'default_page_size' => $this->normalizePageSize($input['default_page_size'] ?? 25),
            'excel_export_enabled' => $this->normalizeBool($input['excel_export_enabled'] ?? false),
            'push_notifications_enabled' => $this->normalizeBool($input['push_notifications_enabled'] ?? false),
            'collection_entry_enabled' => $this->normalizeBool($input['collection_entry_enabled'] ?? false),
            'ads_enabled' => $this->normalizeBool($input['ads_enabled'] ?? false),
            'ads_test_mode' => $this->normalizeBool($input['ads_test_mode'] ?? true),
            'ads_banner_enabled' => $this->normalizeBool($input['ads_banner_enabled'] ?? true),
            'ads_provider' => $provider,
            'ads_banner_unit_id' => $adMobUnit,
            'ads_admob_banner_unit_id' => $adMobUnit,
            'ads_applovin_sdk_key' => $appLovinSdkKey,
            'ads_applovin_banner_unit_id' => $appLovinBannerUnit,
        ];

        if ($config['ads_enabled'] && $config['ads_banner_enabled']) {
            if ($provider === self::AD_PROVIDER_ADMOB && ! $config['ads_test_mode'] && $adMobUnit === '') {
                throw new InvalidArgumentException('A production AdMob banner ad unit ID is required when AdMob is enabled outside test mode.');
            }
            if ($provider === self::AD_PROVIDER_APPLOVIN && ($appLovinSdkKey === '' || $appLovinBannerUnit === '')) {
                throw new InvalidArgumentException('AppLovin MAX requires both the SDK key and banner ad unit ID before it can be enabled.');
            }
        }

        update_option(self::OPTION, $config, false);
        do_action('safecontracts_mobile_configuration_saved', get_current_user_id(), $config);
        return $config;
    }

    /** @return array<string,mixed> */
    public static function defaults(): array
    {
        return [
            'support_text' => '',
            'default_page_size' => 25,
            'excel_export_enabled' => false,
            'push_notifications_enabled' => false,
            'collection_entry_enabled' => false,
            'ads_enabled' => false,
            'ads_test_mode' => true,
            'ads_banner_enabled' => true,
            'ads_provider' => self::AD_PROVIDER_ADMOB,
            'ads_banner_unit_id' => '',
            'ads_admob_banner_unit_id' => '',
            'ads_applovin_sdk_key' => '',
            'ads_applovin_banner_unit_id' => '',
        ];
    }

    private function normalizeText(mixed $value): string
    {
        $text = trim(strip_tags(Input::string($value, 'Mobile support text')));
        if (strlen($text) > 500 || preg_match('/[\x00]/', $text)) {
            throw new InvalidArgumentException('Mobile support text must not exceed 500 characters.');
        }
        return $text;
    }

    private function normalizePageSize(mixed $value): int
    {
        $size = filter_var($value, FILTER_VALIDATE_INT);
        if ($size === false || $size < 10 || $size > 200) {
            throw new InvalidArgumentException('Mobile default page size must be between 10 and 200.');
        }
        return (int) $size;
    }

    private function normalizeBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if ($value === 1 || $value === '1' || $value === 'true' || $value === 'on') {
            return true;
        }
        if ($value === 0 || $value === '0' || $value === 'false' || $value === null || $value === '') {
            return false;
        }
        throw new InvalidArgumentException('Mobile feature flag value is invalid.');
    }

    private function normalizeProvider(mixed $value): string
    {
        $provider = strtolower(trim(Input::string($value, 'Advertising provider')));
        if (! in_array($provider, [self::AD_PROVIDER_ADMOB, self::AD_PROVIDER_APPLOVIN], true)) {
            throw new InvalidArgumentException('Advertising provider must be AdMob or AppLovin MAX.');
        }
        return $provider;
    }

    private function normalizeAdMobUnitId(mixed $value): string
    {
        $id = trim(strip_tags(Input::string($value, 'AdMob banner ad unit ID')));
        if ($id === '') {
            return '';
        }
        if (! preg_match('/^ca-app-pub-\d{16}\/\d{10}$/', $id)) {
            throw new InvalidArgumentException('AdMob banner ad unit ID must use the ca-app-pub-XXXXXXXXXXXXXXXX/YYYYYYYYYY format.');
        }
        return $id;
    }

    private function normalizeAppLovinToken(mixed $value, string $label, int $min, int $max): string
    {
        $token = trim(strip_tags(Input::string($value, $label)));
        if ($token === '') {
            return '';
        }
        $length = strlen($token);
        if ($length < $min || $length > $max || preg_match('/[\s\x00-\x1F\x7F]/', $token)) {
            throw new InvalidArgumentException($label . ' has an invalid format.');
        }
        return $token;
    }

    private function readText(mixed $value): string
    {
        if (! is_scalar($value) && $value !== null) {
            return '';
        }
        $text = trim(strip_tags((string) $value));
        return strlen($text) <= 500 ? $text : '';
    }

    private function readPageSize(mixed $value): int
    {
        $size = filter_var($value, FILTER_VALIDATE_INT);
        return $size !== false && $size >= 10 && $size <= 200 ? (int) $size : self::defaults()['default_page_size'];
    }

    private function readBool(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 'true' || $value === 'on';
    }

    private function readProvider(mixed $value): string
    {
        if (! is_scalar($value) && $value !== null) {
            return self::AD_PROVIDER_ADMOB;
        }
        $provider = strtolower(trim((string) $value));
        return in_array($provider, [self::AD_PROVIDER_ADMOB, self::AD_PROVIDER_APPLOVIN], true)
            ? $provider
            : self::AD_PROVIDER_ADMOB;
    }

    private function readAdMobUnitId(mixed $value): string
    {
        if (! is_scalar($value) && $value !== null) {
            return '';
        }
        $id = trim(strip_tags((string) $value));
        return preg_match('/^ca-app-pub-\d{16}\/\d{10}$/', $id) ? $id : '';
    }

    private function readAppLovinToken(mixed $value, int $min, int $max): string
    {
        if (! is_scalar($value) && $value !== null) {
            return '';
        }
        $token = trim(strip_tags((string) $value));
        $length = strlen($token);
        return $length >= $min && $length <= $max && ! preg_match('/[\s\x00-\x1F\x7F]/', $token) ? $token : '';
    }

    private function requireManage(): void
    {
        if (! current_user_can(Capabilities::MANAGE_SYSTEM)) {
            throw new DomainException('You do not have permission to manage SafeContracts mobile configuration.');
        }
    }
}
