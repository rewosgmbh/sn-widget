<?php
/**
 * Main plugin controller. Wires the individual components together.
 *
 * The plugin performs no work at load time that could affect the host site:
 * everything is hooked. There is intentionally no public-facing PHP output on
 * the source site beyond serving the static widget asset files.
 *
 * @package SteigerwaldNewsWidget
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SNW_Plugin {

    /**
     * Boot the plugin.
     *
     * @return void
     */
    public static function init() {
        add_action( 'admin_menu', array( 'SNW_Admin', 'admin_menu' ) );
        add_action( 'init', array( __CLASS__, 'load_textdomain' ) );

        SNW_Settings::register();
        SNW_REST::init();
        SNW_Shortcode::init();
        if ( class_exists( 'SNW_Telemetry' ) ) {
            SNW_Telemetry::init();
        }

        add_filter( 'plugin_action_links_' . plugin_basename( SNW_FILE ), array( __CLASS__, 'action_links' ) );

        add_action( 'init', array( __CLASS__, 'maybe_upgrade' ) );
    }

    /**
     * Bring an existing installation up to date when the plugin is updated in
     * place. WordPress only runs the activation hook on a fresh activation, so
     * structures introduced by a newer version (the public builder page and the
     * telemetry tables) must be created here instead.
     *
     * @return void
     */
    public static function maybe_upgrade() {
        $installed = get_option( 'snw_version', '' );
        if ( $installed === SNW_VERSION ) {
            return;
        }

        if ( class_exists( 'SNW_Requests' ) ) {
            SNW_Requests::ensure_builder_page();
        }
        if ( class_exists( 'SNW_Telemetry' ) ) {
            SNW_Telemetry::activate();
        }

        update_option( 'snw_version', SNW_VERSION );
    }

    /**
     * Plugin activation: create the public widget-builder page and install
     * the telemetry subsystem (tables, rewrite alias, cron).
     *
     * @return void
     */
    public static function activate() {
        if ( class_exists( 'SNW_Requests' ) ) {
            SNW_Requests::ensure_builder_page();
        }
        if ( class_exists( 'SNW_Telemetry' ) ) {
            SNW_Telemetry::activate();
        }
        update_option( 'snw_version', SNW_VERSION );
    }

    /**
     * Plugin deactivation: stop the telemetry cron.
     *
     * @return void
     */
    public static function deactivate() {
        if ( class_exists( 'SNW_Telemetry' ) ) {
            SNW_Telemetry::deactivate();
        }
    }

    /**
     * Load the translation files.
     *
     * @return void
     */
    public static function load_textdomain() {
        load_plugin_textdomain(
            'steigerwald-news-widget',
            false,
            dirname( plugin_basename( SNW_FILE ) ) . '/languages'
        );
    }

    /**
     * Add a "Einstellungen" link to the plugin row on the Plugins page.
     *
     * @param array $links
     * @return array
     */
    public static function action_links( $links ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return $links;
        }
        $url = admin_url( 'admin.php?page=steigerwald-news-widget-create' );
        $links[] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Widget erstellen', 'steigerwald-news-widget' ) . '</a>';
        return $links;
    }
}
