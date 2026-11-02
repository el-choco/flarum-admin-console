# 0.2.0-rc.4 vorbereiten und veröffentlichen

Die lokalen Änderungen enthalten Englisch, Deutsch und Spanisch. Diese Version ist noch nicht auf GitHub oder Packagist veröffentlicht. Es wurden keine Serverdateien geändert.

## 1. Quellcode hochladen

Den Inhalt des vorbereiteten ZIPs im Hauptverzeichnis des vorhandenen Repositorys `el-choco/flarum-admin-console` übernehmen. Insbesondere `locale`, die neuen PHP-Klassen und die fertigen Dateien unter `js/dist` mitnehmen. `composer.json` behält den Namen `el-choco/admin-console` und weiterhin kein festes `version`-Feld.

Die Änderungen betreffen auch die vorhandenen Veröffentlichungstexte mit den inzwischen bestätigten Flarum-1.8-Tests. Sie wurden lokal erhalten und aktualisiert.

Unter GitHub **Actions** auf erfolgreiche Tests warten. Erst danach über **Releases → Draft a new release** einen neuen Tag `v0.2.0-rc.4` auf `main` erstellen, Titel `Admin Console 0.2.0-rc.4` und **This is a pre-release** auswählen. Als Beschreibung den Inhalt aus `docs/RELEASE.en.md` verwenden. Den bestehenden rc.3-Tag unverändert lassen.

Der bereits eingerichtete Packagist-Webhook übernimmt den neuen Tag. Prüfen, dass die Version auf der Paketseite erscheint. Kein neuer Webhook erforderlich.

## 2. Zuerst Flarum 1.8 aktualisieren

Erst nach Veröffentlichung auf Packagist. Alle Aufträge abschließen lassen und die vorhandenen Datei-/Datenbanksicherungen erstellen. Der Dienstname ist hier bewusst der deiner nativen Installation:

```bash
systemctl stop el-choco-admin-console-flarum1.service
cd /var/www/flarum
runuser -u www-data -- composer require el-choco/admin-console:"0.2.0-rc.4" --dry-run --update-no-dev --minimal-changes --no-scripts --no-interaction
```

Nur nach erfolgreichem Probelauf ohne unerwartete Paketänderungen:

```bash
runuser -u www-data -- composer require el-choco/admin-console:"0.2.0-rc.4" --update-no-dev --minimal-changes --no-scripts --no-interaction && \
runuser -u www-data -- php flarum cache:clear
```

PHP-FPM über die tatsächlich verwendete Dienstverwaltung neu laden, falls der Web-OPcache alten Code behält. Der konkrete PHP-FPM-Dienstname der nativen Installation wurde hier noch nicht festgestellt; nicht den Docker-Befehl dafür übernehmen. Anschließend:

```bash
systemctl start el-choco-admin-console-flarum1.service
systemctl status el-choco-admin-console-flarum1.service --no-pager -l
```

Das Neuladen von Web-PHP und der Neustart des Workers sind unterschiedliche Schritte. Bereits laufende Worker behalten geladene Klassen im Speicher und müssen nach diesem Update neu starten. Kein neuer Datenbankmigrationsschritt für diese Erweiterung erforderlich.

## 3. Sprache prüfen

Adminseite mit Strg+F5 neu laden. Die passende installierte und aktivierte Flarum-Sprache auswählen und jeweils Englisch, Deutsch sowie Spanisch prüfen. Auf jeder Sprache `info` ausführen. Originalausgaben des Befehls bleiben unverändert; Schaltflächen, Status und Hinweise sollen der Anzeigesprache folgen. Danach `cache:clear` und die Adminseiten-Nachprüfung testen.

Historische Ausgaben aus rc.3 werden nicht nachträglich übersetzt. Eigene neu gespeicherte Fehlermeldungen tragen Übersetzungsschlüssel und können beim Anzeigen in der aktiven Sprache erscheinen. Die schnellen Worker-Abfragen und nicht blockierenden Nachprüfungen bleiben erhalten.

## 4. Docker getrennt behandeln

Deine Docker-Installation nutzt bisher ein lokales Path-Repository und den Worker unter `/data/admin-console-rc`. Die öffentliche Packagist-Installation verwendet dagegen `/opt/flarum/vendor/el-choco/admin-console`. Beides nicht ungeprüft mischen. Die Umstellung oder Aktualisierung dieser Installation separat anhand der tatsächlich aktiven Composer-Konfiguration durchführen. Keine Container-Neuerstellung und keinen zweiten Worker starten.

## Weblate

Die Dateien sind für Übersetzungen vorbereitet. Die Aufnahme in ein Weblate-Projekt ist noch nicht erfolgt. Siehe `docs/TRANSLATING.md`.
