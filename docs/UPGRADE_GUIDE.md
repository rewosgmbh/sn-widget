# Upgrade-Hinweise: v1.0.2 → v1.1.0

## Was sich ändert

* Die einfache Einstellungsseite wird durch einen vollständigen
  **Widget Builder** ersetzt.
* Der Einbettungscode ändert sich von vielen `data-*`-Attributen (teilweise
  Slug-basiert) zu einem einzigen `data-config`-Attribut mit base64url-kodiertem
  JSON plus einem `<script>`-Tag.
* Es gibt keine eigene DB-Tabelle und keinen eigenen REST-Endpunkt (wie schon
  seit 1.0.2).

## Vorgehen

1. **Backup** der WordPress-Installation (Dateien + Datenbank).
2. Alte Version **deaktivieren** (nicht löschen, falls Rollback nötig).
3. Alte Plugin-Dateien löschen (`steigerwald-news-widget.php`,
   `assets/widgets.js` falls vorhanden).
4. Neue Version hochladen (`steigerwald-news-widget/`) und **aktivieren**.
5. **Einstellungen → News Widget** öffnen.
6. Vorhandene Einbettungscodes auf Partnerseiten wurden mit dem alten,
   slug-basierten Format erzeugt. Diese funktionieren mit v1.1.0 **nicht
   mehr** (andere Attribut-Struktur). Bitte im Builder neue Presets anlegen
   und die neuen Einbettungscodes auf den Partnerseiten ersetzen.
7. Presets werden in der Option `snw_presets` gespeichert – ein Import alter
   Konfigurationen ist nicht vorgesehen (alte Configs waren slug-basiert und
   nicht kompatibel).

## Regression: einfaches Widget bleibt erhalten

Ein Widget ohne Sonderkonfiguration (Modus „Neueste Beiträge", Limit 5,
Standard-Sichtbarkeit) funktioniert weiterhin:

1. lädt veröffentlichte Posts,
2. zeigt Titel,
3. zeigt Datum,
4. zeigt optional Bild,
5. zeigt Teaser,
6. verlinkt zum Originalartikel,
7. funktioniert auf externer Website.

## Uninstall

Beim normalen Deaktivieren werden **keine** Daten gelöscht. Erst über
*Plugins → Deinstallieren* (bzw. `uninstall.php`) wird die Option
`snw_presets` entfernt – keine Beiträge/Kategorien/Tags/Medien.
