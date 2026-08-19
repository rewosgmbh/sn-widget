<?php
/**
 * Plugin Name: SN News Widget
 * Plugin URI:  https://ld3.ottili.one
 * Description: Erstellt extern einbettbare Nachrichten-Widgets aus WordPress-Beiträgen über die WordPress-REST-API. Optionale, DSGVO-konforme Statistik/Telemetrie.
 * Version:     1.2.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Tested up to:      7.0.4
 * Author:      Ottili ONE
 * Author URI:  https://ld3.ottili.one/
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

define( 'SNW_VERSION', '1.2.0' );
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
require_once SNW_PATH . 'includes/Builder.php';
require_once SNW_PATH . 'includes/Shortcode.php';
require_once SNW_PATH . 'includes/Admin.php';
require_once SNW_PATH . 'includes/Telemetry.php';
require_once SNW_PATH . 'includes/Plugin.php';

/**
 * Boot the plugin. Everything is hooked, nothing runs at load time
 * that could break the host site.
 */
register_activation_hook( __FILE__, array( 'SNW_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'SNW_Plugin', 'deactivate' ) );
add_action( 'plugins_loaded', array( 'SNW_Plugin', 'init' ) );
