=== SN News Widget ===
Contributors: ottilione
Tags: news, widget, embed, rest-api, rss, external, analytics
Requires at least: 6.0
Tested up to: 7.0.4
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPL-2
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Erstellt extern einbettbare Nachrichten-Widgets aus deinen WordPress-Beiträgen – optional mit DSGVO-konformer Statistik/Telemetrie.

== Description ==

SN News Widget macht deine WordPress-Beiträge auf fremden Websites sichtbar:
WordPress auswählen, Widget konfigurieren, Live-Vorschau prüfen, Einbettungscode kopieren, fertig.

* Die Beiträge werden über die vorhandene WordPress-REST-API geladen.
* Kein eigenes Plugin auf der Partnerseite nötig – ein HTML/JS-Embed genügt.
* Fünf Layouts: Raster, News-Liste, Karten, Kompakt, Nur Überschriften.
* Inhaltsmodi: Neueste, Kategorie, Schlagwörter, Kategorie+Tags, Einzelne Beiträge, Hybrid (Angeheftet + Automatisch).
* Live-Vorschau im Admin mit echten Beiträgen.
* Presets (gespeicherte Konfigurationen) in WordPress-Optionen.
* Partnerkennung für UTM-Tracking.
* Stateless: der Partner muss kein Plugin installieren.
* Vanilla-JS-Public-Renderer, keine Frameworks, keine externen CDNs.
* Optionale, DSGVO-konforme Statistik/Telemetrie (Analytics-Subsystem, Server-seitige Aggregation, keine IP-Speicherung, kein Fingerprinting).
* Konfigurierbares, globales Branding an allen Ausspielwegen.

== Installation ==

1. Plugin hochladen und aktivieren.
2. SN News Widget → Erstellen.
3. Widget konfigurieren und in der Live-Vorschau prüfen.
4. "Widget speichern".
5. "Einbettungscode kopieren" und auf der Partnerseite einfügen.

== Frequently Asked Questions ==

= Braucht die Partnerseite das Plugin? =
Nein. Der Einbettungscode enthält nur ein <div> mit Token und einen <script>-Tag.

= Wird ein eigener REST-Endpunkt für Beiträge registriert? =
Nein. Die Beiträge werden ausschließlich über die WordPress-Core-REST-API geladen. (Zusätzlich gibt es – sofern die Telemetrie aktiviert ist – eine abgekoppelte, öffentliche Telemetry-API sowie einen Partner-Endpunkt.)

= Werden Beiträge kopiert? =
Nein. Presets speichern nur Konfiguration, keine Inhalte.

== Screenshots ==

1. Widget Builder mit Live-Vorschau.
2. Gespeicherte Widgets (Presets).

== Changelog ==

= 1.2.0 =
Konsolidiertes Release. Fasst alle Weiterentwicklungen seit 1.1.1 zusammen:

* **Telemetrie & Statistik (DSGVO-konform):** Analytics-Subsystem mit eigener, öffentlicher Telemetry-API (`/sn-widget/telemetry/v1/*` → intern `snw-telemetry/v1/*`), Server-seitiger Aggregation (Raw → Daily), tagesgenauer Unique-Visitor-Zahlen ohne IP-Speicherung/Fingerprinting, Statistik-Dashboard (KPIs, SVG-Charts, Top-Listen, Realtime, CSV-Export), robustem Client-Telemetry und Retention-Cleanup per Cron.
* **Partner-Modell:** Ein Partner (E-Mail) erhält genau einen Widget-Code; beim Annehmen wird ein bestehender Code wiederverwendet. Neue "Partner"-Seite, Code↔Partner-Verknüpfung in der Statistik, Builder-Nutzungs-KPI.
* **Branding:** Global konfigurierbares Branding (Bild, Bildgröße, Textgröße, Text, Name, Link), injiziert an allen Ausspielwegen.
* **Öffentlicher Builder:** Voller Funktionsumfang inkl. Live-Vorschau; Einreichungen als Partneranfrage.
* **Sicherheit:** Token-basierter Embed (kein Base64-Customization mehr in externen Snippets), Domain-Lock (Backend 403 + Client-Prüfung), CORS nur für öffentliche Telemetry-Ingestion, Analytics-Lesen via `manage_options` + Nonce.
* **Admin:** Dashboard als Startseite, eigener Top-Level-Menüpunkt, getrennte Unterseiten für Gespeicherte/Anfragen/Partner.
* **Stabilität:** Admin-REST-Routen-Dispatcher (404-Bug behoben), Telemetrie-Tabellen-Schema-Fix, konsolidierte Fehlerbehandlung.

= 1.1.1 =
Bugfix-Release – behebt mehrere Release-Blocker aus der Praxis-Prüfung (Builder-Start, Preset-Bearbeitung, REST `_embedded`, Layout/Inhaltsmodus-Isolation, Timeouts, Link-Targets, i18n).

= 1.1.0 =
Vollständige Neugestaltung als produktionsreifes Widget: Builder mit Live-Vorschau, sechs Inhaltsmodi, vier Layouts, Design-Builder, Presets, Public-Renderer, UTM-Tracking, Sicherheit (Capabilities/Nonces/Sanitizing/Escaping), i18n-Vorbereitung.

= 1.0.2 =
Entfernung des eigenen REST-Endpunkts (Ursache eines HTTP-500 auf einer Testinstallation). Nutzung ausschließlich der WordPress-Core-REST-API.

= 1.0.1 =
Einführung eines eigenen REST-Endpunkts (später entfernt – siehe 1.0.2).

= 1.0.0 =
Erste Veröffentlichung.

== Upgrade Notice ==

= 1.2.0 =
Konsolidiertes Release. Bestehende Einbettungscodes (Token/Shortcode) bleiben funktionsfähig; bei aktivierter Telemetrie werden beim Upgrade die Analytics-Tabellen angelegt.

== Arbitrary section ==

Keine versteckten Tracking-Requests ohne Einwilligung. Die Telemetrie ist standardmäßig deaktiviert und DSGVO-konform ausgelegt.
