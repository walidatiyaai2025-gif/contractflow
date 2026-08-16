<?php

declare(strict_types=1);

namespace SafeContracts\Admin;

use SafeContracts\Roles\Capabilities;
use SafeContracts\Translations\TranslationCatalog;

final class TranslationsPage
{
    public const SLUG = 'safecontracts-translations';
    public const SAVE_ACTION = 'safecontracts_save_translations';

    public static function register(): void
    {
        add_submenu_page(
            AdminShell::SLUG,
            __('Translations', 'safecontracts'),
            __('Translations', 'safecontracts'),
            Capabilities::MANAGE_SYSTEM,
            self::SLUG,
            [self::class, 'render']
        );
    }

    public static function handleSave(): void
    {
        if (! current_user_can(Capabilities::MANAGE_SYSTEM)) {
            wp_die(__('You do not have permission to manage SafeContracts translations.', 'safecontracts'));
        }
        check_admin_referer(self::SAVE_ACTION);

        $mode = sanitize_key((string) ($_POST['translation_mode'] ?? 'save'));
        $status = 'translations_saved';
        if ($mode === 'reset_all') {
            TranslationCatalog::reset();
            $status = 'translations_reset';
        } elseif ($mode === 'reset_ar') {
            TranslationCatalog::reset('ar');
            $status = 'translations_reset';
        } elseif ($mode === 'reset_en') {
            TranslationCatalog::reset('en');
            $status = 'translations_reset';
        } else {
            $rows = $_POST['translation_rows'] ?? [];
            TranslationCatalog::saveRows(is_array($rows) ? $rows : []);
        }

        $args = ['page' => self::SLUG, 'safecontracts_status' => $status];
        $search = sanitize_text_field((string) ($_POST['translation_search'] ?? ''));
        if ($search !== '') {
            $args['translation_search'] = $search;
        }
        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    public static function render(): void
    {
        if (! current_user_can(Capabilities::MANAGE_SYSTEM)) {
            wp_die(__('You do not have permission to manage SafeContracts translations.', 'safecontracts'));
        }

        $search = sanitize_text_field((string) ($_GET['translation_search'] ?? ''));
        $catalog = TranslationCatalog::catalog();
        $overrides = TranslationCatalog::overrides();
        if ($search !== '') {
            $needle = function_exists('mb_strtolower') ? mb_strtolower($search) : strtolower($search);
            $catalog = array_filter(
                $catalog,
                static function (array $row, string $source) use ($needle): bool {
                    $haystack = $source . ' ' . $row['ar'] . ' ' . implode(' ', $row['surfaces']);
                    $haystack = function_exists('mb_strtolower') ? mb_strtolower($haystack) : strtolower($haystack);
                    return str_contains($haystack, $needle);
                },
                ARRAY_FILTER_USE_BOTH
            );
        }
        ?>
        <div class="wrap safecontracts-settings safecontracts-translations" dir="auto">
            <div class="safecontracts-section-heading">
                <div>
                    <p class="safecontracts-admin-shell__eyebrow"><?php echo esc_html__('Translation management', 'safecontracts'); ?></p>
                    <h1><?php echo esc_html__('Translations', 'safecontracts'); ?></h1>
                    <p><?php echo esc_html__('Edit SafeContracts Arabic and English wording without changing the WordPress language.', 'safecontracts'); ?></p>
                </div>
            </div>

            <section class="safecontracts-admin-card safecontracts-settings-card">
                <form method="get" class="safecontracts-filter-bar">
                    <input type="hidden" name="page" value="<?php echo esc_attr(self::SLUG); ?>">
                    <label><?php echo esc_html__('Search translations', 'safecontracts'); ?>
                        <input type="search" name="translation_search" value="<?php echo esc_attr($search); ?>">
                    </label>
                    <button class="button" type="submit"><?php echo esc_html__('Search translations', 'safecontracts'); ?></button>
                </form>
                <p class="description"><?php echo esc_html__('Leave an override empty to use the built-in default.', 'safecontracts'); ?></p>
            </section>

            <section class="safecontracts-admin-card safecontracts-table-card">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_ACTION); ?>">
                    <input type="hidden" name="translation_search" value="<?php echo esc_attr($search); ?>">
                    <?php wp_nonce_field(self::SAVE_ACTION); ?>
                    <?php if ($catalog === []) : ?>
                        <p><?php echo esc_html__('No translation entries match this search.', 'safecontracts'); ?></p>
                    <?php else : ?>
                        <div class="safecontracts-translations__table-wrap">
                            <table class="widefat striped safecontracts-translations__table">
                                <thead>
                                <tr>
                                    <th><?php echo esc_html__('Source / key', 'safecontracts'); ?></th>
                                    <th><?php echo esc_html__('Default English', 'safecontracts'); ?></th>
                                    <th><?php echo esc_html__('English override', 'safecontracts'); ?></th>
                                    <th><?php echo esc_html__('Default Arabic', 'safecontracts'); ?></th>
                                    <th><?php echo esc_html__('Arabic override', 'safecontracts'); ?></th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php $index = 0; foreach ($catalog as $source => $defaults) : ?>
                                    <tr>
                                        <td>
                                            <input type="hidden" name="translation_rows[<?php echo esc_attr((string) $index); ?>][source]" value="<?php echo esc_attr($source); ?>">
                                            <code><?php echo esc_html(implode(', ', $defaults['surfaces'])); ?></code>
                                            <p><small><?php echo esc_html($source); ?></small></p>
                                        </td>
                                        <td><?php echo esc_html($defaults['en']); ?></td>
                                        <td><textarea class="widefat" rows="3" name="translation_rows[<?php echo esc_attr((string) $index); ?>][en]" placeholder="<?php echo esc_attr($defaults['en']); ?>"><?php echo esc_textarea((string) ($overrides['en'][$source] ?? '')); ?></textarea></td>
                                        <td dir="rtl"><?php echo esc_html($defaults['ar']); ?></td>
                                        <td><textarea class="widefat" dir="rtl" rows="3" name="translation_rows[<?php echo esc_attr((string) $index); ?>][ar]" placeholder="<?php echo esc_attr($defaults['ar']); ?>"><?php echo esc_textarea((string) ($overrides['ar'][$source] ?? '')); ?></textarea></td>
                                    </tr>
                                <?php $index++; endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <div class="safecontracts-translations__actions">
                        <button class="button button-primary" type="submit" name="translation_mode" value="save"><?php echo esc_html__('Save translations', 'safecontracts'); ?></button>
                        <button class="button" type="submit" name="translation_mode" value="reset_ar" data-safecontracts-reset-translations><?php echo esc_html__('Reset Arabic', 'safecontracts'); ?></button>
                        <button class="button" type="submit" name="translation_mode" value="reset_en" data-safecontracts-reset-translations><?php echo esc_html__('Reset English', 'safecontracts'); ?></button>
                        <button class="button button-link-delete" type="submit" name="translation_mode" value="reset_all" data-safecontracts-reset-translations><?php echo esc_html__('Reset all translations', 'safecontracts'); ?></button>
                    </div>
                </form>
            </section>
        </div>
        <?php
    }
}
