<?php
/**
 * Admin UI: the Widget Builder.
 *
 * Renders a two-column builder (configuration | live preview) that integrates
 * with the WordPress admin look & feel. All heavy lifting (REST queries, live
 * preview, preset CRUD) happens in admin.js; this file only outputs safe,
 * escaped markup and wires up the assets.
 *
 * @package SteigerwaldNewsWidget
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SNW_Admin {

    /**
     * Register the admin menu entry.
     *
     * @return void
     */
    public static function admin_menu() {
        add_menu_page(
            __( 'Steigerwald-News Widget', 'steigerwald-news-widget' ),
            __( 'News Widget', 'steigerwald-news-widget' ),
            'manage_options',
            'steigerwald-news-widget',
            array( __CLASS__, 'render_dashboard' ),
            'dashicons-megaphone',
            30
        );

        add_submenu_page(
            'steigerwald-news-widget',
            __( 'Dashboard', 'steigerwald-news-widget' ),
            __( 'Dashboard', 'steigerwald-news-widget' ),
            'manage_options',
            'steigerwald-news-widget',
            array( __CLASS__, 'render_dashboard' )
        );

        add_submenu_page(
            'steigerwald-news-widget',
            __( 'Widget erstellen', 'steigerwald-news-widget' ),
            __( 'Erstellen', 'steigerwald-news-widget' ),
            'manage_options',
            'steigerwald-news-widget-create',
            array( __CLASS__, 'render_create' )
        );

        add_submenu_page(
            'steigerwald-news-widget',
            __( 'Gespeicherte Widgets', 'steigerwald-news-widget' ),
            __( 'Gespeicherte', 'steigerwald-news-widget' ),
            'manage_options',
            'steigerwald-news-widget-saved',
            array( __CLASS__, 'render_saved' )
        );

        add_submenu_page(
            'steigerwald-news-widget',
            __( 'Partner-Anfragen', 'steigerwald-news-widget' ),
            __( 'Partneranfragen', 'steigerwald-news-widget' ),
            'manage_options',
            'steigerwald-news-widget-requests',
            array( __CLASS__, 'render_requests' )
        );

        add_action( 'admin_enqueue_scripts', array( 'SNW_Assets', 'enqueue_builder' ) );
    }

    /**
     * Render the builder (Erstellen) page.
     *
     * @return void
     */
    public static function render_create() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Keine Berechtigung.', 'steigerwald-news-widget' ) );
        }

        ?>
        <div class="wrap snw-wrap">
            <h1><?php echo esc_html__( 'Steigerwald-News Widget', 'steigerwald-news-widget' ); ?></h1>
            <p class="snw-intro">
                <?php echo esc_html__( 'Erstelle extern einbettbare Nachrichten-Widgets aus deinen WordPress-Beiträgen. Das Widget nutzt ausschließlich die vorhandene WordPress-REST-API – es wird kein eigener Endpunkt registriert.', 'steigerwald-news-widget' ); ?>
            </p>
            <p class="snw-steps">
                <strong><?php echo esc_html__( 'So geht’s:', 'steigerwald-news-widget' ); ?></strong>
                <?php echo esc_html__( '1) Inhalt & Modus wählen · 2) Design anpassen (Vorschau rechts) · 3) Speichern & Einbettungscode kopieren.', 'steigerwald-news-widget' ); ?>
            </p>

            <?php SNW_Builder::render_form( array( 'context' => 'admin' ) ); ?>

            <details class="snw-help">
                <summary><?php echo esc_html__( 'Hilfe & Erklärung', 'steigerwald-news-widget' ); ?></summary>
                <ol>
                    <li><?php echo esc_html__( 'Widget konfigurieren.', 'steigerwald-news-widget' ); ?></li>
                    <li><?php echo esc_html__( 'Vorschau prüfen.', 'steigerwald-news-widget' ); ?></li>
                    <li><?php echo esc_html__( 'Widget speichern.', 'steigerwald-news-widget' ); ?></li>
                    <li><?php echo esc_html__( 'Einbettungscode kopieren.', 'steigerwald-news-widget' ); ?></li>
                    <li><?php echo esc_html__( 'Code auf der Partnerseite einfügen.', 'steigerwald-news-widget' ); ?></li>
                </ol>
                <h3><?php echo esc_html__( 'Partnerkennung', 'steigerwald-news-widget' ); ?></h3>
                <p><?php echo esc_html__( 'Die Partnerkennung wird als utm_source an die Artikellinks angehängt, damit du die Klicks aus dem eingebetteten Widget in deiner Statistik erkennen kannst. Sie erscheint nicht öffentlich als Text.', 'steigerwald-news-widget' ); ?></p>
                <h3><?php echo esc_html__( 'Kategorie & Schlagwörter', 'steigerwald-news-widget' ); ?></h3>
                <p><?php echo esc_html__( 'Du wählst Kategorien und Schlagwörter bequem aus der Liste – Slugs musst du nicht kennen. Das Widget filtert die passenden Beiträge automatisch.', 'steigerwald-news-widget' ); ?></p>
                <h3><?php echo esc_html__( 'Preset vs. Einbettungscode', 'steigerwald-news-widget' ); ?></h3>
                <p><?php echo esc_html__( 'Ein Preset ist deine gespeicherte Konfiguration im WordPress-Backend. Der Einbettungscode ist der HTML-Schnipsel, den du auf der Partnerseite einfügst. Ein Preset kannst du beliebig oft als Code exportieren.', 'steigerwald-news-widget' ); ?></p>
            </details>
            <p class="description" style="margin-top: 24px;">
                <?php
                printf(
                    esc_html__( 'Build with %s', 'steigerwald-news-widget' ),
                    '<a href="' . esc_url( 'https://ottili.one/coder' ) . '" target="_blank" rel="noopener">Ottili Coder</a>'
                );
                ?>
            </p>
        </div>
        <?php
    }

    /**
     * Render the dashboard (home) page with statistics.
     *
     * @return void
     */
    public static function render_dashboard() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Keine Berechtigung.', 'steigerwald-news-widget' ) );
        }

        $presets  = SNW_Presets::get_all();
        $requests = SNW_Requests::get_all();
        $saved_count = count( $presets );

        $pending  = 0;
        $accepted = 0;
        foreach ( $requests as $r ) {
            $s = isset( $r['status'] ) ? $r['status'] : 'pending';
            if ( 'accepted' === $s ) { $accepted++; }
            elseif ( 'pending' === $s ) { $pending++; }
        }

        $post_counts = wp_count_posts( 'post' );
        $published = isset( $post_counts->publish ) ? (int) $post_counts->publish : 0;

        $mode_labels = array(
            'latest'         => __( 'Neueste', 'steigerwald-news-widget' ),
            'category'       => __( 'Kategorie', 'steigerwald-news-widget' ),
            'tags'           => __( 'Schlagwörter', 'steigerwald-news-widget' ),
            'category_tags'  => __( 'Kategorie + Tags', 'steigerwald-news-widget' ),
            'manual'         => __( 'Einzelne Beiträge', 'steigerwald-news-widget' ),
            'hybrid'         => __( 'Hybrid', 'steigerwald-news-widget' ),
        );
        $modes = array();
        foreach ( $presets as $p ) {
            $m = isset( $p['config']['mode'] ) ? $p['config']['mode'] : 'latest';
            $modes[ $m ] = isset( $modes[ $m ] ) ? $modes[ $m ] + 1 : 1;
        }

        $recent_widgets = $presets;
        usort( $recent_widgets, function ( $a, $b ) {
            return strtotime( $b['updated'] ?? '0' ) - strtotime( $a['updated'] ?? '0' );
        });
        $recent_widgets = array_slice( $recent_widgets, 0, 5 );
        $recent_requests = array_slice( $requests, 0, 5 );

        $page_id = (int) get_option( 'snw_builder_page_id', 0 );
        $builder_url = $page_id ? get_permalink( $page_id ) : '';

        ?>
        <div class="wrap snw-wrap">
            <h1><?php echo esc_html__( 'Dashboard', 'steigerwald-news-widget' ); ?></h1>
            <p class="snw-intro">
                <?php echo esc_html__( 'Überblick über deine News-Widgets, Partner-Anfragen und Inhalte.', 'steigerwald-news-widget' ); ?>
            </p>

            <p class="snw-quick">
                <a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=steigerwald-news-widget-create' ) ); ?>"><?php echo esc_html__( 'Neues Widget erstellen', 'steigerwald-news-widget' ); ?></a>
                <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=steigerwald-news-widget-saved' ) ); ?>"><?php echo esc_html__( 'Gespeicherte Widgets', 'steigerwald-news-widget' ); ?></a>
                <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=steigerwald-news-widget-requests' ) ); ?>"><?php echo esc_html__( 'Partneranfragen', 'steigerwald-news-widget' ); ?></a>
                <?php if ( $builder_url ) : ?>
                    <a class="button" href="<?php echo esc_url( $builder_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html__( 'Öffentliche Erstellseite', 'steigerwald-news-widget' ); ?></a>
                <?php endif; ?>
            </p>

            <div class="snw-dash-grid">
                <div class="snw-stat-card">
                    <div class="snw-stat-num"><?php echo esc_html( $saved_count ); ?></div>
                    <div class="snw-stat-label"><?php echo esc_html__( 'Gespeicherte Widgets', 'steigerwald-news-widget' ); ?></div>
                </div>
                <div class="snw-stat-card">
                    <div class="snw-stat-num"><?php echo esc_html( $pending ); ?></div>
                    <div class="snw-stat-label"><?php echo esc_html__( 'Offene Anfragen', 'steigerwald-news-widget' ); ?></div>
                </div>
                <div class="snw-stat-card">
                    <div class="snw-stat-num"><?php echo esc_html( $accepted ); ?></div>
                    <div class="snw-stat-label"><?php echo esc_html__( 'Akzeptierte Anfragen', 'steigerwald-news-widget' ); ?></div>
                </div>
                <div class="snw-stat-card">
                    <div class="snw-stat-num"><?php echo esc_html( $published ); ?></div>
                    <div class="snw-stat-label"><?php echo esc_html__( 'Veröffentlichte Beiträge', 'steigerwald-news-widget' ); ?></div>
                </div>
            </div>

            <div class="snw-dash-cols">
                <div class="snw-card">
                    <h2><?php echo esc_html__( 'Zuletzt gespeicherte Widgets', 'steigerwald-news-widget' ); ?></h2>
                    <?php if ( ! $recent_widgets ) : ?>
                        <p class="description"><?php echo esc_html__( 'Noch keine Widgets gespeichert.', 'steigerwald-news-widget' ); ?></p>
                    <?php else : ?>
                        <ul class="snw-dash-list">
                            <?php foreach ( $recent_widgets as $w ) : ?>
                                <li>
                                    <span><?php echo esc_html( $w['name'] ); ?></span>
                                    <span class="snw-badge"><?php echo esc_html( isset( $mode_labels[ $w['config']['mode'] ?? 'latest' ] ) ? $mode_labels[ $w['config']['mode'] ?? 'latest' ] : ( $w['config']['mode'] ?? '' ) ); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if ( $modes ) : ?>
                        <h3 style="margin-top:20px;"><?php echo esc_html__( 'Verteilung nach Modus', 'steigerwald-news-widget' ); ?></h3>
                        <?php foreach ( $modes as $m => $c ) : ?>
                            <div class="snw-bar-row">
                                <span class="snw-bar-label"><?php echo esc_html( isset( $mode_labels[ $m ] ) ? $mode_labels[ $m ] : $m ); ?></span>
                                <span class="snw-bar-track"><span class="snw-bar-fill" style="width:<?php echo esc_attr( $saved_count ? round( $c / $saved_count * 100 ) : 0 ); ?>%"></span></span>
                                <span class="snw-bar-val"><?php echo esc_html( $c ); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="snw-card">
                    <h2><?php echo esc_html__( 'Letzte Anfragen', 'steigerwald-news-widget' ); ?></h2>
                    <?php if ( ! $recent_requests ) : ?>
                        <p class="description"><?php echo esc_html__( 'Keine Anfragen vorhanden.', 'steigerwald-news-widget' ); ?></p>
                    <?php else : ?>
                        <ul class="snw-dash-list">
                            <?php foreach ( $recent_requests as $r ) : ?>
                                <li>
                                    <span><?php echo esc_html( $r['email'] ?? '' ); ?></span>
                                    <span class="snw-badge snw-badge-<?php echo esc_attr( $r['status'] ?? 'pending' ); ?>"><?php echo esc_html( ucfirst( $r['status'] ?? 'pending' ) ); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <p style="margin-top:16px;">
                            <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=steigerwald-news-widget-requests' ) ); ?>"><?php echo esc_html__( 'Alle Anfragen ansehen', 'steigerwald-news-widget' ); ?></a>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render the saved-presets page.
     *
     * @return void
     */
    public static function render_saved() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Keine Berechtigung.', 'steigerwald-news-widget' ) );
        }
        ?>
        <div class="wrap snw-wrap">
            <h1><?php echo esc_html__( 'Gespeicherte Widgets', 'steigerwald-news-widget' ); ?></h1>
            <p class="snw-intro">
                <?php echo esc_html__( 'Hier verwaltest du deine gespeicherten Widget-Konfigurationen.', 'steigerwald-news-widget' ); ?>
            </p>

            <p class="description"><?php echo esc_html__( 'Presets speichern nur Konfiguration, keine Beiträge.', 'steigerwald-news-widget' ); ?></p>
            <?php echo self::render_presets_table(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
        <?php
    }

    /**
     * Render the partner-requests page.
     *
     * @return void
     */
    public static function render_requests() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Keine Berechtigung.', 'steigerwald-news-widget' ) );
        }
        ?>
        <div class="wrap snw-wrap">
            <h1><?php echo esc_html__( 'Partner-Anfragen', 'steigerwald-news-widget' ); ?></h1>
            <p class="snw-intro">
                <?php echo esc_html__( 'Externe Nutzer können auf der öffentlichen Erstellseite ein Widget gestalten und anfragen. Hier siehst du die Einreichungen, kannst sie annehmen (erzeugt einen domain-gebundenen Einbettungscode) oder ablehnen.', 'steigerwald-news-widget' ); ?>
            </p>

            <p class="snw-requests-settings">
                <label for="snw-builder-slug"><?php echo esc_html__( 'Adresse der Erstellseite', 'steigerwald-news-widget' ); ?></label>
                <code>/</code><input type="text" id="snw-builder-slug" class="regular-text" value="<?php echo esc_attr( get_option( 'snw_builder_slug', 'widget/new' ) ); ?>">
                <button type="button" id="snw-create-page" class="button"><?php echo esc_html__( 'Seite erstellen/aktualisieren', 'steigerwald-news-widget' ); ?></button>
                <span id="snw-page-status" class="snw-status" role="status" aria-live="polite"></span>
            </p>

            <div id="snw-requests-panel" class="snw-requests-panel">
                <button type="button" id="snw-load-requests" class="button"><?php echo esc_html__( 'Anfragen laden', 'steigerwald-news-widget' ); ?></button>
                <div id="snw-requests-list"></div>
                <div id="snw-request-detail"></div>
            </div>
        </div>
        <?php
    }

    /**
     * Render the saved-presets table.
     *
     * @return string
     */
    public static function render_presets_table() {
        $presets = SNW_Presets::get_all();
        $out = '<table class="wp-list-table widefat fixed striped snw-preset-table" id="snw-preset-table">';
        $out .= '<thead><tr>';
        $out .= '<th>' . esc_html__( 'Name', 'steigerwald-news-widget' ) . '</th>';
        $out .= '<th>' . esc_html__( 'Widget-ID', 'steigerwald-news-widget' ) . '</th>';
        $out .= '<th>' . esc_html__( 'Modus', 'steigerwald-news-widget' ) . '</th>';
        $out .= '<th>' . esc_html__( 'Aktionen', 'steigerwald-news-widget' ) . '</th>';
        $out .= '</tr></thead><tbody>';

        if ( ! $presets ) {
            $out .= '<tr class="snw-preset-empty"><td colspan="4">' . esc_html__( 'Noch keine Widgets gespeichert.', 'steigerwald-news-widget' ) . '</td></tr>';
        } else {
            foreach ( $presets as $preset ) {
                $id   = esc_attr( $preset['id'] );
                $name = esc_html( $preset['name'] );
                $mode = isset( $preset['config']['mode'] ) ? esc_html( $preset['config']['mode'] ) : '';
                $out .= '<tr data-preset-id="' . $id . '">';
                $out .= '<td class="snw-preset-name">' . $name . '</td>';
                $out .= '<td class="snw-preset-id">' . $id . '</td>';
                $out .= '<td>' . $mode . '</td>';
                $out .= '<td class="snw-preset-actions">';
                $out .= '<button type="button" class="button snw-act-edit" data-id="' . $id . '">' . esc_html__( 'Bearbeiten', 'steigerwald-news-widget' ) . '</button> ';
                $out .= '<button type="button" class="button snw-act-duplicate" data-id="' . $id . '">' . esc_html__( 'Duplizieren', 'steigerwald-news-widget' ) . '</button> ';
                $out .= '<button type="button" class="button snw-act-copy" data-id="' . $id . '">' . esc_html__( 'Code kopieren', 'steigerwald-news-widget' ) . '</button> ';
                $out .= '<button type="button" class="button snw-act-delete" data-id="' . $id . '">' . esc_html__( 'Löschen', 'steigerwald-news-widget' ) . '</button>';
                $out .= '</td></tr>';
            }
        }

        $out .= '</tbody></table>';
        return $out;
    }
}
