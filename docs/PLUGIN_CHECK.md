# WordPress.org Plugin Check – Ergebnis (Selbst-Assessment)

Das offizielle `plugin-check` CLI-Tool konnte im Build-Umfeld nicht ausgeführt
werden (benötigt eine WordPress-Installation). Stattdessen eine
strukturierte Selbsteinschätzung gegen die häufigsten Prüfpunkte von
Plugin Check (Stand 2026).

## Ergebnisübersicht

| Prüfung | Status | Hinweis |
|---------|--------|---------|
| Plugin-Header vollständig | PASS | Name, Version, Requires at least, Requires PHP, Tested up to, Text Domain, License, Author. |
| `readme.txt` vorhanden & formatkonform | PASS | Stable tag, Requires, Tested, Changelog. |
| GPL-kompatibel | PASS | License GPL-2, kein proprietärer Code. |
| Keine direkten SQL-Queries | PASS | Nur Options-API. |
| Keine `eval` / `create_function` | PASS | Nicht verwendet. |
| Keine Remote-Code-Includes | PASS | Keine `include`/`require` von URLs. |
| Keine versteckten Telemetrie/Tracking-Requests | PASS | Keine externen Requests außer Core-REST (vom Nutzer konfiguriert). |
| Capability-Checks | PASS | `manage_options` überall. |
| Nonces bei mutierenden Aktionen | PASS | `wp_verify_nonce` in `Settings.php`. |
| Sanitisierung & Validierung | PASS | `Helpers::sanitize_config()`. |
| Output Escaping | PASS | `esc_html`/`esc_attr`/`esc_url`; JS nutzt `textContent`. |
| Text Domain & i18n | PASS | `steigerwald-news-widget`, `.pot` vorhanden. |
| `uninstall.php` entfernt nur eigene Optionen | PASS | Nur `snw_presets`. |
| Keine ungefragten Admin-Notices / Backlinks | PASS | Keine erzwungenen Backlinks/SEO-Spam. |
| Namespacing / Präfixe | PASS | Klassenpräfix `SNW_`, eindeutige Option `snw_presets`. |
| Kein eigener REST-Endpunkt | PASS | Nutzung ausschließlich Core-REST. |
| PHP 7.4-Kompatibilität | PASS* | Kein `match`, keine Union-Types, kein Constructor-Promotion, kein `enum`. (`php -l` auf 8.3 grün; Logik auf 7.4-Syntax geprüft.) |
| JS: keine Frameworks/CDNs | PASS | Vanilla JS, `AbortController`/`fetch` nur. |
| Enqueue statt inline-Skripte | PASS | Assets via `wp_enqueue_*`. |

`*` *Empfohlene Maßnahme vor echter Veröffentlichung:* Plugin Check unter
PHP 7.4 ausführen (oder `phpcs` mit `WordPress-Extra` + `PHPCompatibilityWP`
Ruleset), da die lokale Laufzeitumgebung PHP 8.3 ist.

## Empfohlene vor-Veröffentlichung-Checks

1. `composer global require wp-cli/wp-cli-bundle` + `wp plugin list` (Sanity).
2. `plugin-check` (WP-CLI) auf Ziel-WordPress ausführen.
3. `phpcs --standard=WordPress` über `includes/`, `admin/`, `public/`.
4. Manueller Browser-Durchlauf (siehe Testbericht).

## Dokumentierte legitime Ausnahmen

* **`base64_encode`/`base64_decode`** in `EmbedGenerator.php` werden für die
  kompakte, url-sichere Config-Kodierung genutzt (kein Obfuscation-Zweck,
  sondern platzsparende Einbettung). Die Config ist kein Code und wird nie
  ausgeführt.
* **Öffentliche `widget.js`** lädt ihr Basis-CSS via `style`-Injection, damit
  externe Partner nur einen `<script>`-Tag einbinden müssen (robustester
  Embed). Eine statische `widget.css` liegt parallel (identisch) bei.
