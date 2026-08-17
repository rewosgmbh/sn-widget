# Gespeicherte WordPress-Optionen & öffentliche Assets

## Gespeicherte WordPress-Optionen

Das Plugin erzeugt **genau eine** eigene Option. Beim Deaktivieren werden
keine Daten gelöscht; beim Deinstallieren (über `uninstall.php`) wird nur
diese Option entfernt.

| Option         | Typ    | Inhalt                                                                 | Autoload |
|----------------|--------|------------------------------------------------------------------------|----------|
| `snw_presets`  | array  | Liste gespeicherter Presets. Jedes Preset: `{id, name, config, created, updated}`. `config` ist das vollständige, sanitierte Config-Objekt (siehe Architektur). | `false` |

Es werden **keine** Beiträge, Kategorien, Tags, Mediendateien oder anderer
fremder Inhalt verändert oder gespeichert. Es existiert **keine** eigene
Datenbanktabelle.

## Öffentliche Assets (extern einbindbar)

Nur diese beiden Dateien werden auf der Partnerseite referenziert. Beide
werden vom Quell-WordPress ausgeliefert (CORS-fähig über Core-REST).

| Asset | Zweck | Abhängigkeiten |
|-------|-------|----------------|
| `public/js/widget.js` | Kern-Renderer. Vanilla JS, injectt Basis-CSS (falls `widget.css` nicht geladen), decodiert `data-config`, holt Beiträge via Core-REST, rendert, cached. | keine (kein jQuery/Framework/CDN) |
| `public/css/widget.css` | Statisches Basis-Stylesheet (identisch zur JS-injizierten CSS). Dient als Fallback/WordPress.org-Artefakt; wird von `widget.js` nicht zwingend benötigt. | keine |

Die vom Admin erzeugte Embed-URL zeigt auf
`…/steigerwald-news-widget/public/js/widget.js`.

## Weitere Plugin-Dateien (nicht extern, nur Quell-WordPress)

* `steigerwald-news-widget.php` – Bootstrap.
* `includes/*.php` – Server-Logik (Admin, AJAX, Presets, Embed, Helpers, Assets, Plugin-Controller).
* `admin/css/admin.css`, `admin/js/admin.js` – Builder-Oberfläche (nur Backend).
* `languages/steigerwald-news-widget.pot` – Übersetzungsvorlage.
* `uninstall.php` – entfernt `snw_presets`.
