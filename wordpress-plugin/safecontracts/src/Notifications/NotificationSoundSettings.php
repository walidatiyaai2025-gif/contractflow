<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

final class NotificationSoundSettings
{
    public const OPTION = 'safecontracts_notification_sounds';
    public const SOUND_DEFAULT = 'default';
    public const SOUND_BANKNOTE_COUNTER = 'banknote_counter';
    public const SOUND_CASHIER_KA_CHING = 'cashier_ka_ching';
    public const SOUND_COIN_DROP = 'coin_drop';

    public const CATEGORY_CONTRACT_PAYMENT = 'contract_payment';
    public const CATEGORY_COLLECTION = 'collection';
    public const CATEGORY_SETTLEMENT = 'settlement';
    public const CATEGORY_REVERSAL_REFUND = 'reversal_refund';
    public const CATEGORY_DUE_REMINDER = 'due_reminder';

    public static function soundKeys(): array
    {
        return [self::SOUND_DEFAULT, self::SOUND_BANKNOTE_COUNTER, self::SOUND_CASHIER_KA_CHING, self::SOUND_COIN_DROP];
    }

    public static function categories(): array
    {
        return [self::CATEGORY_CONTRACT_PAYMENT, self::CATEGORY_COLLECTION, self::CATEGORY_SETTLEMENT, self::CATEGORY_REVERSAL_REFUND, self::CATEGORY_DUE_REMINDER];
    }

    public function defaults(): array
    {
        return [
            'enabled' => false,
            'default_sound' => self::SOUND_DEFAULT,
            self::CATEGORY_CONTRACT_PAYMENT => self::SOUND_CASHIER_KA_CHING,
            self::CATEGORY_COLLECTION => self::SOUND_BANKNOTE_COUNTER,
            self::CATEGORY_SETTLEMENT => self::SOUND_COIN_DROP,
            self::CATEGORY_REVERSAL_REFUND => self::SOUND_COIN_DROP,
            self::CATEGORY_DUE_REMINDER => self::SOUND_DEFAULT,
        ];
    }

    public function get(): array
    {
        $stored = get_option(self::OPTION, []);
        return $this->normalize(is_array($stored) ? $stored : []);
    }

    public function save(array $input): array
    {
        $normalized = $this->normalize($input);
        update_option(self::OPTION, $normalized, false);
        return $normalized;
    }

    public function resolve(array $payload): array
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $category = $this->categoryFor(array_merge($payload, $data));
        $settings = $this->get();
        $soundKey = self::SOUND_DEFAULT;
        if ($settings['enabled']) {
            $soundKey = $settings[$category] ?? $settings['default_sound'];
            if ($soundKey === self::SOUND_DEFAULT) {
                $soundKey = $settings['default_sound'];
            }
        }
        if (! in_array($soundKey, self::soundKeys(), true)) {
            $soundKey = self::SOUND_DEFAULT;
        }
        return [
            'category' => $category,
            'sound_key' => $soundKey,
            'channel_id' => self::channelId($soundKey),
            'android_sound' => self::androidSound($soundKey),
            'source_filename' => self::sourceFilename($soundKey),
        ];
    }

    public function categoryFor(array $metadata): string
    {
        $event = $this->metadataText($metadata, 'event_code');
        $template = $this->metadataText($metadata, 'template_code');
        $rule = $this->metadataText($metadata, 'rule_code');
        $resource = $this->metadataText($metadata, 'resource_type');
        $direction = $this->metadataText($metadata, 'financial_direction');
        $haystack = implode(' ', [$event, $template, $rule, $resource]);

        if ($this->containsAny($haystack, ['collection_archived', 'reversal', 'refund', 'reversed'])) {
            return self::CATEGORY_REVERSAL_REFUND;
        }
        if ($this->containsAny($haystack, ['followup', 'follow_up', 'before_due', 'due_day', 'overdue', 'reminder', 'due_soon'])) {
            return self::CATEGORY_DUE_REMINDER;
        }
        if ($this->containsAny($haystack, ['financial_settlement_recorded', 'settlement', 'settled'])) {
            return $direction === 'receivable' ? self::CATEGORY_COLLECTION : self::CATEGORY_SETTLEMENT;
        }
        if ($this->containsAny($haystack, ['collection', 'collected', 'receivable_received'])) {
            return self::CATEGORY_COLLECTION;
        }
        return self::CATEGORY_CONTRACT_PAYMENT;
    }

    public static function channelId(string $soundKey): string
    {
        return match ($soundKey) {
            self::SOUND_BANKNOTE_COUNTER => 'safe_contracts_alerts_banknote_counter',
            self::SOUND_CASHIER_KA_CHING => 'safe_contracts_alerts_cashier_ka_ching',
            self::SOUND_COIN_DROP => 'safe_contracts_alerts_coin_drop',
            default => 'safe_contracts_alerts',
        };
    }

    public static function androidSound(string $soundKey): string
    {
        return match ($soundKey) {
            self::SOUND_BANKNOTE_COUNTER => 'banknote_counter_106014',
            self::SOUND_CASHIER_KA_CHING => 'cashier_ka_ching',
            self::SOUND_COIN_DROP => 'coin_drop_229314',
            default => 'default',
        };
    }

    public static function sourceFilename(string $soundKey): ?string
    {
        return match ($soundKey) {
            self::SOUND_BANKNOTE_COUNTER => 'freesound_community-banknote-counter-106014.mp3(2).mpeg',
            self::SOUND_CASHIER_KA_CHING => 'u_byub5wd934-cashier-quotka-chingquot.mp3.mpeg',
            self::SOUND_COIN_DROP => 'universfield-coin-drop-229314.mp3(2).mpeg',
            default => null,
        };
    }

    private function normalize(array $input): array
    {
        $defaults = $this->defaults();
        $normalized = $defaults;
        $normalized['enabled'] = $this->normalizeBool($input['enabled'] ?? $defaults['enabled']);
        foreach (['default_sound', ...self::categories()] as $field) {
            $value = strtolower(trim((string) ($input[$field] ?? $defaults[$field])));
            $normalized[$field] = in_array($value, self::soundKeys(), true) ? $value : $defaults[$field];
        }
        return $normalized;
    }

    private function normalizeBool(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 'true' || $value === 'on';
    }

    private function metadataText(array $metadata, string $key): string
    {
        $value = $metadata[$key] ?? '';
        return is_scalar($value) ? strtolower(trim((string) $value)) : '';
    }

    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }
        return false;
    }
}
