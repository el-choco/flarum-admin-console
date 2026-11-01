# Admin Console - Wartungsbefehle direkt im Flarum-Adminbereich

Admin Console bringt eine Befehlsoberfläche in deinen Flarum-Adminbereich. Fuehre freigegebene Wartungsbefehle aus, prüfe deren Ausgabe und installiere oder entferne Composer-Pakete.

## Funktionen

- Durchsuchbare Auswahl der im Forum vorhandenen Befehle.
- Forum-Informationen, Cache leeren und Erweiterungen aktivieren/deaktivieren.
- Composer-Pakete installieren, entfernen und anzeigen.
- Separater Worker: Ein geschlossener Browser bricht den Auftrag nicht ab.
- Gespeicherte Ausgaben, Exit-Codes, Warnungen und Auftragsverlauf.
- Bestätigung vor Änderungen und Sicherung der Paketkonfiguration.
- Native Linux-Installation und getesteter CrazyMax-Dockerbetrieb.

## Stand und Kompatibilität

Erster öffentlicher Release Candidate: `0.2.0-rc.3`.

Die Erweiterung ist für Flarum 1.8 und 2.x vorgesehen. Automatisierte Prüfungen verwenden die echten Abhaengigkeiten von Flarum 1.8.21 und 2.0.2. Praxistests erfolgten mit Flarum 2 im CrazyMax-Container. Der vollständige Praxistest einer nativen Flarum-1.8-Installation steht noch aus. Die Oberfläche ist derzeit auf Deutsch.

## Installation

Nach Veröffentlichung auf Packagist im Flarum-Verzeichnis als normaler Installationsbenutzer:

```bash
composer require el-choco/admin-console:"0.2.0-rc.3"
php flarum extension:enable el-choco-admin-console
php flarum cache:clear
```

**Zusätzlich muss der CLI-Worker eingerichtet werden.** INSTALL.md enthaelt Dienstvorlagen für Linux mit und ohne Docker. Der dauerhafte Worker prüft etwa jede Sekunde auf neue Aufträge. Ein zusätzlicher Cron-Eintrag für die Console ist dabei nicht erforderlich.

## Voraussetzungen und Wiederherstellung

Nur für vertrauenswuerdige Administratoren. Die Console erlaubt festgelegte Befehle; eine beliebige Shell steht nicht zur Verfuegung. Composer-Pakete können dennoch fremden Code mit den Rechten des Forums ausführen. Keine gleichzeitige Paketwartung über andere Werkzeuge starten.

Paketsicherungen enthalten Composer-Konfiguration und gegebenenfalls die Erweiterungsliste des Docker-Images. Sie ersetzen keine Sicherung von Datenbank, Uploads und Dateien. Normalen Terminalzugang zur Wiederherstellung beibehalten.

Linux, PHP CLI, Unterstützung für Unterprozesse und ein Worker mit derselben Benutzer-ID wie Web-PHP sind erforderlich; root wird abgewiesen. Shared Hosting ohne Worker-Unterstützung ist ungeeignet. Nach Updates kann das Neuladen von PHP-FPM erforderlich sein, wenn der Web-OPcache alten Code behaelt.

Lizenz: MIT. Rückmeldungen und nachvollziehbare Fehlerberichte sind willkommen. Bitte Flarum-/PHP-Version, Installationsart, ausgeführten Befehl und bereinigte Protokollauszuege angeben.


[GitHub](https://github.com/el-choco/flarum-admin-console) | [Issues](https://github.com/el-choco/flarum-admin-console/issues) | [Packagist](https://packagist.org/packages/el-choco/admin-console) | [Installation](https://github.com/el-choco/flarum-admin-console/blob/main/INSTALL.md)
