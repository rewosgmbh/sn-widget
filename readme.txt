=== Steigerwald-News Widget ===
Contributors: steigerwald-news
Tags: news, widget, embed, rest-api, rss, external
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.1.1
License: GPL-2
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Erstellt extern einbettbare Nachrichten-Widgets aus deinen WordPress-Beiträgen – ohne eigenen REST-Endpunkt, ohne eigene Datenbanktabelle.

== Description ==

Das Steigerwald-News Widget macht deine WordPress-Beiträge auf fremden Websites sichtbar:
WordPress auswählen, Widget konfigurieren, Live-Vorschau prüfen, Einbettungscode kopieren, fertig.

* Nutzt ausschließlich die vorhandene WordPress-REST-API (`/wp-json/wp/v2/...`).
* Kein eigener Endpunkt, keine eigene Datenbanktabelle, keine Cronjobs.
* Vier Layouts: News-Liste, Karten, Kompakt, Nur Überschriften.
* Inhaltsmodi: Neueste, Kategorie, Schlagwörter, Kategorie+Tags, Einzelne Beiträge, Hybrid (Angeheftet + Automatisch).
* Live-Vorschau im Admin mit echten Beiträgen.
* Presets (gespeicherte Konfigurationen) in WordPress-Optionen.
* Partnerkennung für UTM-Tracking.
* Stateless: der Partner muss kein Plugin installieren – ein HTML/JS-Embed genügt.
* Vanilla-JS-Public-Renderer, keine Frameworks, keine externen CDNs.

== Installation ==

1. Plugin hochladen und aktivieren.
2. Einstellungen → News Widget.
3. Widget konfigurieren und in der Live-Vorschau prüfen.
4. "Widget speichern".
5. "Einbettungscode kopieren" und auf der Partnerseite einfügen.

== Frequently Asked Questions ==

= Braucht die Partnerseite das Plugin? =
Nein. Der Einbettungscode enthält nur ein `<div>` mit Konfiguration und einen `<script>`-Tag.

= Wird ein eigener REST-Endpunkt registriert? =
Nein. Es wird ausschließlich die WordPress-Core-REST-API verwendet.

= Werden Beiträge kopiert? =
Nein. Presets speichern nur Konfiguration, keine Inhalte.

== Screenshots ==

1. Widget Builder mit Live-Vorschau.
2. Gespeicherte Widgets (Presets).

== Changelog ==

= 1.1.0 =
* Vollständiger Widget Builder (Konfiguration + Live-Vorschau).
* Sechs Inhaltsmodi inkl. Hybrid/Pinned.
* Vier Layouts (News-Liste, Karten, Kompakt, Überschriften).
* Design-Builder mit Farbwähler, Radius, Abstand, Schriftart.
* Tag-Suche/Autocomplete, Post-Suche mit Sortierung.
* Presets in WordPress-Optionen (keine DB-Tabelle).
* Kompakter, base64url-kodierter Einbettungscode.
* Public-Renderer: Vanilla JS, Caching, AbortController-Timeout, Fehlerzustände, CSS-Isolation.
* Vollständige i18n-Vorbereitung.

= 1.0.2 =
* Entfernung des eigenen REST-Endpunkts (HTTP-500-Ursache).
* Nutzung der WordPress-Core-REST-API.

== Upgrade Notice ==

= 1.1.0 =
Ersetzt die einfache Einstellungsseite durch einen Widget Builder. Bestehende Einbettungscodes (Slug-basiert) sollten durch neue Presets neu erzeugt werden.

== Arbitrary section ==

Keine Telemetrie, keine versteckten Tracking-Requests, keine erzwungenen Backlinks.
