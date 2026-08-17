<?php
/**
 * Preset storage. Presets store only configuration, never content.
 *
 * Storage uses a single WordPress option (no custom database table). This
 * keeps the plugin uninstall-safe and avoids any schema migrations.
 *
 * @package SteigerwaldNewsWidget
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SNW_Presets {

    const OPTION_KEY = 'snw_presets';

    /**
     * Return all saved presets (empty array if none / invalid).
     *
     * @return array
     */
    public static function get_all() {
        $raw = get_option( self::OPTION_KEY, array() );
        if ( ! is_array( $raw ) ) {
            return array();
        }

        // Drop any structurally broken entries defensively.
        $clean = array();
        foreach ( $raw as $entry ) {
            if ( is_array( $entry ) && isset( $entry['id'], $entry['config'] ) ) {
                $clean[] = $entry;
            }
        }
        return $clean;
    }

    /**
     * Get a single preset by id, or null.
     *
     * @param string $id
     * @return array|null
     */
    public static function get( $id ) {
        foreach ( self::get_all() as $entry ) {
            if ( (string) $entry['id'] === (string) $id ) {
                return $entry;
            }
        }
        return null;
    }

    /**
     * Save (insert or update) a preset.
     *
     * @param string $name   Preset display name.
     * @param array  $config Sanitized config.
     * @param string $id     Existing id when updating, empty for new.
     * @return array|false The stored preset array, or false on failure.
     */
    public static function save( $name, $config, $id = '' ) {
        $name   = sanitize_text_field( $name );
        $config = SNW_Helpers::sanitize_config( $config );

        if ( '' === $name ) {
            $name = __( 'Unbenanntes Widget', 'steigerwald-news-widget' );
        }

        $presets = self::get_all();
        $now     = current_time( 'mysql' );

        if ( $id && self::get( $id ) ) {
            foreach ( $presets as &$entry ) {
                if ( (string) $entry['id'] === (string) $id ) {
                    $entry['name']      = $name;
                    // Bind the widget id verbindlich to the preset id so the
                    // tracked UTM links always reference the saved widget.
                    $config['widget_id'] = $id;
                    $entry['config']    = $config;
                    $entry['updated']   = $now;
                    break;
                }
            }
            unset( $entry );
            $saved_id = $id;
        } else {
            $used_ids = wp_list_pluck( $presets, 'id' );
            $saved_id = SNW_Helpers::generate_widget_id( $used_ids );
            $config['widget_id'] = $saved_id;
            $presets[] = array(
                'id'      => $saved_id,
                'name'    => $name,
                'config'  => $config,
                'created' => $now,
                'updated' => $now,
            );
        }

        if ( ! update_option( self::OPTION_KEY, $presets, false ) ) {
            // update_option returns false both on failure and "no change".
            // Re-read to confirm the value is actually present.
            $reread = self::get( $saved_id );
            if ( ! $reread ) {
                return false;
            }
        }

        return self::get( $saved_id );
    }

    /**
     * Delete a preset by id.
     *
     * @param string $id
     * @return bool
     */
    public static function delete( $id ) {
        $presets = self::get_all();
        $found   = false;
        foreach ( $presets as $key => $entry ) {
            if ( (string) $entry['id'] === (string) $id ) {
                unset( $presets[ $key ] );
                $found = true;
                break;
            }
        }
        if ( ! $found ) {
            return false;
        }
        return (bool) update_option( self::OPTION_KEY, array_values( $presets ), false );
    }

    /**
     * Duplicate an existing preset with a fresh id and "(Kopie)" suffix.
     *
     * @param string $id
     * @return array|false
     */
    public static function duplicate( $id ) {
        $source = self::get( $id );
        if ( ! $source ) {
            return false;
        }
        $new_name = $source['name'] . ' ' . __( '(Kopie)', 'steigerwald-news-widget' );
        return self::save( $new_name, $source['config'] );
    }

    /**
     * Attach partner-request metadata to a stored preset. Used to lock an
     * accepted widget to the requester's domain and to remember the owner.
     *
     * @param string $id
     * @param array  $meta Keys: allowed_domain, email, source.
     * @return bool
     */
    public static function save_meta( $id, $meta ) {
        $presets = self::get_all();
        $found   = false;
        foreach ( $presets as &$entry ) {
            if ( (string) $entry['id'] === (string) $id ) {
                if ( isset( $meta['allowed_domain'] ) ) {
                    $entry['allowed_domain'] = SNW_Helpers::sanitize_domain( $meta['allowed_domain'] );
                }
                if ( isset( $meta['email'] ) ) {
                    $entry['email'] = sanitize_email( $meta['email'] );
                }
                if ( isset( $meta['source'] ) ) {
                    $entry['source'] = sanitize_text_field( $meta['source'] );
                }
                $found = true;
                break;
            }
        }
        unset( $entry );
        if ( ! $found ) {
            return false;
        }
        return (bool) update_option( self::OPTION_KEY, $presets, false );
    }
}
