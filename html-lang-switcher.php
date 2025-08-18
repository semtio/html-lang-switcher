<?php
/**
 * Plugin Name: HTML Lang Switcher
 * Description: Лёгкий селектор HTML lang/dir для каждой записи/страницы. Метабокс для выбора локали, применение только на одиночных шаблонах. Автоматически «молча» отключается при активном WPML или Polylang.
 * Version: 1.0.0
 * Author: 7on
 * License: GPL-2.0-or-later
 * Text Domain: html-lang-switcher
 * Domain Path: /languages
 * Requires at least: 5.4
 * Requires PHP: 7.4
 */

namespace SaintsMedia\HtmlLangSwitcher;

if (!defined('ABSPATH')) { exit; }

final class Plugin {
    const META_KEY = '_html_lang_locale';

    public static function boot(): void {
        // i18n
        add_action('plugins_loaded', function () {
            load_plugin_textdomain('html-lang-switcher', false, dirname(plugin_basename(__FILE__)) . '/languages/');
        });

        if (self::is_multilang_active()) {
            return;
        }

        if (is_admin()) {
            add_action('add_meta_boxes', [__CLASS__, 'register_metabox']);
            add_action('save_post', [__CLASS__, 'save_metabox'], 10, 2);
        }

        add_filter('language_attributes', [__CLASS__, 'filter_language_attributes'], 20, 2);
    }

    private static function is_multilang_active(): bool {
        return (
            defined('ICL_SITEPRESS_VERSION') || class_exists('SitePress') ||
            class_exists('Polylang') || defined('POLYLANG_VERSION')
        );
    }

    public static function supported_post_types(): array {
        $types = ['post', 'page'];
        return apply_filters('hls_supported_post_types', $types);
    }

    public static function supported_locales(): array {
        $locales = [
            ''      => __('Use site default language', 'html-lang-switcher'),
            'en'    => 'English',
            'en-US' => 'English (US)',
            'en-GB' => 'English (UK)',
            'pl'    => 'Polski',
            'ru'    => 'Русский',
            'uk'    => 'Українська',
            'de-DE' => 'Deutsch (DE)',
            'fr-FR' => 'Français',
            'es-ES' => 'Español',
            'ar'    => 'العربية',
            'he'    => 'עברית',
            'fa'    => 'فارسی',
            'ur'    => 'اردو',
            'zh-CN' => '简体中文 (CN)',
            'zh-TW' => '繁體中文 (TW)',
            'ja'    => '日本語',
            'ko'    => '한국어',
        ];

        return apply_filters('hls_supported_locales', $locales);
    }

    public static function is_rtl_lang(string $locale): bool {
        $code = strtolower(strtok($locale, '-'));
        return in_array($code, ['ar', 'he', 'fa', 'ur'], true);
    }

    public static function register_metabox(): void {
        foreach (self::supported_post_types() as $type) {
            add_meta_box(
                'hls_lang_box',
                __('Page language (HTML lang)', 'html-lang-switcher'),
                [__CLASS__, 'render_metabox'],
                $type,
                'side',
                'default'
            );
        }
    }

    public static function render_metabox(\WP_Post $post): void {
        $locales = self::supported_locales();
        $current = get_post_meta($post->ID, self::META_KEY, true);
        wp_nonce_field('hls_save_meta', 'hls_nonce');

        echo '<p><label for="hls_locale_select">' . esc_html__('Locale for this post/page:', 'html-lang-switcher') . '</label></p>';
        echo '<select name="hls_locale" id="hls_locale_select" class="widefat">';
        foreach ($locales as $value => $label) {
            $selected = selected($current, $value, false);
            echo '<option value="' . esc_attr($value) . '" ' . $selected . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
    }

    public static function save_metabox(int $post_id, \WP_Post $post): void {
        if (!in_array($post->post_type, self::supported_post_types(), true)) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) return;
        if (!isset($_POST['hls_nonce']) || !wp_verify_nonce($_POST['hls_nonce'], 'hls_save_meta')) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $val = isset($_POST['hls_locale']) ? trim((string) $_POST['hls_locale']) : '';

        if ($val === '') {
            delete_post_meta($post_id, self::META_KEY);
            return;
        }

        if (!preg_match('/^[a-z]{2,3}(?:-[A-Z]{2})?$/', $val)) {
            return;
        }

        update_post_meta($post_id, self::META_KEY, $val);
    }

    public static function filter_language_attributes(string $output, string $doctype): string {
        if (!is_singular()) {
            return $output;
        }

        $post = get_queried_object();
        if (!$post instanceof \WP_Post) {
            return $output;
        }

        $locale = get_post_meta($post->ID, self::META_KEY, true);
        $locale = is_string($locale) ? trim($locale) : '';

        if ($locale === '') {
            return $output;
        }

        $dir = self::is_rtl_lang($locale) ? 'rtl' : 'ltr';

        return sprintf('lang="%s" dir="%s"', esc_attr($locale), esc_attr($dir));
    }
}

Plugin::boot();
