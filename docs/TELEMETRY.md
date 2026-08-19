# Telemetry & Analytics (v1.6.0)

Internes, DSGVO-konformes Telemetrie-System. Kein Fingerprinting, keine
personenbezogene Profilbildung, keine IP-Speicherung.

## Endpunkte

| Öffentlich (CORS)            | Intern (`wp-json/...`)            | Methode | Auth        |
|------------------------------|-----------------------------------|---------|-------------|
| `/sn-widget/telemetry/v1/event`  | `snw-telemetry/v1/event`          | POST    | öffentlich  |
| `/sn-widget/telemetry/v1/health` | `snw-telemetry/v1/health`         | GET     | öffentlich  |

| Admin (nur `manage_options` + REST-Nonce) | Intern | Methode |
|-------------------------------------------|--------|---------|
| `snw-telemetry/v1/stats`                  | GET    | KPIs + Serien |
| `snw-telemetry/v1/widgets`                | GET    | Widget-Ranking |
| `snw-telemetry/v1/pages`                  | GET    | Seiten-Analytics |
| `snw-telemetry/v1/articles`               | GET    | Artikel-Analytics |
| `snw-telemetry/v1/partners`               | GET    | Partner-Analytics |
| `snw-telemetry/v1/realtime`               | GET    | Live-Zähler |
| `snw-telemetry/v1/aggregate`              | GET    | manuelles Rollup |

## Events

| `event`               | Wann                                  | `article_id` | `ms` |
|-----------------------|---------------------------------------|--------------|------|
| `widget_load`         | init (DOMContentLoaded)               | –            | –    |
| `viewable_impression` | ≥50 % sichtbar ≥1000 ms               | –            | –    |
| `article_click`       | Klick auf Artikel-Link                | ja           | –    |
| `rest_error`          | REST-Fehler (Widget-Render)           | –            | ja   |
| `cors_error`          | CORS-Fehler                           | –            | –    |
| `network_error`       | Netzwerkfehler                        | –            | –    |
| `render_error`        | sonstiger Renderfehler                | –            | –    |

Jede Instanz sendet `widget_load` und `viewable_impression` max. **einmal** pro
Page-Load.

## Besucher-Schlüssel (Privacy)

```
visitor_key = wp_hash( coarse_ua . '|' . rotation_bucket(ts) . '|' . SALT )
rotation_bucket(ts) = gmdate('Y-m-d', floor(floor(ts/DAY) / days) * days)   // UTC-Tag
```

- `days` = `snw_telemetry_rotation_days` (Standard 1) → tagesgenaue Unique-Visitor.
- Keine IP im Schlüssel oder in der DB.
- `coarse_ua` = nur Browserfamilie + OS (z. B. `chrome/win`), keine Version.

## Aggregation

- `rollup_day($ymd)`: schreibt `daily` für einen abgeschlossenen Tag aus `events`.
- `rollup_completed_days()`: alle Tage < heute, die noch kein Rollup haben.
- `ensure_aggregated()`: throttled (stündlich) für „heute" → live aus Rohdaten.
- `cleanup_retention()`: löscht `events`/`daily` älter als Retention (365 Tage).

## Tabellen

`{prefix}snw_telemetry_events` (Rohdaten), `…_daily` (Rollups),
`…_articles` (Top-Artikel), `…_widget_pages` (Instanz→Seite-Mapping).

## Tests

- `tests/php/test-telemetry.php` — PHP-Logik (44 Assertions, reines PHP, keine WP-Runtime nötig).
- `tests/js/telemetry.test.js` — Viewability + Endpoint + Fehler-Mapping (node).
- `tests/js/stats.test.js` — Dashboard-Formatierung (node).
- `tests/browser/telemetry.spec.js` — Playwright-Akzeptanz (gegen live WP via `BASE_URL`).

## CI

`.github/workflows/test.yml`: PHP-Lint → PHP-Tests → JS-Syntax → JS-Tests →
Build-ZIP (nur Runtime). Grün erforderlich.
