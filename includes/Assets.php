<?php
/**
 * Asset registration and localization.
 *
 * The public widget.js is intentionally also loaded on the admin builder page
 * so the live preview renders through the exact same code path as the real
 * embed. The admin builder itself uses WordPress-core APIs (including the
 * color picker), but no front-end framework.
 *
 * @package SteigerwaldNewsWidget
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SNW_Assets {

    const ADMIN_JS_HANDLE  = 'snw-admin';
    const ADMIN_CSS_HANDLE = 'snw-admin-css';
    const WIDGET_JS_HANDLE = 'snw-widget';
    const STATS_JS_HANDLE  = 'snw-stats';
    const STATS_CSS_HANDLE = 'snw-stats-css';

    /**
     * Build the localization object shared with admin.js.
     *
     * @param string $ajax_url
     * @param string $nonce
     * @return array
     */
    public static function admin_l10n( $ajax_url, $nonce ) {
        return array(
            'ajaxUrl'      => $ajax_url,
            'nonce'       => $nonce,
            'nonceField'  => SNW_Settings::NONCE_FIELD,
            'apiBase'     => rest_url( 'wp/v2' ),
            'adminAjax'   => admin_url( 'admin-ajax.php' ),
            'widgetJsUrl' => SNW_Embed_Generator::script_url(),
            'sourceName'  => get_bloginfo( 'name' ),
            'sourceUrl'   => home_url( '/' ),
            'isAdmin'     => true,
            'restRequestUrl' => esc_url_raw( rest_url( 'snw/v1/request' ) ),
            'defaultConfig' => SNW_Helpers::default_config(),
            'presets'     => SNW_Presets::get_all(),
            'branding'    => SNW_Settings::get_branding(),
            'i18n'        => array(
                'saveOk'        => __( 'Widget gespeichert.', 'steigerwald-news-widget' ),
                'saveError'     => __( 'Speichern fehlgeschlagen.', 'steigerwald-news-widget' ),
                'brandingSaved' => __( 'Branding gespeichert.', 'steigerwald-news-widget' ),
                'brandingError' => __( 'Speichern fehlgeschlagen.', 'steigerwald-news-widget' ),
                'selectImage'   => __( 'Bild auswählen', 'steigerwald-news-widget' ),
                'brandingText'  => __( 'Nachrichten von', 'steigerwald-news-widget' ),
                'confirmDelete' => __( 'Dieses Widget wirklich löschen?', 'steigerwald-news-widget' ),
                'copied'        => __( 'Code kopiert.', 'steigerwald-news-widget' ),
                'copyError'     => __( 'Kopieren nicht möglich – bitte manuell auswählen.', 'steigerwald-news-widget' ),
                'loading'       => __( 'Nachrichten werden geladen …', 'steigerwald-news-widget' ),
                'empty'         => __( 'Aktuell sind keine passenden Beiträge vorhanden.', 'steigerwald-news-widget' ),
                'error'         => __( 'Die Nachrichten konnten gerade nicht geladen werden.', 'steigerwald-news-widget' ),
                'untitled'      => __( 'Beitrag', 'steigerwald-news-widget' ),
                'readmore'      => __( 'Artikel lesen', 'steigerwald-news-widget' ),
                'searchPosts'   => __( 'Artikel suchen …', 'steigerwald-news-widget' ),
                'searchTags'    => __( 'Schlagwort suchen …', 'steigerwald-news-widget' ),
                'noTags'        => __( 'Keine Schlagwörter gefunden.', 'steigerwald-news-widget' ),
                'code'          => __( 'Code', 'steigerwald-news-widget' ),
                'email'         => __( 'E-Mail', 'steigerwald-news-widget' ),
                'domain'        => __( 'Domain', 'steigerwald-news-widget' ),
                'created'       => __( 'Erstellt', 'steigerwald-news-widget' ),
                'lastSeen'      => __( 'Letzte Aktivität', 'steigerwald-news-widget' ),
                'status'        => __( 'Status', 'steigerwald-news-widget' ),
                'partnerEmpty'  => __( 'Noch keine Partner freigegeben.', 'steigerwald-news-widget' ),
                'search'        => __( 'Suche', 'steigerwald-news-widget' ),
                'filterStatus'  => __( 'Status', 'steigerwald-news-widget' ),
                'all'           => __( 'Alle', 'steigerwald-news-widget' ),
                'loadPartners'  => __( 'Partner laden', 'steigerwald-news-widget' ),
            ),
        );
    }

    /**
     * Dispatch the correct asset bundle per admin screen.
     *
     * @param string $hook Page hook suffix for our admin menu entry.
     * @return void
     */
    public static function enqueue_admin( $hook ) {
        $is_plugin    = ( false !== strpos( $hook, 'steigerwald-news-widget' ) );
        $is_dashboard = ( 'index.php' === $hook );

        if ( ! $is_plugin && ! $is_dashboard ) {
            return;
        }

        // The WordPress dashboard needs the admin stylesheet so the plugin's
        // dashboard widget (KPI cards) is styled; no builder scripts required.
        if ( $is_dashboard ) {
            wp_enqueue_style(
                self::ADMIN_CSS_HANDLE,
                SNW_URL . 'admin/css/admin.css',
                array(),
                SNW_VERSION
            );
            return;
        }

        if ( false !== strpos( $hook, 'steigerwald-news-widget-stats' ) ) {
            self::enqueue_stats();
        } else {
            self::enqueue_builder( $hook );
        }

        if ( false !== strpos( $hook, 'steigerwald-news-widget-settings' ) ) {
            wp_enqueue_media();
        }
    }

    /**
     * Enqueue assets for the builder page only.
     *
     * @param string $hook Page hook suffix for our admin menu entry.
     * @return void
     */
    public static function enqueue_builder( $hook ) {
        if ( empty( $hook ) || false === strpos( $hook, 'steigerwald-news-widget' ) ) {
            return;
        }

        // Public widget renderer (for the live preview) + its base styles.
        wp_enqueue_script(
            self::WIDGET_JS_HANDLE,
            SNW_Embed_Generator::script_url(),
            array(),
            SNW_VERSION,
            true
        );

        wp_enqueue_style(
            'snw-widget-css',
            SNW_URL . 'public/css/widget.css',
            array(),
            SNW_VERSION
        );

        // Admin builder styles + script.
        wp_enqueue_style(
            self::ADMIN_CSS_HANDLE,
            SNW_URL . 'admin/css/admin.css',
            array( 'wp-color-picker' ),
            SNW_VERSION
        );

        wp_enqueue_script(
            self::ADMIN_JS_HANDLE,
            SNW_URL . 'admin/js/admin.js',
            array( 'jquery', 'wp-color-picker', self::WIDGET_JS_HANDLE ),
            SNW_VERSION,
            true
        );

        wp_localize_script(
            self::ADMIN_JS_HANDLE,
            'SNW_Admin',
            self::admin_l10n( admin_url( 'admin-ajax.php' ), wp_create_nonce( SNW_Settings::NONCE_ACTION ) )
        );
    }

    /**
     * Enqueue assets for the Statistik dashboard.
     *
     * @return void
     */
    public static function enqueue_stats() {
        wp_enqueue_style(
            self::STATS_CSS_HANDLE,
            SNW_URL . 'admin/css/stats.css',
            array(),
            SNW_VERSION
        );

        wp_enqueue_script(
            self::STATS_JS_HANDLE,
            SNW_URL . 'admin/js/stats.js',
            array( 'jquery' ),
            SNW_VERSION,
            true
        );

        wp_localize_script(
            self::STATS_JS_HANDLE,
            'SNW_Stats',
            self::stats_l10n()
        );
    }

    /**
     * Build the localization object for the Statistik dashboard.
     *
     * @return array
     */
    public static function stats_l10n() {
        return array(
            'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
            'nonce'       => wp_create_nonce( SNW_Settings::NONCE_ACTION ),
            'nonceField'  => SNW_Settings::NONCE_FIELD,
            'restUrl'     => rest_url( 'snw-telemetry/v1' ),
            'restNonce'   => wp_create_nonce( 'wp_rest' ),
            'publicAlias' => home_url( '/sn-widget/telemetry/v1/event' ),
            'endpoint'    => rest_url( 'snw-telemetry/v1/event' ),
            'version'     => SNW_VERSION,
            'i18n'        => array(
                'loading'        => __( 'Wird geladen …', 'steigerwald-news-widget' ),
                'error'          => __( 'Daten konnten nicht geladen werden.', 'steigerwald-news-widget' ),
                'noData'         => __( 'Noch keine Daten für diesen Zeitraum.', 'steigerwald-news-widget' ),
                'rawLoads'       => __( 'Raw Loads', 'steigerwald-news-widget' ),
                'viewable'       => __( 'Viewable Impressions', 'steigerwald-news-widget' ),
                'visitors'       => __( 'Unique Visitors', 'steigerwald-news-widget' ),
                'clicks'         => __( 'Clicks', 'steigerwald-news-widget' ),
                'uniqueClickers' => __( 'Unique Clickers', 'steigerwald-news-widget' ),
                'ctr'            => __( 'CTR', 'steigerwald-news-widget' ),
                'viewability'    => __( 'Viewability', 'steigerwald-news-widget' ),
                'activeWidgets'  => __( 'Active Widgets', 'steigerwald-news-widget' ),
                'builderUses'    => __( 'Builder-Nutzungen', 'steigerwald-news-widget' ),
                'widget'         => __( 'Widget', 'steigerwald-news-widget' ),
                'partner'        => __( 'Partner', 'steigerwald-news-widget' ),
                'host'           => __( 'Host', 'steigerwald-news-widget' ),
                'page'           => __( 'Seite', 'steigerwald-news-widget' ),
                'loads'          => __( 'Loads', 'steigerwald-news-widget' ),
                'unique'         => __( 'Unique', 'steigerwald-news-widget' ),
                'lastSeen'       => __( 'Last Seen', 'steigerwald-news-widget' ),
                'status'         => __( 'Status', 'steigerwald-news-widget' ),
                'article'        => __( 'Artikel', 'steigerwald-news-widget' ),
                'title'          => __( 'Titel', 'steigerwald-news-widget' ),
                'saved'          => __( 'Einstellungen gespeichert.', 'steigerwald-news-widget' ),
                'purged'         => __( 'Alle Telemetriedaten gelöscht.', 'steigerwald-news-widget' ),
                'aggregated'     => __( 'Aggregation ausgeführt.', 'steigerwald-news-widget' ),
                'exportDaily'    => __( 'CSV Tagesstatistik', 'steigerwald-news-widget' ),
                'exportWidgets'  => __( 'CSV Widget-Statistik', 'steigerwald-news-widget' ),
                'exportArticles' => __( 'CSV Artikel-Klicks', 'steigerwald-news-widget' ),
                'active'         => __( 'Aktiv', 'steigerwald-news-widget' ),
                'idle'           => __( 'Wenig genutzt', 'steigerwald-news-widget' ),
                'removed'        => __( 'Wahrscheinlich entfernt', 'steigerwald-news-widget' ),
                'unknown'        => __( 'Unbekannt', 'steigerwald-news-widget' ),
            ),
        );
    }
}
