# Changelog

## 1.1.0 - 2026-08-17

**Vollständige Neugestaltung als produktionsreifes Widget.**

* **Widget Builder**: Zweispaltige Admin-Oberfläche (Konfiguration + Live-Vorschau),
  die sich sauber in WordPress integriert.
* **Sechs Inhaltsmodi**: Neueste Beiträge, Kategorie, Schlagwörter,
  Kategorie + Schlagwörter, Einzelne Beiträge (Suche, sortierbar) und
  Hybrid (angeheftete + automatische Beiträge).
* **Vier Layouts**: News-Liste (Bild links), Karten (responsives Raster),
  Kompakt (Sidebar) und Nur-Überschriften.
* **Design-Builder**: Farbwähler (Akzent, Hintergrund, Text, sekundär, Rahmen,
  Link), Eckenradius, Abstand-Presets, Schriftart (Host/System/Arial/Georgia/Serif/Sans).
* **Tag-Suche** mit Autocomplete, **Post-Suche** mit Sortierung (Drag & Drop / Pfeile).
* **Presets** in WordPress-Optionen (keine eigene Datenbanktabelle), mit
  Bearbeiten, Duplizieren, Code kopieren, Löschen (mit Bestätigung).
* **Kompakter Einbettungscode**: `data-config` mit base64url-kodiertem JSON;
  Partner muss kein Plugin installieren.
* **Public-Renderer** (`public/js/widget.js`): Vanilla JS, keine Frameworks,
  keine externen CDNs, keine jQuery-Abhängigkeit; idempotent bei
  Mehrfach-Einbindung; mehrere Widgets gleichzeitig; CSS-Isolation unter
  `.steigerwald-news-widget`; `AbortController`-Timeout; clientseitiges Caching
  (memory + SessionStorage, TTL ~10 Min); saubere Fehler-/Empty-States.
* **UTM-Tracking** über Partnerkennung (`utm_source`/`utm_medium`/
  `utm_campaign`/`utm_content=<widget-id>`), bestehende Query-Parameter bleiben erhalten.
* **Sicherheit**: Capability-Checks, Nonces, Sanitisierung/Validierung,
  Escaping, keine SQL-, `eval`- oder Remote-Include-Pfade.
* **WordPress.org-Readiness**: GPL, vollständiger Header, `readme.txt`,
  Text Domain, i18n-`.pot`, keine Telemetrie/versteckten Requests.
* **Regression**: Einfaches Standard-Widget (neueste Beiträge) funktioniert
  weiterhin ohne Sonderkonfiguration.

## 1.0.2

* Entfernung des eigenen REST-Endpunkts (Ursache eines HTTP-500 auf einer
  Testinstallation). Nutzung ausschließlich der WordPress-Core-REST-API.

## 1.0.1

* Einführung eines eigenen REST-Endpunkts (später entfernt – siehe 1.0.2).

## 1.0.0

* Erste Veröffentlichung.
