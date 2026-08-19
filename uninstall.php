<?php
/**
 * Uninstallation routine.
 *
 * Removes all plugin-specific options, the auto-created public builder page
 * and the optional telemetry database tables. No posts, terms, media or
 * foreign options are touched.
 *
 * @package SteigerwaldNewsWidget
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Capture the auto-created builder page id before the options are removed.
$builder_page_id = (int) get_option( 'snw_builder_page_id' );

// Remove the auto-created public builder page, if it still exists.
if ( $builder_page_id > 0 && get_post( $builder_page_id ) ) {
    wp_delete_post( $builder_page_id, true );
}

// Plugin options: settings, presets, requests, branding, builder page and
// the optional telemetry subsystem options.
$options = array(
    'snw_presets',
    'snw_requests',
    'snw_branding',
    'snw_builder_page_id',
    'snw_builder_slug',
    'snw_version',
    'snw_telemetry_enabled',
    'snw_telemetry_retention_days',
    'snw_telemetry_bot_filter',
    'snw_telemetry_rotation_days',
    'snw_telemetry_debug',
    'snw_telemetry_last_aggregate',
    'snw_telemetry_last_aggregate_run',
    'snw_telemetry_last_cleanup',
    'snw_telemetry_schema',
);

foreach ( $options as $option ) {
    delete_option( $option );
}

// Drop telemetry tables (optional subsystem). Table names are composed from
// the fixed DB prefix plus literal suffixes; no user input is involved.
$tables = array( 'snw_telemetry_events', 'snw_telemetry_daily', 'snw_telemetry_articles', 'snw_telemetry_widget_pages' );
foreach ( $tables as $table ) {
    $table_name = $wpdb->prefix . $table;
    $wpdb->query( 'DROP TABLE IF EXISTS `' . $table_name . '`' );
}
