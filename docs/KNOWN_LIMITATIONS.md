# Bekannte Einschränkungen – v1.1.0

* **Kategorie-Dropdown**: Es werden bis zu 100 Kategorien geladen
  (`per_page=100`). Bei sehr großen Kategorie-Mengen (>100) werden nicht alle
  im Dropdown angezeigt; als Workaround kann die Kategorie-ID im
  Erweiterte-Einstellungen-Feld gesetzt werden. (Bewusste Einfachheit statt
  Suche über alle Kategorien.)
* **Kein eigenes Caching auf Serverseite**: Das Widget cached clientseitig
  (memory + SessionStorage, ~10 Min). Bei sehr hohem Traffic auf der
  Partnerseite entstehen dennoch Core-REST-Requests pro Cache-Fenster – durch
  `_fields` und `_embed` aber klein gehalten.
* **CORS**: Die Quell-WordPress muss anonyme Core-REST-Zugriffe erlauben
  (Standard). Wenn ein Hosting CORS für `/wp-json` blockiert, lädt das externe
  Widget nicht (zeigt sauberen Fehlerhinweis).
* **Private/Draft nie sichtbar**: bewusst via `status=publish`.
* **Design „Host übernehmen"**: nutzt `inherit`. In exotischen Host-CSS
  kann dies gelegentlich zu unerwarteter Schriftgröße führen; dann eine
  explizite Schriftart wählen.
* **Dark Mode**: kein separater Auto/Light/Dark-Schalter (bewusst nicht
  überengineered). Bei „Host übernehmen" bleibt das Widget in dunklen Themes
  lesbar, da Farben größtenteils vererbt werden.
* **Manuelle/Angeheftete Posts**: werden über IDs gespeichert; Titel werden
  beim Bearbeiten über die Core-REST rehydriert (benötigt erreichbare REST).
* **Tags-Suche**: lädt max. 20 Treffer pro Suche (`per_page=20`).
* **Automatisierte Tests**: Render-Tests nutzen ein DOM-Shim + Mock-Fetch;
  visuelles Layout ist nicht automatisiert abgedeckt (siehe Testbericht).
* **Keine WP-CLI / keine Block-/Shortcode-Einbettung** auf der Quellseite
  (bewusst, um Scope schlank zu halten – der Embed ist rein HTML/JS).

## Bewusst NICHT umgesetzt (Scope-Disziplin)

LD3, AI, Content-Generierung, CRM, Partner-CRM, Billing, User-Accounts,
SaaS-Backend, eigene Analytics, Newsletter, Social Publishing, Content-Sync,
eigene REST-Datenplattform, API-Keys, Multi-Tenant, vollständige
Artikel-Syndication. Ideen dazu gehören unter *Future Ideas*, nicht in diese
Version.
