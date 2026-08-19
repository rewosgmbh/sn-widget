<?php
/**
 * Shared widget builder markup.
 *
 * The admin "Erstellen" page renders the full power-user builder. The public
 * [steigerwald_news_widget_builder] shortcode renders a streamlined 4-step
 * creator (Inhalte → Darstellung → Design → Anfragen) focused on the partner
 * use case. Both share the same field IDs and the live preview, so the JS
 * config/preview logic is identical; only the visible structure differs.
 *
 * Admin-only controls that the public creator must not expose are rendered in
 * a hidden `.snw-admin-only` block on the public form so the config/JS keeps
 * working with sensible defaults (the public UI simply never shows them).
 *
 * @package SteigerwaldNewsWidget
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SNW_Builder {

    /**
     * Render the builder (configuration column + live preview).
     *
     * @param array $args Optional. 'context' => 'admin' | 'public'.
     * @return void
     */
    public static function render_form( $args = array() ) {
        $context   = ( isset( $args['context'] ) && 'public' === $args['context'] ) ? 'public' : 'admin';
        $is_public = ( 'public' === $context );
        $t         = 'steigerwald-news-widget';
        ?>
        <div class="snw-builder<?php echo $is_public ? ' snw-builder--public' : ''; ?>">
            <!-- ============ Configuration column ============ -->
            <form id="snw-builder-form" class="snw-builder__config" autocomplete="off">
                <?php if ( $is_public ) : ?>
                    <?php self::render_public_form( $t ); ?>
                <?php else : ?>
                    <?php self::render_admin_form( $t ); ?>
                <?php endif; ?>
            </form>

            <!-- ============ Live preview column (shared) ============ -->
            <div class="snw-builder__preview" id="snw-sec-preview">
                <div class="snw-preview-head">
                    <h2>
                        <?php echo esc_html__( 'Live-Vorschau', $t ); ?>
                        <span class="snw-live-badge"><span class="snw-live-dot" aria-hidden="true"></span><?php echo esc_html__( 'Live', $t ); ?></span>
                    </h2>
                    <p class="description"><?php echo esc_html__( 'Zeigt echte aktuelle Beiträge aus dieser WordPress-Installation – Änderungen erscheinen sofort.', $t ); ?></p>
                    <div class="snw-preview-toolbar">
                        <label for="snw-preview-width"><?php echo esc_html__( 'Vorschau-Breite', $t ); ?></label>
                        <select id="snw-preview-width">
                            <option value="100%"><?php echo esc_html__( 'Container (100%)', $t ); ?></option>
                            <option value="320px">320 px</option>
                            <option value="480px">480 px</option>
                            <option value="768px"><?php echo esc_html__( '768 px (Tablet)', $t ); ?></option>
                            <option value="1024px"><?php echo esc_html__( '1024 px (Desktop)', $t ); ?></option>
                            <option value="1280px"><?php echo esc_html__( '1280 px (Breit)', $t ); ?></option>
                        </select>
                    </div>
                </div>
                <div class="snw-preview-body">
                    <div class="snw-preview-frame">
                        <div id="snw-preview"></div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Full admin builder (power-user "NASA command center").
     */
    private static function render_admin_form( $t ) {
        ?>
        <nav class="snw-section-nav" aria-label="<?php echo esc_attr__( 'Abschnitte', $t ); ?>">
            <a href="#snw-sec-name"><?php echo esc_html__( 'Name', $t ); ?></a>
            <a href="#snw-sec-content"><?php echo esc_html__( 'Inhalt', $t ); ?></a>
            <a href="#snw-sec-design"><?php echo esc_html__( 'Design', $t ); ?></a>
            <a href="#snw-sec-partner"><?php echo esc_html__( 'Partner', $t ); ?></a>
            <a href="#snw-sec-advanced"><?php echo esc_html__( 'Erweitert', $t ); ?></a>
            <a href="#snw-sec-preview"><?php echo esc_html__( 'Vorschau', $t ); ?></a>
        </nav>
        <section class="snw-card" id="snw-sec-name">
            <h2><?php echo esc_html__( 'Widget-Name', $t ); ?></h2>
            <p class="description">
                <?php echo esc_html__( 'Interner Name zur Verwaltung gespeicherter Widgets. Erscheint nicht öffentlich.', $t ); ?>
            </p>
            <label for="snw-name"><?php echo esc_html__( 'Name', $t ); ?></label>
            <input type="text" id="snw-name" class="regular-text" maxlength="80" placeholder="<?php echo esc_attr__( 'z. B. Vereinswidget Standard', $t ); ?>">
        </section>

        <section class="snw-card" id="snw-sec-content">
            <h2><?php echo esc_html__( 'Inhalt', $t ); ?></h2>

            <label for="snw-mode"><?php echo esc_html__( 'Inhaltsmodus', $t ); ?></label>
            <select id="snw-mode">
                <option value="latest"><?php echo esc_html__( 'Neueste Beiträge', $t ); ?></option>
                <option value="category"><?php echo esc_html__( 'Kategorie', $t ); ?></option>
                <option value="tags"><?php echo esc_html__( 'Schlagwörter', $t ); ?></option>
                <option value="category_tags"><?php echo esc_html__( 'Kategorie + Schlagwörter', $t ); ?></option>
                <option value="manual"><?php echo esc_html__( 'Einzelne Beiträge', $t ); ?></option>
                <option value="hybrid"><?php echo esc_html__( 'Hybrid (Angeheftet + Automatisch)', $t ); ?></option>
            </select>
            <p class="snw-mode-hint" id="snw-mode-hint"></p>

            <div class="snw-cond" data-show-modes="category,category_tags,hybrid">
                <label for="snw-category"><?php echo esc_html__( 'Kategorie', $t ); ?></label>
                <select id="snw-category" data-placeholder="<?php echo esc_attr__( 'Kategorie wählen', $t ); ?>"></select>
            </div>

            <div class="snw-cond" data-show-modes="tags,category_tags,hybrid">
                <label><?php echo esc_html__( 'Schlagwörter', $t ); ?></label>
                <div class="snw-token" id="snw-tags">
                    <input type="text" class="snw-token__input" id="snw-tag-search" placeholder="<?php echo esc_attr__( 'Schlagwort suchen …', $t ); ?>" aria-label="<?php echo esc_attr__( 'Schlagwort suchen', $t ); ?>">
                    <ul class="snw-token__list" id="snw-tag-list"></ul>
                    <ul class="snw-token__results" id="snw-tag-results" role="listbox"></ul>
                </div>
            </div>

            <div class="snw-cond" data-show-modes="manual">
                <label for="snw-post-search"><?php echo esc_html__( 'Beiträge auswählen', $t ); ?></label>
                <input type="text" id="snw-post-search" placeholder="<?php echo esc_attr__( 'Artikel suchen …', $t ); ?>" aria-label="<?php echo esc_attr__( 'Artikel suchen', $t ); ?>">
                <ul class="snw-results" id="snw-post-results" role="listbox"></ul>
                <p class="description"><?php echo esc_html__( 'Gewählte Beiträge (ziehbar, Reihenfolge = Anzeige):', $t ); ?></p>
                <ol class="snw-selected" id="snw-selected-posts"></ol>
            </div>

            <div class="snw-cond" data-show-modes="hybrid">
                <label for="snw-pinned-search"><?php echo esc_html__( 'Angeheftete Beiträge (Pinned)', $t ); ?></label>
                <input type="text" id="snw-pinned-search" placeholder="<?php echo esc_attr__( 'Artikel suchen …', $t ); ?>" aria-label="<?php echo esc_attr__( 'Angeheftete Beiträge suchen', $t ); ?>">
                <ul class="snw-results" id="snw-pinned-results" role="listbox"></ul>
                <ol class="snw-selected" id="snw-pinned-list"></ol>
                <label for="snw-auto-count"><?php echo esc_html__( 'Danach weitere automatische Beiträge', $t ); ?></label>
                <input type="number" id="snw-auto-count" min="0" max="20" value="3">
            </div>

            <label for="snw-limit"><?php echo esc_html__( 'Anzahl Beiträge', $t ); ?></label>
            <input type="number" id="snw-limit" min="1" max="20" value="6">

            <label for="snw-sort"><?php echo esc_html__( 'Sortierung', $t ); ?></label>
            <select id="snw-sort">
                <option value="newest"><?php echo esc_html__( 'Neueste zuerst', $t ); ?></option>
                <option value="oldest"><?php echo esc_html__( 'Älteste zuerst', $t ); ?></option>
                <option value="manual"><?php echo esc_html__( 'Manuelle Reihenfolge', $t ); ?></option>
                <option value="title"><?php echo esc_html__( 'Titel A–Z', $t ); ?></option>
            </select>
        </section>

        <section class="snw-card" id="snw-sec-design">
            <h2><?php echo esc_html__( 'Design', $t ); ?></h2>

            <label><?php echo esc_html__( 'Layout', $t ); ?></label>
            <div class="snw-layout-picker" id="snw-layout-picker" role="radiogroup" aria-label="<?php echo esc_attr__( 'Layout', $t ); ?>">
                <button type="button" class="snw-layout-opt is-active" data-layout="grid" aria-pressed="true">
                    <span class="snw-layout-thumb snw-thumb-grid" aria-hidden="true"></span>
                    <span class="snw-layout-name"><?php echo esc_html__( 'Raster', $t ); ?></span>
                </button>
                <button type="button" class="snw-layout-opt" data-layout="list" aria-pressed="false">
                    <span class="snw-layout-thumb snw-thumb-list" aria-hidden="true"></span>
                    <span class="snw-layout-name"><?php echo esc_html__( 'Liste', $t ); ?></span>
                </button>
                <button type="button" class="snw-layout-opt" data-layout="cards" aria-pressed="false">
                    <span class="snw-layout-thumb snw-thumb-cards" aria-hidden="true"></span>
                    <span class="snw-layout-name"><?php echo esc_html__( 'Karten', $t ); ?></span>
                </button>
                <button type="button" class="snw-layout-opt" data-layout="compact" aria-pressed="false">
                    <span class="snw-layout-thumb snw-thumb-compact" aria-hidden="true"></span>
                    <span class="snw-layout-name"><?php echo esc_html__( 'Kompakt', $t ); ?></span>
                </button>
                <button type="button" class="snw-layout-opt" data-layout="headlines" aria-pressed="false">
                    <span class="snw-layout-thumb snw-thumb-headlines" aria-hidden="true"></span>
                    <span class="snw-layout-name"><?php echo esc_html__( 'Nur Überschriften', $t ); ?></span>
                </button>
            </div>
            <input type="hidden" id="snw-layout" value="grid">

            <fieldset class="snw-checkboxes">
                <legend><?php echo esc_html__( 'Sichtbare Elemente', $t ); ?></legend>
                <label><input type="checkbox" id="snw-show-image" checked> <?php echo esc_html__( 'Beitragsbild', $t ); ?></label>
                <label><input type="checkbox" id="snw-show-date" checked> <?php echo esc_html__( 'Datum', $t ); ?></label>
                <label><input type="checkbox" id="snw-show-category"> <?php echo esc_html__( 'Kategorie', $t ); ?></label>
                <label><input type="checkbox" id="snw-show-excerpt" checked> <?php echo esc_html__( 'Teaser', $t ); ?></label>
                <label><input type="checkbox" id="snw-show-readmore" checked> <?php echo esc_html__( 'Weiterlesen-Link', $t ); ?></label>
                <label><input type="checkbox" id="snw-show-branding" checked> <?php echo esc_html__( 'Quellenhinweis', $t ); ?></label>
                <label><input type="checkbox" id="snw-show-author"> <?php echo esc_html__( 'Autor anzeigen', $t ); ?></label>
            </fieldset>

            <label for="snw-teaser"><?php echo esc_html__( 'Teaser-Länge (Zeichen)', $t ); ?></label>
            <input type="range" id="snw-teaser" min="50" max="400" step="10" value="180">
            <output id="snw-teaser-out">180</output>

            <div class="snw-colors" id="snw-colors">
                <label><?php echo esc_html__( 'Farben', $t ); ?></label>
                <span class="snw-color"><input type="text" class="snw-color__input" id="snw-color-accent" value="#c59a20" data-default="#c59a20"> <span><?php echo esc_html__( 'Akzentfarbe', $t ); ?></span></span>
                <span class="snw-color"><input type="text" class="snw-color__input" id="snw-color-background" value="" data-default=""> <span><?php echo esc_html__( 'Hintergrund', $t ); ?></span></span>
                <span class="snw-color"><input type="text" class="snw-color__input" id="snw-color-text" value="" data-default=""> <span><?php echo esc_html__( 'Textfarbe', $t ); ?></span></span>
                <span class="snw-color"><input type="text" class="snw-color__input" id="snw-color-muted" value="" data-default=""> <span><?php echo esc_html__( 'Sekundäre Textfarbe', $t ); ?></span></span>
                <span class="snw-color"><input type="text" class="snw-color__input" id="snw-color-border" value="" data-default=""> <span><?php echo esc_html__( 'Rahmenfarbe', $t ); ?></span></span>
                <span class="snw-color"><input type="text" class="snw-color__input" id="snw-color-link" value="" data-default=""> <span><?php echo esc_html__( 'Linkfarbe', $t ); ?></span></span>
            </div>

            <label for="snw-radius"><?php echo esc_html__( 'Eckenradius', $t ); ?></label>
            <select id="snw-radius">
                <option value="0">0</option>
                <option value="4">4</option>
                <option value="8" selected>8</option>
                <option value="12">12</option>
                <option value="16">16</option>
            </select>
            <p class="snw-field-hint"><?php echo esc_html__( 'Rundung der Ecken (0 = eckig).', $t ); ?></p>

            <label for="snw-spacing"><?php echo esc_html__( 'Abstand', $t ); ?></label>
            <select id="snw-spacing">
                <option value="compact"><?php echo esc_html__( 'Kompakt', $t ); ?></option>
                <option value="normal" selected><?php echo esc_html__( 'Normal', $t ); ?></option>
                <option value="spacious"><?php echo esc_html__( 'Großzügig', $t ); ?></option>
            </select>

            <label for="snw-typography"><?php echo esc_html__( 'Schriftart', $t ); ?></label>
            <select id="snw-typography">
                <option value="host" selected><?php echo esc_html__( 'Host-Website übernehmen', $t ); ?></option>
                <option value="system"><?php echo esc_html__( 'System Sans', $t ); ?></option>
                <option value="arial">Arial</option>
                <option value="georgia">Georgia</option>
                <option value="serif"><?php echo esc_html__( 'Serif', $t ); ?></option>
                <option value="sans"><?php echo esc_html__( 'Sans Serif', $t ); ?></option>
            </select>

            <details class="snw-fieldset" open>
                <summary><?php echo esc_html__( 'Bild & Layout', $t ); ?></summary>

                <label for="snw-columns"><?php echo esc_html__( 'Artikel pro Reihe', $t ); ?></label>
                <select id="snw-columns">
                    <option value="1">1</option>
                    <option value="2" selected>2</option>
                    <option value="3">3</option>
                    <option value="4">4</option>
                </select>
                <p class="snw-field-hint"><?php echo esc_html__( 'Wirkt bei Layout „Raster“ und „Karten“.', $t ); ?></p>

                <label for="snw-image-ratio"><?php echo esc_html__( 'Bildseitenverhältnis', $t ); ?></label>
                <select id="snw-image-ratio">
                    <option value="16:9" selected>16:9</option>
                    <option value="4:3">4:3</option>
                    <option value="3:2">3:2</option>
                    <option value="1:1">1:1</option>
                    <option value="auto"><?php echo esc_html__( 'Automatisch', $t ); ?></option>
                </select>

                <label for="snw-image-fit"><?php echo esc_html__( 'Bildfüllung', $t ); ?></label>
                <select id="snw-image-fit">
                    <option value="cover" selected><?php echo esc_html__( 'Zuschneiden', $t ); ?></option>
                    <option value="contain"><?php echo esc_html__( 'Einpassen', $t ); ?></option>
                </select>

                <div class="snw-cond" data-show-layout="list">
                    <label for="snw-image-position"><?php echo esc_html__( 'Bildposition (Liste)', $t ); ?></label>
                    <select id="snw-image-position">
                        <option value="left" selected><?php echo esc_html__( 'Links', $t ); ?></option>
                        <option value="right"><?php echo esc_html__( 'Rechts', $t ); ?></option>
                        <option value="top"><?php echo esc_html__( 'Oben', $t ); ?></option>
                    </select>
                </div>
            </details>

            <details class="snw-fieldset" open>
                <summary><?php echo esc_html__( 'Text & Meta', $t ); ?></summary>

                <label for="snw-date-format"><?php echo esc_html__( 'Datumsformat', $t ); ?></label>
                <select id="snw-date-format">
                    <option value="absolute" selected><?php echo esc_html__( 'Absolut (02. August 2026)', $t ); ?></option>
                    <option value="relative"><?php echo esc_html__( 'Relativ (vor 3 Tagen)', $t ); ?></option>
                </select>

                <label for="snw-heading-level"><?php echo esc_html__( 'Überschriften-Ebene', $t ); ?></label>
                <select id="snw-heading-level">
                    <option value="h2">H2</option>
                    <option value="h3" selected>H3</option>
                    <option value="h4">H4</option>
                </select>

                <label for="snw-title-length"><?php echo esc_html__( 'Titel-Kürzung (Zeichen, 0 = aus)', $t ); ?></label>
                <input type="number" id="snw-title-length" min="0" max="200" value="0">
            </details>

            <details class="snw-fieldset" open>
                <summary><?php echo esc_html__( 'Theme & Stil', $t ); ?></summary>

                <label><?php echo esc_html__( 'Farbschema', $t ); ?></label>
                <div class="snw-segmented" id="snw-theme-picker" role="radiogroup" aria-label="<?php echo esc_attr__( 'Farbschema', $t ); ?>">
                    <button type="button" class="snw-seg-opt is-active" data-theme="light" aria-pressed="true"><?php echo esc_html__( 'Hell', $t ); ?></button>
                    <button type="button" class="snw-seg-opt" data-theme="dark" aria-pressed="false"><?php echo esc_html__( 'Dunkel', $t ); ?></button>
                </div>
                <input type="hidden" id="snw-theme" value="light">

                <label for="snw-shadow"><?php echo esc_html__( 'Schatten', $t ); ?></label>
                <select id="snw-shadow">
                    <option value="none" selected><?php echo esc_html__( 'Keiner', $t ); ?></option>
                    <option value="sm"><?php echo esc_html__( 'Dezent', $t ); ?></option>
                    <option value="md"><?php echo esc_html__( 'Mittel', $t ); ?></option>
                    <option value="lg"><?php echo esc_html__( 'Stark', $t ); ?></option>
                </select>

                <label for="snw-separator"><?php echo esc_html__( 'Artikel trennen', $t ); ?></label>
                <select id="snw-separator">
                    <option value="line" selected><?php echo esc_html__( 'Linie', $t ); ?></option>
                    <option value="none"><?php echo esc_html__( 'Keine Trennung', $t ); ?></option>
                </select>

                <label for="snw-align"><?php echo esc_html__( 'Textausrichtung', $t ); ?></label>
                <select id="snw-align">
                    <option value="left" selected><?php echo esc_html__( 'Links', $t ); ?></option>
                    <option value="center"><?php echo esc_html__( 'Zentriert', $t ); ?></option>
                    <option value="right"><?php echo esc_html__( 'Rechts', $t ); ?></option>
                </select>

                <label for="snw-link-mode"><?php echo esc_html__( 'Klickziel', $t ); ?></label>
                <select id="snw-link-mode">
                    <option value="title" selected><?php echo esc_html__( 'Nur Titel', $t ); ?></option>
                    <option value="card"><?php echo esc_html__( 'Ganze Karte', $t ); ?></option>
                </select>
            </details>

            <details class="snw-card snw-customcss">
                <summary><?php echo esc_html__( 'Eigenes CSS (Profis)', $t ); ?></summary>
                <label for="snw-custom-css"><?php echo esc_html__( 'Zusätzliches CSS (nur für dieses Widget)', $t ); ?></label>
                <textarea id="snw-custom-css" class="large-text code" rows="4" placeholder=".snw-title { letter-spacing: .02em; }"></textarea>
                <p class="description"><?php echo esc_html__( 'Wird automatisch auf dieses Widget begrenzt – es wirkt nicht auf die Restseite.', $t ); ?></p>
            </details>
        </section>

        <section class="snw-card" id="snw-sec-partner">
            <h2><?php echo esc_html__( 'Partner & Tracking', $t ); ?></h2>
            <label for="snw-partner"><?php echo esc_html__( 'Partnerkennung', $t ); ?></label>
            <input type="text" id="snw-partner" class="regular-text" placeholder="<?php echo esc_attr__( 'z. B. mein-verein', $t ); ?>">
            <p class="description">
                <?php echo esc_html__( 'Dient ausschließlich dem UTM-Tracking der Artikellinks (utm_source). Optional.', $t ); ?>
            </p>

            <label for="snw-title"><?php echo esc_html__( 'Überschrift (optional)', $t ); ?></label>
            <input type="text" id="snw-title" class="regular-text" placeholder="<?php echo esc_attr__( 'z. B. Aktuelles aus Ihrer Region', $t ); ?>">

            <label for="snw-on-error"><?php echo esc_html__( 'Fehlerverhalten', $t ); ?></label>
            <select id="snw-on-error">
                <option value="message" selected><?php echo esc_html__( 'Hinweis anzeigen', $t ); ?></option>
                <option value="hide"><?php echo esc_html__( 'Widget ausblenden', $t ); ?></option>
            </select>

            <h3><?php echo esc_html__( 'Texte anpassen', $t ); ?></h3>
            <p class="description"><?php echo esc_html__( 'Überschreibt die Standardtexte des Widgets.', $t ); ?></p>

            <label for="snw-readmore-label"><?php echo esc_html__( '„Weiterlesen“-Text', $t ); ?></label>
            <input type="text" id="snw-readmore-label" class="regular-text" maxlength="80" placeholder="<?php echo esc_attr__( 'Artikel lesen', $t ); ?>">

            <label for="snw-empty-label"><?php echo esc_html__( 'Hinweis bei leerer Liste', $t ); ?></label>
            <input type="text" id="snw-empty-label" class="regular-text" maxlength="160" placeholder="<?php echo esc_attr__( 'Aktuell sind keine passenden Beiträge vorhanden.', $t ); ?>">

            <label for="snw-error-label"><?php echo esc_html__( 'Fehlerhinweis', $t ); ?></label>
            <input type="text" id="snw-error-label" class="regular-text" maxlength="160" placeholder="<?php echo esc_attr__( 'Die Nachrichten konnten gerade nicht geladen werden.', $t ); ?>">
        </section>

        <details class="snw-card snw-advanced" id="snw-sec-advanced">
            <summary><?php echo esc_html__( 'Erweiterte Einstellungen', $t ); ?></summary>

            <label for="snw-api"><?php echo esc_html__( 'API-Basis (Quelle)', $t ); ?></label>
            <input type="url" id="snw-api" class="regular-text" placeholder="https://example.com/wp-json/wp/v2">

            <label for="snw-source-name"><?php echo esc_html__( 'Quellname', $t ); ?></label>
            <input type="text" id="snw-source-name" class="regular-text">

            <label for="snw-source-url"><?php echo esc_html__( 'Quell-URL', $t ); ?></label>
            <input type="url" id="snw-source-url" class="regular-text">

            <label for="snw-widget-id"><?php echo esc_html__( 'Widget-ID', $t ); ?></label>
            <input type="text" id="snw-widget-id" class="regular-text" readonly>

            <label for="snw-exclude"><?php echo esc_html__( 'Auszuschließende Beitrags-IDs', $t ); ?></label>
            <input type="text" id="snw-exclude" class="regular-text" placeholder="123,456">

            <details>
                <summary><?php echo esc_html__( 'Rohkonfiguration (JSON)', $t ); ?></summary>
                <textarea id="snw-raw" class="large-text code" rows="6" readonly></textarea>
            </details>
        </details>

        <div class="snw-actions">
            <button type="button" id="snw-save" class="button button-primary"><?php echo esc_html__( 'Widget speichern', $t ); ?></button>
            <button type="button" id="snw-copy" class="button"><?php echo esc_html__( 'Einbettungscode kopieren', $t ); ?></button>
            <button type="button" id="snw-reset" class="button"><?php echo esc_html__( 'Zurücksetzen', $t ); ?></button>
            <span id="snw-save-status" class="snw-status" role="status" aria-live="polite"></span>
        </div>
        <?php
    }

    /**
     * Streamlined public creator (4 steps). Admin-only controls are kept in the
     * DOM (hidden) so the shared JS config/preview logic keeps working.
     */
    private static function render_public_form( $t ) {
        ?>
        <nav class="snw-section-nav" aria-label="<?php echo esc_attr__( 'Schritte', $t ); ?>">
            <a href="#snw-sec-content"><?php echo esc_html__( '1 · Inhalte', $t ); ?></a>
            <a href="#snw-sec-presentation"><?php echo esc_html__( '2 · Darstellung', $t ); ?></a>
            <a href="#snw-sec-design"><?php echo esc_html__( '3 · Design', $t ); ?></a>
            <a href="#snw-sec-request"><?php echo esc_html__( '4 · Anfragen', $t ); ?></a>
            <a href="#snw-sec-preview"><?php echo esc_html__( 'Vorschau', $t ); ?></a>
        </nav>

        <!-- ============ STEP 1: INHALTE ============ -->
        <section class="snw-step" id="snw-sec-content">
            <header class="snw-step__head">
                <span class="snw-step__num">1</span>
                <h2><?php echo esc_html__( 'Inhalte', $t ); ?></h2>
            </header>
            <div class="snw-card">
                <p class="snw-step__lead"><?php echo esc_html__( 'Welche Nachrichten möchten Sie anzeigen?', $t ); ?></p>

                <label for="snw-mode"><?php echo esc_html__( 'Inhaltsauswahl', $t ); ?></label>
                <select id="snw-mode">
                    <option value="latest"><?php echo esc_html__( 'Neueste Beiträge', $t ); ?></option>
                    <option value="category"><?php echo esc_html__( 'Kategorie', $t ); ?></option>
                    <option value="tags"><?php echo esc_html__( 'Schlagwörter', $t ); ?></option>
                    <option value="category_tags"><?php echo esc_html__( 'Kategorie + Schlagwörter', $t ); ?></option>
                    <option value="manual"><?php echo esc_html__( 'Bestimmte Beiträge', $t ); ?></option>
                </select>
                <p class="snw-mode-hint" id="snw-mode-hint"></p>

                <div class="snw-cond" data-show-modes="category,category_tags">
                    <label for="snw-category"><?php echo esc_html__( 'Kategorie', $t ); ?></label>
                    <select id="snw-category" data-placeholder="<?php echo esc_attr__( 'Kategorie wählen', $t ); ?>"></select>
                </div>

                <div class="snw-cond" data-show-modes="tags,category_tags">
                    <label><?php echo esc_html__( 'Schlagwörter', $t ); ?></label>
                    <div class="snw-token" id="snw-tags">
                        <input type="text" class="snw-token__input" id="snw-tag-search" placeholder="<?php echo esc_attr__( 'Schlagwort suchen …', $t ); ?>" aria-label="<?php echo esc_attr__( 'Schlagwort suchen', $t ); ?>">
                        <ul class="snw-token__list" id="snw-tag-list"></ul>
                        <ul class="snw-token__results" id="snw-tag-results" role="listbox"></ul>
                    </div>
                </div>

                <div class="snw-cond" data-show-modes="manual">
                    <label for="snw-post-search"><?php echo esc_html__( 'Beiträge auswählen', $t ); ?></label>
                    <input type="text" id="snw-post-search" placeholder="<?php echo esc_attr__( 'Artikel suchen …', $t ); ?>" aria-label="<?php echo esc_attr__( 'Artikel suchen', $t ); ?>">
                    <ul class="snw-results" id="snw-post-results" role="listbox"></ul>
                    <p class="description"><?php echo esc_html__( 'Gewählte Beiträge (ziehbar, Reihenfolge = Anzeige):', $t ); ?></p>
                    <ol class="snw-selected" id="snw-selected-posts"></ol>
                </div>

                <label for="snw-limit"><?php echo esc_html__( 'Anzahl der Beiträge', $t ); ?></label>
                <input type="number" id="snw-limit" min="1" max="20" value="6">

                <label for="snw-sort"><?php echo esc_html__( 'Sortierung', $t ); ?></label>
                <select id="snw-sort">
                    <option value="newest"><?php echo esc_html__( 'Neueste zuerst', $t ); ?></option>
                    <option value="oldest"><?php echo esc_html__( 'Älteste zuerst', $t ); ?></option>
                    <option value="title"><?php echo esc_html__( 'Titel A–Z', $t ); ?></option>
                    <option value="manual" class="snw-sort-manual"><?php echo esc_html__( 'Manuelle Reihenfolge', $t ); ?></option>
                </select>
            </div>
        </section>

        <!-- ============ STEP 2: DARSTELLUNG ============ -->
        <section class="snw-step" id="snw-sec-presentation">
            <header class="snw-step__head">
                <span class="snw-step__num">2</span>
                <h2><?php echo esc_html__( 'Darstellung', $t ); ?></h2>
            </header>
            <div class="snw-card">
                <label for="snw-title"><?php echo esc_html__( 'Überschrift', $t ); ?></label>
                <input type="text" id="snw-title" class="regular-text" maxlength="80" placeholder="<?php echo esc_attr__( 'z. B. Aktuelles aus der Region', $t ); ?>">
                <p class="description"><?php echo esc_html__( 'Optional. Wird oberhalb der Beiträge angezeigt.', $t ); ?></p>

                <label><?php echo esc_html__( 'Wie sollen die Nachrichten dargestellt werden?', $t ); ?></label>
                <div class="snw-layout-picker" id="snw-layout-picker" role="radiogroup" aria-label="<?php echo esc_attr__( 'Layout', $t ); ?>">
                    <button type="button" class="snw-layout-opt is-active" data-layout="grid" aria-pressed="true">
                        <span class="snw-layout-thumb snw-thumb-grid" aria-hidden="true"></span>
                        <span class="snw-layout-name"><?php echo esc_html__( 'Raster', $t ); ?></span>
                    </button>
                    <button type="button" class="snw-layout-opt" data-layout="list" aria-pressed="false">
                        <span class="snw-layout-thumb snw-thumb-list" aria-hidden="true"></span>
                        <span class="snw-layout-name"><?php echo esc_html__( 'Liste', $t ); ?></span>
                    </button>
                    <button type="button" class="snw-layout-opt" data-layout="cards" aria-pressed="false">
                        <span class="snw-layout-thumb snw-thumb-cards" aria-hidden="true"></span>
                        <span class="snw-layout-name"><?php echo esc_html__( 'Karten', $t ); ?></span>
                    </button>
                    <button type="button" class="snw-layout-opt" data-layout="compact" aria-pressed="false">
                        <span class="snw-layout-thumb snw-thumb-compact" aria-hidden="true"></span>
                        <span class="snw-layout-name"><?php echo esc_html__( 'Kompakt', $t ); ?></span>
                    </button>
                    <button type="button" class="snw-layout-opt" data-layout="headlines" aria-pressed="false">
                        <span class="snw-layout-thumb snw-thumb-headlines" aria-hidden="true"></span>
                        <span class="snw-layout-name"><?php echo esc_html__( 'Nur Überschriften', $t ); ?></span>
                    </button>
                </div>
                <input type="hidden" id="snw-layout" value="grid">

                <fieldset class="snw-checkboxes">
                    <legend><?php echo esc_html__( 'Angezeigte Informationen', $t ); ?></legend>
                    <label><input type="checkbox" id="snw-show-image" checked> <?php echo esc_html__( 'Beitragsbild', $t ); ?></label>
                    <label><input type="checkbox" id="snw-show-date" checked> <?php echo esc_html__( 'Datum', $t ); ?></label>
                    <label><input type="checkbox" id="snw-show-category"> <?php echo esc_html__( 'Kategorie', $t ); ?></label>
                    <label><input type="checkbox" id="snw-show-excerpt" checked> <?php echo esc_html__( 'Teaser', $t ); ?></label>
                    <label><input type="checkbox" id="snw-show-readmore" checked> <?php echo esc_html__( 'Weiterlesen-Link', $t ); ?></label>
                    <label><input type="checkbox" id="snw-show-author"> <?php echo esc_html__( 'Autor anzeigen', $t ); ?></label>
                </fieldset>
            </div>
        </section>

        <!-- ============ STEP 3: DESIGN ============ -->
        <section class="snw-step" id="snw-sec-design">
            <header class="snw-step__head">
                <span class="snw-step__num">3</span>
                <h2><?php echo esc_html__( 'Design', $t ); ?></h2>
            </header>
            <div class="snw-card">
                <label><?php echo esc_html__( 'Farbschema', $t ); ?></label>
                <div class="snw-segmented" id="snw-theme-picker" role="radiogroup" aria-label="<?php echo esc_attr__( 'Farbschema', $t ); ?>">
                    <button type="button" class="snw-seg-opt is-active" data-theme="light" aria-pressed="true"><?php echo esc_html__( 'Hell', $t ); ?></button>
                    <button type="button" class="snw-seg-opt" data-theme="dark" aria-pressed="false"><?php echo esc_html__( 'Dunkel', $t ); ?></button>
                </div>
                <input type="hidden" id="snw-theme" value="light">

                <label for="snw-color-accent"><?php echo esc_html__( 'Akzentfarbe', $t ); ?></label>
                <input type="text" class="snw-color__input" id="snw-color-accent" value="#c59a20" data-default="#c59a20">

                <label for="snw-color-background"><?php echo esc_html__( 'Hintergrundfarbe', $t ); ?> <span class="snw-optional"><?php echo esc_html__( '(optional)', $t ); ?></span></label>
                <input type="text" class="snw-color__input" id="snw-color-background" value="" data-default="">

                <div class="snw-cond" data-show-checkbox="snw-show-image">
                    <details class="snw-fieldset" id="snw-image-options">
                        <summary><?php echo esc_html__( 'Bilddarstellung', $t ); ?></summary>

                        <label for="snw-image-ratio"><?php echo esc_html__( 'Bildformat', $t ); ?></label>
                        <select id="snw-image-ratio">
                            <option value="16:9" selected>16:9</option>
                            <option value="4:3">4:3</option>
                            <option value="3:2">3:2</option>
                            <option value="1:1">1:1</option>
                        </select>

                        <label for="snw-image-fit"><?php echo esc_html__( 'Bildfüllung', $t ); ?></label>
                        <select id="snw-image-fit">
                            <option value="cover" selected><?php echo esc_html__( 'Zuschneiden', $t ); ?></option>
                            <option value="contain"><?php echo esc_html__( 'Vollständig anzeigen', $t ); ?></option>
                        </select>

                        <div class="snw-cond" data-show-layout="list">
                            <label for="snw-image-position"><?php echo esc_html__( 'Bildposition (Liste)', $t ); ?></label>
                            <select id="snw-image-position">
                                <option value="left" selected><?php echo esc_html__( 'Links', $t ); ?></option>
                                <option value="right"><?php echo esc_html__( 'Rechts', $t ); ?></option>
                                <option value="top"><?php echo esc_html__( 'Oben', $t ); ?></option>
                            </select>
                        </div>
                    </details>
                </div>

                <div class="snw-cond" data-show-layout="grid,cards">
                    <label for="snw-columns"><?php echo esc_html__( 'Artikel pro Reihe', $t ); ?></label>
                    <select id="snw-columns">
                        <option value="1">1</option>
                        <option value="2" selected>2</option>
                        <option value="3">3</option>
                        <option value="4">4</option>
                    </select>
                </div>

                <details class="snw-fieldset" id="snw-more-design">
                    <summary><?php echo esc_html__( 'Weitere Designoptionen', $t ); ?></summary>

                    <label><?php echo esc_html__( 'Ecken', $t ); ?></label>
                    <div class="snw-segmented" id="snw-radius-picker" role="radiogroup" aria-label="<?php echo esc_attr__( 'Ecken', $t ); ?>">
                        <button type="button" class="snw-seg-opt" data-radius="0"><?php echo esc_html__( 'Eckig', $t ); ?></button>
                        <button type="button" class="snw-seg-opt" data-radius="4"><?php echo esc_html__( 'Leicht abgerundet', $t ); ?></button>
                        <button type="button" class="snw-seg-opt is-active" data-radius="8"><?php echo esc_html__( 'Abgerundet', $t ); ?></button>
                        <button type="button" class="snw-seg-opt" data-radius="12"><?php echo esc_html__( 'Stark abgerundet', $t ); ?></button>
                    </div>
                    <input type="hidden" id="snw-radius" value="8">

                    <label><?php echo esc_html__( 'Abstände', $t ); ?></label>
                    <div class="snw-segmented" id="snw-spacing-picker" role="radiogroup" aria-label="<?php echo esc_attr__( 'Abstände', $t ); ?>">
                        <button type="button" class="snw-seg-opt" data-spacing="compact"><?php echo esc_html__( 'Kompakt', $t ); ?></button>
                        <button type="button" class="snw-seg-opt is-active" data-spacing="normal"><?php echo esc_html__( 'Normal', $t ); ?></button>
                        <button type="button" class="snw-seg-opt" data-spacing="spacious"><?php echo esc_html__( 'Großzügig', $t ); ?></button>
                    </div>
                    <input type="hidden" id="snw-spacing" value="normal">

                    <label for="snw-teaser"><?php echo esc_html__( 'Teaser-Länge (Zeichen)', $t ); ?></label>
                    <input type="range" id="snw-teaser" min="50" max="400" step="10" value="180">
                    <output id="snw-teaser-out">180</output>

                    <label for="snw-shadow"><?php echo esc_html__( 'Schatten', $t ); ?></label>
                    <select id="snw-shadow">
                        <option value="none" selected><?php echo esc_html__( 'Keiner', $t ); ?></option>
                        <option value="sm"><?php echo esc_html__( 'Dezent', $t ); ?></option>
                        <option value="md"><?php echo esc_html__( 'Mittel', $t ); ?></option>
                        <option value="lg"><?php echo esc_html__( 'Stark', $t ); ?></option>
                    </select>

                    <label for="snw-separator"><?php echo esc_html__( 'Artikel trennen', $t ); ?></label>
                    <select id="snw-separator">
                        <option value="line" selected><?php echo esc_html__( 'Linie', $t ); ?></option>
                        <option value="none"><?php echo esc_html__( 'Keine Trennung', $t ); ?></option>
                    </select>

                    <label for="snw-align"><?php echo esc_html__( 'Textausrichtung', $t ); ?></label>
                    <select id="snw-align">
                        <option value="left" selected><?php echo esc_html__( 'Links', $t ); ?></option>
                        <option value="center"><?php echo esc_html__( 'Zentriert', $t ); ?></option>
                        <option value="right"><?php echo esc_html__( 'Rechts', $t ); ?></option>
                    </select>

                    <label for="snw-link-mode"><?php echo esc_html__( 'Klickbereich', $t ); ?></label>
                    <select id="snw-link-mode">
                        <option value="title" selected><?php echo esc_html__( 'Überschrift', $t ); ?></option>
                        <option value="card"><?php echo esc_html__( 'Gesamter Beitrag', $t ); ?></option>
                    </select>

                    <label for="snw-readmore-label"><?php echo esc_html__( '„Weiterlesen“-Text', $t ); ?></label>
                    <input type="text" id="snw-readmore-label" class="regular-text" maxlength="80" placeholder="<?php echo esc_attr__( 'Artikel lesen', $t ); ?>">

                    <details class="snw-fieldset" id="snw-more-colors">
                        <summary><?php echo esc_html__( 'Weitere Farben', $t ); ?></summary>
                        <span class="snw-color"><input type="text" class="snw-color__input" id="snw-color-text" value="" data-default=""> <span><?php echo esc_html__( 'Textfarbe', $t ); ?></span></span>
                        <span class="snw-color"><input type="text" class="snw-color__input" id="snw-color-muted" value="" data-default=""> <span><?php echo esc_html__( 'Sekundäre Textfarbe', $t ); ?></span></span>
                        <span class="snw-color"><input type="text" class="snw-color__input" id="snw-color-border" value="" data-default=""> <span><?php echo esc_html__( 'Rahmenfarbe', $t ); ?></span></span>
                        <span class="snw-color"><input type="text" class="snw-color__input" id="snw-color-link" value="" data-default=""> <span><?php echo esc_html__( 'Linkfarbe', $t ); ?></span></span>
                    </details>
                </details>
            </div>
        </section>

        <!-- ============ Admin-only controls (hidden on public) ============ -->
        <div class="snw-admin-only">
            <input type="text" id="snw-name" value="">
            <input type="text" id="snw-partner" value="">
            <select id="snw-on-error">
                <option value="message" selected><?php echo esc_html__( 'Hinweis anzeigen', $t ); ?></option>
                <option value="hide"><?php echo esc_html__( 'Widget ausblenden', $t ); ?></option>
            </select>
            <textarea id="snw-custom-css" rows="1"></textarea>
            <input type="text" id="snw-empty-label" value="">
            <input type="text" id="snw-error-label" value="">
            <select id="snw-heading-level">
                <option value="h2">H2</option>
                <option value="h3" selected>H3</option>
                <option value="h4">H4</option>
            </select>
            <input type="number" id="snw-title-length" min="0" max="200" value="0">
            <select id="snw-typography">
                <option value="host" selected><?php echo esc_html__( 'Host-Website übernehmen', $t ); ?></option>
                <option value="system"><?php echo esc_html__( 'System Sans', $t ); ?></option>
                <option value="arial">Arial</option>
                <option value="georgia">Georgia</option>
                <option value="serif"><?php echo esc_html__( 'Serif', $t ); ?></option>
                <option value="sans"><?php echo esc_html__( 'Sans Serif', $t ); ?></option>
            </select>
            <select id="snw-date-format">
                <option value="absolute" selected><?php echo esc_html__( 'Absolut', $t ); ?></option>
                <option value="relative"><?php echo esc_html__( 'Relativ', $t ); ?></option>
            </select>
            <input type="checkbox" id="snw-show-branding" checked>
            <input type="url" id="snw-api" value="">
            <input type="text" id="snw-source-name" value="">
            <input type="url" id="snw-source-url" value="">
            <input type="text" id="snw-widget-id" value="" readonly>
            <input type="text" id="snw-exclude" value="">
            <input type="number" id="snw-auto-count" min="0" max="20" value="3">
            <input type="text" id="snw-pinned-search" value="">
            <ul id="snw-pinned-results"></ul>
            <ol id="snw-pinned-list"></ol>
            <textarea id="snw-raw" rows="1" readonly></textarea>
        </div>

        <!-- ============ STEP 4: WIDGET ANFRAGEN ============ -->
        <section class="snw-step snw-request" id="snw-sec-request">
            <header class="snw-step__head">
                <span class="snw-step__num">4</span>
                <h2><?php echo esc_html__( 'Widget anfragen', $t ); ?></h2>
            </header>
            <div class="snw-card snw-request__card">
                <p class="snw-request__lead">
                    <?php echo esc_html__( 'Gefällt Ihnen das Widget? Senden Sie uns Ihre Anfrage. Wir prüfen die Konfiguration und schicken Ihnen anschließend den fertigen Einbettungscode für Ihre Website.', $t ); ?>
                </p>

                <label for="snw-req-name"><?php echo esc_html__( 'Name', $t ); ?> <span class="snw-optional"><?php echo esc_html__( '(optional)', $t ); ?></span></label>
                <input type="text" id="snw-req-name" class="regular-text" maxlength="100" autocomplete="name">

                <label for="snw-req-email"><?php echo esc_html__( 'E-Mail-Adresse', $t ); ?> <span aria-hidden="true">*</span></label>
                <input type="email" id="snw-req-email" class="regular-text" required autocomplete="email">

                <label for="snw-req-domain"><?php echo esc_html__( 'Website', $t ); ?> <span aria-hidden="true">*</span></label>
                <input type="text" id="snw-req-domain" class="regular-text" required placeholder="https://www.verein.de">

                <button type="button" id="snw-request-submit" class="button button-primary snw-request-btn">
                    <?php echo esc_html__( 'Widget kostenlos anfragen', $t ); ?>
                </button>
                <p class="snw-request__note">
                    <?php echo esc_html__( 'Ihre Einstellungen werden zusammen mit der Anfrage übermittelt. Nach der Freigabe erhalten Sie den fertigen Einbettungscode.', $t ); ?>
                </p>
                <span id="snw-save-status" class="snw-status" role="status" aria-live="polite"></span>
            </div>
        </section>
        <?php
    }
}
