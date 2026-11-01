# Veröffentlichung von el-choco/admin-console

Repository: https://github.com/el-choco/flarum-admin-console

Paketname auf Packagist: `el-choco/admin-console`. Der Repositoryname darf davon abweichen. Der vorgeschlagene erste öffentliche Tag ist `v0.2.0-rc.3`. Noch nichts davon ist durch die lokale Vorbereitung veröffentlicht worden.

## 1. Quellcode auf GitHub

Der vorbereitete Ordner enthält `composer.json`, PHP-Quellcode, fertige Frontenddateien, MIT-Lizenz, Anleitungen und GitHub-Tests. `composer.json` muss direkt im Repository-Hauptverzeichnis liegen. `js/dist` gehört ins Repository; `node_modules` und `vendor` nicht.

In PowerShell auf deinem Windows-PC:

```powershell
cd 'C:\Users\franc\.gemini\antigravity\scratch\webmail\admin-console'
git init -b main
git add .
git commit -m "Prepare Admin Console public release candidate"
git remote add origin https://github.com/el-choco/flarum-admin-console.git
git push -u origin main
```

Die GitHub-Anmeldung erfolgt bei Bedarf über Git. Falls Git eine fehlende Autorenidentität meldet, deine eigene Git-Identität einrichten und den Commit wiederholen. Keine Zugangsdaten in Dateien schreiben.

Alternativ die Dateien über GitHub hochladen. Dabei auch den versteckten Ordner `.github` und die fertigen Dateien unter `js/dist` mitnehmen. Den Ordnerinhalt hochladen, nicht einen zusätzlichen übergeordneten `admin-console`-Ordner erzeugen.

## 2. GitHub Actions prüfen

Auf der Repository-Seite unter **Actions → Admin Console** müssen die beiden Core-Prüfungen und der CrazyMax-Test erfolgreich sein. Diese Linux-/Docker-Prüfungen sind bislang nur vorbereitet. Bei Fehlern zuerst korrigieren; noch kein Release als fertig ankündigen.

Die öffentliche Anleitung für Worker und Installation gegenlesen. Den Screenshot für den Erweiterungsbeitrag ergänzen. Der eigenständige Praxistest auf einer nativen Flarum-1.8-Installation steht noch aus; deshalb zunächst ein öffentlicher Release Candidate.

## 3. Version taggen und Release anlegen

Erst nach erfolgreichen Prüfungen:

```powershell
git tag v0.2.0-rc.3
git push origin v0.2.0-rc.3
```

Auf GitHub unter **Releases → Draft a new release** diesen bestehenden Tag auswählen. Titel: `Admin Console 0.2.0-rc.3`. Als Vorabversion / **pre-release** markieren. Den Entwurf aus `docs/RELEASE.en.md` verwenden und veröffentlichen. Bereits veröffentlichte Tags später nicht verschieben: Korrekturen bekommen eine neue Versionsnummer.

Für Composer zählt der Tag; die GitHub-Release-Seite liefert ergänzend Beschreibung und Downloads. In `composer.json` steht bewusst kein festes `version`-Feld.

## 4. Bei Packagist einreichen

Bei https://packagist.org anmelden, möglichst über dein GitHub-Konto. Unter https://packagist.org/packages/submit die öffentliche Repository-Adresse eintragen:

```text
https://github.com/el-choco/flarum-admin-console
```

Packagist liest die Paketdaten und Versionen aus GitHub. Keine ZIP-Datei hochladen. Prüfen, dass `el-choco/admin-console` und die Version `0.2.0-rc.3` erscheinen. Falls der Vendorname bereits geschützt ist, muss dein Packagist-Konto dafür berechtigt sein.

## 5. Automatische Aktualisierung / Webhook

Bei Anmeldung über GitHub kann Packagist die Integration mit den erteilten Berechtigungen einrichten. Auf der Paketseite kontrollieren, dass automatische Updates aktiv sind; dann keinen zweiten Hook anlegen.

Wenn du den Hook selbst einrichten möchtest: **GitHub-Repository → Settings → Webhooks → Add webhook**.

| Feld | Wert |
| --- | --- |
| Payload URL | `https://packagist.org/api/github?username=DEIN_PACKAGIST_NAME` |
| Content type | `application/json` |
| Secret | Dein Packagist-API-Token aus deinem Packagist-Profil |
| Events | Nur `push` |
| Active | Aktiviert |

Den Token direkt in das Secret-Feld eintragen, nicht in die URL, das Repository oder einen Chat kopieren. Spätere Versions-Tags werden gepusht und lösen die Aktualisierung aus. In den Webhook-Übermittlungen auf eine erfolgreiche Antwort achten und auf Packagist kontrollieren, dass der neue Tag erscheint. Ein zusätzlicher Deployment-Webhook auf deinem DietPi ist nicht erforderlich.

## 6. Installation und Erweiterungsbeitrag

Nach Veröffentlichung zunächst in einer separaten Testinstallation direkt von Packagist installieren, Worker einrichten und `info` sowie `cache:clear` testen:

```bash
composer require el-choco/admin-console:"0.2.0-rc.3"
```

Deine bestehende Testinstallation verwendet noch ein lokales Path-Repository. Sie dadurch nicht ungeprüft auf Packagist umstellen: Der lokale Repository-Eintrag und der Worker-Quellpfad müssen bei einer späteren Migration gemeinsam angepasst werden.

Danach den Entwurf aus `docs/EXTENSION-PAGE.en.md` im Bereich **Extensions** auf https://discuss.flarum.org/t/extensions veröffentlichen. Für einen deutschen Beitrag liegt `docs/EXTENSION-PAGE.de.md` bei. Vor dem Posten einen aktuellen Screenshot ergänzen und die Installations-, Repository- und Packagist-Links prüfen.

## Spätere Updates

Änderungen committen → GitHub-Tests prüfen → neuen Versions-Tag pushen → GitHub-Release veröffentlichen. Packagist übernimmt den Tag über die Integration. Nutzer aktualisieren anschließend mit Composer. Ein Git-Push ohne Versions-Tag erzeugt keine neue stabile Version.

Quellen: [Flarum: Distribution](https://docs.flarum.org/extend/distribution/), [Packagist: Einreichung, Versionen und Webhooks](https://packagist.org/about).
