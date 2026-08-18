# Changelog

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
