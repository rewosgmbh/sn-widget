<?php
/**
 * Telemetry & Analytics subsystem.
 *
 * Design goals (see project spec):
 *  - Separate, versioned Telemetry API, fully decoupled from the WordPress
 *    Core REST content API (/wp-json/wp/v2/*).
 *  - Two distinct view metrics: raw `widget_load` (page load / init) and
 *    `viewable_impression` (>=50% visible for >=1s via IntersectionObserver).
 *  - Privacy-safe unique visitors: a server-side, time-rotating HMAC key
 *    derived from coarse request material. No IP is stored, no fingerprinting.
 *  - Raw events are short-lived; daily aggregates are the long-term store the
 *    dashboard reads from. Nightly rollup + retention keep things fast.
 *  - Event ingestion is public (partner sites POST); analytics GET endpoints
 *    are admin-only (manage_options + REST nonce).
 *
 * No part of this file emits output at load time; everything is hooked.
 *
 * @package SteigerwaldNewsWidget
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SNW_Telemetry {

    const SCHEMA_VERSION = 1;

    // --- Option keys ---------------------------------------------------
    const OPT_ENABLED     = 'snw_telemetry_enabled';
    const OPT_RETENTION   = 'snw_telemetry_retention_days';
    const OPT_BOT_FILTER  = 'snw_telemetry_bot_filter';
    const OPT_ROTATION    = 'snw_telemetry_rotation_days';
    const OPT_DEBUG       = 'snw_telemetry_debug';
    const OPT_LAST_AGG    = 'snw_telemetry_last_aggregate';
    const OPT_LAST_AGGRUN = 'snw_telemetry_last_aggregate_run';
    const OPT_LAST_CLEAN  = 'snw_telemetry_last_cleanup';
    const OPT_SCHEMA_VER  = 'snw_telemetry_schema';

    // --- Event types ----------------------------------------------------
    const EVENT_WIDGET_LOAD          = 'widget_load';
    const EVENT_VIEWABLE_IMPRESSION  = 'viewable_impression';
    const EVENT_ARTICLE_CLICK        = 'article_click';
    const EVENT_WIDGET_ERROR         = 'widget_error';
    const EVENT_WIDGET_RENDERED      = 'widget_rendered';
    const EVENT_API_SUCCESS          = 'api_success';
    const EVENT_API_ERROR            = 'api_error';
    const EVENT_CONFIG_ERROR         = 'config_error';

    // --- Error codes ----------------------------------------------------
    const ERROR_CODES = array(
        'REST_TIMEOUT', 'REST_400', 'REST_401', 'REST_403', 'REST_404',
        'REST_429', 'REST_500', 'CORS_ERROR', 'INVALID_CONFIG',
        'RENDER_ERROR', 'NO_POSTS', 'NETWORK_ERROR', 'UNKNOWN',
    );

    /**
     * Boot the telemetry subsystem: REST routes, rewrite alias, cron.
     *
     * @return void
     */
    public static function init() {
        // Ensure the tables exist even on a code-only update where the
        // activation hook (which also installs) did not run.
        if ( (int) get_option( self::OPT_SCHEMA_VER, 0 ) !== self::SCHEMA_VERSION ) {
            self::install();
        }

        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
        add_action( 'init', array( __CLASS__, 'add_rewrite' ) );
        add_action( 'snw_telemetry_cron', array( __CLASS__, 'cron_run' ) );

        // Admin analytics endpoints reused by the dashboard (admin-ajax keeps
        // our existing nonce pattern; REST is the canonical "API" surface).
        add_action( 'wp_ajax_snw_telemetry_export', array( __CLASS__, 'ajax_export' ) );
        add_action( 'wp_ajax_snw_telemetry_save_settings', array( __CLASS__, 'ajax_save_settings' ) );
        add_action( 'wp_ajax_snw_telemetry_purge', array( __CLASS__, 'ajax_purge' ) );
        add_action( 'wp_ajax_snw_telemetry_status', array( __CLASS__, 'ajax_status' ) );
    }

    /**
     * Plugin (de)activation hooks.
     *
     * @return void
     */
    public static function activate() {
        self::install();
        self::add_rewrite();
        flush_rewrite_rules();
        if ( ! wp_next_scheduled( 'snw_telemetry_cron' ) ) {
            // Run a couple of times a day; not aggressive.
            wp_schedule_event( time(), 'twicedaily', 'snw_telemetry_cron' );
        }
        // Seed the last-aggregated marker so the first rollup starts yesterday.
        if ( false === get_option( self::OPT_LAST_AGG ) ) {
            update_option( self::OPT_LAST_AGG, gmdate( 'Y-m-d', time() - DAY_IN_SECONDS ), false );
        }
    }

    public static function deactivate() {
        $ts = wp_next_scheduled( 'snw_telemetry_cron' );
        if ( $ts ) {
            wp_unschedule_event( $ts, 'snw_telemetry_cron' );
        }
    }

    // ------------------------------------------------------------------
    // Table helpers
    // ------------------------------------------------------------------

    /**
     * Full table name for a telemetry sub-table.
     *
     * @param string $name events | daily | articles | widget_pages
     * @return string
     */
    public static function table( $name ) {
        global $wpdb;
        return $wpdb->prefix . 'snw_telemetry_' . $name;
    }

    /**
     * Create / upgrade the telemetry tables. Idempotent (dbDelta).
     *
     * @return void
     */
    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();

        $events = self::table( 'events' );
        $daily  = self::table( 'daily' );
        $arts   = self::table( 'articles' );
        $pages  = self::table( 'widget_pages' );

        dbDelta( "
            CREATE TABLE {$events} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                event_type VARCHAR(32) NOT NULL,
                widget_id VARCHAR(16) NOT NULL DEFAULT '',
                partner VARCHAR(60) NOT NULL DEFAULT '',
                host VARCHAR(255) NOT NULL,
                page_path VARCHAR(512) NOT NULL DEFAULT '/',
                article_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                visitor_key VARCHAR(64) NOT NULL,
                widget_version VARCHAR(32) NOT NULL DEFAULT '',
                layout VARCHAR(32) NOT NULL DEFAULT '',
                mode VARCHAR(32) NOT NULL DEFAULT '',
                rest_ms INT NOT NULL DEFAULT 0,
                render_ms INT NOT NULL DEFAULT 0,
                error_code VARCHAR(32) NOT NULL DEFAULT '',
                is_bot TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY idx_created (created_at),
                KEY idx_event (event_type),
                KEY idx_widget (widget_id),
                KEY idx_partner (partner),
                KEY idx_host (host),
                KEY idx_article (article_id),
                KEY idx_visitor (visitor_key),
                KEY idx_bot (is_bot)
            ) {$charset};
        " );

        dbDelta( "
            CREATE TABLE {$daily} (
                date DATE NOT NULL,
                widget_id VARCHAR(16) NOT NULL DEFAULT '',
                partner VARCHAR(60) NOT NULL DEFAULT '',
                host VARCHAR(255) NOT NULL DEFAULT '',
                page_path VARCHAR(512) NOT NULL DEFAULT '/',
                loads INT UNSIGNED NOT NULL DEFAULT 0,
                unique_load_visitors INT UNSIGNED NOT NULL DEFAULT 0,
                viewable_impressions INT UNSIGNED NOT NULL DEFAULT 0,
                unique_viewable_visitors INT UNSIGNED NOT NULL DEFAULT 0,
                clicks INT UNSIGNED NOT NULL DEFAULT 0,
                unique_clickers INT UNSIGNED NOT NULL DEFAULT 0,
                errors INT UNSIGNED NOT NULL DEFAULT 0,
                rest_ms_sum INT UNSIGNED NOT NULL DEFAULT 0,
                rest_ms_n INT UNSIGNED NOT NULL DEFAULT 0,
                render_ms_sum INT UNSIGNED NOT NULL DEFAULT 0,
                render_ms_n INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (date, widget_id, partner, host, page_path),
                KEY idx_date (date),
                KEY idx_widget (widget_id),
                KEY idx_partner (partner),
                KEY idx_host (host)
            ) {$charset};
        " );

        dbDelta( "
            CREATE TABLE {$arts} (
                date DATE NOT NULL,
                widget_id VARCHAR(16) NOT NULL DEFAULT '',
                article_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                clicks INT UNSIGNED NOT NULL DEFAULT 0,
                unique_clickers INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (date, widget_id, article_id),
                KEY idx_date (date),
                KEY idx_widget (widget_id),
                KEY idx_article (article_id)
            ) {$charset};
        " );

        dbDelta( "
            CREATE TABLE {$pages} (
                widget_id VARCHAR(16) NOT NULL DEFAULT '',
                host VARCHAR(255) NOT NULL,
                page_path VARCHAR(512) NOT NULL DEFAULT '/',
                first_seen DATETIME NOT NULL,
                last_seen DATETIME NOT NULL,
                loads BIGINT UNSIGNED NOT NULL DEFAULT 0,
                viewable BIGINT UNSIGNED NOT NULL DEFAULT 0,
                clicks BIGINT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (widget_id, host, page_path),
                KEY idx_last (last_seen)
            ) {$charset};
        " );

        update_option( self::OPT_SCHEMA_VER, self::SCHEMA_VERSION, false );
    }

    // ------------------------------------------------------------------
    // Public rewrite alias: /sn-widget/telemetry/v1/* -> REST route
    // ------------------------------------------------------------------

    public static function add_rewrite() {
        add_rewrite_rule(
            '^sn-widget/telemetry/v1/(.*)$',
            'index.php?rest_route=/snw-telemetry/v1/$matches[1]',
            'top'
        );
    }

    // ------------------------------------------------------------------
    // Pure, DB-free helpers (also unit-tested in Node/PHP standalone)
    // ------------------------------------------------------------------

    /**
     * Normalize a host to lower-case, port-stripped, plausible host.
     * Returns '' when not a plausible registered host.
     *
     * @param string $value
     * @return string
     */
    public static function normalize_host( $value ) {
        $value = trim( (string) $value );
        if ( '' === $value ) {
            return '';
        }
        if ( ! preg_match( '#^[a-z][a-z0-9+.\-]*://#i', $value ) ) {
            $value = 'http://' . $value;
        }
        $host = wp_parse_url( $value, PHP_URL_HOST );
        if ( ! $host ) {
            return '';
        }
        $host = strtolower( $host );
        $host = preg_replace( '/:\d+$/', '', $host );
        // Loopback / local hosts are accepted so telemetry works in dev and on
        // the local test instance (no real TLD there).
        if ( in_array( $host, array( 'localhost', '127.0.0.1', '::1', '0.0.0.0' ), true ) ) {
            return $host;
        }
        if ( ! preg_match( '/^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/i', $host ) ) {
            return '';
        }
        return $host;
    }

    /**
     * Normalize a page path: strip query string and fragment, ensure leading
     * slash, cap length. An empty/fragment-only value becomes '/'.
     *
     * @param string $value
     * @return string
     */
    public static function normalize_page_path( $value ) {
        $value = trim( (string) $value );
        // Drop fragment.
        $hash = strpos( $value, '#' );
        if ( false !== $hash ) {
            $value = substr( $value, 0, $hash );
        }
        // Drop query string.
        $q = strpos( $value, '?' );
        if ( false !== $q ) {
            $value = substr( $value, 0, $q );
        }
        if ( '' === $value || '/' !== substr( $value, 0, 1 ) ) {
            $value = '/' . ltrim( $value, '/' );
        }
        if ( strlen( $value ) > 512 ) {
            $value = substr( $value, 0, 512 );
        }
        return $value;
    }

    /**
     * Return the list of accepted event types.
     *
     * @return array
     */
    public static function allowed_event_types() {
        return array(
            self::EVENT_WIDGET_LOAD,
            self::EVENT_VIEWABLE_IMPRESSION,
            self::EVENT_ARTICLE_CLICK,
            self::EVENT_WIDGET_ERROR,
            self::EVENT_WIDGET_RENDERED,
            self::EVENT_API_SUCCESS,
            self::EVENT_API_ERROR,
            self::EVENT_CONFIG_ERROR,
        );
    }

    /**
     * Sanitize an event type; returns '' when not allowed.
     *
     * @param string $value
     * @return string
     */
    public static function sanitize_event_type( $value ) {
        $value = strtolower( trim( (string) $value ) );
        return in_array( $value, self::allowed_event_types(), true ) ? $value : '';
    }

    /**
     * Map a User-Agent to a coarse family string. Deliberately low-entropy:
     * browser/OS only, no version, no fingerprinting material.
     *
     * @param string $ua
     * @return string
     */
    public static function coarse_ua( $ua ) {
        $ua = strtolower( (string) $ua );

        if ( preg_match( '/(bot|spider|crawl|slurp|ahrefs|semrush|bingpreview|facebookexternalhit|headlesscrawler|headless|curl|wget|python-requests|go-http)/', $ua ) ) {
            return 'bot';
        }

        $browser = 'other';
        if ( strpos( $ua, 'edg/' ) !== false || strpos( $ua, 'edge/' ) !== false ) {
            $browser = 'edge';
        } elseif ( strpos( $ua, 'chrome' ) !== false && strpos( $ua, 'chromium' ) === false ) {
            $browser = 'chrome';
        } elseif ( strpos( $ua, 'firefox' ) !== false || strpos( $ua, 'fxios' ) !== false ) {
            $browser = 'firefox';
        } elseif ( strpos( $ua, 'safari' ) !== false && strpos( $ua, 'chrome' ) === false ) {
            $browser = 'safari';
        } elseif ( strpos( $ua, 'msie' ) !== false || strpos( $ua, 'trident' ) !== false ) {
            $browser = 'ie';
        }

        $os = 'other';
        if ( strpos( $ua, 'windows' ) !== false ) {
            $os = 'win';
        } elseif ( strpos( $ua, 'mac os' ) !== false || strpos( $ua, 'macintosh' ) !== false ) {
            $os = 'mac';
        } elseif ( strpos( $ua, 'android' ) !== false ) {
            $os = 'android';
        } elseif ( strpos( $ua, 'iphone' ) !== false || strpos( $ua, 'ipad' ) !== false ) {
            $os = 'ios';
        } elseif ( strpos( $ua, 'linux' ) !== false ) {
            $os = 'linux';
        }

        return $browser . '/' . $os;
    }

    /**
     * Is the given User-Agent an obvious bot/crawler?
     *
     * @param string $ua
     * @return bool
     */
    public static function is_bot_ua( $ua ) {
        return 'bot' === self::coarse_ua( $ua );
    }

    /**
     * Build the HMAC material for a visitor key. Combines coarse request
     * identity, coarse UA, and the rotation bucket. The raw IP is part of the
     * material but is NEVER stored; only the resulting HMAC is persisted.
     *
     * @param string $ip
     * @param string $ua
     * @param string $bucket
     * @return string
     */
    public static function visitor_material( $ip, $ua, $bucket ) {
        return $bucket . '|' . self::coarse_ua( $ua ) . '|' . $ip;
    }

    /**
     * Rotation bucket for a timestamp, based on the configured rotation
     * period (default 1 day). Two timestamps in the same window share a bucket.
     *
     * @param int $ts Unix timestamp (UTC).
     * @return string
     */
    public static function rotation_bucket( $ts ) {
        $days = (int) get_option( self::OPT_ROTATION, 1 );
        if ( $days < 1 ) {
            $days = 1;
        }
        // Floor to the start of the rotation window (UTC-day aligned).
        $day_num = floor( $ts / DAY_IN_SECONDS );
        $win     = floor( $day_num / $days ) * $days;
        return gmdate( 'Y-m-d', $win * DAY_IN_SECONDS );
    }

    /**
     * Server-side pseudonymous, time-rotating visitor key.
     *
     * @param string $ip
     * @param string $ua
     * @param int    $ts
     * @return string
     */
    public static function visitor_key( $ip, $ua, $ts ) {
        return wp_hash( self::visitor_material( $ip, $ua, self::rotation_bucket( $ts ) ), 'snw-telemetry' );
    }

    // ------------------------------------------------------------------
    // Payload validation -> sanitized record or WP_Error
    // ------------------------------------------------------------------

    /**
     * Validate and sanitize an incoming event payload.
     *
     * @param array $body Decoded JSON body.
     * @return array|WP_Error Sanitized record on success.
     */
    public static function build_record( $body ) {
        if ( ! is_array( $body ) ) {
            return new WP_Error( 'snw_invalid_body', 'Invalid body', array( 'status' => 400 ) );
        }

        $event = self::sanitize_event_type( isset( $body['event'] ) ? $body['event'] : '' );
        if ( '' === $event ) {
            return new WP_Error( 'snw_invalid_event', 'Unknown or missing event type', array( 'status' => 400 ) );
        }

        // Widget id: must be empty or match SNW-XXXX. Anything else is rejected.
        $widget_id = isset( $body['widget_id'] ) ? SNW_Helpers::sanitize_widget_id( (string) $body['widget_id'] ) : '';
        if ( isset( $body['widget_id'] ) && is_string( $body['widget_id'] ) && '' !== $body['widget_id'] && '' === $widget_id ) {
            return new WP_Error( 'snw_invalid_widget', 'Invalid widget id', array( 'status' => 400 ) );
        }

        $partner = isset( $body['partner'] ) ? SNW_Helpers::sanitize_partner( (string) $body['partner'] ) : '';

        // Host: trust the client value, but normalize; fall back to Origin/Referer.
        $host = self::normalize_host( isset( $body['host'] ) ? (string) $body['host'] : '' );
        if ( '' === $host ) {
            $origin  = isset( $_SERVER['HTTP_ORIGIN'] ) ? (string) $_SERVER['HTTP_ORIGIN'] : '';
            $referer = isset( $_SERVER['HTTP_REFERER'] ) ? (string) $_SERVER['HTTP_REFERER'] : '';
            $host    = self::normalize_host( $origin );
            if ( '' === $host ) {
                $host = self::normalize_host( $referer );
            }
        }
        if ( '' === $host ) {
            return new WP_Error( 'snw_invalid_host', 'Invalid host', array( 'status' => 400 ) );
        }

        $page_path = self::normalize_page_path( isset( $body['page_path'] ) ? (string) $body['page_path'] : '' );

        $widget_version = isset( $body['widget_version'] ) ? sanitize_text_field( substr( (string) $body['widget_version'], 0, 32 ) ) : '';
        $layout         = isset( $body['layout'] ) ? sanitize_text_field( substr( (string) $body['layout'], 0, 32 ) ) : '';
        $mode           = isset( $body['mode'] ) ? sanitize_text_field( substr( (string) $body['mode'], 0, 32 ) ) : '';

        $article_id = 0;
        if ( self::EVENT_ARTICLE_CLICK === $event ) {
            $article_id = isset( $body['article_id'] ) ? absint( $body['article_id'] ) : 0;
            if ( $article_id < 1 ) {
                return new WP_Error( 'snw_invalid_article', 'Invalid article id', array( 'status' => 400 ) );
            }
        }

        $rest_ms   = isset( $body['performance']['rest_ms'] ) ? absint( $body['performance']['rest_ms'] ) : 0;
        $render_ms = isset( $body['performance']['render_ms'] ) ? absint( $body['performance']['render_ms'] ) : 0;
        $rest_ms   = min( $rest_ms, 60000 );
        $render_ms = min( $render_ms, 60000 );

        $error_code = '';
        if ( self::EVENT_WIDGET_ERROR === $event ) {
            $code = isset( $body['error_code'] ) ? strtoupper( trim( (string) $body['error_code'] ) ) : '';
            $error_code = in_array( $code, self::ERROR_CODES, true ) ? $code : 'UNKNOWN';
        }

        $ua   = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
        $ip   = self::client_ip();
        $ts   = time();
        $is_bot = self::is_bot_ua( $ua ) ? 1 : 0;
        $visitor_key = self::visitor_key( $ip, $ua, $ts );

        return array(
            'event_type'     => $event,
            'widget_id'      => $widget_id,
            'partner'        => $partner,
            'host'           => $host,
            'page_path'      => $page_path,
            'article_id'     => $article_id,
            'visitor_key'    => $visitor_key,
            'widget_version' => $widget_version,
            'layout'         => $layout,
            'mode'           => $mode,
            'rest_ms'        => $rest_ms,
            'render_ms'      => $render_ms,
            'error_code'     => $error_code,
            'is_bot'         => $is_bot,
            'created_at'     => gmdate( 'Y-m-d H:i:s', $ts ),
        );
    }

    /**
     * Best-effort client IP, honouring a reverse proxy if present.
     *
     * @return string
     */
    public static function client_ip() {
        if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $parts = explode( ',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'] );
            $ip    = trim( $parts[0] );
            if ( '' !== $ip ) {
                return $ip;
            }
        }
        return isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }

    // ------------------------------------------------------------------
    // Anti-abuse
    // ------------------------------------------------------------------

    /**
     * Per-IP rate limit. Returns true when the caller is over the limit.
     *
     * @param string $ip
     * @param bool   $is_bot
     * @return bool
     */
    public static function rate_limited( $ip, $is_bot ) {
        $key   = 'snw_tm_' . md5( (string) $ip );
        $limit = $is_bot ? 20 : 200; // events per 10-minute window
        $count = get_transient( $key );
        if ( false === $count ) {
            set_transient( $key, 1, 10 * MINUTE_IN_SECONDS );
            return false;
        }
        if ( (int) $count >= $limit ) {
            return true;
        }
        set_transient( $key, (int) $count + 1, 10 * MINUTE_IN_SECONDS );
        return false;
    }

    // ------------------------------------------------------------------
    // REST routes
    // ------------------------------------------------------------------

    public static function register_routes() {
        register_rest_route(
            'snw-telemetry/v1',
            '/event',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( __CLASS__, 'handle_event' ),
                'permission_callback' => '__return_true',
            )
        );

        register_rest_route(
            'snw-telemetry/v1',
            '/health',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'handle_health' ),
                'permission_callback' => '__return_true',
            )
        );

        // Admin-only analytics endpoints.
        $admin = array(
            'stats'     => 'handle_stats',
            'widgets'   => 'handle_widgets',
            'pages'     => 'handle_pages',
            'articles'  => 'handle_articles',
            'partners'  => 'handle_partners',
            'realtime'  => 'handle_realtime',
            'aggregate' => 'handle_aggregate',
        );
        foreach ( $admin as $route => $cb ) {
            register_rest_route(
                'snw-telemetry/v1',
                '/' . $route,
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( __CLASS__, $cb ),
                    'permission_callback' => array( __CLASS__, 'admin_only' ),
                )
            );
        }
    }

    /**
     * Guard for admin-only analytics endpoints.
     *
     * @return bool
     */
    public static function admin_only() {
        return current_user_can( 'manage_options' );
    }

    /**
     * Public event ingestion. CORS-open because partner sites POST cross-origin.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public static function handle_event( $request ) {
        self::cors_open();

        if ( 'yes' !== get_option( self::OPT_ENABLED, 'yes' ) ) {
            return new WP_REST_Response( array( 'status' => 'disabled' ), 200 );
        }

        // Payload size guard (no multi-megabyte JSON).
        $len = isset( $_SERVER['CONTENT_LENGTH'] ) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
        if ( $len > 8192 ) {
            return new WP_Error( 'snw_too_large', 'Payload too large', array( 'status' => 413 ) );
        }

        $body = $request->get_json_params();
        if ( ! is_array( $body ) ) {
            // Allow form-encoded fallback.
            $body = $request->get_body_params();
        }
        if ( ! is_array( $body ) ) {
            return new WP_Error( 'snw_invalid_body', 'Invalid body', array( 'status' => 400 ) );
        }

        $ua     = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
        $is_bot = self::is_bot_ua( $ua ) ? 1 : 0;

        if ( self::rate_limited( self::client_ip(), (bool) $is_bot ) ) {
            return new WP_Error( 'snw_rate_limited', 'Rate limited', array( 'status' => 429 ) );
        }

        $record = self::build_record( $body );
        if ( is_wp_error( $record ) ) {
            return $record;
        }

        $inserted = self::insert_event( $record );
        if ( ! $inserted ) {
            return new WP_Error( 'snw_db_error', 'Could not store event', array( 'status' => 500 ) );
        }

        // Keep widget_pages fresh for status / "Installed On" (human traffic only).
        if ( ! $record['is_bot'] ) {
            self::touch_widget_page( $record );
        }

        return new WP_REST_Response( array( 'status' => 'accepted' ), 202 );
    }

    /**
     * Minimal public health probe. Never reveals internals.
     *
     * @return WP_REST_Response
     */
    public static function handle_health() {
        self::cors_open();
        return new WP_REST_Response(
            array(
                'status'  => 'ok',
                'version' => SNW_VERSION,
            ),
            200
        );
    }

    /**
     * Send permissive CORS headers for the public ingestion endpoints.
     *
     * @return void
     */
    private static function cors_open() {
        $origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? (string) $_SERVER['HTTP_ORIGIN'] : '';
        header( 'Access-Control-Allow-Origin: ' . ( '' !== $origin ? $origin : '*' ) );
        header( 'Access-Control-Allow-Methods: POST, GET, OPTIONS' );
        header( 'Access-Control-Allow-Headers: Content-Type, X-WP-Nonce' );
        header( 'Vary: Origin' );
        if ( 'OPTIONS' === ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) ) {
            status_header( 204 );
            exit;
        }
    }

    /**
     * Insert a sanitized event record.
     *
     * @param array $record
     * @return bool
     */
    public static function insert_event( $record ) {
        global $wpdb;
        $table = self::table( 'events' );
        $ok = $wpdb->insert(
            $table,
            array(
                'event_type'     => $record['event_type'],
                'widget_id'      => $record['widget_id'],
                'partner'        => $record['partner'],
                'host'           => $record['host'],
                'page_path'      => $record['page_path'],
                'article_id'     => $record['article_id'],
                'visitor_key'    => $record['visitor_key'],
                'widget_version' => $record['widget_version'],
                'layout'         => $record['layout'],
                'mode'           => $record['mode'],
                'rest_ms'        => $record['rest_ms'],
                'render_ms'      => $record['render_ms'],
                'error_code'     => $record['error_code'],
                'is_bot'         => $record['is_bot'],
                'created_at'     => $record['created_at'],
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s' )
        );
        return false !== $ok;
    }

    /**
     * Upsert a widget_pages row for fresh status tracking.
     *
     * @param array $record
     * @return void
     */
    public static function touch_widget_page( $record ) {
        global $wpdb;
        $table = self::table( 'widget_pages' );
        $now   = $record['created_at'];

        $load_inc  = ( self::EVENT_WIDGET_LOAD === $record['event_type'] ) ? 1 : 0;
        $view_inc  = ( self::EVENT_VIEWABLE_IMPRESSION === $record['event_type'] ) ? 1 : 0;
        $click_inc = ( self::EVENT_ARTICLE_CLICK === $record['event_type'] ) ? 1 : 0;

        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE widget_id = %s AND host = %s AND page_path = %s",
                $record['widget_id'],
                $record['host'],
                $record['page_path']
            )
        );

        if ( $existing ) {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table}
                        SET last_seen = GREATEST(last_seen, %s),
                            loads = loads + %d,
                            viewable = viewable + %d,
                            clicks = clicks + %d
                      WHERE widget_id = %s AND host = %s AND page_path = %s",
                    $now,
                    $load_inc,
                    $view_inc,
                    $click_inc,
                    $record['widget_id'],
                    $record['host'],
                    $record['page_path']
                )
            );
        } else {
            $wpdb->insert(
                $table,
                array(
                    'widget_id'  => $record['widget_id'],
                    'host'       => $record['host'],
                    'page_path'  => $record['page_path'],
                    'first_seen' => $now,
                    'last_seen'  => $now,
                    'loads'      => $load_inc,
                    'viewable'   => $view_inc,
                    'clicks'     => $click_inc,
                ),
                array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d' )
            );
        }
    }

    // ------------------------------------------------------------------
    // Aggregation & retention
    // ------------------------------------------------------------------

    /**
     * Cron entry: roll up completed days, then clean up old raw events.
     *
     * @return void
     */
    public static function cron_run() {
        if ( 'yes' !== get_option( self::OPT_ENABLED, 'yes' ) ) {
            return;
        }
        self::rollup_completed_days();
        self::cleanup_retention();
    }

    /**
     * Roll up every completed day from (last aggregated + 1) through yesterday.
     * Idempotent: each day is recomputed from raw events.
     *
     * @return int Number of days processed.
     */
    public static function rollup_completed_days() {
        global $wpdb;
        $last = get_option( self::OPT_LAST_AGG, gmdate( 'Y-m-d', time() - DAY_IN_SECONDS ) );
        $last_ts = strtotime( $last . ' 00:00:00' );
        if ( false === $last_ts ) {
            $last_ts = time() - DAY_IN_SECONDS;
        }

        $yesterday = gmdate( 'Y-m-d', time() - DAY_IN_SECONDS );
        $processed = 0;

        // Cap iterations to avoid a runaway loop after a long downtime.
        for ( $i = 0; $i < 400; $i++ ) {
            $day_ts = $last_ts + $i * DAY_IN_SECONDS;
            $day    = gmdate( 'Y-m-d', $day_ts );
            if ( $day > $yesterday ) {
                break;
            }
            self::rollup_day( $day );
            $processed++;
        }

        update_option( self::OPT_LAST_AGG, $yesterday, false );
        return $processed;
    }

    /**
     * Recompute the daily + article aggregates for a single completed day from
     * raw events. Deletes the day's existing daily rows first (idempotent).
     *
     * @param string $day Y-m-d
     * @return void
     */
    public static function rollup_day( $day ) {
        global $wpdb;
        $events = self::table( 'events' );
        $daily  = self::table( 'daily' );
        $arts   = self::table( 'articles' );

        // Core event aggregates grouped by bucket.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT widget_id, partner, host, page_path,
                        SUM(event_type = %s) AS loads,
                        SUM(event_type = %s) AS viewable,
                        SUM(event_type = %s) AS clicks,
                        SUM(event_type = %s) AS errors,
                        SUM(rest_ms) AS rest_sum,
                        SUM(rest_ms > 0) AS rest_n,
                        SUM(render_ms) AS render_sum,
                        SUM(render_ms > 0) AS render_n
                   FROM {$events}
                  WHERE DATE(created_at) = %s
                  GROUP BY widget_id, partner, host, page_path",
                self::EVENT_WIDGET_LOAD,
                self::EVENT_VIEWABLE_IMPRESSION,
                self::EVENT_ARTICLE_CLICK,
                self::EVENT_WIDGET_ERROR,
                $day
            ),
            ARRAY_A
        );

        // Unique visitors per bucket (distinct visitor_key) for load/view/click.
        $uniq = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT widget_id, partner, host, page_path,
                        COUNT(DISTINCT CASE WHEN event_type = %s THEN visitor_key END) AS u_load,
                        COUNT(DISTINCT CASE WHEN event_type = %s THEN visitor_key END) AS u_view,
                        COUNT(DISTINCT CASE WHEN event_type = %s THEN visitor_key END) AS u_click
                   FROM {$events}
                  WHERE DATE(created_at) = %s
                  GROUP BY widget_id, partner, host, page_path",
                self::EVENT_WIDGET_LOAD,
                self::EVENT_VIEWABLE_IMPRESSION,
                self::EVENT_ARTICLE_CLICK,
                $day
            ),
            ARRAY_A
        );

        $uniq_map = array();
        foreach ( $uniq as $u ) {
            $k = $u['widget_id'] . '|' . $u['partner'] . '|' . $u['host'] . '|' . $u['page_path'];
            $uniq_map[ $k ] = $u;
        }

        // Article clicks + unique clickers per widget/article.
        $article_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT widget_id, article_id,
                        COUNT(*) AS clicks,
                        COUNT(DISTINCT visitor_key) AS u_click
                   FROM {$events}
                  WHERE DATE(created_at) = %s AND event_type = %s AND article_id > 0
                  GROUP BY widget_id, article_id",
                $day,
                self::EVENT_ARTICLE_CLICK
            ),
            ARRAY_A
        );

        // Remove the day's prior daily/article rows, then re-insert.
        $wpdb->delete( $daily, array( 'date' => $day ), array( '%s' ) );
        $wpdb->delete( $arts, array( 'date' => $day ), array( '%s' ) );

        foreach ( (array) $rows as $r ) {
            $k = $r['widget_id'] . '|' . $r['partner'] . '|' . $r['host'] . '|' . $r['page_path'];
            $u = isset( $uniq_map[ $k ] ) ? $uniq_map[ $k ] : array( 'u_load' => 0, 'u_view' => 0, 'u_click' => 0 );

            $wpdb->insert(
                $daily,
                array(
                    'date'                    => $day,
                    'widget_id'               => $r['widget_id'],
                    'partner'                 => $r['partner'],
                    'host'                    => $r['host'],
                    'page_path'               => $r['page_path'],
                    'loads'                   => (int) $r['loads'],
                    'unique_load_visitors'    => (int) $u['u_load'],
                    'viewable_impressions'    => (int) $r['viewable'],
                    'unique_viewable_visitors'=> (int) $u['u_view'],
                    'clicks'                  => (int) $r['clicks'],
                    'unique_clickers'         => (int) $u['u_click'],
                    'errors'                  => (int) $r['errors'],
                    'rest_ms_sum'             => (int) $r['rest_sum'],
                    'rest_ms_n'               => (int) $r['rest_n'],
                    'render_ms_sum'           => (int) $r['render_sum'],
                    'render_ms_n'             => (int) $r['render_n'],
                ),
                array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d' )
            );
        }

        foreach ( (array) $article_rows as $a ) {
            $wpdb->insert(
                $arts,
                array(
                    'date'           => $day,
                    'widget_id'      => $a['widget_id'],
                    'article_id'     => (int) $a['article_id'],
                    'clicks'         => (int) $a['clicks'],
                    'unique_clickers'=> (int) $a['u_click'],
                ),
                array( '%s', '%s', '%d', '%d', '%d' )
            );
        }
    }

    /**
     * Delete raw events older than the retention window. Daily aggregates and
     * article rows are retained long-term.
     *
     * @return int Rows deleted.
     */
    public static function cleanup_retention() {
        global $wpdb;
        $days = (int) get_option( self::OPT_RETENTION, 90 );
        if ( $days < 1 ) {
            $days = 90;
        }
        $cutoff = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
        $table  = self::table( 'events' );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
        $deleted = $wpdb->query(
            $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s LIMIT 50000", $cutoff )
        );
        return (int) $deleted;
    }

    /**
     * Ensure completed days are rolled up; throttled so dashboard loads stay
     * cheap. Always rolls at most once per hour.
     *
     * @return void
     */
    public static function ensure_aggregated() {
        $last = (int) get_option( self::OPT_LAST_AGGRUN, 0 );
        if ( time() - $last < HOUR_IN_SECONDS ) {
            return;
        }
        update_option( self::OPT_LAST_AGGRUN, time(), false );
        self::rollup_completed_days();
    }

    // ------------------------------------------------------------------
    // Range resolution & querying
    // ------------------------------------------------------------------

    /**
     * Resolve a range key (or custom dates) to [start, end] Y-m-d strings.
     *
     * @param string $range today|7|30|90|year|custom
     * @param string $start
     * @param string $end
     * @return array [start, end]
     */
    public static function resolve_range( $range, $start = '', $end = '' ) {
        $today = gmdate( 'Y-m-d' );
        switch ( $range ) {
            case 'today':
                return array( $today, $today );
            case '7':
                return array( gmdate( 'Y-m-d', time() - 6 * DAY_IN_SECONDS ), $today );
            case '30':
                return array( gmdate( 'Y-m-d', time() - 29 * DAY_IN_SECONDS ), $today );
            case '90':
                return array( gmdate( 'Y-m-d', time() - 89 * DAY_IN_SECONDS ), $today );
            case 'year':
                return array( gmdate( 'Y-m-d', time() - 364 * DAY_IN_SECONDS ), $today );
            case 'custom':
                $s = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start ) ? $start : $today;
                $e = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $end ) ? $end : $today;
                if ( strtotime( $s ) > strtotime( $e ) ) {
                    $tmp = $s; $s = $e; $e = $tmp;
                }
                return array( $s, $e );
            default:
                return array( gmdate( 'Y-m-d', time() - 29 * DAY_IN_SECONDS ), $today );
        }
    }

    /**
     * Apply the bot filter clause + optional widget/partner/host filters.
     *
     * @param array $filters
     * @return array { where: string, args: array }
     */
    private static function filter_clause( $filters ) {
        $where = array( '1=1' );
        $args  = array();

        $bot = isset( $filters['bots'] ) ? $filters['bots'] : 'exclude';
        if ( 'exclude' === $bot && 'yes' === get_option( self::OPT_BOT_FILTER, 'yes' ) ) {
            $where[] = 'is_bot = 0';
        } elseif ( 'only' === $bot ) {
            $where[] = 'is_bot = 1';
        }

        if ( ! empty( $filters['widget_id'] ) ) {
            $where[] = 'widget_id = %s';
            $args[]  = SNW_Helpers::sanitize_widget_id( $filters['widget_id'] );
        }
        if ( ! empty( $filters['partner'] ) ) {
            $where[] = 'partner = %s';
            $args[]  = SNW_Helpers::sanitize_partner( $filters['partner'] );
        }
        if ( ! empty( $filters['host'] ) ) {
            $where[] = 'host = %s';
            $args[]  = self::normalize_host( $filters['host'] );
        }
        if ( ! empty( $filters['page'] ) ) {
            $where[] = 'page_path = %s';
            $args[]  = self::normalize_page_path( $filters['page'] );
        }
        if ( ! empty( $filters['article'] ) ) {
            $where[] = 'article_id = %d';
            $args[]  = absint( $filters['article'] );
        }

        return array( 'where' => implode( ' AND ', $where ), 'args' => $args );
    }

    /**
     * Build the full metrics for a date range, combining daily aggregates
     * (past days) with raw events (today, which is not yet rolled up).
     *
     * @param string $start
     * @param string $end
     * @param array  $filters
     * @return array Aggregated structure used by all dashboard endpoints.
     */
    public static function query_range( $start, $end, $filters = array() ) {
        self::ensure_aggregated();
        global $wpdb;
        $daily  = self::table( 'daily' );
        $events = self::table( 'events' );
        $arts   = self::table( 'articles' );

        $today = gmdate( 'Y-m-d' );

        $fc = self::filter_clause( $filters );

        // --- Daily rows for the range (past days) ---
        $daily_args = array_merge( array( $start, $end ), $fc['args'] );
        $daily_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT date, widget_id, partner, host, page_path,
                        loads, unique_load_visitors, viewable_impressions,
                        unique_viewable_visitors, clicks, unique_clickers,
                        errors, rest_ms_sum, rest_ms_n, render_ms_sum, render_ms_n
                   FROM {$daily}
                  WHERE date BETWEEN %s AND %s AND {$fc['where']}",
                $daily_args
            ),
            ARRAY_A
        );

        // --- Raw rows for today (not rolled up yet) ---
        $use_today = ( $end >= $today );
        $raw_rows  = array();
        if ( $use_today ) {
            $rfc = self::filter_clause( $filters );
            $raw_args = array_merge( array( $today ), $rfc['args'] );
            $raw_rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT widget_id, partner, host, page_path,
                            event_type, visitor_key, article_id,
                            rest_ms, render_ms, error_code
                       FROM {$events}
                      WHERE DATE(created_at) = %s AND {$rfc['where']}",
                    $raw_args
                ),
                ARRAY_A
            );
        }

        // Merge into per-date buckets.
        $by_date    = array();
        $by_widget  = array();
        $by_page    = array();
        $by_article = array();

        $empty_bucket = function () {
            return array(
                'loads' => 0, 'unique_load' => 0, 'viewable' => 0, 'unique_view' => 0,
                'clicks' => 0, 'unique_clickers' => 0, 'errors' => 0,
                'rest_sum' => 0, 'rest_n' => 0, 'render_sum' => 0, 'render_n' => 0,
            );
        };

        // From daily.
        foreach ( (array) $daily_rows as $r ) {
            $d = $r['date'];
            if ( ! isset( $by_date[ $d ] ) ) { $by_date[ $d ] = $empty_bucket(); }
            $b = &$by_date[ $d ];
            $b['loads']            += (int) $r['loads'];
            $b['unique_load']      += (int) $r['unique_load_visitors'];
            $b['viewable']         += (int) $r['viewable_impressions'];
            $b['unique_view']      += (int) $r['unique_viewable_visitors'];
            $b['clicks']           += (int) $r['clicks'];
            $b['unique_clickers']  += (int) $r['unique_clickers'];
            $b['errors']           += (int) $r['errors'];
            $b['rest_sum']         += (int) $r['rest_ms_sum'];
            $b['rest_n']           += (int) $r['rest_ms_n'];
            $b['render_sum']       += (int) $r['render_ms_sum'];
            $b['render_n']         += (int) $r['render_ms_n'];
            unset( $b );

            self::accumulate_widget( $by_widget, $r['widget_id'], $r['partner'], $r, $empty_bucket );
            self::accumulate_page( $by_page, $r['widget_id'], $r['host'], $r['page_path'], $r, $empty_bucket );
        }

        // From today's raw (compute uniques per bucket).
        $raw_buckets = array(); // key -> set of visitor_keys per kind
        foreach ( (array) $raw_rows as $r ) {
            $wkey = $r['widget_id'];
            $pkey = $r['widget_id'] . '|' . $r['host'] . '|' . $r['page_path'];
            $akey = $r['widget_id'] . '|' . $r['article_id'];

            if ( ! isset( $raw_buckets[ $wkey ] ) ) {
                $raw_buckets[ $wkey ] = array( 'load' => array(), 'view' => array(), 'click' => array() );
            }
            if ( ! isset( $raw_buckets[ $pkey ] ) ) {
                $raw_buckets[ $pkey ] = array( 'load' => array(), 'view' => array(), 'click' => array() );
            }

            if ( self::EVENT_WIDGET_LOAD === $r['event_type'] ) {
                if ( ! isset( $by_date[ $today ] ) ) { $by_date[ $today ] = $empty_bucket(); }
                $by_date[ $today ]['loads']++;
                $raw_buckets[ $wkey ]['load'][] = $r['visitor_key'];
                $raw_buckets[ $pkey ]['load'][] = $r['visitor_key'];
                if ( ! isset( $by_widget[ $wkey ] ) ) { $by_widget[ $wkey ] = array( 'partner' => $r['partner'] ) + $empty_bucket(); }
                $by_widget[ $wkey ]['loads']++;
                if ( ! isset( $by_page[ $pkey ] ) ) { $by_page[ $pkey ] = array( 'host' => $r['host'], 'page_path' => $r['page_path'] ) + $empty_bucket(); }
                $by_page[ $pkey ]['loads']++;
            } elseif ( self::EVENT_VIEWABLE_IMPRESSION === $r['event_type'] ) {
                if ( ! isset( $by_date[ $today ] ) ) { $by_date[ $today ] = $empty_bucket(); }
                $by_date[ $today ]['viewable']++;
                $raw_buckets[ $wkey ]['view'][] = $r['visitor_key'];
                $raw_buckets[ $pkey ]['view'][] = $r['visitor_key'];
                if ( ! isset( $by_widget[ $wkey ] ) ) { $by_widget[ $wkey ] = array( 'partner' => $r['partner'] ) + $empty_bucket(); }
                $by_widget[ $wkey ]['viewable']++;
                if ( ! isset( $by_page[ $pkey ] ) ) { $by_page[ $pkey ] = array( 'host' => $r['host'], 'page_path' => $r['page_path'] ) + $empty_bucket(); }
                $by_page[ $pkey ]['viewable']++;
            } elseif ( self::EVENT_ARTICLE_CLICK === $r['event_type'] ) {
                if ( ! isset( $by_date[ $today ] ) ) { $by_date[ $today ] = $empty_bucket(); }
                $by_date[ $today ]['clicks']++;
                $raw_buckets[ $wkey ]['click'][] = $r['visitor_key'];
                $raw_buckets[ $pkey ]['click'][] = $r['visitor_key'];
                if ( ! isset( $by_widget[ $wkey ] ) ) { $by_widget[ $wkey ] = array( 'partner' => $r['partner'] ) + $empty_bucket(); }
                $by_widget[ $wkey ]['clicks']++;
                if ( ! isset( $by_page[ $pkey ] ) ) { $by_page[ $pkey ] = array( 'host' => $r['host'], 'page_path' => $r['page_path'] ) + $empty_bucket(); }
                $by_page[ $pkey ]['clicks']++;
                if ( ! isset( $by_article[ $akey ] ) ) { $by_article[ $akey ] = array( 'widget_id' => $r['widget_id'], 'article_id' => (int) $r['article_id'], 'clicks' => 0, 'unique_clickers' => 0 ); }
                $by_article[ $akey ]['clicks']++;
            } elseif ( self::EVENT_WIDGET_ERROR === $r['event_type'] ) {
                if ( ! isset( $by_date[ $today ] ) ) { $by_date[ $today ] = $empty_bucket(); }
                $by_date[ $today ]['errors']++;
                if ( ! isset( $by_widget[ $wkey ] ) ) { $by_widget[ $wkey ] = array( 'partner' => $r['partner'] ) + $empty_bucket(); }
                $by_widget[ $wkey ]['errors']++;
            }
            // Performance (only meaningful for load/render events).
            if ( (int) $r['rest_ms'] > 0 ) {
                if ( ! isset( $by_date[ $today ] ) ) { $by_date[ $today ] = $empty_bucket(); }
                $by_date[ $today ]['rest_sum'] += (int) $r['rest_ms'];
                $by_date[ $today ]['rest_n']++;
            }
            if ( (int) $r['render_ms'] > 0 ) {
                if ( ! isset( $by_date[ $today ] ) ) { $by_date[ $today ] = $empty_bucket(); }
                $by_date[ $today ]['render_sum'] += (int) $r['render_ms'];
                $by_date[ $today ]['render_n']++;
            }
        }

        // Fold distinct visitor counts from raw into the buckets.
        foreach ( $raw_buckets as $key => $sets ) {
            if ( isset( $by_widget[ $key ] ) ) {
                $by_widget[ $key ]['unique_load']     = count( array_unique( $sets['load'] ) );
                $by_widget[ $key ]['unique_view']     = count( array_unique( $sets['view'] ) );
                $by_widget[ $key ]['unique_clickers'] = count( array_unique( $sets['click'] ) );
            }
            if ( isset( $by_page[ $key ] ) ) {
                $by_page[ $key ]['unique_load']     = count( array_unique( $sets['load'] ) );
                $by_page[ $key ]['unique_view']     = count( array_unique( $sets['view'] ) );
                $by_page[ $key ]['unique_clickers'] = count( array_unique( $sets['click'] ) );
            }
        }

        // Unique clickers per article from today's raw.
        $article_clickers = array();
        foreach ( (array) $raw_rows as $r ) {
            if ( self::EVENT_ARTICLE_CLICK === $r['event_type'] ) {
                $akey = $r['widget_id'] . '|' . $r['article_id'];
                $article_clickers[ $akey ][] = $r['visitor_key'];
            }
        }
        foreach ( $article_clickers as $akey => $keys ) {
            if ( isset( $by_article[ $akey ] ) ) {
                $by_article[ $akey ]['unique_clickers'] = count( array_unique( $keys ) );
            }
        }

        // Also merge article clicks from daily (article table).
        $afc = self::filter_clause( $filters );
        $article_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT date, widget_id, article_id, clicks, unique_clickers
                   FROM {$arts}
                  WHERE date BETWEEN %s AND %s AND {$afc['where']}",
                array_merge( array( $start, $end ), $afc['args'] )
            ),
            ARRAY_A
        );
        foreach ( (array) $article_rows as $a ) {
            $akey = $a['widget_id'] . '|' . $a['article_id'];
            if ( ! isset( $by_article[ $akey ] ) ) {
                $by_article[ $akey ] = array( 'widget_id' => $a['widget_id'], 'article_id' => (int) $a['article_id'], 'clicks' => 0, 'unique_clickers' => 0 );
            }
            $by_article[ $akey ]['clicks']          += (int) $a['clicks'];
            $by_article[ $akey ]['unique_clickers'] += (int) $a['unique_clickers'];
        }

        return array(
            'start'      => $start,
            'end'        => $end,
            'today'      => $today,
            'by_date'    => $by_date,
            'by_widget'  => $by_widget,
            'by_page'    => $by_page,
            'by_article' => $by_article,
            'empty_bucket' => $empty_bucket(),
        );
    }

    private static function accumulate_widget( &$by_widget, $widget_id, $partner, $r, $empty_bucket ) {
        if ( ! isset( $by_widget[ $widget_id ] ) ) {
            $by_widget[ $widget_id ] = array( 'partner' => $partner ) + $empty_bucket();
        }
        $b = &$by_widget[ $widget_id ];
        $b['loads']            += (int) $r['loads'];
        $b['unique_load']      += (int) $r['unique_load_visitors'];
        $b['viewable']         += (int) $r['viewable_impressions'];
        $b['unique_view']      += (int) $r['unique_viewable_visitors'];
        $b['clicks']           += (int) $r['clicks'];
        $b['unique_clickers']  += (int) $r['unique_clickers'];
        $b['errors']           += (int) $r['errors'];
        $b['rest_sum']         += (int) $r['rest_ms_sum'];
        $b['rest_n']           += (int) $r['rest_ms_n'];
        $b['render_sum']       += (int) $r['render_ms_sum'];
        $b['render_n']         += (int) $r['render_ms_n'];
        unset( $b );
    }

    private static function accumulate_page( &$by_page, $widget_id, $host, $page_path, $r, $empty_bucket ) {
        $key = $widget_id . '|' . $host . '|' . $page_path;
        if ( ! isset( $by_page[ $key ] ) ) {
            $by_page[ $key ] = array( 'host' => $host, 'page_path' => $page_path ) + $empty_bucket();
        }
        $b = &$by_page[ $key ];
        $b['loads']            += (int) $r['loads'];
        $b['unique_load']      += (int) $r['unique_load_visitors'];
        $b['viewable']         += (int) $r['viewable_impressions'];
        $b['unique_view']      += (int) $r['unique_viewable_visitors'];
        $b['clicks']           += (int) $r['clicks'];
        $b['unique_clickers']  += (int) $r['unique_clickers'];
        $b['errors']           += (int) $r['errors'];
        unset( $b );
    }

    // ------------------------------------------------------------------
    // Derived KPIs / series
    // ------------------------------------------------------------------

    /**
     * Sum a bucket list's metrics.
     *
     * @param array $buckets
     * @return array
     */
    private static function sum_buckets( $buckets ) {
        $totals = array(
            'loads' => 0, 'unique_load' => 0, 'viewable' => 0, 'unique_view' => 0,
            'clicks' => 0, 'unique_clickers' => 0, 'errors' => 0,
            'rest_sum' => 0, 'rest_n' => 0, 'render_sum' => 0, 'render_n' => 0,
        );
        foreach ( $buckets as $b ) {
            foreach ( $totals as $k => $v ) {
                $totals[ $k ] += isset( $b[ $k ] ) ? (int) $b[ $k] : 0;
            }
        }
        return $totals;
    }

    /**
     * Compute the overview KPIs (+ previous period comparison) for a range.
     *
     * @param string $start
     * @param string $end
     * @param array  $filters
     * @return array
     */
    public static function get_kpis( $start, $end, $filters = array() ) {
        $data = self::query_range( $start, $end, $filters );
        $cur  = self::sum_buckets( $data['by_date'] );

        // Previous equal-length window.
        $s = strtotime( $start );
        $e = strtotime( $end );
        $len = max( 1, $e - $s );
        $prev_start = gmdate( 'Y-m-d', $s - $len );
        $prev_end   = gmdate( 'Y-m-d', $e - $len );
        $prev_data  = self::query_range( $prev_start, $prev_end, $filters );
        $prev       = self::sum_buckets( $prev_data['by_date'] );

        $ctr = self::pct( $cur['clicks'], $cur['loads'] );
        $viewability = self::pct( $cur['viewable'], $cur['loads'] );
        $active_widgets = count( $data['by_widget'] );

        return array(
            'raw_loads'             => $cur['loads'],
            'viewable_impressions'  => $cur['viewable'],
            'unique_visitors'       => $cur['unique_load'],
            'clicks'                => $cur['clicks'],
            'unique_clickers'       => $cur['unique_clickers'],
            'ctr'                   => $ctr,
            'viewability_rate'      => $viewability,
            'active_widgets'        => $active_widgets,
            'avg_rest_ms'           => self::avg( $cur['rest_sum'], $cur['rest_n'] ),
            'avg_render_ms'         => self::avg( $cur['render_sum'], $cur['render_n'] ),
            'errors'                => $cur['errors'],
            'prev' => array(
                'raw_loads'            => $prev['loads'],
                'viewable_impressions' => $prev['viewable'],
                'unique_visitors'      => $prev['unique_load'],
                'clicks'               => $prev['clicks'],
                'unique_clickers'      => $prev['unique_clickers'],
                'ctr'                  => self::pct( $prev['clicks'], $prev['loads'] ),
                'viewability_rate'     => self::pct( $prev['viewable'], $prev['loads'] ),
            ),
        );
    }

    /**
     * Build the traffic-over-time series for a range.
     *
     * @param string $start
     * @param string $end
     * @param array  $filters
     * @return array
     */
    public static function get_series( $start, $end, $filters = array() ) {
        $data = self::query_range( $start, $end, $filters );
        $today = $data['today'];

        $days = (int) round( ( strtotime( $end ) - strtotime( $start ) ) / DAY_IN_SECONDS ) + 1;
        $labels = array();
        $buckets_by_label = array();

        if ( 'today' === $start && $end === $today ) {
            // Hourly.
            for ( $h = 0; $h < 24; $h++ ) {
                $label = sprintf( '%02d:00', $h );
                $labels[] = $label;
                $buckets_by_label[ $label ] = $data['empty_bucket'];
            }
            global $wpdb;
            $events = self::table( 'events' );
            $fc = self::filter_clause( $filters );
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT HOUR(created_at) AS h, event_type, COUNT(*) AS n
                       FROM {$events}
                      WHERE DATE(created_at) = %s AND {$fc['where']}
                      GROUP BY HOUR(created_at), event_type",
                    array_merge( array( $today ), $fc['args'] )
                ),
                ARRAY_A
            );
            $hour_counts = array();
            foreach ( (array) $rows as $r ) {
                $l = sprintf( '%02d:00', (int) $r['h'] );
                if ( ! isset( $hour_counts[ $l ] ) ) { $hour_counts[ $l ] = array( 'loads' => 0, 'viewable' => 0, 'clicks' => 0 ); }
                if ( self::EVENT_WIDGET_LOAD === $r['event_type'] ) { $hour_counts[ $l ]['loads'] += (int) $r['n']; }
                elseif ( self::EVENT_VIEWABLE_IMPRESSION === $r['event_type'] ) { $hour_counts[ $l ]['viewable'] += (int) $r['n']; }
                elseif ( self::EVENT_ARTICLE_CLICK === $r['event_type'] ) { $hour_counts[ $l ]['clicks'] += (int) $r['n']; }
            }
            foreach ( $hour_counts as $l => $c ) {
                if ( isset( $buckets_by_label[ $l ] ) ) {
                    $buckets_by_label[ $l ]['loads']    = $c['loads'];
                    $buckets_by_label[ $l ]['viewable'] = $c['viewable'];
                    $buckets_by_label[ $l ]['clicks']   = $c['clicks'];
                }
            }
        } else {
            // Daily (or weekly/monthly for the year range).
            $step = 'day';
            if ( $days > 120 ) { $step = 'month'; }
            elseif ( $days > 90 ) { $step = 'week'; }

            if ( 'day' === $step ) {
                $cursor = strtotime( $start );
                $end_ts = strtotime( $end );
                while ( $cursor <= $end_ts ) {
                    $d = gmdate( 'Y-m-d', $cursor );
                    $labels[] = $d;
                    $buckets_by_label[ $d ] = isset( $data['by_date'][ $d ] ) ? $data['by_date'][ $d ] : $data['empty_bucket'];
                    $cursor = strtotime( '+1 day', $cursor );
                }
            } else {
                // Week or month buckets aggregated from by_date.
                $agg = array();
                foreach ( $data['by_date'] as $d => $b ) {
                    if ( $d < $start || $d > $end ) { continue; }
                    if ( 'week' === $step ) {
                        $wl = gmdate( 'Y-m-d', strtotime( 'monday this week', strtotime( $d ) ) );
                    } else {
                        $wl = substr( $d, 0, 7 ) . '-01';
                    }
                    if ( ! isset( $agg[ $wl ] ) ) { $agg[ $wl ] = $data['empty_bucket']; }
                    foreach ( $agg[ $wl ] as $k => $v ) { $agg[ $wl ][ $k ] += isset( $b[ $k ] ) ? (int) $b[ $k ] : 0; }
                }
                ksort( $agg );
                foreach ( $agg as $l => $b ) { $labels[] = $l; $buckets_by_label[ $l ] = $b; }
            }
        }

        $loads = array();
        $viewable = array();
        $clicks = array();
        $unique = array();
        $ctr = array();
        $view = array();
        foreach ( $labels as $l ) {
            $b = $buckets_by_label[ $l ];
            $loads[]    = (int) $b['loads'];
            $viewable[] = (int) $b['viewable'];
            $clicks[]   = (int) $b['clicks'];
            $unique[]   = (int) $b['unique_load'];
            $ctr[]      = self::pct( $b['clicks'], $b['loads'] );
            $view[]     = self::pct( $b['viewable'], $b['loads'] );
        }

        return array(
            'granularity' => ( 'today' === $start && $end === $today ) ? 'hour' : $step,
            'labels'      => $labels,
            'loads'       => $loads,
            'viewable'    => $viewable,
            'clicks'      => $clicks,
            'unique'      => $unique,
            'ctr_series'  => $ctr,
            'view_series' => $view,
        );
    }

    /**
     * Widget ranking for the range.
     *
     * @param string $start
     * @param string $end
     * @param array  $filters
     * @return array
     */
    public static function get_widget_ranking( $start, $end, $filters = array() ) {
        $data = self::query_range( $start, $end, $filters );
        $widgets = array();
        foreach ( $data['by_widget'] as $id => $b ) {
            $widgets[] = array(
                'widget_id'        => $id,
                'partner'          => isset( $b['partner'] ) ? $b['partner'] : '',
                'loads'            => (int) $b['loads'],
                'viewable'         => (int) $b['viewable'],
                'unique'           => (int) $b['unique_load'],
                'clicks'           => (int) $b['clicks'],
                'unique_clickers'  => (int) $b['unique_clickers'],
                'ctr'              => self::pct( $b['clicks'], $b['loads'] ),
                'viewability'      => self::pct( $b['viewable'], $b['loads'] ),
                'errors'           => (int) $b['errors'],
            );
        }
        // Attach last_seen + status from widget_pages.
        $pages = self::table( 'widget_pages' );
        $status_map = array();
        if ( count( $widgets ) ) {
            $ids = array_map( function ( $w ) { return $w['widget_id']; }, $widgets );
            $placeholders = implode( ',', array_fill( 0, count( $ids ), '%s' ) );
            global $wpdb;
            $rows = $wpdb->get_results(
                $wpdb->prepare( "SELECT widget_id, MAX(last_seen) AS last_seen FROM {$pages} WHERE widget_id IN ({$placeholders}) GROUP BY widget_id", $ids ),
                ARRAY_A
            );
            foreach ( (array) $rows as $r ) {
                $status_map[ $r['widget_id'] ] = $r['last_seen'];
            }
        }
        foreach ( $widgets as &$w ) {
            $ls = isset( $status_map[ $w['widget_id'] ] ) ? $status_map[ $w['widget_id'] ] : '';
            $w['last_seen'] = $ls;
            $w['status']    = self::status_from_last_seen( $ls );
        }
        unset( $w );

        usort( $widgets, function ( $a, $b ) { return $b['loads'] - $a['loads']; } );
        return $widgets;
    }

    /**
     * Page analytics for the range.
     *
     * @param string $start
     * @param string $end
     * @param array  $filters
     * @return array
     */
    public static function get_page_analytics( $start, $end, $filters = array() ) {
        $data = self::query_range( $start, $end, $filters );
        $pages = array();
        foreach ( $data['by_page'] as $key => $b ) {
            $pages[] = array(
                'widget_id'   => $key,
                'host'        => isset( $b['host'] ) ? $b['host'] : '',
                'page_path'   => isset( $b['page_path'] ) ? $b['page_path'] : '',
                'loads'       => (int) $b['loads'],
                'viewable'    => (int) $b['viewable'],
                'unique'      => (int) $b['unique_load'],
                'clicks'      => (int) $b['clicks'],
                'ctr'         => self::pct( $b['clicks'], $b['loads'] ),
                'viewability' => self::pct( $b['viewable'], $b['loads'] ),
            );
        }
        usort( $pages, function ( $a, $b ) { return $b['loads'] - $a['loads']; } );
        return $pages;
    }

    /**
     * Article analytics for the range (with local post title resolution).
     *
     * @param string $start
     * @param string $end
     * @param array  $filters
     * @return array
     */
    public static function get_article_analytics( $start, $end, $filters = array() ) {
        $data = self::query_range( $start, $end, $filters );
        $articles = array();
        foreach ( $data['by_article'] as $key => $b ) {
            $articles[] = array(
                'widget_id'       => $b['widget_id'],
                'article_id'      => (int) $b['article_id'],
                'clicks'          => (int) $b['clicks'],
                'unique_clickers' => (int) $b['unique_clickers'],
                'title'           => self::resolve_article_title( (int) $b['article_id'] ),
            );
        }
        usort( $articles, function ( $a, $b ) { return $b['clicks'] - $a['clicks']; } );
        return $articles;
    }

    /**
     * Partner analytics for the range.
     *
     * @param string $start
     * @param string $end
     * @param array  $filters
     * @return array
     */
    public static function get_partner_analytics( $start, $end, $filters = array() ) {
        $data = self::query_range( $start, $end, $filters );
        $partners = array();
        foreach ( $data['by_widget'] as $id => $b ) {
            $p = isset( $b['partner'] ) ? $b['partner'] : '';
            if ( '' === $p ) { continue; }
            if ( ! isset( $partners[ $p ] ) ) {
                $partners[ $p ] = array( 'partner' => $p, 'loads' => 0, 'viewable' => 0, 'clicks' => 0, 'unique' => 0, 'widgets' => 0 );
            }
            $partners[ $p ]['loads']    += (int) $b['loads'];
            $partners[ $p ]['viewable'] += (int) $b['viewable'];
            $partners[ $p ]['clicks']   += (int) $b['clicks'];
            $partners[ $p ]['unique']   += (int) $b['unique_load'];
            $partners[ $p ]['widgets']++;
        }
        $out = array_values( $partners );
        foreach ( $out as &$p ) {
            $p['ctr']        = self::pct( $p['clicks'], $p['loads'] );
            $p['viewability']= self::pct( $p['viewable'], $p['loads'] );
        }
        unset( $p );
        usort( $out, function ( $a, $b ) { return $b['loads'] - $a['loads']; } );
        return $out;
    }

    /**
     * Status thresholds (central, adjustable).
     *
     * @param string $last_seen MySQL datetime or ''
     * @return string active|idle|removed|unknown
     */
    public static function status_from_last_seen( $last_seen ) {
        $idle_minutes    = (int) apply_filters( 'snw_telemetry_idle_minutes', 60 * 48 );        // ~2 days
        $removed_minutes = (int) apply_filters( 'snw_telemetry_removed_minutes', 60 * 24 * 30 ); // ~30 days

        if ( '' === $last_seen ) {
            return 'unknown';
        }
        $ts = strtotime( $last_seen );
        if ( false === $ts ) {
            return 'unknown';
        }
        $mins = ( time() - $ts ) / 60;
        if ( $mins <= $idle_minutes ) {
            return 'active';
        }
        if ( $mins <= $removed_minutes ) {
            return 'idle';
        }
        return 'removed';
    }

    /**
     * Resolve a local WordPress post title for an article id.
     *
     * @param int $article_id
     * @return string
     */
    public static function resolve_article_title( $article_id ) {
        if ( $article_id < 1 ) {
            return '';
        }
        $post = get_post( $article_id );
        if ( $post && ! empty( $post->post_title ) ) {
            return $post->post_title;
        }
        return '';
    }

    // ------------------------------------------------------------------
    // REST handlers (admin)
    // ------------------------------------------------------------------

    private static function parse_filters( $request ) {
        $p = $request->get_params();
        return array(
            'bots'      => isset( $p['bots'] ) ? $p['bots'] : 'exclude',
            'widget_id' => isset( $p['widget_id'] ) ? $p['widget_id'] : '',
            'partner'   => isset( $p['partner'] ) ? $p['partner'] : '',
            'host'      => isset( $p['host'] ) ? $p['host'] : '',
            'page'      => isset( $p['page'] ) ? $p['page'] : '',
            'article'   => isset( $p['article'] ) ? $p['article'] : '',
        );
    }

    public static function handle_stats( $request ) {
        $p = $request->get_params();
        list( $start, $end ) = self::resolve_range( isset( $p['range'] ) ? $p['range'] : '30', isset( $p['start'] ) ? $p['start'] : '', isset( $p['end'] ) ? $p['end'] : '' );
        $filters = self::parse_filters( $request );
        return new WP_REST_Response(
            array(
                'kpis'   => self::get_kpis( $start, $end, $filters ),
                'series' => self::get_series( $start, $end, $filters ),
                'range'  => array( 'start' => $start, 'end' => $end ),
            ),
            200
        );
    }

    public static function handle_widgets( $request ) {
        $p = $request->get_params();
        list( $start, $end ) = self::resolve_range( isset( $p['range'] ) ? $p['range'] : '30', isset( $p['start'] ) ? $p['start'] : '', isset( $p['end'] ) ? $p['end'] : '' );
        $filters = self::parse_filters( $request );
        return new WP_REST_Response( self::get_widget_ranking( $start, $end, $filters ), 200 );
    }

    public static function handle_pages( $request ) {
        $p = $request->get_params();
        list( $start, $end ) = self::resolve_range( isset( $p['range'] ) ? $p['range'] : '30', isset( $p['start'] ) ? $p['start'] : '', isset( $p['end'] ) ? $p['end'] : '' );
        $filters = self::parse_filters( $request );
        return new WP_REST_Response( self::get_page_analytics( $start, $end, $filters ), 200 );
    }

    public static function handle_articles( $request ) {
        $p = $request->get_params();
        list( $start, $end ) = self::resolve_range( isset( $p['range'] ) ? $p['range'] : '30', isset( $p['start'] ) ? $p['start'] : '', isset( $p['end'] ) ? $p['end'] : '' );
        $filters = self::parse_filters( $request );
        return new WP_REST_Response( self::get_article_analytics( $start, $end, $filters ), 200 );
    }

    public static function handle_partners( $request ) {
        $p = $request->get_params();
        list( $start, $end ) = self::resolve_range( isset( $p['range'] ) ? $p['range'] : '30', isset( $p['start'] ) ? $p['start'] : '', isset( $p['end'] ) ? $p['end'] : '' );
        $filters = self::parse_filters( $request );
        return new WP_REST_Response( self::get_partner_analytics( $start, $end, $filters ), 200 );
    }

    public static function handle_realtime( $request ) {
        global $wpdb;
        $events = self::table( 'events' );
        $since = gmdate( 'Y-m-d H:i:s', time() - 60 * MINUTE_IN_SECONDS );
        $bot_filter = ( 'yes' === get_option( self::OPT_BOT_FILTER, 'yes' ) ) ? ' AND is_bot = 0' : '';
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT
                    SUM(event_type = %s) AS loads,
                    SUM(event_type = %s) AS viewable,
                    SUM(event_type = %s) AS clicks,
                    COUNT(DISTINCT widget_id) AS widgets
                   FROM {$events}
                  WHERE created_at >= %s {$bot_filter}",
                self::EVENT_WIDGET_LOAD,
                self::EVENT_VIEWABLE_IMPRESSION,
                self::EVENT_ARTICLE_CLICK,
                $since
            ),
            ARRAY_A
        );
        return new WP_REST_Response(
            array(
                'loads'   => (int) ( $row['loads'] ?? 0 ),
                'viewable'=> (int) ( $row['viewable'] ?? 0 ),
                'clicks'  => (int) ( $row['clicks'] ?? 0 ),
                'widgets' => (int) ( $row['widgets'] ?? 0 ),
                'since'   => $since,
            ),
            200
        );
    }

    public static function handle_aggregate( $request ) {
        $days = self::rollup_completed_days();
        $cleaned = self::cleanup_retention();
        return new WP_REST_Response(
            array( 'status' => 'ok', 'days_rolled' => $days, 'cleaned' => $cleaned ),
            200
        );
    }

    // ------------------------------------------------------------------
    // CSV export (admin-ajax)
    // ------------------------------------------------------------------

    public static function ajax_export() {
        if ( ! self::admin_only() ) {
            wp_die( '', '', 403 );
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $nonce = isset( $_POST[ SNW_Settings::NONCE_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ SNW_Settings::NONCE_FIELD ] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, SNW_Settings::NONCE_ACTION ) ) {
            wp_die( '', '', 403 );
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $type = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : 'daily';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $range = isset( $_POST['range'] ) ? sanitize_text_field( wp_unslash( $_POST['range'] ) ) : '30';
        list( $start, $end ) = self::resolve_range( $range );

        $filters = self::parse_filters_from_post();

        if ( 'daily' === $type ) {
            $rows = self::export_daily( $start, $end, $filters );
            $cols = array( 'date', 'widget_id', 'partner', 'host', 'page_path', 'loads', 'unique_load_visitors', 'viewable_impressions', 'unique_viewable_visitors', 'clicks', 'unique_clickers', 'errors', 'avg_rest_ms', 'avg_render_ms' );
            $name = 'snw-telemetry-daily.csv';
        } elseif ( 'widgets' === $type ) {
            $rows = self::get_widget_ranking( $start, $end, $filters );
            $cols = array( 'widget_id', 'partner', 'loads', 'viewable', 'unique', 'clicks', 'unique_clickers', 'ctr', 'viewability', 'last_seen', 'status' );
            $name = 'snw-telemetry-widgets.csv';
        } else {
            $rows = self::get_article_analytics( $start, $end, $filters );
            $cols = array( 'widget_id', 'article_id', 'title', 'clicks', 'unique_clickers' );
            $name = 'snw-telemetry-articles.csv';
        }

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $name . '"' );
        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, $cols );
        foreach ( $rows as $row ) {
            $line = array();
            foreach ( $cols as $c ) {
                $v = isset( $row[ $c ] ) ? $row[ $c ] : '';
                if ( is_numeric( $v ) ) { $v = (string) $v; }
                $line[] = $v;
            }
            fputcsv( $out, $line );
        }
        fclose( $out );
        exit;
    }

    private static function parse_filters_from_post() {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $get = function ( $k ) { return isset( $_POST[ $k ] ) ? sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) : ''; };
        return array(
            'bots'      => $get( 'bots' ),
            'widget_id' => $get( 'widget_id' ),
            'partner'   => $get( 'partner' ),
            'host'      => $get( 'host' ),
            'page'      => $get( 'page' ),
            'article'   => $get( 'article' ),
        );
    }

    private static function export_daily( $start, $end, $filters ) {
        $data = self::query_range( $start, $end, $filters );
        $rows = array();
        foreach ( $data['by_date'] as $date => $b ) {
            $rows[] = array(
                'date'                    => $date,
                'widget_id'               => '',
                'partner'                 => '',
                'host'                    => '',
                'page_path'               => '',
                'loads'                   => $b['loads'],
                'unique_load_visitors'    => $b['unique_load'],
                'viewable_impressions'    => $b['viewable'],
                'unique_view'             => $b['unique_view'],
                'clicks'                  => $b['clicks'],
                'unique_clickers'         => $b['unique_clickers'],
                'errors'                  => $b['errors'],
                'avg_rest_ms'             => self::avg( $b['rest_sum'], $b['rest_n'] ),
                'avg_render_ms'           => self::avg( $b['render_sum'], $b['render_n'] ),
            );
        }
        ksort( $rows );
        return $rows;
    }

    // ------------------------------------------------------------------
    // Settings (admin-ajax)
    // ------------------------------------------------------------------

    public static function ajax_save_settings() {
        if ( ! self::admin_only() ) {
            wp_die( '', '', 403 );
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $nonce = isset( $_POST[ SNW_Settings::NONCE_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ SNW_Settings::NONCE_FIELD ] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, SNW_Settings::NONCE_ACTION ) ) {
            wp_send_json_error( 'invalid_nonce' );
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $enabled = isset( $_POST['enabled'] ) ? sanitize_text_field( wp_unslash( $_POST['enabled'] ) ) : 'yes';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $retention = isset( $_POST['retention'] ) ? absint( $_POST['retention'] ) : 90;
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $bot = isset( $_POST['bot_filter'] ) ? sanitize_text_field( wp_unslash( $_POST['bot_filter'] ) ) : 'yes';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $rotation = isset( $_POST['rotation'] ) ? absint( $_POST['rotation'] ) : 1;
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $debug = isset( $_POST['debug'] ) ? absint( $_POST['debug'] ) : 0;

        update_option( self::OPT_ENABLED, $enabled, false );
        update_option( self::OPT_RETENTION, max( 1, min( 3650, $retention ) ), false );
        update_option( self::OPT_BOT_FILTER, $bot, false );
        update_option( self::OPT_ROTATION, max( 1, min( 30, $rotation ) ), false );
        update_option( self::OPT_DEBUG, $debug ? 1 : 0, false );

        wp_send_json_success( array( 'message' => 'gespeichert' ) );
    }

    public static function ajax_purge() {
        if ( ! self::admin_only() ) {
            wp_die( '', '', 403 );
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $nonce = isset( $_POST[ SNW_Settings::NONCE_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ SNW_Settings::NONCE_FIELD ] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, SNW_Settings::NONCE_ACTION ) ) {
            wp_send_json_error( 'invalid_nonce' );
        }
        global $wpdb;
        $wpdb->query( 'DELETE FROM ' . self::table( 'events' ) );
        $wpdb->query( 'DELETE FROM ' . self::table( 'daily' ) );
        $wpdb->query( 'DELETE FROM ' . self::table( 'articles' ) );
        $wpdb->query( 'DELETE FROM ' . self::table( 'widget_pages' ) );
        wp_send_json_success( array( 'message' => 'purged' ) );
    }

    public static function ajax_status() {
        if ( ! self::admin_only() ) {
            wp_die( '', '', 403 );
        }
        wp_send_json_success( self::debug_status() );
    }

    // ------------------------------------------------------------------
    // Small numeric helpers
    // ------------------------------------------------------------------

    public static function pct( $num, $den ) {
        $num = (float) $num;
        $den = (float) $den;
        if ( $den <= 0 ) {
            return 0.0;
        }
        return round( ( $num / $den ) * 100, 2 );
    }

    public static function avg( $sum, $n ) {
        $sum = (float) $sum;
        $n   = (float) $n;
        if ( $n <= 0 ) {
            return 0.0;
        }
        return round( $sum / $n, 1 );
    }

    /**
     * Detailed diagnostics for the admin debug panel.
     *
     * @return array
     */
    public static function debug_status() {
        global $wpdb;
        $events = self::table( 'events' );
        $daily  = self::table( 'daily' );
        $raw_count   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$events}" );
        $daily_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$daily}" );
        $last_agg    = get_option( self::OPT_LAST_AGG, '' );
        $last_run    = (int) get_option( self::OPT_LAST_AGGRUN, 0 );
        return array(
            'schema'              => (int) get_option( self::OPT_SCHEMA_VER, 0 ),
            'enabled'             => get_option( self::OPT_ENABLED, 'yes' ),
            'retention'           => (int) get_option( self::OPT_RETENTION, 90 ),
            'bot_filter'          => get_option( self::OPT_BOT_FILTER, 'yes' ),
            'rotation'            => (int) get_option( self::OPT_ROTATION, 1 ),
            'raw_events'          => $raw_count,
            'daily_rows'          => $daily_count,
            'last_aggregated'     => $last_agg,
            'last_aggregate_run'  => $last_run ? gmdate( 'Y-m-d H:i:s', $last_run ) : '',
            'endpoint'            => rest_url( 'snw-telemetry/v1/event' ),
            'public_alias'        => home_url( '/sn-widget/telemetry/v1/event' ),
            'version'             => SNW_VERSION,
        );
    }
}
