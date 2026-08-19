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
        add_action( 'wp', array( __CLASS__, 'maybe_builder_body_class' ) );
    }

    /**
     * Add a body class on pages that contain the public builder shortcode so the
     * full-bleed layout can clip any scrollbar-induced horizontal overflow.
     * Registered on `wp` (before the header) so it applies in time.
     *
     * @return void
     */
    public static function maybe_builder_body_class() {
        if ( ! is_singular() ) {
            return;
        }
        $post = get_post();
        if ( $post && has_shortcode( $post->post_content, 'steigerwald_news_widget_builder' ) ) {
            add_filter(
                'body_class',
                function ( $classes ) {
                    $classes[] = 'snw-builder-page';
                    return $classes;
                }
            );
        }
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
        $config = SNW_Helpers::apply_branding( $config );
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
            // The colour fields use wp-color-picker, which is only registered
            // in the admin by default (and may be deregistered on the
            // front-end). Register the core handles explicitly when missing so
            // the visual picker works here too.
            self::ensure_color_picker();
            $has_color_picker = wp_script_is( 'wp-color-picker', 'registered' );

            wp_enqueue_style(
                'snw-admin-css',
                SNW_URL . 'admin/css/admin.css',
                $has_color_picker ? array( 'wp-color-picker' ) : array(),
                SNW_VERSION
            );

            $admin_deps = array( 'jquery', SNW_Assets::WIDGET_JS_HANDLE );
            if ( $has_color_picker ) {
                $admin_deps[] = 'wp-color-picker';
            }

            wp_enqueue_script(
                SNW_Assets::ADMIN_JS_HANDLE,
                SNW_URL . 'admin/js/admin.js',
                $admin_deps,
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
     * Ensure the core wp-color-picker (and its iris / wp-i18n dependencies)
     * are registered on the front-end. They are only registered in the admin
     * by default and may be deregistered there too, so register the core
     * handles explicitly when missing. Safe to call more than once.
     *
     * @return void
     */
    private static function ensure_color_picker() {
        if ( wp_script_is( 'wp-color-picker', 'registered' )
            && wp_style_is( 'wp-color-picker', 'registered' ) ) {
            return;
        }

        $suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

        if ( ! wp_script_is( 'iris', 'registered' ) ) {
            wp_register_script(
                'iris',
                admin_url( "js/iris{$suffix}.js" ),
                array( 'jquery-ui-draggable', 'jquery-ui-slider', 'jquery-touch-punch' ),
                '1.1.1',
                true
            );
        }

        if ( ! wp_script_is( 'wp-color-picker', 'registered' ) ) {
            wp_register_script(
                'wp-color-picker',
                admin_url( "js/color-picker{$suffix}.js" ),
                array( 'iris', 'wp-i18n' ),
                false,
                true
            );
            wp_localize_script(
                'wp-color-picker',
                'wpColorPickerL10n',
                array(
                    'clear'         => __( 'Klar', 'steigerwald-news-widget' ),
                    'defaultString' => __( 'Standard', 'steigerwald-news-widget' ),
                    'pick'          => __( 'Wählen', 'steigerwald-news-widget' ),
                    'current'       => __( 'Aktuell', 'steigerwald-news-widget' ),
                )
            );
        }

        if ( ! wp_style_is( 'wp-color-picker', 'registered' ) ) {
            wp_register_style(
                'wp-color-picker',
                admin_url( "css/color-picker{$suffix}.css" ),
                array(),
                false
            );
        }
    }

    /**
     * @var bool
     */
    private static $assets_enqueued = false;
}
