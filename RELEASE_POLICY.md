# Release Policy

Diese Richtlinie regelt, wann und wie stabile Releases des **SN News Widget**
erzeugt werden. Sie gilt für menschliche Entwickler **und** automatisierte
Coding-/AI-Agents.

## Grundsatz

Normale Development-Commits erhalten **keine** Stable-Tags und erzeugen **keine**
GitHub-Releases. Eine kleine UI-Änderung ist kein neuer Stable-Release. Ein
Bugfix während der aktiven Entwicklung ist kein automatischer Patch-Release.

## Stable Releases

Ein Stable-Release (z. B. `v1.2.0`) benötigt eine **explizite Freigabe**. Es
entsteht nicht automatisch aus einem normalen Commit oder aus einer
Entwicklungs-Session.

Vor einem Stable Release müssen zwingend erfüllt sein:

* **CI grün** (PHP-Lint, alle PHP-Unit-Tests, JS-Syntax, alle JS-Unit-Tests).
* **Production-ZIP** gebaut und als GitHub-Actions-Artifact verfügbar.
* **Production-ZIP-Test**: Das finale ZIP wurde auf einer sauberen
  WordPress-Installation installiert und aktiviert (kein White Screen, keine
  PHP-Fatal-Errors, keine kaputte REST/Telemetrie).
* **Clean Install** geprüft (frische WP-Installation, keine Altdaten).
* **Upgrade-Test** geprüft (vorherige Stable-Version → neue Version, Daten
  bleiben erhalten).
* **Dokumentation aktuell** (`README.md`, `readme.txt`, `CHANGELOG.md`).
* **Versionsnummer** an einer Stelle (`SNW_VERSION` + Plugin-Header) gesetzt.
* **Keine** Debug-Ausgaben, Test-Domains, lokale Pfade oder Entwicklungs-Artefakte
  im ZIP.

## CI / Release-Gate

* `.github/workflows/test.yml` läuft bei jedem Push auf `master` und bei
  Pull-Requests: Lint → PHP-Tests → JS-Tests → Build des ZIP als **Artifact**
  (kein Tag, kein Release).
* `.github/workflows/build-zip.yml` läuft **nur** bei Push eines `v*`-Tags:
  Lint → PHP-Tests → JS-Tests → Build → GitHub-Release mit ZIP-Asset.
  Schlägt ein kritischer Test fehl, wird **kein** Release erzeugt.

## Agenten-Regel (AI / Coding Agents)

Ein AI- bzw. Coding-Agent darf **nicht** eigenständig nach normalen Tasks
Stable-Tags oder GitHub-Releases erstellen. Ein Stable-Release darf erst
erfolgen, wenn die aktuelle Aufgabe **ausdrücklich** einen Release verlangt.

Dies verhindert, dass für jede kleine Änderung neue Versionsnummern und Releases
entstehen.

## Versionslinie

Die öffentliche Release-Linie ist: `v1.1.0`, `v1.1.1`, `v1.2.0`, … Mikro-
Zwischenversionen werden vor einem Stable-Release thematisch zusammengeführt.
Git-History, Branches und Commits werden nicht umgeschrieben; es werden nur
überflüssige Release-Tags/Releases entfernt.
