<?php
/**
 * Embed code generator.
 *
 * Produces a compact, copy-pasteable embed snippet. The full configuration is
 * encoded as a single base64url JSON string inside `data-config`, so the
 * fragment stays small and the partner never has to install the plugin or
 * understand WordPress internals.
 *
 * No central SaaS config platform is involved — the widget stays stateless.
 *
 * @package SteigerwaldNewsWidget
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SNW_Embed_Generator {

    /**
     * Encode a config array as a URL-safe base64 JSON string.
     *
     * @param array $config
     * @return string
     */
    public static function encode_config( $config ) {
        $json    = wp_json_encode( $config );
        $b64     = base64_encode( $json ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
        return rtrim( strtr( $b64, '+/', '-_' ), '=' );
    }

    /**
     * Decode a previously encoded config string.
     *
     * @param string $encoded
     * @return array|null
     */
    public static function decode_config( $encoded ) {
        $b64 = strtr( (string) $encoded, '-_', '+/' );
        // Restore padding.
        $pad = strlen( $b64 ) % 4;
        if ( $pad ) {
            $b64 .= str_repeat( '=', 4 - $pad );
        }
        $json = base64_decode( $b64, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
        if ( false === $json ) {
            return null;
        }
        $data = json_decode( $json, true );
        if ( ! is_array( $data ) ) {
            return null;
        }
        return SNW_Helpers::sanitize_config( $data );
    }

    /**
     * Build the full HTML embed snippet for a saved preset / config.
     *
     * @param array  $config     Sanitized config.
     * @param string $script_url Absolute URL to the public widget.js.
     * @return string
     */
    public static function generate( $config, $script_url ) {
        $config      = SNW_Helpers::sanitize_config( $config );
        $encoded     = self::encode_config( $config );
        $script_url  = esc_url( $script_url );

        return sprintf(
            '<div class="steigerwald-news-widget" data-config="%s"></div>%s<script src="%s" async></script>',
            esc_attr( $encoded ),
            "\n",
            $script_url
        );
    }

    /**
     * Build the standalone public script URL. Exposed so the admin UI and the
     * embed generator agree on a single source of truth.
     *
     * @return string
     */
    public static function script_url() {
        return SNW_URL . 'public/js/widget.js';
    }
}
