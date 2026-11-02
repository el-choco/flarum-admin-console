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

Aktueller Release Candidate: `0.2.0-rc.4`.

Die Erweiterung ist für Flarum 1.8 und 2.x vorgesehen. Automatisierte Prüfungen verwenden die echten Abhängigkeiten von Flarum 1.8.21 und 2.0.2. Praxistests erfolgten mit Flarum 2 im CrazyMax-Container und Flarum 1.8.20 ohne Docker mit PHP 8.4.24. Unter Flarum 1.8.20 wurden die Installation von Packagist, Aktivierung, Worker-Ausführung sowie `info`, `cache:clear` und `composer show` bestätigt. Die Adminseite blieb nach dem Leeren des Caches erreichbar. Die Installation und Entfernung von Composer-Paketen über die Console wurde auf der nativen 1.8-Installation noch nicht geprüft. Die Oberfläche ist auf Englisch, Deutsch und Spanisch verfügbar und folgt Flarums gewählter Anzeigesprache. Originalausgaben der Unterprozesse werden nicht übersetzt.

## Installation

Nach Veröffentlichung auf Packagist im Flarum-Verzeichnis als normaler Installationsbenutzer:

```bash
composer require el-choco/admin-console:"0.2.0-rc.4"
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
