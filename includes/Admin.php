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
        $hook = add_menu_page(
            __( 'Steigerwald-News Widget', 'steigerwald-news-widget' ),
            __( 'News Widget', 'steigerwald-news-widget' ),
            'manage_options',
            'steigerwald-news-widget',
            array( __CLASS__, 'render_page' ),
            'dashicons-megaphone',
            30
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
                            <option value="grid" selected><?php echo esc_html__( 'Raster (Artikel nebeneinander)', 'steigerwald-news-widget' ); ?></option>
                            <option value="list"><?php echo esc_html__( 'News Liste (Bild links)', 'steigerwald-news-widget' ); ?></option>
                            <option value="cards"><?php echo esc_html__( 'Karten (mit Rahmen)', 'steigerwald-news-widget' ); ?></option>
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
                            <label><input type="checkbox" id="snw-show-author"> <?php echo esc_html__( 'Autor anzeigen', 'steigerwald-news-widget' ); ?></label>
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

                        <fieldset class="snw-fieldset">
                            <legend><?php echo esc_html__( 'Bild & Layout', 'steigerwald-news-widget' ); ?></legend>

                            <label for="snw-columns"><?php echo esc_html__( 'Artikel pro Reihe', 'steigerwald-news-widget' ); ?></label>
                            <select id="snw-columns">
                                <option value="1">1</option>
                                <option value="2" selected>2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                            </select>

                            <label for="snw-image-ratio"><?php echo esc_html__( 'Bildseitenverhältnis', 'steigerwald-news-widget' ); ?></label>
                            <select id="snw-image-ratio">
                                <option value="16:9" selected>16:9</option>
                                <option value="4:3">4:3</option>
                                <option value="3:2">3:2</option>
                                <option value="1:1">1:1</option>
                                <option value="auto"><?php echo esc_html__( 'Automatisch', 'steigerwald-news-widget' ); ?></option>
                            </select>

                            <label for="snw-image-fit"><?php echo esc_html__( 'Bildfüllung', 'steigerwald-news-widget' ); ?></label>
                            <select id="snw-image-fit">
                                <option value="cover" selected><?php echo esc_html__( 'Zuschneiden', 'steigerwald-news-widget' ); ?></option>
                                <option value="contain"><?php echo esc_html__( 'Einpassen', 'steigerwald-news-widget' ); ?></option>
                            </select>

                            <div class="snw-cond" data-show-layout="list">
                                <label for="snw-image-position"><?php echo esc_html__( 'Bildposition (Liste)', 'steigerwald-news-widget' ); ?></label>
                                <select id="snw-image-position">
                                    <option value="left" selected><?php echo esc_html__( 'Links', 'steigerwald-news-widget' ); ?></option>
                                    <option value="right"><?php echo esc_html__( 'Rechts', 'steigerwald-news-widget' ); ?></option>
                                    <option value="top"><?php echo esc_html__( 'Oben', 'steigerwald-news-widget' ); ?></option>
                                </select>
                            </div>
                        </fieldset>

                        <fieldset class="snw-fieldset">
                            <legend><?php echo esc_html__( 'Text & Meta', 'steigerwald-news-widget' ); ?></legend>

                            <label for="snw-date-format"><?php echo esc_html__( 'Datumsformat', 'steigerwald-news-widget' ); ?></label>
                            <select id="snw-date-format">
                                <option value="absolute" selected><?php echo esc_html__( 'Absolut (02. August 2026)', 'steigerwald-news-widget' ); ?></option>
                                <option value="relative"><?php echo esc_html__( 'Relativ (vor 3 Tagen)', 'steigerwald-news-widget' ); ?></option>
                            </select>

                            <label for="snw-heading-level"><?php echo esc_html__( 'Überschriften-Ebene', 'steigerwald-news-widget' ); ?></label>
                            <select id="snw-heading-level">
                                <option value="h2">H2</option>
                                <option value="h3" selected>H3</option>
                                <option value="h4">H4</option>
                            </select>

                            <label for="snw-title-length"><?php echo esc_html__( 'Titel-Kürzung (Zeichen, 0 = aus)', 'steigerwald-news-widget' ); ?></label>
                            <input type="number" id="snw-title-length" min="0" max="200" value="0">
                        </fieldset>

                        <fieldset class="snw-fieldset">
                            <legend><?php echo esc_html__( 'Theme & Stil', 'steigerwald-news-widget' ); ?></legend>

                            <label for="snw-theme"><?php echo esc_html__( 'Farbschema', 'steigerwald-news-widget' ); ?></label>
                            <select id="snw-theme">
                                <option value="light" selected><?php echo esc_html__( 'Hell', 'steigerwald-news-widget' ); ?></option>
                                <option value="dark"><?php echo esc_html__( 'Dunkel', 'steigerwald-news-widget' ); ?></option>
                            </select>

                            <label for="snw-shadow"><?php echo esc_html__( 'Schatten', 'steigerwald-news-widget' ); ?></label>
                            <select id="snw-shadow">
                                <option value="none" selected><?php echo esc_html__( 'Keiner', 'steigerwald-news-widget' ); ?></option>
                                <option value="sm"><?php echo esc_html__( 'Dezent', 'steigerwald-news-widget' ); ?></option>
                                <option value="md"><?php echo esc_html__( 'Mittel', 'steigerwald-news-widget' ); ?></option>
                                <option value="lg"><?php echo esc_html__( 'Stark', 'steigerwald-news-widget' ); ?></option>
                            </select>

                            <label for="snw-separator"><?php echo esc_html__( 'Artikel trennen', 'steigerwald-news-widget' ); ?></label>
                            <select id="snw-separator">
                                <option value="line" selected><?php echo esc_html__( 'Linie', 'steigerwald-news-widget' ); ?></option>
                                <option value="none"><?php echo esc_html__( 'Keine Trennung', 'steigerwald-news-widget' ); ?></option>
                            </select>

                            <label for="snw-align"><?php echo esc_html__( 'Textausrichtung', 'steigerwald-news-widget' ); ?></label>
                            <select id="snw-align">
                                <option value="left" selected><?php echo esc_html__( 'Links', 'steigerwald-news-widget' ); ?></option>
                                <option value="center"><?php echo esc_html__( 'Zentriert', 'steigerwald-news-widget' ); ?></option>
                                <option value="right"><?php echo esc_html__( 'Rechts', 'steigerwald-news-widget' ); ?></option>
                            </select>

                            <label for="snw-link-mode"><?php echo esc_html__( 'Klickziel', 'steigerwald-news-widget' ); ?></label>
                            <select id="snw-link-mode">
                                <option value="title" selected><?php echo esc_html__( 'Nur Titel', 'steigerwald-news-widget' ); ?></option>
                                <option value="card"><?php echo esc_html__( 'Ganze Karte', 'steigerwald-news-widget' ); ?></option>
                            </select>
                        </fieldset>

                        <details class="snw-card snw-customcss">
                            <summary><?php echo esc_html__( 'Eigenes CSS (Profis)', 'steigerwald-news-widget' ); ?></summary>
                            <label for="snw-custom-css"><?php echo esc_html__( 'Zusätzliches CSS (nur für dieses Widget)', 'steigerwald-news-widget' ); ?></label>
                            <textarea id="snw-custom-css" class="large-text code" rows="4" placeholder=".snw-title { letter-spacing: .02em; }"></textarea>
                            <p class="description"><?php echo esc_html__( 'Wird automatisch auf dieses Widget begrenzt – es wirkt nicht auf die Restseite.', 'steigerwald-news-widget' ); ?></p>
                        </details>
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

                        <h3><?php echo esc_html__( 'Texte anpassen', 'steigerwald-news-widget' ); ?></h3>
                        <p class="description"><?php echo esc_html__( 'Überschreibt die Standardtexte des Widgets.', 'steigerwald-news-widget' ); ?></p>

                        <label for="snw-readmore-label"><?php echo esc_html__( '„Weiterlesen“-Text', 'steigerwald-news-widget' ); ?></label>
                        <input type="text" id="snw-readmore-label" class="regular-text" maxlength="80" placeholder="<?php echo esc_attr__( 'Artikel lesen', 'steigerwald-news-widget' ); ?>">

                        <label for="snw-empty-label"><?php echo esc_html__( 'Hinweis bei leerer Liste', 'steigerwald-news-widget' ); ?></label>
                        <input type="text" id="snw-empty-label" class="regular-text" maxlength="160" placeholder="<?php echo esc_attr__( 'Aktuell sind keine passenden Beiträge vorhanden.', 'steigerwald-news-widget' ); ?>">

                        <label for="snw-error-label"><?php echo esc_html__( 'Fehlerhinweis', 'steigerwald-news-widget' ); ?></label>
                        <input type="text" id="snw-error-label" class="regular-text" maxlength="160" placeholder="<?php echo esc_attr__( 'Die Nachrichten konnten gerade nicht geladen werden.', 'steigerwald-news-widget' ); ?>">
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
                    <div class="snw-preview-toolbar">
                        <label for="snw-preview-width"><?php echo esc_html__( 'Vorschau-Breite', 'steigerwald-news-widget' ); ?></label>
                        <select id="snw-preview-width">
                            <option value="100%"><?php echo esc_html__( 'Container (100%)', 'steigerwald-news-widget' ); ?></option>
                            <option value="320px">320 px</option>
                            <option value="480px">480 px</option>
                            <option value="768px"><?php echo esc_html__( '768 px (Tablet)', 'steigerwald-news-widget' ); ?></option>
                            <option value="1024px"><?php echo esc_html__( '1024 px (Desktop)', 'steigerwald-news-widget' ); ?></option>
                            <option value="1280px"><?php echo esc_html__( '1280 px (Breit)', 'steigerwald-news-widget' ); ?></option>
                        </select>
                    </div>
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

            <h2><?php echo esc_html__( 'Partner-Anfragen', 'steigerwald-news-widget' ); ?></h2>
            <p class="description">
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
