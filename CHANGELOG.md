# Changelog

## 1.2.0

Konsolidiertes Release. Fasst alle Weiterentwicklungen zwischen 1.1.1 und dem
vorherigen internen Entwicklungsstand zusammen und bereinigt die
Zwischen-Versionierung.

### Telemetrie & Statistik (DSGVO-konform)
* Neues Analytics-Subsystem mit abgekoppelter, öffentlicher Telemetry-API:
  Alias `/sn-widget/telemetry/v1/*` → intern `wp-json/snw-telemetry/v1/*`.
  Öffentliches Ingestion (`POST /event`) ist CORS-offen und rate-limitiert;
  Analytics-Lesen ist durch `manage_options` + REST-Nonce abgesichert.
* Roh- vs. Viewable-Metriken (`widget_load`, `viewable_impression` via
  IntersectionObserver, `article_click`), ohne Fingerprinting und ohne
  IP-Speicherung (server-seitiger HMAC-Visitor-Key).
* Server-seitige Aggregation (Raw → Daily) für abgeschlossene Tage; Retention-Cleanup
  (Standard 365 Tage) und Cron-Rollup.
* Statistik-Dashboard: KPI-Karten, zwei SVG-Liniencharts (kein externes CDN),
  Top-Widgets/Seiten/Artikel/Partner, Detail-Modal, Realtime-Polling,
  Einstellungen/Debug, CSV-Export (admin-ajax).

### Partner-Modell
* Ein Partner (E-Mail) erhält genau einen Widget-Code; beim Annehmen einer
  Anfrage wird ein bestehender Code derselben E-Mail wiederverwendet
  (`SNW_Presets::find_by_email`, `SNW_Settings::ajax_accept_request`).
* Code↔Partner-Verknüpfung in der Statistik; neue Admin-Seite "Partner".

### Branding
* Global konfigurierbares Branding (Bild, Bildgröße, Textgröße, Text
  "Nachrichten von", Markenname, Link) unter Einstellungen → SN News Widget.
* Injection an allen Ausspielwegen (`SNW_Helpers::apply_branding`) – Änderungen
  wirken ohne erneutes Einbetten.

### Öffentlicher Builder
* Voller Funktionsumfang inkl. Live-Vorschau; zentrales `SNW_Builder::render_form`
  für Admin- und Public-Kontext; Einreichungen als Partneranfrage.

### Sicherheit
* Token-basierter Embed: externe Snippets geben nur noch ein `data-code`-Token
  aus (kein Base64-Customization mehr im Quelltext).
* Domain-Lock: Backend liefert Konfiguration nur an die freigegebene Domain
  (sonst 403) + Client-seitige Prüfung im Renderer.
* `client_ip()` vertraut X-Forwarded-For nur noch hinter einer privaten/loopback
  Proxy – öffentliche Spoofing-Versuche umgehen den Rate-Limit nicht mehr.
* Health-Endpoint gibt keine Versionsnummer mehr preis.

### Admin & Stabilität
* Dashboard als Startseite, eigener Top-Level-Menüpunkt "SN News Widget",
  getrennte Unterseiten (Gespeicherte / Partneranfragen / Partner).
* Admin-REST-Routen über direkte Callback-Registrierung (behobener 404-Bug
  durch Bare-Skalar-Request-Arg im generischen Dispatcher).
* Telemetrie-Tabellen-Schema-Fix (Primary-Key-Länge), `dbDelta`-kompatibel und
  idempotent bei wiederholter Aktivierung.
* Produktname überall auf "SN News Widget" vereinheitlicht (nutzersichtbar).

## 1.1.1

Bugfix-Release – behebt mehrere Release-Blocker aus der Praxis-Prüfung:
Builder-Start (Farbnwähler-Init), Preset-Bearbeiten/Zurücksetzen, fehlendes
`_embedded` bei echter API, Bild-ODER-Kategorie-Embed, Inhaltsmodus-Isolation,
defekte Layouts, `radius=0`/`auto_count=0` Defaults, Preset-ID-Bindung,
Timeout-Ladezustand, Link-Targets (`_blank`), i18n-Texte. Release-ZIP enthält
keine `tests/`/`docs/`.

## 1.1.0

Vollständige Neugestaltung als produktionsreifes Widget:
* Widget Builder (Konfiguration + Live-Vorschau).
* Sechs Inhaltsmodi inkl. Hybrid/Pinned.
* Vier Layouts (News-Liste, Karten, Kompakt, Überschriften).
* Design-Builder mit Farbwähler, Radius, Abstand, Schriftart.
* Tag-/Post-Suche mit Sortierung.
* Presets in WordPress-Optionen (keine DB-Tabelle).
* Kompakter, base64url-kodierter Einbettungscode.
* Public-Renderer: Vanilla JS, Caching, AbortController-Timeout, Fehlerzustände,
  CSS-Isolation.
* UTM-Tracking, Capabilities/Nonces/Sanitizing/Escaping, i18n-Vorbereitung.

## 1.0.2

Entfernung des eigenen REST-Endpunkts (HTTP-500-Ursache). Nutzung der
WordPress-Core-REST-API.

## 1.0.1

Einführung eines eigenen REST-Endpunkts (später entfernt – siehe 1.0.2).

## 1.0.0

Erste Veröffentlichung.
