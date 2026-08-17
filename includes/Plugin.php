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

        add_filter( 'plugin_action_links_' . plugin_basename( SNW_FILE ), array( __CLASS__, 'action_links' ) );
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
        $url = admin_url( 'options-general.php?page=steigerwald-news-widget' );
        $links[] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Einstellungen', 'steigerwald-news-widget' ) . '</a>';
        return $links;
    }
}
