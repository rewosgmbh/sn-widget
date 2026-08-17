# Architektur – Steigerwald-News Widget 1.1.0

## Prinzipien

1. **Stabilität vor allem** – das Widget darf niemals die Host- oder
   Source-Website mitreißen. Jeder Fehler führt zu einem sauberen Fallback
   (Hinweis oder Ausblenden), nie zu einem uncaught Error.
2. **Kein eigener REST-Endpunkt.** Es wird ausschließlich die
   WordPress-Core-REST-API genutzt:
   `/wp-json/wp/v2/posts`, `/categories`, `/tags`, `/media`.
   Der frühere eigene Endpunkt (`/sn-widget/v1/posts`) wurde in 1.0.2 entfernt
   und bleibt dauerhaft entfernt (er war die Ursache eines HTTP-500).
3. **Keine eigene Datenbanktabelle.** Presets liegen in einer WordPress-Option.
4. **Stateless & extern.** Der Partner bindet nur einen HTML-/JS-Embed ein –
   kein Plugin auf der Partnerseite nötig.
5. **Vanilla JS im Public-Renderer.** Keine Frameworks, kein jQuery, keine
   externen CDNs, keine externen Fonts.

## Verzeichnisstruktur

```
steigerwald-news-widget/
├── steigerwald-news-widget.php   # Bootstrap, Plugin-Header, Konstanten
├── uninstall.php                 # Entfernt nur snw_presets
├── readme.txt                    # WordPress.org-Format
├── CHANGELOG.md
├── includes/
│   ├── Plugin.php                # Hooks, Textdomain, Action-Links
│   ├── Admin.php                 # Widget-Builder-Seite (HTML + Preset-Tabelle)
│   ├── Settings.php              # admin-ajax Handler (Speichern/Löschen/Duplizieren/Liste)
│   ├── Presets.php               # CRUD in WP-Option (kein eigenes DB-Table)
│   ├── EmbedGenerator.php        # base64url-Kodierung + Embed-Code
│   ├── Assets.php                # Enqueue + Localization
│   └── Helpers.php               # Config-Defaults, Sanitisierung, Validierung, ID-Generierung
├── admin/
│   ├── css/admin.css
│   └── js/admin.js              # Builder-Logik, Live-Vorschau, Preset-CRUD
├── public/
│   ├── css/widget.css           # Statisches Basis-Stylesheet (Spiegel von JS-Inline)
│   └── js/widget.js             # Kern-Renderer (Vanilla JS)
├── languages/steigerwald-news-widget.pot
└── tests/
    ├── php/test-helpers.php
    └── js/widget.test.js, render.test.js
```

## Datenfluss

```
Admin (WordPress)
  └─ admin.js
       ├─ liest Kategorien/Tags/Posts via Core-REST (gleiche Origin)
       ├─ baut Config-Objekt
       ├─ codiert → data-config (base64url JSON)
       ├─ Live-Vorschau via public/widget.js (gleiche Render-Pipeline)
       └─ Speichern → admin-ajax (Settings.php) → Presets.php → WP-Option

Partner-Website (beliebiges CMS/static)
  └─ <div class="steigerwald-news-widget" data-config="…"></div>
     <script src="…/public/js/widget.js" async></script>
        └─ widget.js dekodiert Config, holt Beiträge via Core-REST (CORS),
           rendert, cached, isoliert durch CSS unter .steigerwald-news-widget
```

## Konfigurations-Schema (Config-Objekt)

Gespeichert/embedded als JSON, base64url-kodiert. Wesentliche Felder:
`api`, `source_name`, `source_url`, `widget_id`, `partner`, `title`, `mode`
(`latest|category|tags|category_tags|manual|hybrid`), `category[]`, `tags[]`,
`include[]` (manuelle Posts), `pinned[]` (Hybrid), `exclude[]`, `auto_count`,
`limit` (1–20), `sort` (`newest|oldest|manual|title`), `layout`
(`list|cards|compact|headlines`), `show{image,date,category,excerpt,readmore,branding}`,
`teaser` (50–400), `design{accent,background,text,muted,border,link,radius,spacing,typography}`,
`on_error` (`message|hide`).

## Public-Renderer (widget.js)

* **Idempotenz**: `el.__snwBuilt` verhindert Doppel-Render; `STYLE_ID` verhindert
  doppeltes Stylesheet; Guard gegen Mehrfach-Einbindung des Scripts.
* **Fetch**: nur Core-REST, `_fields` zur Minimierung der Payload, `_embed`
  (`wp:term` nur wenn Kategorie sichtbar, sonst `wp:featuredmedia`) – vermeidet
  N+1-Requests für Kategorie-Labels.
* **Caching**: Memory + SessionStorage, TTL ~10 Min, Key aus
  Source/Modus/Kategorie/Tags/Sortierung/Anzahl/IDs – aber **nicht** Design
  (Design ändert keine Daten).
* **Timeout**: `AbortController` (~9 s); bei Abbruch/Fehler kein Host-Bruch.
* **Fehlerzustände**: API weg → Hinweis oder Ausblenden (config-abhängig);
  keine Posts/Kategorie/Tag nicht gefunden → Empty-State; fehlendes Bild →
  Layout bleibt intakt; ungültige Config → `console.debug`, kein Stack-Trace.
* **CSS-Isolation**: alle Selektoren unter `.steigerwald-news-widget`;
  Container-Queries (mit `@media`-Fallback) für echte Container-Breite.
* **A11y**: semantisches HTML (`section`/`h2`/`article`/`h3`/`time`),
  Links sind Links, Buttons sind Buttons, sichtbare Focus-States, `alt=""`
  bei dekorativen Bildern.

## Admin-Builder (admin.js)

* Nutzt **WordPress-Core-APIs** (Color Picker, REST). Kein Frontend-Framework.
* Live-Vorschau rendert durch exakt denselben `widget.js` wie die Production
  (kein Duplikat-Code).
* Presets: Speichern/Laden/Löschen/Duplizieren über `admin-ajax`
  (Capability + Nonce). Gespeicherte IDs werden beim Bearbeiten zu
  lesbaren Titeln/Namen rehydriert.
