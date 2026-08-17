# Changelog

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
