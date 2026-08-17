<?php
/**
 * Uninstallation routine.
 *
 * Only plugin-specific options are removed. No posts, categories, tags,
 * media or foreign options are touched. No custom database tables exist.
 *
 * @package SteigerwaldNewsWidget
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Remove only our own stored configuration.
$options = array( 'snw_presets' );

foreach ( $options as $option ) {
    delete_option( $option );
}
