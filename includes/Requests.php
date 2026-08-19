<?php
/**
 * Partner widget requests.
 *
 * External visitors configure a widget on the public builder page, supply their
 * e-mail, the target domain and (optionally) a name. The submission is stored
 * here as a pending request. An administrator reviews, accepts (which creates a
 * domain-locked preset) or rejects it.
 *
 * Storage uses a single WordPress option (no custom database table), matching
 * the rest of the plugin. Rate limiting for public submissions is tracked with
 * transients keyed by IP.
 *
 * @package SteigerwaldNewsWidget
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SNW_Requests {

    const OPTION_KEY       = 'snw_requests';
    const PAGE_OPTION_KEY  = 'snw_builder_page_id';
    const SLUG_OPTION_KEY  = 'snw_builder_slug';
    const DEFAULT_SLUG     = 'widget/new';
    const RATE_LIMIT       = 10;
    const RATE_WINDOW      = HOUR_IN_SECONDS;

    /**
     * Return all stored requests (empty array if none / invalid).
     *
     * @return array
     */
    public static function get_all() {
        $raw = get_option( self::OPTION_KEY, array() );
        if ( ! is_array( $raw ) ) {
            return array();
        }
        // Newest first.
        return array_reverse( $raw );
    }

    /**
     * Get a single request by id.
     *
     * @param string $id
     * @return array|null
     */
    public static function get( $id ) {
        $raw = get_option( self::OPTION_KEY, array() );
        if ( ! is_array( $raw ) ) {
            return null;
        }
        foreach ( $raw as $entry ) {
            if ( isset( $entry['id'] ) && (string) $entry['id'] === (string) $id ) {
                return $entry;
            }
        }
        return null;
    }

    /**
     * Store a new request.
     *
     * @param string $name
     * @param string $email
     * @param string $domain
     * @param array  $config Sanitized widget config.
     * @return string The new request id.
     */
    public static function add( $name, $email, $domain, $config ) {
        $requests = get_option( self::OPTION_KEY, array() );
        if ( ! is_array( $requests ) ) {
            $requests = array();
        }
        $id         = self::generate_id( $requests );
        $requests[] = array(
            'id'        => $id,
            'name'      => sanitize_text_field( $name ),
            'email'     => sanitize_email( $email ),
            'domain'    => SNW_Helpers::sanitize_domain( $domain ),
            'config'    => $config,
            'status'    => 'pending',
            'preset_id' => '',
            'created'   => current_time( 'mysql' ),
        );
        update_option( self::OPTION_KEY, $requests, false );
        return $id;
    }

    /**
     * Update arbitrary fields on a request.
     *
     * @param string $id
     * @param array  $data
     * @return bool
     */
    public static function update( $id, $data ) {
        $requests = get_option( self::OPTION_KEY, array() );
        if ( ! is_array( $requests ) ) {
            return false;
        }
        $found = false;
        foreach ( $requests as &$entry ) {
            if ( isset( $entry['id'] ) && (string) $entry['id'] === (string) $id ) {
                foreach ( $data as $k => $v ) {
                    $entry[ $k ] = $v;
                }
                $found = true;
                break;
            }
        }
        unset( $entry );
        if ( ! $found ) {
            return false;
        }
        return (bool) update_option( self::OPTION_KEY, $requests, false );
    }

    /**
     * Delete a request.
     *
     * @param string $id
     * @return bool
     */
    public static function delete( $id ) {
        $requests = get_option( self::OPTION_KEY, array() );
        if ( ! is_array( $requests ) ) {
            return false;
        }
        $found = false;
        foreach ( $requests as $key => $entry ) {
            if ( isset( $entry['id'] ) && (string) $entry['id'] === (string) $id ) {
                unset( $requests[ $key ] );
                $found = true;
                break;
            }
        }
        if ( ! $found ) {
            return false;
        }
        return (bool) update_option( self::OPTION_KEY, array_values( $requests ), false );
    }

    /**
     * Generate a unique request id like REQ-7F3K.
     *
     * @param array $existing
     * @return string
     */
    private static function generate_id( $existing ) {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $used     = array();
        foreach ( $existing as $e ) {
            if ( isset( $e['id'] ) ) {
                $used[] = $e['id'];
            }
        }
        for ( $i = 0; $i < 50; $i++ ) {
            $suffix = '';
            for ( $j = 0; $j < 4; $j++ ) {
                $suffix .= $alphabet[ wp_rand( 0, strlen( $alphabet ) - 1 ) ];
            }
            $id = 'REQ-' . $suffix;
            if ( ! in_array( $id, $used, true ) ) {
                return $id;
            }
        }
        return 'REQ-' . substr( md5( (string) wp_rand() ), 0, 4 );
    }

    /**
     * Check + record the per-IP rate limit for public submissions.
     *
     * Returns true when the caller has exceeded the limit.
     *
     * @param string $ip
     * @return bool
     */
    public static function rate_limited( $ip ) {
        $key   = 'snw_rl_' . md5( (string) $ip );
        $count = get_transient( $key );
        if ( false === $count ) {
            set_transient( $key, 1, self::RATE_WINDOW );
            return false;
        }
        if ( (int) $count >= self::RATE_LIMIT ) {
            return true;
        }
        set_transient( $key, (int) $count + 1, self::RATE_WINDOW );
        return false;
    }

    /**
     * Ensure the public builder page exists at the configured slug.
     *
     * Creates the page on first call and stores its id. If the stored page is
     * missing (e.g. trashed), it is recreated. The slug can be changed by
     * passing a new value.
     *
     * @param string $slug Optional override of the stored slug.
     * @return array|false {id, url} or false on failure.
     */
    public static function ensure_builder_page( $slug = '' ) {
        $stored_slug = get_option( self::SLUG_OPTION_KEY, self::DEFAULT_SLUG );
        if ( $slug ) {
            $stored_slug = sanitize_title( $slug );
            update_option( self::SLUG_OPTION_KEY, $stored_slug, false );
        }

        $page_id = (int) get_option( self::PAGE_OPTION_KEY, 0 );
        if ( $page_id ) {
            $page = get_post( $page_id );
            if ( $page && 'trash' !== $page->post_status ) {
                // When a new slug was requested, move the existing page to it
                // so "erstellen/aktualisieren" actually renames the page.
                if ( $slug && $page->post_name !== $stored_slug ) {
                    wp_update_post(
                        array(
                            'ID'        => $page_id,
                            'post_name' => $stored_slug,
                        )
                    );
                }
                return array(
                    'id'  => $page_id,
                    'url' => get_permalink( $page_id ),
                );
            }
        }

        $page_id = wp_insert_post( array(
            'post_title'   => __( 'SN News Widget erstellen', 'steigerwald-news-widget' ),
            'post_name'    => $stored_slug,
            'post_content' => '[steigerwald_news_widget_builder]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ), true );

        if ( is_wp_error( $page_id ) ) {
            return false;
        }

        update_option( self::PAGE_OPTION_KEY, $page_id, false );
        return array(
            'id'  => $page_id,
            'url' => get_permalink( $page_id ),
        );
    }
}
