# Changelog

## 1.6.0

**Internes Telemetrie- & Analytics-System (DSGVO-konform):**

* **Neue Telemetry-API, strikt getrennt von WP Core REST:** Öffentlicher Alias `/sn-widget/telemetry/v1/*` → intern `wp-json/snw-telemetry/v1/*`. Event-Ingestion ist öffentlich (CORS), Analytics-Lesen ist `manage_options` + REST-Nonce abgesichert.
* **Raw- vs. Viewable-Metriken:**
  * `widget_load` — beim Initialisieren des Widgets (init).
  * `viewable_impression` — erst bei ≥50 % sichtbarer Fläche für ≥1000 ms (IntersectionObserver), je max. 1 pro Instanz/Page-Load.
  * `article_click` — Klick auf einen Artikel-Link (mit `article_id`/ `article_url`, aber ohne Profilbildung).
* **Kein Fingerprinting, keine IP-Speicherung:** Besucher-Schlüssel ist ein server-seitiger HMAC (`wp_hash`) über `coarse UA + Rotations-Tagesbucket` — tagesgenaue Unique-Visitor-Zahlen ohne personenbezogene Daten.
* **Server-seitige Aggregation:** Tägliche Rollups (`events` → `daily`) für abgeschlossene Tage; „heute" wird live aus den Rohdaten gelesen. Retention-Cleanup (Standard 365 Tage, filterbar).
* **Dashboard „Statistik":** KPI-Karten (Aufrufe, Unique, Viewable-Rate, CTR), zwei SVG-Liniencharts (kein externes CDN), Top-Widgets/Seiten/Artikel/Partner, Widget-Detail-Modal, Realtime-Polling, Einstellungen + Debug, CSV-Export (admin-ajax).
* **Robustes Client-Telemetry:** `sendBeacon`/`fetch`-keepalive, nie blockierend, bricht das Widget bei Ausfall nicht.
* **Tests & CI:** PHP-Logiktests (44 Assertions), JS-Unit-Tests (Viewability/Formatierung), Playwright-Browser-Spec, GitHub Actions (Lint + Unit + Build-ZIP).

**Bugfix (vor Release entdeckt):** Die Admin-REST-Routen (`stats`, `widgets`, `pages`, `articles`, `partners`, `realtime`, `aggregate`) wurden über einen generischen Dispatcher registriert, der den Callback-Namen aus einem Request-Arg las. Da dieses Arg als Bare-Skalar statt als `{ default: ... }` übergeben wurde, lieferte `WP_REST_Request::get_param()` `null` → Dispatcher antwortete mit **404 für jeden Admin-Endpunkt** → Dashboard zeigte überall „Daten konnten nicht geladen werden." Behoben durch direkte Callback-Registrierung pro Route.

**Release-Paket (vor Release ergänzt):**

* **Partner-Modell (1 Code je E-Mail):** Ein Partner (identifiziert per E-Mail) erhält genau einen Widget-Code. Beim Annehmen einer Anfrage wird ein bestehender Code derselben E-Mail wiederverwendet, statt einen neuen Code zu erzeugen (`SNW_Presets::find_by_email`, `SNW_Settings::ajax_accept_request`).
* **Statistik – Code↔Partner:** Widget-Ranking und Partner-Analytics verknüpfen jetzt jeden Code mit dem zugehörigen Partner (E-Mail/Name aus dem Preset), statt des leeren Telemetry-`partner`-Felds.
* **Statistik – Builder-Nutzung:** Neues Telemetry-Event `builder_submit` und neue KPI „Builder-Nutzungen" (wie oft der öffentliche Widget-Builder abgesendet wurde). Schema-Version auf 2 angehoben, neue Spalte `daily.builders`.
* **Neue Admin-Seite „Partner":** Separate Seite für freigegebene Partner (getrennt von „Partneranfragen"), mit Suche-, Status-Filter und Letzte-Aktivität (Telemetry `widget_pages`).
* **WordPress-Dashboard-Widget:** Kompakte Statistik-Übersicht (Widgets, Partner, Offen, Builder-Nutzungen, Aufrufe, Klicks) inkl. Ottili-LD3-Branding-Link.
* **Branding & Texte:** E-Mail-Versand enthält keinen WP-Shortcode mehr (nur universelles HTML-Snippet); „Steigerwald-News" im Widget wird durch den Websitenamen ersetzt; Plugin-Autor „Ottili" mit Link `ld3.ottili.one`; „Erstellen"-Seite heißt „News Widget erstellen"; Favicon im Widget leicht vergrößert (22→26 px).
* **Schema-Fix:** Die `daily`- und `widget_pages`-Telemetrie-Tabellen wurden wegen eines zu langen zusammengesetzten Primary-Keys (page_path 512 + host 255) unter utf8mb4 nie erstellt – `page_path` auf 255 reduziert, damit Statistik auf echten Installationen funktioniert.

**Konfigurierbares Widget-Branding (Admin → Einstellungen):**

* **Neue Einstellungsseite:** Branding-Bild (Standard `/favicon.ico`, alternativ eigene Datei aus der WordPress-Mediathek oder per URL), Bildgröße (frei, Standard 32 px), Branding-Text (Standard „Nachrichten von"), Marken-/Websitename (Standard = Website-Titel, frei überschreibbar) und Branding-Link (Standard = Startseite, frei überschreibbar).
* **Darstellung:** `[Logo] Nachrichten von` gefolgt von der fett hervorgehobenen Marke in einer zweiten Zeile; das Bild wird sauber auf die konfigurierte Größe skaliert (`object-fit: contain`). Light/Dark-Mode und Responsive über die bestehenden CSS-Variablen/`@container`-Regeln.
* **Globale Einstellung:** Das Branding wird an allen Ausspielwegen (Partner-Embed via `data-code`, Same-Site-Shortcode, Copy-Snippet) aus der globalen Option injiziert (`SNW_Helpers::apply_branding`) – Änderungen wirken ohne erneutes Einbetten auf bereits platzierten Widgets.

## 1.5.1

**Public Builder – Layout & Live-Vorschau verfeinert:**

* **Full-Page-Width:** Der öffentliche Builder bricht aus der Theme-Inhaltsspalte aus und nutzt die volle Seitenbreite (kein horizontaler Scrollbalken, `body.snw-builder-page` clippt Overflow).
* **Interne Felder ausgeblendet:** Im Public-Kontext werden der „Erweitert"-Abschnitt und dessen Navigationslink nicht gerendert.
* **Bottom-gedockte Live-Vorschau:** Die Vorschau wandert beim Runterscrollen mit der Seite, bis ihr unteres Ende den Bildschirmrand erreicht, und bleibt dann unten angedockt – ohne eigenen Scrollbalken. (Native `position: sticky; bottom` klebt im Theme nicht zuverlässig, daher wird sie über einen `top`-Sticky mit dynamischem Offset `viewportHöhe − Vorschaulänge` emuliert; `admin.js` berechnet den Offset und aktualisiert ihn über einen `ResizeObserver`.)
* Der Platzhalter im Embed-Beispiel heißt jetzt `mein-verein` (statt des irritierenden Testwerts).

## 1.5.0

**Öffentliches Widget-Erstellen mit vollem Funktionsumfang:**

* Das öffentliche `[steigerwald_news_widget_builder]`-Formular nutzt jetzt **denselben Builder** wie die Admin-"Erstellen"-Seite – inkl. aller Inhaltsmodi (Kategorie, Schlagwörter, einzelne/hybride Beiträge), Design-Reglern, Farben, Theme-Umschalter und **Live-Vorschau**.
* Einreichungen werden weiterhin als Partneranfrage an `POST /snw/v1/request` gesendet (Name, E-Mail, Domain + vollständige Konfiguration).
* Kein Duplicate-Code mehr: Builder-Markup liegt zentral in `SNW_Builder::render_form()`, das Admin- und Public-Seite teilen; `admin.js` wird kontextabhängig (Admin speichert Preset + kopiert Snippet, Public sendet Anfrage).

## 1.4.9

**Sicherheit & Einbettung:**

* **Keine Base64-Customization mehr in externen Snippets:** Der „Einbettungscode kopieren"-Button im Builder und der „Code kopieren"-Button in der Preset-Tabelle geben jetzt nur noch ein **Token** (`data-code`) aus – die Customization steht nicht mehr im Quelltext. Das Backend liefert die Konfiguration bei Bedarf aus.
* **Domain-Lock (Backend):** Das Token-basierte Widget wird nur auf der freigegebenen Domain (z. B. maggus.com) ausgeliefert; auf einer anderen Domain (z. B. maggus.de) antwortet das Backend mit 403 und das Widget zeigt einen klaren Block-Hinweis.
* **Interner Shortcode** (`[steigerwald_news_widget]`) behält weiterhin `data-config` (Base64) – er läuft auf der Quellseite selbst.

## 1.4.8

**Admin – Menü:**

* **„Gespeicherte & Anfragen" getrennt:** Aus der kombinierten Unterseite wurden zwei eigene Unterseiten – **Gespeicherte** (Presets) und **Partneranfragen** (Einreichungen).

## 1.4.7

**Admin – Struktur:**

* **Dashboard als Startseite:** Die Plugin-Startseite ist jetzt ein Übersichts-Dashboard (gespeicherte Widgets, offene/akzeptierte Anfragen, veröffentlichte Beiträge, letzte Widgets + Anfragen, Verteilung nach Modus).
* **„Erstellen“-Unterseite:** Der Widget-Builder (Konfiguration + Live-Vorschau) ist jetzt eine eigene Unterseite.
* **„Gespeicherte & Anfragen“-Unterseite:** Presets-Tabelle und Partner-Anfragen wurden aus der Startseite ausgegliedert.

## 1.4.6

**Admin – Bedienung:**

* **Visueller Layout-Wähler** mit Miniatur-Vorschauen (Raster, Liste, Karten, Kompakt, Nur Überschriften) ersetzt das Layout-Dropdown – man sieht sofort, wie das Layout aussieht.
* **Farbschema als Segmented-Toggle** (Hell / Dunkel) statt Dropdown.
* **„Live“-Kennzeichnung** bei der Vorschau (pulsierender Punkt), die erklärt, dass Änderungen sofort erscheinen.

## 1.4.5

**Admin – Bedienung & Übersicht:**

* **Abschnitts-Navigation** oben in der Konfigurationsspalte (Name, Inhalt, Design, Partner, Erweitert, Vorschau) – bleibt beim Scrollen stehen und springt zum gewählten Bereich.
* **3-Schritte-Hinweis** direkt unter der Einleitung (Inhalt → Design → Speichern/Einbetten).
* **Dynamischer Modus-Hinweis** unter „Inhaltsmodus“: erklärt die jeweils gewählte Modus-Option in einem Satz.
* **Einklappbare Design-Unterbereiche** (Bild & Layout, Text & Meta, Theme & Stil) mit Aufklapp-Indikator – weniger Bildschirm voll Controls.
* **Kleine Feld-Hinweise** bei „Artikel pro Reihe“ (wirkt bei Raster/Karten) und „Eckenradius“.
* **Speichern-Leiste** wird beim Scrollen am unteren Rand fixiert, sodass „Widget speichern“ immer erreichbar ist.

## 1.4.4

**Admin:**

* Das Widget separat von „Einstellungen“ geführt: statt `add_options_page` nutzt das Plugin jetzt `add_menu_page` und erscheint als eigener Eintrag **„News Widget“** (Megaphone-Icon) in der Sidebar. Plugin-Link in der Plugin-Liste zeigt nun ebenfalls dorthin (`admin.php?page=steigerwald-news-widget`).

**Customization:**

* Neue Design-Option **„Artikel trennen“** (`Linie` / `Keine Trennung`). Steuert eine Trennlinie zwischen den Beiträgen; bei Karten entfernt `Keine Trennung` zusätzlich den Kartenrahmen. Die Linienfarbe folgt der Rahmenfarbe.

## 1.4.3

**Bugfix / Verbesserung:**

* Admin-Builder: Vorschau war ein schmales Feld, das nur in der Höhe wuchs. Neuer Regler **"Vorschau-Breite"** (Container 100 %, 320/480/768/1024/1280 px) dehnt die Vorschau in die Breite – so lässt sich das Widget auch in voller Breite und responsiv begutachten. Die Preview-Spalte ist zudem etwas breiter geworden.

## 1.4.2

**Bugfix:**

* Widget nutzte nicht die volle Breite des Containers, sondern wurde vom Theme auf die "Content-Size" (z. B. 645 px) begrenzt. `.steigerwald-news-widget` erhält jetzt `max-width: none !important`, sodass es die verfügbare Breite füllt (responsive Spaltenregeln bleiben unberührt).

## 1.4.1

**Bugfix:**

* Admin-Vorschau zeigte die Einstellung "Artikel pro Reihe" (vorher "Spalten pro Reihe") nicht: das Vorschaufenster ist schmaler als der responsive Bruchpunkt, wodurch die Spalten immer auf eine reduziert wurden. Die Vorschau zeigt jetzt die eingestellte Spaltenzahl; echte Einbettungen behalten das responsive Verhalten.
* Beschriftung im Builder in "Artikel pro Reihe" umbenannt.

## 1.4.0

**Neu / Behoben:**

* **"Raster"-Layout** als eigenständiges, nebeneinander angeordnetes Layout hinzugefügt. Bisher war `grid` zwar als Standard hinterlegt, wurde aber beim Speichern still auf "Liste" (einspaltig) zurückgesetzt – Artikel ließen sich nicht nebeneinander anordnen.
* **Spalten pro Reihe (1–4)** wirksam für Raster- und Karten-Layout (steuert `--snw-columns`). Standard: 2.
* **Responsives Raster:** bis 768 px maximal 2 Spalten, bis 520 px eine Spalte – verhindert zu schmale Spalten auf Mobilgeräten.
* **"Spalten"** ist jetzt auch im öffentlichen Anfrage-Formular wählbar; Raster ist dort das Standard-Layout.

## 1.3.2

**Bugfixes:**

* Widget-Titel respektiert jetzt die eingestellte Überschriften-Ebene (`design.heading_level`, h2/h3/h4). Zuvor wurde der Titel immer als `<h2>` gerendert, unabhängig von der Konfiguration.

## 1.3.1

**Bugfixes:**

* „Seite erstellen/aktualisieren" benennt die vorhandene Erstellseite jetzt korrekt um, wenn die Adresse geändert wird (zuvor wurde eine Slug-Änderung ignoriert, solange die Seite bereits existierte).
* Widget skaliert nicht mehr mit der Basis-Schriftgröße der einbettenden Seite: die Darstellung war auf Websites mit großer Basisschrift „insane" vergrößert. Die Widget-Wurzel hat jetzt eine begrenzte Basisgröße (`clamp(14px, 1em, 16px)`), sodass Titel und Inhalte konsistent bleiben.

## 1.3.0

**Neue Funktion: Partner-Widgets & Site-Code**

* Öffentliche Erstellseite `/widget/new` (Slug in den Einstellungen änderbar), auf der externe Nutzer ein Widget gestalten, E-Mail, Zieldomain und (optional) Namen angeben.
* Custom REST-Endpunkt `POST /wp-json/snw/v1/request` (rate-limitiert: 10 Anfragen/Stunde/IP) nimmt Einreichungen entgegen.
* Admin-Bereich „Partner-Anfragen": Einreichungen ansehen, Vorschau, **akzeptieren** (erzeugt ein domain-gebundenes Widget + Einbettungscode) oder ablehnen.
* Einbettbarer **Site-Code**: Shortcode `[steigerwald_news_widget id="…"]` für WordPress sowie HTML-Snippet mit `data-code` für beliebige Websites.
* Domain-Lock: ein akzeptiertes Widget wird serverseitig (Endpoint prüft Origin/Referer) **nur auf der angegebenen Domain** ausgeliefert; zusätzliche Client-Prüfung im Renderer.
* Akzeptieren erzeugt einen `mailto:`-Link, über den du den Einbettungscode manuell an die hinterlegte E-Mail sendest (kein SMTP nötig).

**Bugfixes:**

* `scopeCss` behält den Inhalt von `@font-face`/`@keyframes` jetzt korrekt bei,
  statt ihn zu entfernen oder falsch zu scopen. `@media`/`@supports`/`@container`/
  `@layer` werden weiterhin korrekt auf das Widget begrenzt.
* `sanitize_css` entfernt nun `@import`, um das Laden externer Stylesheets über
  das Profi-CSS-Feld zu verhindern.

## 1.2.0 - 2026-08-17

**Neue Customization-Möglichkeiten für das Widget.**

* **Layout & Bild**: Spaltenzahl für Karten (1–4), Bildseitenverhältnis
  (16:9, 4:3, 3:2, 1:1, automatisch), Bildfüllung (zuschneiden/einpassen) und
  Bildposition in der Liste (links/rechts/oben).
* **Text & Meta**: eigenes „Weiterlesen“-Label, relatives Datumsformat
  („vor 3 Tagen“), Autor anzeigen und wählbare Überschriften-Ebene (H2–H4).
* **Theme & Stil**: Hell/Dunkel-Farbschema, Schattenstärke, Textausrichtung
  sowie ein auf das Widget begrenztes Eigenes-CSS-Feld für Profis.
* **Verhalten**: Titel-Kürzung (Zeichen), ganzes Card als Klickziel oder nur der
  Titel, und anpassbare Leer-/Fehlertexte.

## 1.1.1 - 2026-08-17

**Bugfix-Release – behebt mehrere Release-Blocker aus der Praxis-Prüfung.**

* **Admin-Builder startete nicht (P0)**: `init()` rief `.wpColorPicker()` auf
  einem nativen Array statt auf einem jQuery-Objekt → JS-Absturz, der die
  gesamte Konfiguration/Vorschau lahmlegte. Farbwähler werden jetzt pro Element
  initialisiert.
* **Preset „Bearbeiten“/„Zurücksetzen“ absturzgefährdet (P0)**: `renderSelected`
  erhielt den State-Key als Array statt als String → Crash bei Presets mit
  Beiträgen. Key-Übergabe korrigiert.
* **Kein `_embedded` bei echter API (P0)**: `_fields` enthielt `_embedded`, aber
  nicht `_links`. WordPress liefert `_embedded` nur mit `_links` → Bilder und
  Kategorien fehlten. `_links` wird jetzt mit angefragt.
* **Bild ODER Kategorie, nie beides (P0)**: `_embed` fragte nur `wp:term`
  *oder* `wp:featuredmedia` an. Jetzt werden beide Ressourcen embeddet, sofern
  jeweils sichtbar.
* **Inhaltsmodi nicht isoliert (P0)**: Versteckte Kategorie-/Tag-Werte blieben
  in der Konfiguration und filterten z. B. im Modus „Neueste Beiträge“ weiter.
  `getConfig` und der Renderer filtern jetzt streng nach Modus.
* **Drei von vier Layouts defekt (P0)**: `data-layout` wurde auf das innere
  Element gesetzt, das CSS erwartet es am äußeren Widget. `data-layout` liegt
  jetzt am äußeren `.steigerwald-news-widget`.
* **`radius=0` wurde zu 8 (P1)**: `parseInt(...) || 8` überschrieb 0. 0 wird
  jetzt erhalten (eckige Ecken).
* **`auto_count=0` wurde zu automatischen Beiträgen (P1)**: `clamp_int` erzwang
  0 → Standard. 0 („keine automatischen Beiträge“) bleibt jetzt erhalten;
  Hybrid-Modus lädt bei `auto_count <= 0` keine automatischen Beiträge.
* **Preset-Update band Widget-ID nicht zurück (P1)**: Bei Update wird
  `widget_id` jetzt verbindlich an die Preset-ID gebunden.
* **Timeout ließ Ladezustand hängen (P1)**: Ein Timeout (Abort) zeigt jetzt den
  konfigurierten Fehlerzustand statt des endlosen Ladehinweises.
* **Manuelle/angeheftete Auswahl auf ~10 Beiträge begrenzt (P1)**: `per_page`
  wird jetzt beim ID-abhängigen Abruf gesetzt.
* **Links öffnen jetzt in neuem Tab** (`target="_blank"`), wie auf der
  Landingpage versprochen.
* **i18n**: Der Public-Renderer akzeptiert jetzt `config.texts` zur
  Überschreibung der Texte auf Fremdseiten.
* **Release-ZIP** enthält keine `tests/` und `docs/` mehr (nur Laufzeitcode).
* **`Tested up to`** auf 6.7 angehoben.

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
