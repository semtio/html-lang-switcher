<?php

/**
 * Plugin Name: HTML Lang Switcher
 * Description: Лёгкий селектор HTML lang/dir для каждой записи/страницы. Метабокс, Quick Edit и Bulk Edit. Применение только на одиночных шаблонах. Автоматически отключается при активном WPML или Polylang.
 * Version: 1.1.2
 * Author: 7on
 * License: GPL-2.0-or-later
 * Text Domain: html-lang-switcher
 * Domain Path: /languages
 * Requires at least: 5.4
 * Requires PHP: 5.6
 */

namespace SaintsMedia\HtmlLangSwitcher;

if (!defined('ABSPATH')) {
    exit;
}

final class Plugin
{
    const META_KEY = '_html_lang_locale';

    public static function boot()
    {
        add_action('plugins_loaded', function () {
            load_plugin_textdomain('html-lang-switcher', false, dirname(plugin_basename(__FILE__)) . '/languages/');
        });

        if (self::is_multilang_active()) return;

        if (is_admin()) {
            add_action('add_meta_boxes', array(__CLASS__, 'register_metabox'));
            add_action('save_post', array(__CLASS__, 'save_metabox'), 10, 2);

            // Quick Edit + Bulk Edit
            add_action('quick_edit_custom_box', array(__CLASS__, 'render_inline_box'), 10, 2);
            add_action('bulk_edit_custom_box', array(__CLASS__, 'render_bulk_box'), 10, 2);
            add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_assets'));
            add_action('load-edit.php', array(__CLASS__, 'handle_bulk_edit_submit'));

            add_filter('manage_page_posts_columns', array(__CLASS__, 'add_locale_column'));
            add_action('manage_page_posts_custom_column', array(__CLASS__, 'fill_locale_column'), 10, 2);
            add_filter('manage_post_posts_columns', array(__CLASS__, 'add_locale_column'));
            add_action('manage_post_posts_custom_column', array(__CLASS__, 'fill_locale_column'), 10, 2);
        }

        add_filter('language_attributes', array(__CLASS__, 'filter_language_attributes'), 20, 2);
        add_action('wp_head', array(__CLASS__, 'add_hreflang_tags'));
    }

    private static function is_multilang_active()
    {
        return (
            defined('ICL_SITEPRESS_VERSION') || class_exists('SitePress') ||
            class_exists('Polylang') || defined('POLYLANG_VERSION')
        );
    }

    public static function supported_post_types()
    {
        return apply_filters('hls_supported_post_types', array('post', 'page'));
    }

    public static function supported_locales()
    {
        $locales = array(
            ''      => __('Use site default language', 'html-lang-switcher'),
            'en'    => 'en',
            'ru'    => 'ru',
            'es-ES' => 'es-ES',
            'es-AR' => 'es-AR',
            'en-In' => 'en-In',
            'it-IT' => 'it-IT',
            'de-DE' => 'de-DE',
        );
        return apply_filters('hls_supported_locales', $locales);
    }

    public static function is_rtl_lang($locale)
    {
        $code = strtolower(strtok($locale, '-'));
        return in_array($code, array('ar', 'he', 'fa', 'ur'), true);
    }

    /* === Meta box on edit screen === */
    public static function register_metabox()
    {
        foreach (self::supported_post_types() as $type) {
            add_meta_box(
                'hls_lang_box',
                __('Page language (HTML lang)', 'html-lang-switcher'),
                array(__CLASS__, 'render_metabox'),
                $type,
                'side',
                'default'
            );
        }
    }

    public static function render_metabox($post)
    {
        $locales = self::supported_locales();
        $current = get_post_meta($post->ID, self::META_KEY, true);
        wp_nonce_field('hls_save_meta', 'hls_nonce');
        echo '<p><label>' . esc_html__('Locale for this post/page:', 'html-lang-switcher') . '</label></p>';
        echo '<select name="hls_locale" class="widefat">';
        foreach ($locales as $v => $l) {
            $sel = selected($current, $v, false);
            echo "<option value='" . esc_attr($v) . "' $sel>" . esc_html($l) . "</option>";
        }
        echo '</select>';
    }

    /* === Quick Edit / Bulk Edit === */
    public static function render_inline_box($column_name, $post_type)
    {
        if (!in_array($post_type, self::supported_post_types(), true)) return;
        $locales = self::supported_locales();
        echo '<fieldset class="inline-edit-col-right"><div class="inline-edit-col">';
        wp_nonce_field('hls_inline_save', 'hls_inline_nonce');
        echo '<label><span class="title">' . esc_html__('Lang', 'html-lang-switcher') . '</span>';
        echo '<select name="hls_locale" class="hls-locale-select">';
        foreach ($locales as $v => $l) {
            echo "<option value='" . esc_attr($v) . "'>" . esc_html($l) . "</option>";
        }
        echo '</select></label></div></fieldset>';
    }

    public static function render_bulk_box($column_name, $post_type)
    {
        if (!in_array($post_type, self::supported_post_types(), true)) return;
        $locales = array_merge(array('__nochange__' => __('— No change —', 'html-lang-switcher')), self::supported_locales());
        echo '<fieldset class="inline-edit-col-right"><div class="inline-edit-col">';
        wp_nonce_field('hls_bulk_save', 'hls_bulk_nonce');
        echo '<label><span class="title">' . esc_html__('Lang', 'html-lang-switcher') . '</span>';
        echo '<select name="hls_locale_bulk" class="hls-locale-bulk">';
        foreach ($locales as $v => $l) {
            echo "<option value='" . esc_attr($v) . "'>" . esc_html($l) . "</option>";
        }
        echo '</select></label></div></fieldset>';
    }

    public static function enqueue_admin_assets()
    {
        $s = get_current_screen();
        if (! $s || $s->base !== 'edit') {
            return;
        }
        if (! in_array($s->post_type, self::supported_post_types(), true)) {
            return;
        }
        $h = 'hls-inline';
        wp_register_script($h, '', array('jquery'), false, true);
        wp_enqueue_script($h);
        wp_add_inline_script($h, '(function($){function setLocaleInQE(tr){var span=$(tr).find(\'.column-hls_locale .hls-locale-data\');var val=span.data(\'hls\');if(val!==undefined){$(\'.inline-edit-row select.hls-locale-select\').val(val);}}$(document).on(\'click\',\'.editinline\',function(){var tr=$(this).closest(\'tr\');setTimeout(function(){setLocaleInQE(tr);},50);});})(jQuery);');
    }

    public static function handle_bulk_edit_submit()
    {
        if (!isset($_REQUEST['hls_bulk_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_REQUEST['hls_bulk_nonce'])), 'hls_bulk_save')) return;
        if (!current_user_can('edit_posts')) return;
        $locale = isset($_REQUEST['hls_locale_bulk']) ? sanitize_text_field(wp_unslash($_REQUEST['hls_locale_bulk'])) : '__nochange__';
        if ($locale === '__nochange__') return;
        $ids = isset($_REQUEST['post']) ? (array)$_REQUEST['post'] : array();
        $ids = array_map('intval', $ids);
        foreach ($ids as $id) {
            $p = get_post($id);
            if (!$p || !in_array($p->post_type, self::supported_post_types(), true)) continue;
            if (!current_user_can('edit_post', $id)) continue;
            if ($locale === '') {
                delete_post_meta($id, self::META_KEY);
            } elseif (preg_match('/^[a-z]{2,3}(?:-[A-Z]{2})?$/', $locale)) {
                update_post_meta($id, self::META_KEY, $locale);
            }
        }
    }

    public static function add_locale_column($cols)
    {
        $cols['hls_locale'] = __('Lang', 'html-lang-switcher');
        return $cols;
    }

    public static function fill_locale_column($col, $id)
    {
        if ($col !== 'hls_locale') return;
        $v = get_post_meta($id, self::META_KEY, true);
        $show = $v !== '' ? $v : '—';
        echo '<span class="hls-locale-data" data-hls="' . esc_attr($v) . '">' . esc_html($show) . '</span>';
    }

    public static function save_metabox($id, $p)
    {
        if (!in_array($p->post_type, self::supported_post_types(), true)) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_autosave($id) || wp_is_post_revision($id)) return;
        $ok = false;
        if (isset($_POST['hls_nonce']) && wp_verify_nonce($_POST['hls_nonce'], 'hls_save_meta')) $ok = true;
        if (isset($_POST['hls_inline_nonce']) && wp_verify_nonce($_POST['hls_inline_nonce'], 'hls_inline_save')) $ok = true;
        if (!$ok) return;
        if (!current_user_can('edit_post', $id)) return;
        $val = isset($_POST['hls_locale']) ? trim((string) $_POST['hls_locale']) : '';
        if ($val === '') {
            delete_post_meta($id, self::META_KEY);
            return;
        }
        if (!preg_match('/^[a-z]{2,3}(?:-[A-Z]{2})?$/', $val)) return;
        update_post_meta($id, self::META_KEY, $val);
    }

    public static function filter_language_attributes($out, $doctype)
    {
        if (!is_singular()) return $out;
        $p = get_queried_object();
        if (!$p instanceof \WP_Post) return $out;
        $loc = get_post_meta($p->ID, self::META_KEY, true);
        $loc = is_string($loc) ? trim($loc) : '';
        if ($loc === '') return $out;
        $dir = self::is_rtl_lang($loc) ? 'rtl' : 'ltr';
        return sprintf('lang="%s" dir="%s"', esc_attr($loc), esc_attr($dir));
    }

    public static function add_hreflang_tags()
    {
        if (! is_singular()) {
            return;
        }

        $post_id = get_the_ID();
        if (! $post_id) {
            return;
        }

        $locale = get_post_meta($post_id, self::META_KEY, true);
        if (! empty($locale) && $locale !== get_locale()) {
            echo '<link rel="alternate" hreflang="' . esc_attr($locale) . '" href="' . esc_url(get_permalink($post_id)) . '" />' . "\n";
        }
    }
}

Plugin::boot();
