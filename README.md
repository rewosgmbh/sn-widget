# Steigerwald-News Widget

WordPress-Beiträge auswählen → Widget konfigurieren → Live-Vorschau → Einbettungscode erzeugen → auf externen Websites darstellen.

**v1.1.0** – produktionsreif, kein eigener REST-Endpunkt, keine eigene Datenbanktabelle.

## Highlights

* **Widget Builder** im Admin mit Live-Vorschau (echte Beiträge).
* **6 Inhaltsmodi**: Neueste, Kategorie, Schlagwörter, Kategorie+Tags, Einzelne Beiträge, Hybrid (Angeheftet + Automatisch).
* **4 Layouts**: News-Liste, Karten, Kompakt, Nur-Überschriften.
* **Design-Builder**: Farben, Radius, Abstand, Schriftart.
* **Presets** in WordPress-Optionen (kein DB-Table).
* **Externer Embed** ohne Plugin auf der Partnerseite (nur `<div>` + `<script>`).
* **Public-Renderer**: Vanilla JS, caching, Timeout, Fehlerfälle, CSS-Isolation.
* **UTM-Tracking** über Partnerkennung.

## Schnellstart

1. Plugin hochladen & aktivieren.
2. *Einstellungen → News Widget*.
3. Widget konfigurieren, in der Live-Vorschau prüfen.
4. **Widget speichern**.
5. **Einbettungscode kopieren** und auf der Partnerseite einfügen.

## Doku

Siehe `docs/`:

* `ARCHITECTURE.md` – Aufbau & Datenfluss
* `TEST_REPORT.md` – Tests & Ergebnisse
* `SECURITY_REVIEW.md` – Sicherheit
* `UPGRADE_GUIDE.md` – v1.0.2 → v1.1.0
* `KNOWN_LIMITATIONS.md` – Einschränkungen
* `OPTIONS_AND_ASSETS.md` – Optionen & Assets
* `PLUGIN_CHECK.md` – WordPress.org-Check (Selbst-Assessment)

## Tests

```bash
php -l steigerwald-news-widget.php
node --check public/js/widget.js
php tests/php/test-helpers.php
node tests/js/widget.test.js
node tests/js/render.test.js
```

## Häufige Fragen

* Braucht die Partnerseite das Plugin? **Nein.**
* Wird ein eigener REST-Endpunkt registriert? **Nein** (nur WordPress-Core-REST).
* Werden Beiträge kopiert? **Nein** (Presets speichern nur Konfiguration).
