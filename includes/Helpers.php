<?php
/**
 * Helper / utility functions for the Steigerwald-News Widget.
 *
 * No WordPress-Core REST endpoints are registered here. This file only
 * contains pure configuration helpers, sanitizers and validators that are
 * safe to call from admin context. Every public method is defensive.
 *
 * @package SteigerwaldNewsWidget
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SNW_Helpers {

    /**
     * Return the default widget configuration.
     *
     * The shape of this array is the canonical schema for a widget config.
     * Both the admin UI and the public embed use exactly this structure.
     *
     * @return array
     */
    public static function default_config() {
        return array(
            'v'           => 1,
            'api'         => rest_url( 'wp/v2' ),
            'source_name' => get_bloginfo( 'name' ),
            'source_url'  => home_url( '/' ),
            'widget_id'   => '',
            'partner'     => '',
            'title'       => '',
            'mode'        => 'latest',
            'category'    => array(),
            'tags'        => array(),
            'include'     => array(),
            'exclude'     => array(),
            'pinned'      => array(),
            'auto_count'  => 3,
            'limit'       => 5,
            'sort'        => 'newest',
            'layout'      => 'list',
            'show'        => array(
                'image'    => true,
                'date'     => true,
                'category' => false,
                'excerpt'  => true,
                'readmore' => true,
                'branding' => true,
                'author'   => false,
            ),
            'teaser'      => 180,
            'design'      => array(
                'accent'        => '#c59a20',
                'background'    => '',
                'text'          => '',
                'muted'         => '',
                'border'        => '',
                'link'          => '',
                'radius'        => 8,
                'spacing'       => 'normal',
                'typography'    => 'host',
                'columns'       => 2,
                'image_ratio'   => '16:9',
                'image_fit'     => 'cover',
                'image_position' => 'left',
                'date_format'   => 'absolute',
                'heading_level' => 'h3',
                'theme'         => 'light',
                'shadow'        => 'none',
                'align'         => 'left',
                'title_length'  => 0,
                'link_mode'     => 'title',
                'custom_css'    => '',
            ),
            'readmore_label' => '',
            'empty_label'    => '',
            'error_label'    => '',
            'on_error'       => 'message',
            'branding'       => array(
                'image'      => '',
                'image_size' => 32,
                'text'       => 'Nachrichten von',
                'name'       => '',
                'link'       => '',
            ),
        );
    }

    /**
     * Sanitize and validate an incoming config array.
     *
     * Only known keys are kept. Unknown keys are dropped. Every value is
     * type-cast and range-checked so that a malicious or broken payload
     * can never produce invalid output.
     *
     * @param mixed $raw Raw input (usually an array decoded from JSON).
     * @return array Clean, schema-conformant config.
     */
    public static function sanitize_config( $raw ) {
        $default = self::default_config();

        if ( ! is_array( $raw ) ) {
            return $default;
        }

        $cfg = $default;

        // Free-text / url fields.
        if ( isset( $raw['api'] ) && is_string( $raw['api'] ) ) {
            $cfg['api'] = esc_url_raw( trim( $raw['api'] ) );
        }
        if ( isset( $raw['source_url'] ) && is_string( $raw['source_url'] ) ) {
            $cfg['source_url'] = esc_url_raw( trim( $raw['source_url'] ) );
        }
        if ( isset( $raw['source_name'] ) && is_string( $raw['source_name'] ) ) {
            $cfg['source_name'] = sanitize_text_field( $raw['source_name'] );
        }
        if ( isset( $raw['title'] ) && is_string( $raw['title'] ) ) {
            $cfg['title'] = sanitize_text_field( $raw['title'] );
        }
        if ( isset( $raw['partner'] ) && is_string( $raw['partner'] ) ) {
            $cfg['partner'] = self::sanitize_partner( $raw['partner'] );
        }
        if ( isset( $raw['widget_id'] ) && is_string( $raw['widget_id'] ) ) {
            $cfg['widget_id'] = self::sanitize_widget_id( $raw['widget_id'] );
        }

        // Enum: mode.
        $modes = array( 'latest', 'category', 'tags', 'category_tags', 'manual', 'hybrid' );
        if ( isset( $raw['mode'] ) && in_array( $raw['mode'], $modes, true ) ) {
            $cfg['mode'] = $raw['mode'];
        }

        // Integer id lists.
        $cfg['category']   = self::sanitize_id_list( isset( $raw['category'] ) ? $raw['category'] : array() );
        $cfg['tags']       = self::sanitize_id_list( isset( $raw['tags'] ) ? $raw['tags'] : array() );
        $cfg['include']    = self::sanitize_id_list( isset( $raw['include'] ) ? $raw['include'] : array() );
        $cfg['exclude']    = self::sanitize_id_list( isset( $raw['exclude'] ) ? $raw['exclude'] : array() );
        $cfg['pinned']     = self::sanitize_id_list( isset( $raw['pinned'] ) ? $raw['pinned'] : array() );

        // Numeric: limit / auto_count / teaser.
        $cfg['limit']      = self::clamp_int( isset( $raw['limit'] ) ? $raw['limit'] : 5, 1, 20, 5 );
        $cfg['auto_count'] = self::clamp_int( isset( $raw['auto_count'] ) ? $raw['auto_count'] : 3, 0, 20, 3 );
        $cfg['teaser']     = self::clamp_int( isset( $raw['teaser'] ) ? $raw['teaser'] : 180, 0, 600, 180 );

        // Enum: sort.
        $sorts = array( 'newest', 'oldest', 'manual', 'title' );
        if ( isset( $raw['sort'] ) && in_array( $raw['sort'], $sorts, true ) ) {
            $cfg['sort'] = $raw['sort'];
        }

        // Enum: layout.
        $layouts = array( 'list', 'grid', 'cards', 'compact', 'headlines' );
        if ( isset( $raw['layout'] ) && in_array( $raw['layout'], $layouts, true ) ) {
            $cfg['layout'] = $raw['layout'];
        }

        // Boolean show map.
        if ( isset( $raw['show'] ) && is_array( $raw['show'] ) ) {
            foreach ( array( 'image', 'date', 'category', 'excerpt', 'readmore', 'branding', 'author' ) as $key ) {
                if ( isset( $raw['show'][ $key ] ) ) {
                    $cfg['show'][ $key ] = (bool) $raw['show'][ $key ];
                }
            }
        }

        // Branding sub-map (Widget-Quellenhinweis). Individual keys are kept
        // when present so a per-widget override can win; missing keys fall back
        // to the global admin setting at embed time (see apply_branding()).
        if ( isset( $raw['branding'] ) && is_array( $raw['branding'] ) ) {
            $b = $raw['branding'];
            if ( isset( $b['image'] ) && is_string( $b['image'] ) ) {
                $cfg['branding']['image'] = esc_url_raw( trim( $b['image'] ) );
            }
            if ( isset( $b['image_size'] ) ) {
                $cfg['branding']['image_size'] = self::clamp_int( $b['image_size'], 8, 256, 32 );
            }
            if ( isset( $b['text'] ) && is_string( $b['text'] ) ) {
                $cfg['branding']['text'] = sanitize_text_field( $b['text'] );
            }
            if ( isset( $b['name'] ) && is_string( $b['name'] ) ) {
                $cfg['branding']['name'] = sanitize_text_field( $b['name'] );
            }
            if ( isset( $b['link'] ) && is_string( $b['link'] ) ) {
                $cfg['branding']['link'] = esc_url_raw( trim( $b['link'] ) );
            }
        }

        // Design sub-map.
        if ( isset( $raw['design'] ) && is_array( $raw['design'] ) ) {
            $design = $raw['design'];
            $colors = array( 'accent', 'background', 'text', 'muted', 'border', 'link' );
            foreach ( $colors as $c ) {
                if ( isset( $design[ $c ] ) && is_string( $design[ $c ] ) ) {
                    $cfg['design'][ $c ] = self::sanitize_color( $design[ $c ] );
                }
            }
            if ( isset( $design['radius'] ) ) {
                $cfg['design']['radius'] = self::clamp_int( $design['radius'], 0, 32, 8 );
            }
            $spacings = array( 'compact', 'normal', 'spacious' );
            if ( isset( $design['spacing'] ) && in_array( $design['spacing'], $spacings, true ) ) {
                $cfg['design']['spacing'] = $design['spacing'];
            }
            $typos = array( 'host', 'system', 'arial', 'georgia', 'serif', 'sans' );
            if ( isset( $design['typography'] ) && in_array( $design['typography'], $typos, true ) ) {
                $cfg['design']['typography'] = $design['typography'];
            }
            $cfg['design']['columns'] = self::clamp_int( isset( $design['columns'] ) ? $design['columns'] : 2, 1, 4, 2 );
            $ratios = array( '16:9', '4:3', '1:1', '3:2', 'auto' );
            if ( isset( $design['image_ratio'] ) && in_array( $design['image_ratio'], $ratios, true ) ) {
                $cfg['design']['image_ratio'] = $design['image_ratio'];
            }
            $fits = array( 'cover', 'contain' );
            if ( isset( $design['image_fit'] ) && in_array( $design['image_fit'], $fits, true ) ) {
                $cfg['design']['image_fit'] = $design['image_fit'];
            }
            $positions = array( 'left', 'right', 'top' );
            if ( isset( $design['image_position'] ) && in_array( $design['image_position'], $positions, true ) ) {
                $cfg['design']['image_position'] = $design['image_position'];
            }
            $date_formats = array( 'absolute', 'relative' );
            if ( isset( $design['date_format'] ) && in_array( $design['date_format'], $date_formats, true ) ) {
                $cfg['design']['date_format'] = $design['date_format'];
            }
            $levels = array( 'h2', 'h3', 'h4' );
            if ( isset( $design['heading_level'] ) && in_array( $design['heading_level'], $levels, true ) ) {
                $cfg['design']['heading_level'] = $design['heading_level'];
            }
            $themes = array( 'light', 'dark' );
            if ( isset( $design['theme'] ) && in_array( $design['theme'], $themes, true ) ) {
                $cfg['design']['theme'] = $design['theme'];
            }
            $shadows = array( 'none', 'sm', 'md', 'lg' );
            if ( isset( $design['shadow'] ) && in_array( $design['shadow'], $shadows, true ) ) {
                $cfg['design']['shadow'] = $design['shadow'];
            }
            $separators = array( 'line', 'none' );
            if ( isset( $design['separator'] ) && in_array( $design['separator'], $separators, true ) ) {
                $cfg['design']['separator'] = $design['separator'];
            }
            $aligns = array( 'left', 'center', 'right' );
            if ( isset( $design['align'] ) && in_array( $design['align'], $aligns, true ) ) {
                $cfg['design']['align'] = $design['align'];
            }
            $cfg['design']['title_length'] = self::clamp_int( isset( $design['title_length'] ) ? $design['title_length'] : 0, 0, 200, 0 );
            $link_modes = array( 'title', 'card' );
            if ( isset( $design['link_mode'] ) && in_array( $design['link_mode'], $link_modes, true ) ) {
                $cfg['design']['link_mode'] = $design['link_mode'];
            }
            if ( isset( $design['custom_css'] ) && is_string( $design['custom_css'] ) ) {
                $cfg['design']['custom_css'] = self::sanitize_css( $design['custom_css'] );
            }
        }

        // Enum: on_error.
        if ( isset( $raw['on_error'] ) && in_array( $raw['on_error'], array( 'message', 'hide' ), true ) ) {
            $cfg['on_error'] = $raw['on_error'];
        }

        // Free-text labels (localization of the public messages).
        if ( isset( $raw['readmore_label'] ) && is_string( $raw['readmore_label'] ) ) {
            $cfg['readmore_label'] = sanitize_text_field( substr( $raw['readmore_label'], 0, 80 ) );
        }
        if ( isset( $raw['empty_label'] ) && is_string( $raw['empty_label'] ) ) {
            $cfg['empty_label'] = sanitize_text_field( substr( $raw['empty_label'], 0, 160 ) );
        }
        if ( isset( $raw['error_label'] ) && is_string( $raw['error_label'] ) ) {
            $cfg['error_label'] = sanitize_text_field( substr( $raw['error_label'], 0, 160 ) );
        }

        $cfg['v'] = 1;

        return $cfg;
    }

    /**
     * Merge the global admin branding setting into a widget config.
     *
     * Branding is a site-wide setting configured in the admin. It is injected at
     * embed/output time (not stored inside each preset) so changes propagate to
     * already-embedded partner sites without re-copying the snippet. A key that
     * is already present on the config wins; only empty/missing keys are filled
     * from the global setting.
     *
     * @param array $config
     * @return array
     */
    public static function apply_branding( $config ) {
        if ( ! is_array( $config ) ) {
            return $config;
        }
        if ( ! class_exists( 'SNW_Settings' ) || ! method_exists( 'SNW_Settings', 'get_branding' ) ) {
            return $config;
        }
        $global = SNW_Settings::get_branding();
        $b      = ( isset( $config['branding'] ) && is_array( $config['branding'] ) ) ? $config['branding'] : array();
        foreach ( array( 'image', 'image_size', 'text', 'name', 'link' ) as $key ) {
            if ( ! isset( $b[ $key ] ) || $b[ $key ] === '' || $b[ $key ] === null ) {
                $b[ $key ] = isset( $global[ $key ] ) ? $global[ $key ] : '';
            }
        }
        $config['branding'] = $b;
        return $config;
    }

    /**
     * Sanitize a partner slug used for UTM tracking.
     *
     * @param string $value
     * @return string
     */
    public static function sanitize_partner( $value ) {
        $value = strtolower( trim( (string) $value ) );
        $value = preg_replace( '/[^a-z0-9\-_]/', '', $value );
        return substr( $value, 0, 60 );
    }

    /**
     * Sanitize a widget id of the form SNW-XXXX.
     *
     * @param string $value
     * @return string
     */
    public static function sanitize_widget_id( $value ) {
        $value = strtoupper( trim( (string) $value ) );
        if ( preg_match( '/^SNW-[A-Z0-9]{4,8}$/', $value ) ) {
            return $value;
        }
        return '';
    }

    /**
     * Sanitize a color value. Allows hex, rgb(), rgba() and empty.
     *
     * @param string $value
     * @return string
     */
    public static function sanitize_color( $value ) {
        $value = trim( (string) $value );
        if ( '' === $value ) {
            return '';
        }
        if ( preg_match( '/^#([a-f0-9]{3}|[a-f0-9]{4}|[a-f0-9]{6}|[a-f0-9]{8})$/i', $value ) ) {
            return $value;
        }
        if ( preg_match( '/^rgba?\(\s*[\d.,%\s]+\)$/i', $value ) ) {
            return $value;
        }
        return '';
    }

    /**
     * Sanitize free-form custom CSS for the "pro" styling box.
     *
     * We never execute it; it is injected inside a scoped <style> on the
     * embed. We strip control characters and any angle brackets so the value
     * can neither break out of the style element nor smuggle markup, and we
     * cap the length to keep payloads sane.
     *
     * @param string $value
     * @return string
     */
    public static function sanitize_css( $value ) {
        $value = (string) $value;
        $value = preg_replace( '/[\x00-\x1F\x7F<>]/', '', $value );
        // Block external stylesheet inclusion via @import.
        $value = preg_replace( '/@import\b[^;]*;?/i', '', $value );
        return substr( $value, 0, 4000 );
    }

    /**
     * Cast/clean a list of post/term ids to a list of positive integers.
     *
     * @param mixed $list
     * @return array
     */
    public static function sanitize_id_list( $list ) {
        if ( ! is_array( $list ) ) {
            return array();
        }
        $out = array();
        foreach ( $list as $item ) {
            if ( is_numeric( $item ) ) {
                $id = (int) $item;
                if ( $id > 0 ) {
                    $out[] = $id;
                }
            }
        }
        // De-duplicate while preserving order.
        return array_values( array_unique( $out ) );
    }

    /**
     * Clamp an integer between min/max with a fallback default.
     *
     * @param mixed $value
     * @param int   $min
     * @param int   $max
     * @param int   $default
     * @return int
     */
    public static function clamp_int( $value, $min, $max, $default ) {
        $int = absint( $value );
        // A legitimate 0 (e.g. auto_count = 0 = "no automatic posts") must be
        // preserved when 0 is within range. Only fall back to the default when
        // the value is genuinely out of bounds below the minimum.
        if ( $int < $min ) {
            return ( $min > 0 ) ? $min : $default;
        }
        if ( $int > $max ) {
            return $max;
        }
        return $int;
    }

    /**
     * Generate a robust, unique widget id like SNW-8F3K2.
     *
     * @param array $existing List of already-used ids to avoid collisions.
     * @return string
     */
    public static function generate_widget_id( $existing = array() ) {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $existing = (array) $existing;
        $max_tries = 50;
        for ( $i = 0; $i < $max_tries; $i++ ) {
            $suffix = '';
            for ( $j = 0; $j < 5; $j++ ) {
                $suffix .= $alphabet[ wp_rand( 0, strlen( $alphabet ) - 1 ) ];
            }
            $id = 'SNW-' . $suffix;
            if ( ! in_array( $id, $existing, true ) ) {
                return $id;
            }
        }
        // Extremely unlikely fallback.
        return 'SNW-' . substr( md5( (string) wp_rand() ), 0, 5 );
    }

    /**
     * Truncate a plain-text string to a word boundary, leaving room for an
     * ellipsis. Used as a server-side helper; the public widget does its own
     * truncation in JS so no full content is shipped.
     *
     * @param string $text
     * @param int    $length
     * @return string
     */
    public static function truncate_text( $text, $length = 180 ) {
        $text = trim( preg_replace( '/\s+/', ' ', (string) $text ) );
        if ( mb_strlen( $text ) <= $length ) {
            return $text;
        }
        $trimmed = mb_substr( $text, 0, $length );
        $last_space = mb_strrpos( $trimmed, ' ' );
        if ( false !== $last_space ) {
            $trimmed = mb_substr( $trimmed, 0, $last_space );
        }
        return rtrim( $trimmed ) . '…';
    }

    /**
     * Normalize a user-supplied domain (bare host or full URL) to a lower-case
     * host without port. Returns an empty string when the value is not a
     * plausible registered host.
     *
     * @param string $value
     * @return string
     */
    public static function sanitize_domain( $value ) {
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
        if ( ! preg_match( '/^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/i', $host ) ) {
            return '';
        }
        return $host;
    }

    /**
     * Decide whether a request host is permitted for an approved domain.
     *
     * Exact match and subdomains of the approved domain are allowed
     * (e.g. news.example.com for example.com); any other host is rejected.
     *
     * @param string $allowed Approved domain (host).
     * @param string $host    Request host (may include port).
     * @return bool
     */
    public static function domain_allowed( $allowed, $host ) {
        $allowed = strtolower( trim( (string) $allowed ) );
        $host    = strtolower( trim( (string) $host ) );
        $host    = preg_replace( '/:\d+$/', '', $host );
        if ( '' === $allowed || '' === $host ) {
            return false;
        }
        if ( $host === $allowed ) {
            return true;
        }
        return substr( $host, - strlen( '.' . $allowed ) ) === '.' . $allowed;
    }
}
