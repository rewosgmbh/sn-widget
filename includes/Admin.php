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
        $hook = add_options_page(
            __( 'Steigerwald-News Widget', 'steigerwald-news-widget' ),
            __( 'News Widget', 'steigerwald-news-widget' ),
            'manage_options',
            'steigerwald-news-widget',
            array( __CLASS__, 'render_page' )
        );

        add_action( 'admin_enqueue_scripts', array( 'SNW_Assets', 'enqueue_builder' ) );
    }

    /**
     * Render the builder page.
     *
     * @return void
     */
    public static function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Keine Berechtigung.', 'steigerwald-news-widget' ) );
        }

        ?>
        <div class="wrap snw-wrap">
            <h1><?php echo esc_html__( 'Steigerwald-News Widget', 'steigerwald-news-widget' ); ?></h1>
            <p class="snw-intro">
                <?php echo esc_html__( 'Erstelle extern einbettbare Nachrichten-Widgets aus deinen WordPress-Beiträgen. Das Widget nutzt ausschließlich die vorhandene WordPress-REST-API – es wird kein eigener Endpunkt registriert.', 'steigerwald-news-widget' ); ?>
            </p>

            <div class="snw-builder">
                <!-- ============ Configuration column ============ -->
                <form id="snw-builder-form" class="snw-builder__config" autocomplete="off">
                    <section class="snw-card">
                        <h2><?php echo esc_html__( 'Widget-Name', 'steigerwald-news-widget' ); ?></h2>
                        <p class="description">
                            <?php echo esc_html__( 'Interner Name zur Verwaltung gespeicherter Widgets. Erscheint nicht öffentlich.', 'steigerwald-news-widget' ); ?>
                        </p>
                        <label for="snw-name"><?php echo esc_html__( 'Name', 'steigerwald-news-widget' ); ?></label>
                        <input type="text" id="snw-name" class="regular-text" maxlength="80" placeholder="<?php echo esc_attr__( 'z. B. Vereinswidget Standard', 'steigerwald-news-widget' ); ?>">
                    </section>

                    <section class="snw-card">
                        <h2><?php echo esc_html__( 'Inhalt', 'steigerwald-news-widget' ); ?></h2>

                        <label for="snw-mode"><?php echo esc_html__( 'Inhaltsmodus', 'steigerwald-news-widget' ); ?></label>
                        <select id="snw-mode">
                            <option value="latest"><?php echo esc_html__( 'Neueste Beiträge', 'steigerwald-news-widget' ); ?></option>
                            <option value="category"><?php echo esc_html__( 'Kategorie', 'steigerwald-news-widget' ); ?></option>
                            <option value="tags"><?php echo esc_html__( 'Schlagwörter', 'steigerwald-news-widget' ); ?></option>
                            <option value="category_tags"><?php echo esc_html__( 'Kategorie + Schlagwörter', 'steigerwald-news-widget' ); ?></option>
                            <option value="manual"><?php echo esc_html__( 'Einzelne Beiträge', 'steigerwald-news-widget' ); ?></option>
                            <option value="hybrid"><?php echo esc_html__( 'Hybrid (Angeheftet + Automatisch)', 'steigerwald-news-widget' ); ?></option>
                        </select>

                        <div class="snw-cond" data-show-modes="category,category_tags,hybrid">
                            <label for="snw-category"><?php echo esc_html__( 'Kategorie', 'steigerwald-news-widget' ); ?></label>
                            <select id="snw-category" data-placeholder="<?php echo esc_attr__( 'Kategorie wählen', 'steigerwald-news-widget' ); ?>"></select>
                        </div>

                        <div class="snw-cond" data-show-modes="tags,category_tags,hybrid">
                            <label><?php echo esc_html__( 'Schlagwörter', 'steigerwald-news-widget' ); ?></label>
                            <div class="snw-token" id="snw-tags">
                                <input type="text" class="snw-token__input" id="snw-tag-search" placeholder="<?php echo esc_attr__( 'Schlagwort suchen …', 'steigerwald-news-widget' ); ?>" aria-label="<?php echo esc_attr__( 'Schlagwort suchen', 'steigerwald-news-widget' ); ?>">
                                <ul class="snw-token__list" id="snw-tag-list"></ul>
                                <ul class="snw-token__results" id="snw-tag-results" role="listbox"></ul>
                            </div>
                        </div>

                        <div class="snw-cond" data-show-modes="manual">
                            <label for="snw-post-search"><?php echo esc_html__( 'Beiträge auswählen', 'steigerwald-news-widget' ); ?></label>
                            <input type="text" id="snw-post-search" placeholder="<?php echo esc_attr__( 'Artikel suchen …', 'steigerwald-news-widget' ); ?>" aria-label="<?php echo esc_attr__( 'Artikel suchen', 'steigerwald-news-widget' ); ?>">
                            <ul class="snw-results" id="snw-post-results" role="listbox"></ul>
                            <p class="description"><?php echo esc_html__( 'Gewählte Beiträge (ziehbar, Reihenfolge = Anzeige):', 'steigerwald-news-widget' ); ?></p>
                            <ol class="snw-selected" id="snw-selected-posts"></ol>
                        </div>

                        <div class="snw-cond" data-show-modes="hybrid">
                            <label for="snw-pinned-search"><?php echo esc_html__( 'Angeheftete Beiträge (Pinned)', 'steigerwald-news-widget' ); ?></label>
                            <input type="text" id="snw-pinned-search" placeholder="<?php echo esc_attr__( 'Artikel suchen …', 'steigerwald-news-widget' ); ?>" aria-label="<?php echo esc_attr__( 'Angeheftete Beiträge suchen', 'steigerwald-news-widget' ); ?>">
                            <ul class="snw-results" id="snw-pinned-results" role="listbox"></ul>
                            <ol class="snw-selected" id="snw-pinned-list"></ol>
                            <label for="snw-auto-count"><?php echo esc_html__( 'Danach weitere automatische Beiträge', 'steigerwald-news-widget' ); ?></label>
                            <input type="number" id="snw-auto-count" min="0" max="20" value="3">
                        </div>

                        <label for="snw-limit"><?php echo esc_html__( 'Anzahl Beiträge', 'steigerwald-news-widget' ); ?></label>
                        <input type="number" id="snw-limit" min="1" max="20" value="5">

                        <label for="snw-sort"><?php echo esc_html__( 'Sortierung', 'steigerwald-news-widget' ); ?></label>
                        <select id="snw-sort">
                            <option value="newest"><?php echo esc_html__( 'Neueste zuerst', 'steigerwald-news-widget' ); ?></option>
                            <option value="oldest"><?php echo esc_html__( 'Älteste zuerst', 'steigerwald-news-widget' ); ?></option>
                            <option value="manual"><?php echo esc_html__( 'Manuelle Reihenfolge', 'steigerwald-news-widget' ); ?></option>
                            <option value="title"><?php echo esc_html__( 'Titel A–Z', 'steigerwald-news-widget' ); ?></option>
                        </select>
                    </section>

                    <section class="snw-card">
                        <h2><?php echo esc_html__( 'Design', 'steigerwald-news-widget' ); ?></h2>

                        <label for="snw-layout"><?php echo esc_html__( 'Layout', 'steigerwald-news-widget' ); ?></label>
                        <select id="snw-layout">
                            <option value="list"><?php echo esc_html__( 'News Liste (Bild links)', 'steigerwald-news-widget' ); ?></option>
                            <option value="cards"><?php echo esc_html__( 'Karten (Raster)', 'steigerwald-news-widget' ); ?></option>
                            <option value="compact"><?php echo esc_html__( 'Kompakt (Sidebar)', 'steigerwald-news-widget' ); ?></option>
                            <option value="headlines"><?php echo esc_html__( 'Nur Überschriften', 'steigerwald-news-widget' ); ?></option>
                        </select>

                        <fieldset class="snw-checkboxes">
                            <legend><?php echo esc_html__( 'Sichtbare Elemente', 'steigerwald-news-widget' ); ?></legend>
                            <label><input type="checkbox" id="snw-show-image" checked> <?php echo esc_html__( 'Beitragsbild', 'steigerwald-news-widget' ); ?></label>
                            <label><input type="checkbox" id="snw-show-date" checked> <?php echo esc_html__( 'Datum', 'steigerwald-news-widget' ); ?></label>
                            <label><input type="checkbox" id="snw-show-category"> <?php echo esc_html__( 'Kategorie', 'steigerwald-news-widget' ); ?></label>
                            <label><input type="checkbox" id="snw-show-excerpt" checked> <?php echo esc_html__( 'Teaser', 'steigerwald-news-widget' ); ?></label>
                            <label><input type="checkbox" id="snw-show-readmore" checked> <?php echo esc_html__( 'Weiterlesen-Link', 'steigerwald-news-widget' ); ?></label>
                            <label><input type="checkbox" id="snw-show-branding" checked> <?php echo esc_html__( 'Quellenhinweis', 'steigerwald-news-widget' ); ?></label>
                        </fieldset>

                        <label for="snw-teaser"><?php echo esc_html__( 'Teaser-Länge (Zeichen)', 'steigerwald-news-widget' ); ?></label>
                        <input type="range" id="snw-teaser" min="50" max="400" step="10" value="180">
                        <output id="snw-teaser-out">180</output>

                        <div class="snw-colors" id="snw-colors">
                            <label><?php echo esc_html__( 'Farben', 'steigerwald-news-widget' ); ?></label>
                            <span class="snw-color"><input type="text" class="snw-color__input" id="snw-color-accent" value="#c59a20" data-default="#c59a20"> <span><?php echo esc_html__( 'Akzentfarbe', 'steigerwald-news-widget' ); ?></span></span>
                            <span class="snw-color"><input type="text" class="snw-color__input" id="snw-color-background" value="" data-default=""> <span><?php echo esc_html__( 'Hintergrund', 'steigerwald-news-widget' ); ?></span></span>
                            <span class="snw-color"><input type="text" class="snw-color__input" id="snw-color-text" value="" data-default=""> <span><?php echo esc_html__( 'Textfarbe', 'steigerwald-news-widget' ); ?></span></span>
                            <span class="snw-color"><input type="text" class="snw-color__input" id="snw-color-muted" value="" data-default=""> <span><?php echo esc_html__( 'Sekundäre Textfarbe', 'steigerwald-news-widget' ); ?></span></span>
                            <span class="snw-color"><input type="text" class="snw-color__input" id="snw-color-border" value="" data-default=""> <span><?php echo esc_html__( 'Rahmenfarbe', 'steigerwald-news-widget' ); ?></span></span>
                            <span class="snw-color"><input type="text" class="snw-color__input" id="snw-color-link" value="" data-default=""> <span><?php echo esc_html__( 'Linkfarbe', 'steigerwald-news-widget' ); ?></span></span>
                        </div>

                        <label for="snw-radius"><?php echo esc_html__( 'Eckenradius', 'steigerwald-news-widget' ); ?></label>
                        <select id="snw-radius">
                            <option value="0">0</option>
                            <option value="4">4</option>
                            <option value="8" selected>8</option>
                            <option value="12">12</option>
                            <option value="16">16</option>
                        </select>

                        <label for="snw-spacing"><?php echo esc_html__( 'Abstand', 'steigerwald-news-widget' ); ?></label>
                        <select id="snw-spacing">
                            <option value="compact"><?php echo esc_html__( 'Kompakt', 'steigerwald-news-widget' ); ?></option>
                            <option value="normal" selected><?php echo esc_html__( 'Normal', 'steigerwald-news-widget' ); ?></option>
                            <option value="spacious"><?php echo esc_html__( 'Großzügig', 'steigerwald-news-widget' ); ?></option>
                        </select>

                        <label for="snw-typography"><?php echo esc_html__( 'Schriftart', 'steigerwald-news-widget' ); ?></label>
                        <select id="snw-typography">
                            <option value="host" selected><?php echo esc_html__( 'Host-Website übernehmen', 'steigerwald-news-widget' ); ?></option>
                            <option value="system"><?php echo esc_html__( 'System Sans', 'steigerwald-news-widget' ); ?></option>
                            <option value="arial">Arial</option>
                            <option value="georgia">Georgia</option>
                            <option value="serif"><?php echo esc_html__( 'Serif', 'steigerwald-news-widget' ); ?></option>
                            <option value="sans"><?php echo esc_html__( 'Sans Serif', 'steigerwald-news-widget' ); ?></option>
                        </select>
                    </section>

                    <section class="snw-card">
                        <h2><?php echo esc_html__( 'Partner & Tracking', 'steigerwald-news-widget' ); ?></h2>
                        <label for="snw-partner"><?php echo esc_html__( 'Partnerkennung', 'steigerwald-news-widget' ); ?></label>
                        <input type="text" id="snw-partner" class="regular-text" placeholder="<?php echo esc_attr__( 'z. B. asv-sassanfahrt', 'steigerwald-news-widget' ); ?>">
                        <p class="description">
                            <?php echo esc_html__( 'Dient ausschließlich dem UTM-Tracking der Artikellinks (utm_source). Optional.', 'steigerwald-news-widget' ); ?>
                        </p>

                        <label for="snw-title"><?php echo esc_html__( 'Überschrift (optional)', 'steigerwald-news-widget' ); ?></label>
                        <input type="text" id="snw-title" class="regular-text" placeholder="<?php echo esc_attr__( 'z. B. Aktuelles aus dem Steigerwald', 'steigerwald-news-widget' ); ?>">

                        <label for="snw-on-error"><?php echo esc_html__( 'Fehlerverhalten', 'steigerwald-news-widget' ); ?></label>
                        <select id="snw-on-error">
                            <option value="message" selected><?php echo esc_html__( 'Hinweis anzeigen', 'steigerwald-news-widget' ); ?></option>
                            <option value="hide"><?php echo esc_html__( 'Widget ausblenden', 'steigerwald-news-widget' ); ?></option>
                        </select>
                    </section>

                    <details class="snw-card snw-advanced">
                        <summary><?php echo esc_html__( 'Erweiterte Einstellungen', 'steigerwald-news-widget' ); ?></summary>

                        <label for="snw-api"><?php echo esc_html__( 'API-Basis (Quelle)', 'steigerwald-news-widget' ); ?></label>
                        <input type="url" id="snw-api" class="regular-text" placeholder="https://example.com/wp-json/wp/v2">

                        <label for="snw-source-name"><?php echo esc_html__( 'Quellname', 'steigerwald-news-widget' ); ?></label>
                        <input type="text" id="snw-source-name" class="regular-text">

                        <label for="snw-source-url"><?php echo esc_html__( 'Quell-URL', 'steigerwald-news-widget' ); ?></label>
                        <input type="url" id="snw-source-url" class="regular-text">

                        <label for="snw-widget-id"><?php echo esc_html__( 'Widget-ID', 'steigerwald-news-widget' ); ?></label>
                        <input type="text" id="snw-widget-id" class="regular-text" readonly>

                        <label for="snw-exclude"><?php echo esc_html__( 'Auszuschließende Beitrags-IDs', 'steigerwald-news-widget' ); ?></label>
                        <input type="text" id="snw-exclude" class="regular-text" placeholder="123,456">

                        <details>
                            <summary><?php echo esc_html__( 'Rohkonfiguration (JSON)', 'steigerwald-news-widget' ); ?></summary>
                            <textarea id="snw-raw" class="large-text code" rows="6" readonly></textarea>
                        </details>
                    </details>

                    <div class="snw-actions">
                        <button type="button" id="snw-save" class="button button-primary"><?php echo esc_html__( 'Widget speichern', 'steigerwald-news-widget' ); ?></button>
                        <button type="button" id="snw-copy" class="button"><?php echo esc_html__( 'Einbettungscode kopieren', 'steigerwald-news-widget' ); ?></button>
                        <button type="button" id="snw-reset" class="button"><?php echo esc_html__( 'Zurücksetzen', 'steigerwald-news-widget' ); ?></button>
                        <span id="snw-save-status" class="snw-status" role="status" aria-live="polite"></span>
                    </div>
                </form>

                <!-- ============ Live preview column ============ -->
                <div class="snw-builder__preview">
                    <h2><?php echo esc_html__( 'Live-Vorschau', 'steigerwald-news-widget' ); ?></h2>
                    <p class="description"><?php echo esc_html__( 'Zeigt echte aktuelle Beiträge aus dieser WordPress-Installation.', 'steigerwald-news-widget' ); ?></p>
                    <div class="snw-preview-frame">
                        <div id="snw-preview"></div>
                    </div>
                </div>
            </div>

            <hr>

            <h2><?php echo esc_html__( 'Gespeicherte Widgets', 'steigerwald-news-widget' ); ?></h2>
            <p class="description"><?php echo esc_html__( 'Presets speichern nur Konfiguration, keine Beiträge.', 'steigerwald-news-widget' ); ?></p>
            <?php echo self::render_presets_table(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

            <hr>

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
