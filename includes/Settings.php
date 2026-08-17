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

    const NONCE_ACTION = 'snw_admin';
    const NONCE_FIELD  = 'snw_nonce';

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
}
