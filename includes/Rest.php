<?php
/**
 * Custom REST endpoints for partner intake.
 *
 * Two endpoints live under the `snw/v1` namespace:
 *
 *  - POST /snw/v1/request   Public widget-intake submission. Rate limited to
 *                            `SNW_Requests::RATE_LIMIT` requests per hour per IP.
 *                            No authentication — it is a public contact-style
 *                            endpoint. A frontend nonce is intentionally NOT
 *                            required because the caller is an unauthenticated
 *                            visitor; abuse is bounded by the rate limit.
 *
 *  - GET  /snw/v1/widget/<code>  Returns the configuration for an accepted,
 *                            domain-locked widget. Enforces the stored
 *                            `allowed_domain` against the request Origin /
 *                            Referer host, and emits the matching CORS header so
 *                            the embed only loads on the approved site.
 *
 * @package SteigerwaldNewsWidget
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SNW_REST {

    /**
     * Register the REST routes.
     *
     * @return void
     */
    public static function init() {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
    }

    /**
     * Register routes.
     *
     * @return void
     */
    public static function register_routes() {
        register_rest_route(
            'snw/v1',
            '/request',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( __CLASS__, 'handle_request' ),
                'permission_callback' => '__return_true',
            )
        );

        register_rest_route(
            'snw/v1',
            '/widget/(?P<code>[A-Za-z0-9_-]+)',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'handle_widget' ),
                'permission_callback' => '__return_true',
            )
        );
    }

    /**
     * Public submission handler.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public static function handle_request( $request ) {
        $ip = self::client_ip();
        if ( SNW_Requests::rate_limited( $ip ) ) {
            return new WP_Error(
                'snw_rate_limited',
                __( 'Zu viele Anfragen. Bitte versuche es in einer Stunde erneut.', 'steigerwald-news-widget' ),
                array( 'status' => 429 )
            );
        }

        $body = $request->get_json_params();
        if ( ! is_array( $body ) ) {
            $body = array();
        }

        $name   = isset( $body['name'] ) ? sanitize_text_field( substr( (string) $body['name'], 0, 100 ) ) : '';
        $email  = isset( $body['email'] ) ? sanitize_email( (string) $body['email'] ) : '';
        $domain = isset( $body['domain'] ) ? SNW_Helpers::sanitize_domain( (string) $body['domain'] ) : '';

        if ( ! is_email( $email ) ) {
            return new WP_Error(
                'snw_invalid_email',
                __( 'Bitte eine gültige E-Mail-Adresse angeben.', 'steigerwald-news-widget' ),
                array( 'status' => 400 )
            );
        }
        if ( '' === $domain ) {
            return new WP_Error(
                'snw_invalid_domain',
                __( 'Bitte eine gültige Domain angeben.', 'steigerwald-news-widget' ),
                array( 'status' => 400 )
            );
        }

        $raw_config = isset( $body['config'] ) && is_array( $body['config'] ) ? $body['config'] : array();
        $config     = SNW_Helpers::sanitize_config( $raw_config );

        $id = SNW_Requests::add( $name, $email, $domain, $config );

        return new WP_REST_Response(
            array(
                'success' => true,
                'id'      => $id,
            ),
            201
        );
    }

    /**
     * Domain-enforced widget config delivery.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public static function handle_widget( $request ) {
        $code   = $request['code'];
        $preset = SNW_Presets::get( $code );
        if ( ! $preset || empty( $preset['config'] ) ) {
            return new WP_Error(
                'snw_not_found',
                __( 'Widget nicht gefunden.', 'steigerwald-news-widget' ),
                array( 'status' => 404 )
            );
        }

        $allowed = isset( $preset['allowed_domain'] ) ? $preset['allowed_domain'] : '';

        if ( '' !== $allowed ) {
            $origin  = isset( $_SERVER['HTTP_ORIGIN'] ) ? (string) $_SERVER['HTTP_ORIGIN'] : '';
            $referer = isset( $_SERVER['HTTP_REFERER'] ) ? (string) $_SERVER['HTTP_REFERER'] : '';
            $host    = self::host_from_origin( $origin );
            if ( '' === $host ) {
                $host = self::host_from_url( $referer );
            }

            // No Origin/Referer (e.g. same-origin or server tooling) is allowed;
            // an explicit cross-origin host must match the approved domain.
            if ( '' !== $host && ! SNW_Helpers::domain_allowed( $allowed, $host ) ) {
                return new WP_Error(
                    'snw_forbidden',
                    __( 'Dieses Widget ist nicht für diese Domain freigegeben.', 'steigerwald-news-widget' ),
                    array( 'status' => 403 )
                );
            }

            if ( '' !== $origin ) {
                header( 'Access-Control-Allow-Origin: ' . $origin );
                header( 'Access-Control-Allow-Methods: GET' );
                header( 'Vary: Origin' );
            }
        }

        $config = $preset['config'];
        if ( '' !== $allowed ) {
            $config['allowed_domain'] = $allowed;
        }

        return new WP_REST_Response( $config, 200 );
    }

    /**
     * Extract a normalized host from an Origin header.
     *
     * @param string $origin
     * @return string
     */
    public static function host_from_origin( $origin ) {
        if ( '' === trim( (string) $origin ) ) {
            return '';
        }
        return self::host_from_url( $origin );
    }

    /**
     * Extract a normalized host from any URL (Origin or Referer).
     *
     * @param string $url
     * @return string
     */
    public static function host_from_url( $url ) {
        $url = trim( (string) $url );
        if ( '' === $url ) {
            return '';
        }
        $host = wp_parse_url( $url, PHP_URL_HOST );
        if ( ! $host ) {
            return '';
        }
        $host = strtolower( $host );
        return preg_replace( '/:\d+$/', '', $host );
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
}
