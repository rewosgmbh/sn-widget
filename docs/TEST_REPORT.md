# Testbericht – Steigerwald-News Widget 1.1.0

## Ausführung

```bash
php -l <file.php>                 # PHP-Syntax aller Dateien
node --check <file.js>            # JS-Syntax aller Dateien
php tests/php/test-helpers.php    # Server-Logik (Sanitisierung, Embed, Presets)
node tests/js/widget.test.js      # Reine Funktionen des Public-Renderers
node tests/js/render.test.js      # Render-Pipeline mit DOM-Shim + Mock-Fetch
```

Ergebnis (Stand Entwicklung):

| Suite | Ergebnis |
|-------|----------|
| PHP Lint (9 Dateien) | PASS |
| JS Syntax (admin.js, widget.js) | PASS |
| PHP-Logik-Tests | 40/40 PASS |
| JS Pure-Function-Tests | 29/29 PASS |
| JS Render-Tests (DOM-Shim) | 20/20 PASS |

## Abgedeckte Testfälle

| Testfall | Wo |
|----------|-----|
| 0 Posts → Empty-State | render.test (0 posts) |
| 1 Post, fehlendes Bild → Layout intakt | render.test (missing image) |
| fehlender Excerpt / sehr langer Titel | render + pure (truncate) |
| HTML im Excerpt/Titel → entfernt, keine kaputten Entities | render.test + pure (stripHtml/truncate) |
| Unicode / Umlaute / Emoji im Titel & Config | pure (encode/decode roundtrip) |
| Kategorie mit Sonderzeichen (embedded `wp:term`) | pure (categoryNames) + render.test (category) |
| Bildgrößen-Auswahl (medium_large → large → medium → original) | pure (featuredImageUrl) |
| UTM-Parameter + Erhalt bestehender Query-Parameter + `utm_content=widget_id` | pure (trackedUrl) |
| mehrere Widgets / widget.js mehrfach eingebunden | Code-Review (Guards `__snwBuilt`, `STYLE_ID`) |
| 2 Widgets mit unterschiedlichen Designs (Design nicht im Cache-Key) | pure (buildCacheKey) |
| API Timeout/Fehler → `on_error=hide` blendet aus, `message` zeigt Hinweis | render.test (fetch error) |
| ungültige Config → keine Exception, `console.debug` | render.test (invalid config) |
| Sortierung (newest/oldest/title) | pure (sortPosts) |
| Sanitisierung/Validierung (Enums, Clamp, Alnum-Partner, Farben, ID-Listen) | php test-helpers |
| Embed-Kodierung (base64url, url-safe) Roundtrip | php test-helpers + js pure |
| Preset CRUD (save/get/update/duplicate/delete) | php test-helpers |
| Private/Draft-Posts: Fetch nutzt `status=publish` → erscheinen nie | Code-Review + Core-Verhalten |

## Manuelle / Browser-Tests (empfohlen vor Release)

Da keine vollständige WordPress-Instanz im Build-Umfeld läuft, sollten folgende
Punkte auf einer echten Installation verifiziert werden (Playwright/Manuell):

* Admin-Builder: Live-Vorschau zeigt echte Posts; Modi wechseln; Tag-Suche;
  Post-Suche mit Drag & Drop; Farbwähler; Speichern/Laden/Duplizieren/Löschen.
* Externer Embed auf einer Nicht-WordPress-Seite (statisch/Joomla/TYPO3):
  Widget lädt, Fehler fängt ab, CORS funktioniert.
* Responsive: 320/375/768/1024/1440 px sowie schmale Sidebar (Container-Queries).
* Dark-Theme-Host mit Design „Host-Website übernehmen“ lesbar.
* Admin ohne `manage_options` sieht die Seite nicht (Capability-Check).

## Bekannte Einschränkungen der automatisierten Tests

* Die Render-Tests nutzen ein minimales DOM-Shim und Mock-Fetch; sie
  verifizieren Struktur/Inhalt, nicht visuelles Layout.
* Eine vollständige WordPress-Test-Suite (WP_UnitTestCase) ist im Build-Umfeld
  nicht eingerichtet; die PHP-Logik wird mit gestubbten Core-Funktionen geprüft.
