<?php
/**
 * Plugin Name: Steigerwald-News Widget
 * Plugin URI:  https://steigerwald-news.example/
 * Description: Erstellt extern einbettbare Nachrichten-Widgets auf Basis der vorhandenen WordPress-REST-API. Kein eigener REST-Endpunkt, keine eigene Datenbanktabelle.
 * Version:     1.2.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Tested up to:      6.7
 * Author:      Steigerwald-News
 * Author URI:  https://steigerwald-news.example/
 * License:     GPL-2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: steigerwald-news-widget
 * Domain Path: /languages
 *
 * @package SteigerwaldNewsWidget
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SNW_VERSION', '1.3.0' );
define( 'SNW_FILE', __FILE__ );
define( 'SNW_PATH', plugin_dir_path( __FILE__ ) );
define( 'SNW_URL', plugin_dir_url( __FILE__ ) );

/**
 * Load the autoloader-free class files.
 * Files are intentionally kept small and single-purpose.
 */
require_once SNW_PATH . 'includes/Helpers.php';
require_once SNW_PATH . 'includes/Presets.php';
require_once SNW_PATH . 'includes/EmbedGenerator.php';
require_once SNW_PATH . 'includes/Requests.php';
require_once SNW_PATH . 'includes/Rest.php';
require_once SNW_PATH . 'includes/Settings.php';
require_once SNW_PATH . 'includes/Assets.php';
require_once SNW_PATH . 'includes/Shortcode.php';
require_once SNW_PATH . 'includes/Admin.php';
require_once SNW_PATH . 'includes/Plugin.php';

/**
 * Boot the plugin. Everything is hooked, nothing runs at load time
 * that could break the host site.
 */
register_activation_hook( __FILE__, array( 'SNW_Plugin', 'activate' ) );
add_action( 'plugins_loaded', array( 'SNW_Plugin', 'init' ) );
