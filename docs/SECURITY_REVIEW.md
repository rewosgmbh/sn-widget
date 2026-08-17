# Security Review – Steigerwald-News Widget 1.1.0

Ziel: Plugin darf die Host-Website, das WordPress-Admin oder das
WordPress-Frontend niemals beschädigen.

## Maßnahmen (WordPress Best Practices)

| Bereich | Umsetzung |
|---------|-----------|
| Capability-Checks | Alle Admin-Seiten/AJAX prüfen `current_user_can('manage_options')`. |
| Nonces | Alle mutierenden AJAX-Aktionen prüfen `wp_verify_nonce` (Feld `snw_nonce`). |
| Sanitisierung | `SNW_Helpers::sanitize_config()` validiert jeden Wert: Enums, Integer-Clamp, Alnum-Partner, Farb-Regex, ID-Listen (nur positive Ints), `esc_url_raw`/`sanitize_text_field`. |
| Validierung | Unbekannte Config-Keys werden verworfen; ungültige Enums fallen auf Defaults. |
| Escaping (Output) | Admin-HTML nutzt `esc_html`/`esc_attr`/`esc_url`; Embed-Code nutzt `esc_attr`/`esc_url`. JS nutzt `textContent` (kein `innerHTML` mit Nutzerdaten außer kontrollierten Konfigurationswerten, die durch Sanitisierung sicher sind). |
| Kein SQL | Keine direkten DB-Queries; nur `get_option`/`update_option`/`delete_option`. |
| Kein eval / Remote-Include | Kein `eval`, keine `include`/`require` von Remote- oder nutzerkontrollierten Pfaden. |
| Keine Code-Execution | Keine nutzerkontrollierte Ausführung; Config wird nur als Daten interpretiert. |
| Keine Datei-Manipulation | Außer den normalen Plugin-Dateien; `uninstall.php` löscht nur `snw_presets`. |
| Keine geheimen Requests | Keine Telemetrie, keine versteckten Tracking-Requests, keine externen CDNs. |
| Public-Renderer | Vanilla JS, `credentials: 'omit'`, CORS-GET nur gegen Core-REST; try/catch
  um jeden Fetch; keine globalen JS-Variablen außer `window.SteigerwaldNewsWidget`. |

## Bedrohungsmodell (leichter)

* **Schädliche Config im `data-config`**: wird durch `sanitize_config`
  (Server) und durch strikte Dekodierung (Client) neutralisiert. Ungültige
  Config → `console.debug`, Widget leer.
* **XSS über Beitragsinhalte**: Titel/Excerpt werden via `textContent`
  (nicht `innerHTML`) ausgegeben; HTML wird entfernt, Entities dekodiert.
* **CSS-Injektion**: Farbwerte werden serverseitig per Regex auf
  `#hex`/`rgba()` begrenzt, bevor sie als CSS-Variablen gesetzt werden.
* **Cache-Poisoning**: Cache-Key schließt alle datenrelevanten Felder ein;
  fremde Sites können nur ihren eigenen Cache-Eintrag beeinflussen
  (SessionStorage pro Origin).
* **UTM/Open-Redirect**: `trackedUrl` baut nur auf `URL`-API auf und hängt
  feste UTM-Parameter an bereits gültige Core-Links an.

## Nicht im Scope (bewusst)

Kein LD3, keine Auth/Accounts, keine Publishing-Plattform, kein CRM, keine
Analytics, keine eigene REST-Datenplattform, keine TLS/Secret-Verwaltung –
daher auch keine entsprechenden Angriffsflächen.

## Fazit

Das Plugin folgt den WordPress-Sicherheitsrichtlinien und enthält keine der
explizit verbotenen Konstrukte (SQL, eval, Remote-Includes, Telemetrie).
