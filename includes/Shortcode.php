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

        $encoded = SNW_Embed_Generator::encode_config( $preset['config'] );
        return '<div class="steigerwald-news-widget" data-config="' . esc_attr( $encoded ) . '"></div>';
    }

    /**
     * Render the public intake form.
     *
     * @return string
     */
    public static function render_builder() {
        if ( ! self::$assets_enqueued ) {
            wp_enqueue_style(
                'snw-frontend-css',
                SNW_URL . 'public/css/frontend.css',
                array(),
                SNW_VERSION
            );
            wp_enqueue_script(
                'snw-frontend',
                SNW_URL . 'public/js/frontend.js',
                array(),
                SNW_VERSION,
                true
            );

            $l10n = array(
                'restUrl'       => esc_url_raw( rest_url( 'snw/v1/request' ) ),
                'categoriesUrl' => esc_url_raw( rest_url( 'wp/v2/categories' ) ),
                'i18n'          => array(
                    'submit'       => __( 'Widget anfragen', 'steigerwald-news-widget' ),
                    'submitting'   => __( 'Wird gesendet …', 'steigerwald-news-widget' ),
                    'ok'           => __( 'Danke! Deine Anfrage wurde übermittelt. Wir melden uns mit dem Einbettungscode.', 'steigerwald-news-widget' ),
                    'error'        => __( 'Übermittlung fehlgeschlagen. Bitte versuche es erneut.', 'steigerwald-news-widget' ),
                    'invalid'      => __( 'Bitte E-Mail und Domain korrekt ausfüllen.', 'steigerwald-news-widget' ),
                    'rate'         => __( 'Zu viele Anfragen. Bitte versuche es später erneut.', 'steigerwald-news-widget' ),
                    'email'        => __( 'E-Mail', 'steigerwald-news-widget' ),
                    'domain'       => __( 'Domain (wo das Widget eingebettet wird)', 'steigerwald-news-widget' ),
                    'name'         => __( 'Name (optional)', 'steigerwald-news-widget' ),
                    'title'        => __( 'Überschrift (optional)', 'steigerwald-news-widget' ),
                    'layout'       => __( 'Layout', 'steigerwald-news-widget' ),
                    'accent'       => __( 'Akzentfarbe', 'steigerwald-news-widget' ),
                    'count'        => __( 'Anzahl Beiträge', 'steigerwald-news-widget' ),
                    'mode'         => __( 'Inhaltsmodus', 'steigerwald-news-widget' ),
                    'category'     => __( 'Kategorie', 'steigerwald-news-widget' ),
                ),
            );
            wp_localize_script( 'snw-frontend', 'SNW_Public', $l10n );
            self::$assets_enqueued = true;
        }

        ob_start();
        ?>
        <form id="snw-public-form" class="snw-public-form" novalidate>
            <p class="snw-pf-field">
                <label for="snw-pf-name"><?php echo esc_html__( 'Name (optional)', 'steigerwald-news-widget' ); ?></label>
                <input type="text" id="snw-pf-name" name="name" maxlength="100" autocomplete="name">
            </p>
            <p class="snw-pf-field snw-pf-required">
                <label for="snw-pf-email"><?php echo esc_html__( 'E-Mail', 'steigerwald-news-widget' ); ?> <span aria-hidden="true">*</span></label>
                <input type="email" id="snw-pf-email" name="email" required autocomplete="email">
            </p>
            <p class="snw-pf-field snw-pf-required">
                <label for="snw-pf-domain"><?php echo esc_html__( 'Domain (wo das Widget eingebettet wird)', 'steigerwald-news-widget' ); ?> <span aria-hidden="true">*</span></label>
                <input type="text" id="snw-pf-domain" name="domain" required placeholder="example.com" autocomplete="off">
            </p>

            <p class="snw-pf-field">
                <label for="snw-pf-mode"><?php echo esc_html__( 'Inhaltsmodus', 'steigerwald-news-widget' ); ?></label>
                <select id="snw-pf-mode" name="mode">
                    <option value="latest"><?php echo esc_html__( 'Neueste Beiträge', 'steigerwald-news-widget' ); ?></option>
                    <option value="category"><?php echo esc_html__( 'Kategorie', 'steigerwald-news-widget' ); ?></option>
                </select>
            </p>

            <p class="snw-pf-field" id="snw-pf-category-wrap" hidden>
                <label for="snw-pf-category"><?php echo esc_html__( 'Kategorie', 'steigerwald-news-widget' ); ?></label>
                <select id="snw-pf-category" name="category"></select>
            </p>

            <p class="snw-pf-field">
                <label for="snw-pf-layout"><?php echo esc_html__( 'Layout', 'steigerwald-news-widget' ); ?></label>
                <select id="snw-pf-layout" name="layout">
                    <option value="list"><?php echo esc_html__( 'News Liste', 'steigerwald-news-widget' ); ?></option>
                    <option value="cards"><?php echo esc_html__( 'Karten', 'steigerwald-news-widget' ); ?></option>
                    <option value="compact"><?php echo esc_html__( 'Kompakt', 'steigerwald-news-widget' ); ?></option>
                    <option value="headlines"><?php echo esc_html__( 'Nur Überschriften', 'steigerwald-news-widget' ); ?></option>
                </select>
            </p>

            <p class="snw-pf-field snw-pf-inline">
                <label for="snw-pf-accent"><?php echo esc_html__( 'Akzentfarbe', 'steigerwald-news-widget' ); ?></label>
                <input type="color" id="snw-pf-accent" name="accent" value="#c59a20">
            </p>

            <p class="snw-pf-field snw-pf-inline">
                <label for="snw-pf-limit"><?php echo esc_html__( 'Anzahl Beiträge', 'steigerwald-news-widget' ); ?></label>
                <input type="number" id="snw-pf-limit" name="limit" min="1" max="20" value="5">
            </p>

            <p class="snw-pf-field">
                <label for="snw-pf-title"><?php echo esc_html__( 'Überschrift (optional)', 'steigerwald-news-widget' ); ?></label>
                <input type="text" id="snw-pf-title" name="title" maxlength="160">
            </p>

            <fieldset class="snw-pf-fieldset">
                <legend><?php echo esc_html__( 'Sichtbare Elemente', 'steigerwald-news-widget' ); ?></legend>
                <label><input type="checkbox" id="snw-pf-show-image" name="show_image" checked> <?php echo esc_html__( 'Beitragsbild', 'steigerwald-news-widget' ); ?></label>
                <label><input type="checkbox" id="snw-pf-show-date" name="show_date" checked> <?php echo esc_html__( 'Datum', 'steigerwald-news-widget' ); ?></label>
                <label><input type="checkbox" id="snw-pf-show-excerpt" name="show_excerpt" checked> <?php echo esc_html__( 'Teaser', 'steigerwald-news-widget' ); ?></label>
            </fieldset>

            <p class="snw-pf-actions">
                <button type="submit" id="snw-pf-submit" class="button button-primary"><?php echo esc_html__( 'Widget anfragen', 'steigerwald-news-widget' ); ?></button>
            </p>
            <p class="snw-pf-status" id="snw-pf-status" role="status" aria-live="polite"></p>
        </form>
        <?php
        return ob_get_clean();
    }

    /**
     * @var bool
     */
    private static $assets_enqueued = false;
}
