# SN News Widget

**SN News Widget** erstellt extern einbettbare Nachrichten-Widgets aus deinen
WordPress-Beiträgen. Die Widgets werden über die vorhandene WordPress-REST-API
mit Inhalten befüllt – die Partnerseite braucht kein Plugin, nur ein
`<div>`-Element und ein `<script>`.

> Herausgeber: **Ottili ONE** · <https://ld3.ottili.one>
> Lizenz: GPL-2

---

## Was das Plugin kann

* **Widget Builder** mit Live-Vorschau (Admin *und* öffentlich).
* **Sechs Inhaltsmodi:** Neueste Beiträge, Kategorie, Schlagwörter,
  Kategorie + Schlagwörter, Einzelne Beiträge (Suche, sortierbar) und
  **Hybrid** (angeheftete + automatische Beiträge).
* **Fünf Layouts:** Raster, News-Liste, Karten, Kompakt, Nur Überschriften.
* **Design-Builder:** Akzent-/Hintergrund-/Text-/Rahmen-/Linkfarbe, Eckenradius,
  Abstand, Schriftart, Hell/Dunkel-Thema und ein auf das Widget begrenztes
  Profi-CSS-Feld (`@import` wird blockiert, CSS wird auf das Widget gescoped).
* **Presets** (gespeicherte Konfigurationen) in WordPress-Optionen –
  keine eigene Datenbanktabelle für Presets.
* **Externer Embed ohne Plugin** auf der Partnerseite (nur `<div>` + `<script>`).
* **Public-Renderer:** Vanilla JS, keine Frameworks, keine externen CDNs,
  CSS-Isolation, AbortController-Timeout, Client-Caching, saubere Fehler-/Leer-Zustände.
* **UTM-Tracking** über die Partnerkennung.
* **Optionale, DSGVO-konforme Statistik/Telemetrie** (Analytics-Subsystem mit
  eigener, abgekoppelter Telemetry-API, Server-seitiger Aggregation, ohne
  Fingerprinting und ohne IP-Speicherung).
* **Konfigurierbares Branding** (global), das an allen Ausspielwegen injectet wird.
* **WordPress-Dashboard-Widget** mit kompakter Statistik-Übersicht.
* Vollständige i18n-Vorbereitung (`.pot`).

---

## Schnellstart

1. Plugin über den WordPress-Admin hochladen und **aktivieren**.
   (Bei der Aktivierung wird ggf. die öffentliche Builder-Seite und – sofern die
   Telemetrie aktiviert ist – die Analytics-Tabellen angelegt.)
2. **SN News Widget → Erstellen** öffnen.
3. Inhalt & Modus wählen, Design anpassen, in der Live-Vorschau prüfen.
4. **Widget speichern** → es erscheint unter **Gespeicherte**.
5. **Einbettungscode kopieren** und auf der Zielseite einfügen.

---

## Admin-Bereiche

| Menüpunkt | Zweck |
|-----------|-------|
| **Dashboard** | Übersicht: gespeicherte Widgets, offene/akzeptierte Anfragen, Beiträge, letzte Widgets/Anfragen, Verteilung nach Modus. |
| **Erstellen** | Widget-Builder mit Live-Vorschau. |
| **Gespeicherte** | Presets verwalten (Bearbeiten, Duplizieren, Code kopieren, Löschen). |
| **Statistik** | Analytics-Dashboard (KPIs, SVG-Charts, Top-Listen, Realtime, CSV-Export) – nur bei aktiver Telemetrie. |
| **Partneranfragen** | Eingehende Partner-Erstellungen ansehen, Vorschau, akzeptieren/ablehnen. |
| **Partner** | Freigegebene Partner (separat von den Anfragen). |
| **Einstellungen** | Branding (Bild, Textgröße, Text, Name, Link) und Telemetrie-Einstellungen. |

---

## Einbettungsmöglichkeiten

### 1. Same-Site (WordPress-Shortcode)
```shortcode
[steigerwald_news_widget id="MEIN-PRESET"]
```
Nutzt `data-config` (Base64) und läuft auf der Quellseite selbst.

### 2. Extern (Token / `data-code`)
Nach dem **Akzeptieren** einer Partneranfrage erhältst du einen Einbettungscode
für fremde Websites:
```html
<div data-code="SNW-ABCD"></div>
<script src="https://DEINE-DOMAIN/wp-content/plugins/steigerwald-news-widget/public/js/widget.js" async></script>
```
Der Code ist **domain-locked**: Das Backend liefert die Konfiguration nur an die
freigegebene Domain aus (andernfalls HTTP 403). Der Renderer prüft die Domain
zusätzlich clientseitig.

### 3. Öffentlicher Builder
Über den Shortcode `[steigerwald_news_widget_builder]` kannst du eine öffentliche
Erstellseite anbieten, auf der externe Nutzer ein Widget gestalten und eine
Partneranfrage absenden.

---

## Öffentliche APIs

* `POST /wp-json/snw/v1/request` – Partner-Erfassung (rate-limitiert:
  10 Anfragen/Stunde/IP).
* `GET  /wp-json/snw/v1/widget/<code>` – domain-lockede Konfigurationsauslieferung.
* Telemetrie (nur bei Aktivierung): öffentliches Ingestion
  `POST /wp-json/snw-telemetry/v1/event` (CORS offen, rate-limitiert) und
  `GET  /wp-json/snw-telemetry/v1/health`; Analytics-Lesen ist durch
  `manage_options` + REST-Nonce abgesichert.

Die eigentlichen Beiträge werden ausschließlich über die
WordPress-Core-REST-API (`/wp-json/wp/v2/...`) geladen.

---

## Branding

Unter **Einstellungen → SN News Widget** konfigurierst du ein globales Branding
(Bild, Bildgröße, Textgröße, Text „Nachrichten von", Markenname, Link). Es wird
an allen Ausspielwegen (Partner-Embed via `data-code`, Same-Site-Shortcode,
Copy-Snippet) aus der globalen Option injiziert – Änderungen wirken ohne
erneutes Einbetten auf bereits platzierten Widgets.

---

## Fehlerbehebung

* **Widget bleibt leer/zeigt Fehler:** WordPress-REST-API muss öffentlich
  erreichbar sein. Caching/CDN kann veraltete Assets liefern – nach Änderungen
  hart neu laden.
* **Externes Embed lädt nicht:** Domain-Lock – der Embed-Code wurde für eine
  andere Domain freigegeben. In der Partner-Verwaltung die Domain prüfen.
* **Statistik fehlt:** Telemetrie unter **Einstellungen** aktivieren; der
  Cron (`twicedaily`) muss auf dem Server laufen.

---

## Deinstallation

Plugin über den Admin deaktivieren und **löschen**. Dabei werden alle
Plugin-Optionen, die automatisch erstellte Builder-Seite und – sofern vorhanden –
die Telemetrie-Tabellen entfernt. Es werden keine fremden Beiträge, Taxonomien
oder Mediathek-Einträge angetastet.

---

## Tests

```bash
# PHP-Logiktests (WP-Core wird via Stubs abgebildet)
php tests/php/test-helpers.php
php tests/php/test-requests.php
php tests/php/test-telemetry.php
php tests/php/test-branding.php

# JS-Unit-Tests (Node, keine Abhängigkeiten)
node tests/js/widget.test.js
node tests/js/render.test.js
node tests/js/frontend.test.js
node tests/js/telemetry.test.js
node tests/js/stats.test.js
```

---

## Lizenz

GPL-2. Siehe <https://www.gnu.org/licenses/gpl-2.0.html>.
Herausgeber: **Ottili ONE**, <https://ld3.ottili.one>.
