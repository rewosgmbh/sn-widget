<?php
/**
 * Asset registration and localization.
 *
 * The public widget.js is intentionally also loaded on the admin builder page
 * so the live preview renders through the exact same code path as the real
 * embed. The admin builder itself uses WordPress-core APIs (including the
 * color picker), but no front-end framework.
 *
 * @package SteigerwaldNewsWidget
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SNW_Assets {

    const ADMIN_JS_HANDLE  = 'snw-admin';
    const ADMIN_CSS_HANDLE = 'snw-admin-css';
    const WIDGET_JS_HANDLE = 'snw-widget';

    /**
     * Build the localization object shared with admin.js.
     *
     * @param string $ajax_url
     * @param string $nonce
     * @return array
     */
    public static function admin_l10n( $ajax_url, $nonce ) {
        return array(
            'ajaxUrl'      => $ajax_url,
            'nonce'       => $nonce,
            'nonceField'  => SNW_Settings::NONCE_FIELD,
            'apiBase'     => rest_url( 'wp/v2' ),
            'adminAjax'   => admin_url( 'admin-ajax.php' ),
            'widgetJsUrl' => SNW_Embed_Generator::script_url(),
            'sourceName'  => get_bloginfo( 'name' ),
            'sourceUrl'   => home_url( '/' ),
            'isAdmin'     => true,
            'restRequestUrl' => esc_url_raw( rest_url( 'snw/v1/request' ) ),
            'defaultConfig' => SNW_Helpers::default_config(),
            'presets'     => SNW_Presets::get_all(),
            'i18n'        => array(
                'saveOk'        => __( 'Widget gespeichert.', 'steigerwald-news-widget' ),
                'saveError'     => __( 'Speichern fehlgeschlagen.', 'steigerwald-news-widget' ),
                'confirmDelete' => __( 'Dieses Widget wirklich löschen?', 'steigerwald-news-widget' ),
                'copied'        => __( 'Code kopiert.', 'steigerwald-news-widget' ),
                'copyError'     => __( 'Kopieren nicht möglich – bitte manuell auswählen.', 'steigerwald-news-widget' ),
                'loading'       => __( 'Nachrichten werden geladen …', 'steigerwald-news-widget' ),
                'empty'         => __( 'Aktuell sind keine passenden Beiträge vorhanden.', 'steigerwald-news-widget' ),
                'error'         => __( 'Die Nachrichten konnten gerade nicht geladen werden.', 'steigerwald-news-widget' ),
                'untitled'      => __( 'Beitrag', 'steigerwald-news-widget' ),
                'readmore'      => __( 'Artikel lesen', 'steigerwald-news-widget' ),
                'searchPosts'   => __( 'Artikel suchen …', 'steigerwald-news-widget' ),
                'searchTags'    => __( 'Schlagwort suchen …', 'steigerwald-news-widget' ),
                'noTags'        => __( 'Keine Schlagwörter gefunden.', 'steigerwald-news-widget' ),
            ),
        );
    }

    /**
     * Enqueue assets for the builder page only.
     *
     * @param string $hook Page hook suffix for our admin menu entry.
     * @return void
     */
    public static function enqueue_builder( $hook ) {
        if ( empty( $hook ) || false === strpos( $hook, 'steigerwald-news-widget' ) ) {
            return;
        }

        // Public widget renderer (for the live preview) + its base styles.
        wp_enqueue_script(
            self::WIDGET_JS_HANDLE,
            SNW_Embed_Generator::script_url(),
            array(),
            SNW_VERSION,
            true
        );

        wp_enqueue_style(
            'snw-widget-css',
            SNW_URL . 'public/css/widget.css',
            array(),
            SNW_VERSION
        );

        // Admin builder styles + script.
        wp_enqueue_style(
            self::ADMIN_CSS_HANDLE,
            SNW_URL . 'admin/css/admin.css',
            array( 'wp-color-picker' ),
            SNW_VERSION
        );

        wp_enqueue_script(
            self::ADMIN_JS_HANDLE,
            SNW_URL . 'admin/js/admin.js',
            array( 'jquery', 'wp-color-picker', self::WIDGET_JS_HANDLE ),
            SNW_VERSION,
            true
        );

        wp_localize_script(
            self::ADMIN_JS_HANDLE,
            'SNW_Admin',
            self::admin_l10n( admin_url( 'admin-ajax.php' ), wp_create_nonce( SNW_Settings::NONCE_ACTION ) )
        );
    }
}
