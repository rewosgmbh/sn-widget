<?php
/**
 * Public-facing shortcodes.
 *
 *  - [steigerwald_news_widget id="SNW-XXXX"]
 *      Renders a saved widget inline on any WordPress page. The page URL is the
 *      "custom link" the widget lives at. Configuration is pulled from the saved
 *      preset and embedded as a scoped `data-config`.
 *
 *  - [steigerwald_news_widget_builder]
 *      Renders the public widget-intake form (name / e-mail / domain + a small
 *      set of customization controls). Submissions are sent to the custom REST
 *      endpoint and become partner requests in the admin.
 *
 * @package SteigerwaldNewsWidget
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SNW_Shortcode {

    /**
     * Register shortcodes.
     *
     * @return void
     */
    public static function init() {
        add_shortcode( 'steigerwald_news_widget', array( __CLASS__, 'render_widget' ) );
        add_shortcode( 'steigerwald_news_widget_builder', array( __CLASS__, 'render_builder' ) );
    }

    /**
     * Render a saved widget by id.
     *
     * @param array $atts
     * @return string
     */
    public static function render_widget( $atts ) {
        $atts = shortcode_atts( array( 'id' => '' ), $atts, 'steigerwald_news_widget' );
        $id   = SNW_Helpers::sanitize_widget_id( $atts['id'] );
        if ( '' === $id ) {
            return '';
        }
        $preset = SNW_Presets::get( $id );
        if ( ! $preset || empty( $preset['config'] ) ) {
            return '';
        }

        wp_enqueue_script(
            SNW_Assets::WIDGET_JS_HANDLE,
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

        $config = $preset['config'];
        // The internal shortcode always renders on the source site, so any
        // domain lock stored for partner use must not block it here.
        unset( $config['allowed_domain'] );
        $encoded = SNW_Embed_Generator::encode_config( $config );
        return '<div class="steigerwald-news-widget" data-config="' . esc_attr( $encoded ) . '"></div>';
    }

    /**
     * Render the public intake form.
     *
     * @return string
     */
    public static function render_builder() {
        if ( ! self::$assets_enqueued ) {
            // Public widget renderer (drives the live preview) + base styles.
            wp_enqueue_script(
                SNW_Assets::WIDGET_JS_HANDLE,
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

            // Shared builder styles + the same builder script as the admin so
            // the public form has feature parity (controls + live preview).
            wp_enqueue_style(
                'snw-admin-css',
                SNW_URL . 'admin/css/admin.css',
                array(),
                SNW_VERSION
            );
            wp_enqueue_script(
                SNW_Assets::ADMIN_JS_HANDLE,
                SNW_URL . 'admin/js/admin.js',
                array( 'jquery', SNW_Assets::WIDGET_JS_HANDLE ),
                SNW_VERSION,
                true
            );

            $l10n = array(
                'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
                'nonce'          => '',
                'nonceField'     => SNW_Settings::NONCE_FIELD,
                'apiBase'        => rest_url( 'wp/v2' ),
                'adminAjax'      => admin_url( 'admin-ajax.php' ),
                'widgetJsUrl'    => SNW_Embed_Generator::script_url(),
                'sourceName'     => get_bloginfo( 'name' ),
                'sourceUrl'      => home_url( '/' ),
                'isAdmin'        => false,
                'restRequestUrl' => esc_url_raw( rest_url( 'snw/v1/request' ) ),
                'defaultConfig'  => SNW_Helpers::default_config(),
                'i18n'           => array(
                    'saveOk'        => __( 'Widget gespeichert.', 'steigerwald-news-widget' ),
                    'saveError'     => __( 'Speichern fehlgeschlagen.', 'steigerwald-news-widget' ),
                    'confirmDelete' => __( 'Dieses Widget wirklich löschen?', 'steigerwald-news-widget' ),
                    'copied'        => __( 'Code kopiert.', 'steigerwald-news-widget' ),
                    'copyError'     => __( 'Kopieren nicht möglich – bitte manuell auswählen.', 'steigerwald-news-widget' ),
                    'loading'       => __( 'Nachrichten werden geladen …', 'steigerwald-news-widget' ),
                    'empty'         => __( 'Aktuell sind keine passenden Beiträge vorhanden.', 'steigerwald-news-widget' ),
                    'error'         => __( 'Ubermittlung fehlgeschlagen. Bitte versuche es erneut.', 'steigerwald-news-widget' ),
                    'untitled'      => __( 'Beitrag', 'steigerwald-news-widget' ),
                    'readmore'      => __( 'Artikel lesen', 'steigerwald-news-widget' ),
                    'searchPosts'   => __( 'Artikel suchen …', 'steigerwald-news-widget' ),
                    'searchTags'    => __( 'Schlagwort suchen …', 'steigerwald-news-widget' ),
                    'noTags'        => __( 'Keine Schlagworter gefunden.', 'steigerwald-news-widget' ),
                    'submitting'    => __( 'Wird gesendet …', 'steigerwald-news-widget' ),
                    'ok'            => __( 'Danke! Deine Anfrage wurde ubermittelt. Wir melden uns mit dem Einbettungscode.', 'steigerwald-news-widget' ),
                    'invalid'       => __( 'Bitte E-Mail und Domain korrekt ausfullen.', 'steigerwald-news-widget' ),
                    'rate'          => __( 'Zu viele Anfragen. Bitte versuche es spater erneut.', 'steigerwald-news-widget' ),
                ),
            );
            wp_localize_script( SNW_Assets::ADMIN_JS_HANDLE, 'SNW_Admin', $l10n );
            self::$assets_enqueued = true;
        }

        ob_start();
        SNW_Builder::render_form( array( 'context' => 'public' ) );
        return ob_get_clean();
    }

    /**
     * @var bool
     */
    private static $assets_enqueued = false;
}
