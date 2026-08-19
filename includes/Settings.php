<?php
/**
 * AJAX handlers for preset management.
 *
 * All handlers:
 *  - require the `manage_options` capability
 *  - verify the admin-ajax nonce
 *  - sanitize every input through SNW_Helpers / SNW_Presets
 *  - never emit raw SQL or `eval`
 *
 * @package SteigerwaldNewsWidget
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SNW_Settings {

    const NONCE_ACTION    = 'snw_admin';
    const NONCE_FIELD     = 'snw_nonce';
    const BRANDING_OPTION = 'snw_branding';

    /**
     * Register AJAX actions (admin only).
     *
     * @return void
     */
    public static function register() {
        add_action( 'wp_ajax_snw_save_preset', array( __CLASS__, 'ajax_save_preset' ) );
        add_action( 'wp_ajax_snw_delete_preset', array( __CLASS__, 'ajax_delete_preset' ) );
        add_action( 'wp_ajax_snw_duplicate_preset', array( __CLASS__, 'ajax_duplicate_preset' ) );
        add_action( 'wp_ajax_snw_get_presets', array( __CLASS__, 'ajax_get_presets' ) );

        add_action( 'wp_ajax_snw_create_page', array( __CLASS__, 'ajax_create_page' ) );
        add_action( 'wp_ajax_snw_list_requests', array( __CLASS__, 'ajax_list_requests' ) );
        add_action( 'wp_ajax_snw_accept_request', array( __CLASS__, 'ajax_accept_request' ) );
        add_action( 'wp_ajax_snw_reject_request', array( __CLASS__, 'ajax_reject_request' ) );
        add_action( 'wp_ajax_snw_list_partners', array( __CLASS__, 'ajax_list_partners' ) );
        add_action( 'wp_ajax_snw_save_branding', array( __CLASS__, 'ajax_save_branding' ) );
    }

    /**
     * Default branding values (used when no option is stored yet).
     *
     * @return array
     */
    public static function default_branding() {
        return array(
            'image'      => '',
            'image_size' => 32,
            'text_size'  => 14,
            'text'       => __( 'Nachrichten von', 'steigerwald-news-widget' ),
            'name'       => '',
            'link'       => '',
        );
    }

    /**
     * Return the branding setting merged with defaults.
     *
     * @return array
     */
    public static function get_branding() {
        $stored = get_option( self::BRANDING_OPTION, array() );
        if ( ! is_array( $stored ) ) {
            $stored = array();
        }
        return wp_parse_args( $stored, self::default_branding() );
    }

    /**
     * Persist the branding setting (sanitized).
     *
     * @param array $input Raw input.
     * @return array Clean values actually stored.
     */
    public static function save_branding( $input ) {
        $input = is_array( $input ) ? $input : array();
        $clean = self::default_branding();

        if ( isset( $input['image'] ) && is_string( $input['image'] ) ) {
            $clean['image'] = esc_url_raw( trim( $input['image'] ) );
        }
        if ( isset( $input['image_size'] ) ) {
            $clean['image_size'] = SNW_Helpers::clamp_int( $input['image_size'], 8, 256, 32 );
        }
        if ( isset( $input['text_size'] ) ) {
            $clean['text_size'] = SNW_Helpers::clamp_int( $input['text_size'], 8, 64, 14 );
        }
        if ( isset( $input['text'] ) && is_string( $input['text'] ) ) {
            $clean['text'] = sanitize_text_field( $input['text'] );
        }
        if ( isset( $input['name'] ) && is_string( $input['name'] ) ) {
            $clean['name'] = sanitize_text_field( $input['name'] );
        }
        if ( isset( $input['link'] ) && is_string( $input['link'] ) ) {
            $clean['link'] = esc_url_raw( trim( $input['link'] ) );
        }

        update_option( self::BRANDING_OPTION, $clean, false );
        return $clean;
    }

    /**
     * AJAX: save the global branding setting.
     *
     * @return void
     */
    public static function ajax_save_branding() {
        if ( ! self::authorized() ) {
            self::respond( false, array( 'message' => __( 'Keine Berechtigung.', 'steigerwald-news-widget' ) ), 403 );
        }
        $saved = self::save_branding( $_POST );
        self::respond( true, $saved );
    }

    /**
     * Verify the current request is allowed.
     *
     * @return bool
     */
    private static function authorized() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return false;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified here.
        $nonce = isset( $_POST[ self::NONCE_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ) : '';
        return (bool) wp_verify_nonce( $nonce, self::NONCE_ACTION );
    }

    /**
     * Send a JSON response and stop execution.
     *
     * @param bool  $success
     * @param mixed $data
     * @param int   $code
     * @return void
     */
    private static function respond( $success, $data = null, $code = 200 ) {
        status_header( $code );
        header( 'Content-Type: application/json; charset=utf-8' );
        echo wp_json_encode(
            array(
                'success' => (bool) $success,
                'data'    => $data,
            )
        );
        exit;
    }

    /**
     * Save (insert or update) a preset. Expects:
     *  - snw_nonce
     *  - name (string)
     *  - config (JSON string or array)
     *  - id (optional, existing preset id)
     *
     * @return void
     */
    public static function ajax_save_preset() {
        if ( ! self::authorized() ) {
            self::respond( false, array( 'message' => __( 'Keine Berechtigung.', 'steigerwald-news-widget' ) ), 403 );
        }

        $name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';

        // Accept config either as a JSON string or an already-decoded array.
        $config = null;
        if ( isset( $_POST['config'] ) ) {
            $raw = wp_unslash( $_POST['config'] );
            if ( is_string( $raw ) ) {
                $decoded = json_decode( $raw, true );
                $config  = is_array( $decoded ) ? $decoded : null;
            } elseif ( is_array( $raw ) ) {
                $config = $raw;
            }
        }

        if ( null === $config ) {
            self::respond( false, array( 'message' => __( 'Ungültige Konfiguration.', 'steigerwald-news-widget' ) ), 400 );
        }

        $id = isset( $_POST['id'] ) ? SNW_Helpers::sanitize_widget_id( wp_unslash( $_POST['id'] ) ) : '';

        $saved = SNW_Presets::save( $name, $config, $id );
        if ( ! $saved ) {
            self::respond( false, array( 'message' => __( 'Speichern fehlgeschlagen.', 'steigerwald-news-widget' ) ), 500 );
        }

        self::respond( true, $saved );
    }

    /**
     * Delete a preset by id.
     *
     * @return void
     */
    public static function ajax_delete_preset() {
        if ( ! self::authorized() ) {
            self::respond( false, array( 'message' => __( 'Keine Berechtigung.', 'steigerwald-news-widget' ) ), 403 );
        }

        $id = isset( $_POST['id'] ) ? SNW_Helpers::sanitize_widget_id( wp_unslash( $_POST['id'] ) ) : '';
        if ( ! $id ) {
            self::respond( false, array( 'message' => __( 'Ungültige Widget-ID.', 'steigerwald-news-widget' ) ), 400 );
        }

        $ok = SNW_Presets::delete( $id );
        self::respond( $ok, array( 'id' => $id ) );
    }

    /**
     * Duplicate a preset.
     *
     * @return void
     */
    public static function ajax_duplicate_preset() {
        if ( ! self::authorized() ) {
            self::respond( false, array( 'message' => __( 'Keine Berechtigung.', 'steigerwald-news-widget' ) ), 403 );
        }

        $id = isset( $_POST['id'] ) ? SNW_Helpers::sanitize_widget_id( wp_unslash( $_POST['id'] ) ) : '';
        if ( ! $id ) {
            self::respond( false, array( 'message' => __( 'Ungültige Widget-ID.', 'steigerwald-news-widget' ) ), 400 );
        }

        $copy = SNW_Presets::duplicate( $id );
        if ( ! $copy ) {
            self::respond( false, array( 'message' => __( 'Duplizieren fehlgeschlagen.', 'steigerwald-news-widget' ) ), 500 );
        }

        self::respond( true, $copy );
    }

    /**
     * Return the full preset list (used to refresh the admin table).
     *
     * @return void
     */
    public static function ajax_get_presets() {
        if ( ! self::authorized() ) {
            self::respond( false, array( 'message' => __( 'Keine Berechtigung.', 'steigerwald-news-widget' ) ), 403 );
        }
        self::respond( true, SNW_Presets::get_all() );
    }

    /**
     * (Re)create the public builder page at the configured (or given) slug.
     *
     * @return void
     */
    public static function ajax_create_page() {
        if ( ! self::authorized() ) {
            self::respond( false, array( 'message' => __( 'Keine Berechtigung.', 'steigerwald-news-widget' ) ), 403 );
        }
        $slug = isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : '';
        $page = SNW_Requests::ensure_builder_page( $slug );
        if ( ! $page ) {
            self::respond( false, array( 'message' => __( 'Seite konnte nicht erstellt werden.', 'steigerwald-news-widget' ) ), 500 );
        }
        self::respond( true, $page );
    }

    /**
     * Return all partner requests.
     *
     * @return void
     */
    public static function ajax_list_requests() {
        if ( ! self::authorized() ) {
            self::respond( false, array( 'message' => __( 'Keine Berechtigung.', 'steigerwald-news-widget' ) ), 403 );
        }
        self::respond( true, SNW_Requests::get_all() );
    }

    /**
     * Accept a request: create a domain-locked preset and return the embed
     * codes plus a ready-to-send mailto link.
     *
     * @return void
     */
    public static function ajax_accept_request() {
        if ( ! self::authorized() ) {
            self::respond( false, array( 'message' => __( 'Keine Berechtigung.', 'steigerwald-news-widget' ) ), 403 );
        }

        $id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
        if ( ! $id ) {
            self::respond( false, array( 'message' => __( 'Ungültige Anfrage.', 'steigerwald-news-widget' ) ), 400 );
        }
        $request = SNW_Requests::get( $id );
        if ( ! $request ) {
            self::respond( false, array( 'message' => __( 'Anfrage nicht gefunden.', 'steigerwald-news-widget' ) ), 404 );
        }

        $config = isset( $request['config'] ) && is_array( $request['config'] )
            ? $request['config']
            : array();
        $config['api']         = rest_url( 'wp/v2' );
        $config['source_name'] = get_bloginfo( 'name' );
        $config['source_url']  = home_url( '/' );

        $name   = ! empty( $request['name'] ) ? $request['name'] : $request['email'];

        // One code per partner (e-mail). Reuse the existing code if this e-mail
        // already has an accepted widget instead of minting a new one.
        $existing = SNW_Presets::find_by_email( $request['email'] );
        if ( $existing ) {
            $preset = $existing;
            SNW_Presets::save_meta(
                $preset['id'],
                array(
                    'allowed_domain' => $request['domain'],
                    'email'          => $request['email'],
                    'source'         => 'request',
                )
            );
        } else {
            $preset = SNW_Presets::save( $name, $config );
            if ( ! $preset ) {
                self::respond( false, array( 'message' => __( 'Speichern fehlgeschlagen.', 'steigerwald-news-widget' ) ), 500 );
            }
            SNW_Presets::save_meta(
                $preset['id'],
                array(
                    'allowed_domain' => $request['domain'],
                    'email'          => $request['email'],
                    'source'         => 'request',
                )
            );
        }

        SNW_Requests::update(
            $id,
            array(
                'status'    => 'accepted',
                'preset_id' => $preset['id'],
            )
        );

        $shortcode = '[steigerwald_news_widget id="' . $preset['id'] . '"]';
        $html      = '<div class="steigerwald-news-widget" data-code="' . $preset['id'] . '"></div>' . "\n" .
            '<script src="' . esc_url( SNW_Embed_Generator::script_url() ) . '" async></script>';

        $site_name       = get_bloginfo( 'name' );
        $site_url        = home_url( '/' );
        $partner_domain  = isset( $request['domain'] ) ? $request['domain'] : '';
        $subject         = sprintf( __( 'Dein %s Widget', 'steigerwald-news-widget' ), $site_name );
        $body            = __( "Hallo,\n\nhier ist der Einbettungscode für dein News-Widget:\n\n", 'steigerwald-news-widget' ) .
            sprintf( __( "HTML-Snippet für Ihre Website %s:\n", 'steigerwald-news-widget' ), '"' . $partner_domain . '"' ) .
            $html . "\n\n" .
            sprintf( __( "Viele Grüße\n%s — %s", 'steigerwald-news-widget' ), $site_name, $site_url );

        $mailto = 'mailto:' . rawurlencode( $request['email'] ) .
            '?subject=' . rawurlencode( $subject ) .
            '&body=' . rawurlencode( $body );

        self::respond(
            true,
            array(
                'shortcode' => $shortcode,
                'html'      => $html,
                'mailto'    => $mailto,
                'preset_id' => $preset['id'],
                'email'     => $request['email'],
            )
        );
    }

    /**
     * List accepted partners (presets that belong to a partner e-mail), enriched
     * with last-seen telemetry where available.
     *
     * @return void
     */
    public static function ajax_list_partners() {
        if ( ! self::authorized() ) {
            self::respond( false, array( 'message' => __( 'Keine Berechtigung.', 'steigerwald-news-widget' ) ), 403 );
        }

        $last_seen_map = ( class_exists( 'SNW_Telemetry' ) )
            ? SNW_Telemetry::widget_last_seen_map()
            : array();

        $partners = array();
        foreach ( SNW_Presets::get_all() as $preset ) {
            if ( empty( $preset['email'] ) ) {
                continue;
            }
            $ls = isset( $last_seen_map[ $preset['id'] ] ) ? $last_seen_map[ $preset['id'] ] : '';
            $partners[] = array(
                'id'        => $preset['id'],
                'name'      => isset( $preset['name'] ) ? $preset['name'] : '',
                'email'     => $preset['email'],
                'domain'    => isset( $preset['allowed_domain'] ) ? $preset['allowed_domain'] : '',
                'created'   => isset( $preset['created'] ) ? $preset['created'] : '',
                'last_seen' => $ls,
                'status'    => ( class_exists( 'SNW_Telemetry' ) && method_exists( 'SNW_Telemetry', 'status_from_last_seen' ) )
                    ? SNW_Telemetry::status_from_last_seen( $ls )
                    : ( $ls ? 'active' : 'unknown' ),
            );
        }

        // Newest first.
        usort( $partners, function ( $a, $b ) {
            return strtotime( $b['created'] ) - strtotime( $a['created'] );
        } );

        self::respond( true, $partners );
    }

    /**
     * Reject (delete) a request.
     *
     * @return void
     */
    public static function ajax_reject_request() {
        if ( ! self::authorized() ) {
            self::respond( false, array( 'message' => __( 'Keine Berechtigung.', 'steigerwald-news-widget' ) ), 403 );
        }
        $id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
        if ( ! $id ) {
            self::respond( false, array( 'message' => __( 'Ungültige Anfrage.', 'steigerwald-news-widget' ) ), 400 );
        }
        $ok = SNW_Requests::delete( $id );
        self::respond( $ok, array( 'id' => $id ) );
    }
}
