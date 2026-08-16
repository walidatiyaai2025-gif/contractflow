<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use DateTimeImmutable;

final class AdminPeriodFilter
{
    /** @return array{date_from:?string,date_to:?string,date_range_error:bool} */
    public static function normalize(array $input): array
    {
        $fromRaw = array_key_exists('date_from', $input) ? $input['date_from'] : ($input['due_from'] ?? null);
        $toRaw = array_key_exists('date_to', $input) ? $input['date_to'] : ($input['due_to'] ?? null);

        [$from, $fromInvalid] = self::date($fromRaw);
        [$to, $toInvalid] = self::date($toRaw);
        $error = $fromInvalid || $toInvalid;

        if (! $error && $from !== null && $to !== null && $to < $from) {
            $error = true;
        }

        if ($error) {
            return [
                'date_from' => null,
                'date_to' => null,
                'date_range_error' => true,
            ];
        }

        return [
            'date_from' => $from,
            'date_to' => $to,
            'date_range_error' => false,
        ];
    }

    /**
     * Render a standalone period filter while preserving requested scalar query
     * arguments such as a selected payment/contract.
     *
     * @param array<string,mixed> $filters
     * @param array<string,mixed> $preserve
     */
    public static function render(string $pageSlug, array $filters, array $preserve = []): void
    {
        if (! empty($filters['date_range_error'])) {
            ?>
            <div class="notice notice-error inline"><p><?php echo esc_html__('Invalid period. Use valid YYYY-MM-DD dates and make sure the end date is not earlier than the start date.', 'safecontracts'); ?></p></div>
            <?php
        }
        ?>
        <form class="safecontracts-filter-bar safecontracts-period-filter" method="get">
            <input type="hidden" name="page" value="<?php echo esc_attr($pageSlug); ?>">
            <?php foreach ($preserve as $key => $value) : ?>
                <?php if (is_scalar($value) && ! is_bool($value) && (string) $value !== '') : ?>
                    <input type="hidden" name="<?php echo esc_attr((string) $key); ?>" value="<?php echo esc_attr((string) $value); ?>">
                <?php endif; ?>
            <?php endforeach; ?>
            <?php self::renderFields($filters); ?>
            <button class="button button-primary" type="submit"><?php echo esc_html__('Apply period', 'safecontracts'); ?></button>
            <a class="button" href="<?php echo esc_url(add_query_arg(['page' => $pageSlug], admin_url('admin.php'))); ?>"><?php echo esc_html__('Clear period', 'safecontracts'); ?></a>
        </form>
        <?php
    }

    /** @param array<string,mixed> $filters */
    public static function renderFields(array $filters): void
    {
        ?>
        <label><?php echo esc_html__('Period from', 'safecontracts'); ?><input type="date" name="date_from" value="<?php echo esc_attr((string) ($filters['date_from'] ?? '')); ?>"></label>
        <label><?php echo esc_html__('Period to', 'safecontracts'); ?><input type="date" name="date_to" value="<?php echo esc_attr((string) ($filters['date_to'] ?? '')); ?>"></label>
        <?php
    }

    /** @return array{0:?string,1:bool} */
    private static function date(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [null, false];
        }
        if (! is_scalar($value) || is_bool($value)) {
            return [null, true];
        }

        $value = trim((string) $value);
        if ($value === '') {
            return [null, false];
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (! $date || $date->format('Y-m-d') !== $value) {
            return [null, true];
        }
        return [$value, false];
    }
}
