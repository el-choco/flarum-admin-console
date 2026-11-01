export default function createConsolePage(app, ExtensionPage, m) {
  class ConsolePage extends ExtensionPage {
    oninit(vnode) {
      super.oninit(vnode);
      this.command = 'info';
      this.output = 'Bereit. Wähle eine Aktion oder gib einen freigegebenen Befehl ein.';
      this.busy = false;
      this.status = null;
      this.error = '';
      this.result = null;
      this.commandFilter = '';
      this.pickerOpen = false;
      this.disposed = false;
      this.uncertain = false;
      this.loadStatus().then(() => {
        const id = this.status && (this.status.activeJob || this.status.latestJob);
        if (id) this.watchJob(id);
      });
    }

    loadStatus(summary = false) {
      return app.request({ method: 'GET', url: app.forum.attribute('apiUrl') + '/admin-console' + (summary ? '?summary=1' : '') })
        .then((data) => { this.status = { ...this.status, ...data }; m.redraw(); })
        .catch((error) => { this.error = this.message(error); m.redraw(); });
    }

    message(error) {
      return (error.response && (error.response.message || (error.response.errors && error.response.errors[0] && error.response.errors[0].detail))) || error.message || 'Anfrage fehlgeschlagen. Prüfe das Serverprotokoll.';
    }

    onremove() { this.disposed = true; }

    statusLabel(status) {
      return ({ queued: 'Wartet', running: 'Läuft', succeeded: 'Abgeschlossen', failed: 'Fehlgeschlagen', interrupted: 'Unterbrochen', acknowledged: 'Im Terminal geprüft' })[status] || status;
    }

    async run(event) {
      if (event) event.preventDefault();
      if (this.busy || this.uncertain) return;
      const command = this.command.trim();
      const readOnly = /^(?:(?:php )?flarum )?(?:info$|list$|schedule:list$|help )/.test(command) || /^composer show(?: |$)/.test(command);
      if (!readOnly && !window.confirm('Diesen Befehl mit den Rechten des Forums ausführen?\n\n' + command)) return;
      if (!window.crypto || !window.crypto.getRandomValues) {
        this.error = 'Die Konsole benötigt einen Browser mit kryptografischen Zufallszahlen.'; return;
      }
      const bytes = new Uint8Array(16); window.crypto.getRandomValues(bytes);
      const requestId = Array.from(bytes, b => b.toString(16).padStart(2, '0')).join('');
      this.busy = true; this.error = ''; this.result = null;
      this.output = 'Auftrag wird gespeichert …';
      try {
        const job = await app.request({ method: 'POST', url: app.forum.attribute('apiUrl') + '/admin-console', body: { command, confirmation: readOnly ? null : command, requestId } });
        this.busy = false;
        await this.watchJob(job.id);
      } catch (error) {
        this.error = this.message(error);
        this.output += '\nKeine bestätigte Abschlussmeldung. Seite neu laden, um den gespeicherten Status abzurufen. Nicht erneut installieren.';
        this.uncertain = true; this.busy = false; m.redraw();
      }
    }

    async watchJob(id) {
      if (this.watching || this.disposed) return;
      this.watching = true; this.busy = true;
      try {
        while (!this.disposed) {
          const job = await app.request({ method: 'GET', url: app.forum.attribute('apiUrl') + '/admin-console?job=' + encodeURIComponent(id) });
          this.command = job.command; this.result = job;
          this.output = job.output || (job.status === 'queued' ? 'Gespeichert. Wartet auf den Hintergrunddienst.' : 'Befehl läuft …');
          this.output += '\n\nAuftrag: ' + job.id + ' · Status: ' + this.statusLabel(job.status);
          if (job.backup) this.output += '\nPaketsicherung (keine Datenbank-/Vollsicherung): ' + job.backup;
          if (job.truncated) this.output += '\n[Ausgabe auf 256 KiB gekürzt]';
          if (job.opcache && job.opcache.failed && job.opcache.failed.length) this.error = 'Web-OPcache konnte nicht vollständig aktualisiert werden.';
          m.redraw();
          if (!['queued', 'running'].includes(job.status)) {
            if (job.status !== 'succeeded') this.error = 'Auftrag ' + this.statusLabel(job.status) + ' (Exit ' + job.exitCode + '). Details in der Ausgabe. Bei Unterbrechung zuerst im Terminal prüfen.';
            else if (job.hasWarnings || (job.warnings && job.warnings.length)) this.error = 'Der Prozess meldet Exit 0, aber die Ausgabe enthält Warnungen. Bitte prüfen.';
            if (/^(?:(?:php )?flarum )?cache:clear(?: |$)/.test(job.command) && job.status === 'succeeded') void this.checkAdminPage(job);
            void this.loadStatus(true);
            break;
          }
          await new Promise(resolve => setTimeout(resolve, 500));
        }
      } catch (error) {
        this.error = 'Statusabruf unterbrochen: ' + this.message(error) + ' Der gespeicherte Auftrag wird davon nicht abgebrochen. Seite neu laden.';
        this.uncertain = true;
      } finally { this.busy = false; this.watching = false; m.redraw(); }
    }

    async checkAdminPage(job = this.result) {
      if (typeof window.fetch !== 'function') return;
      try {
        const response = await window.fetch(window.location.pathname, { credentials: 'same-origin', cache: 'no-store' });
        await response.text();
        if (this.disposed || this.result !== job) return;
        this.output += '\n\nNachprüfung Adminseite: HTTP ' + response.status;
        if (!response.ok || response.redirected) {
          this.error = 'Der Cache-Befehl ist beendet, aber die Nachprüfung der Adminseite war nicht erfolgreich. Bitte das Flarum-Protokoll prüfen.';
        }
      } catch (error) {
        if (this.disposed || this.result !== job) return;
        this.error = 'Der Cache-Befehl ist beendet; die Adminseite konnte danach nicht geprüft werden.';
      }
      m.redraw();
    }

    commandItems() {
      const flarum = this.status && Array.isArray(this.status.commands) ? this.status.commands : [];
      const items = flarum.map(item => typeof item === 'string' ? { name: item, template: item, description: '', runnable: true } : item);
      return items.concat([
        { name: 'composer show', template: 'composer show', description: 'Installierte Pakete anzeigen', runnable: true },
        { name: 'composer require', template: 'composer require vendor/paket:*', description: 'Erweiterung installieren – Paketnamen ersetzen', runnable: true },
        { name: 'composer remove', template: 'composer remove vendor/paket', description: 'Erweiterung entfernen – Paketnamen ersetzen', runnable: true }
      ]);
    }

    commandPicker() {
      const items = this.commandItems();
      const filter = this.commandFilter.toLocaleLowerCase();
      const shown = items.filter(item => (item.name + ' ' + item.description).toLocaleLowerCase().includes(filter));
      return m('details.AdminConsole-commandPicker', {
        open: this.pickerOpen,
        ontoggle: event => { this.pickerOpen = event.target.open; }
      }, [
        m('summary', 'Befehl auswählen · ' + items.length + ' Einträge'),
        m('.AdminConsole-pickerBody', [
          m('input.FormControl', { 'aria-label': 'Befehle durchsuchen', placeholder: 'Befehle durchsuchen …', value: this.commandFilter, oninput: event => { this.commandFilter = event.target.value; } }),
          m('p.helpText', 'Befehle werden aus dieser Installation gelesen. Bei nicht freigegebenen Aktionen wird die Hilfe übernommen.'),
          m('.AdminConsole-commandList', shown.map(item => m('button.AdminConsole-commandOption', {
            type: 'button', disabled: this.busy,
            onclick: () => { this.command = item.runnable ? item.template : 'help ' + item.name; this.pickerOpen = false; }
          }, [m('code', item.name), m('span', item.description), !item.runnable && m('small', 'Hilfe anzeigen · Ausführung im Terminal')]))),
          !shown.length && m('p', 'Keine passenden Befehle gefunden.')
        ])
      ]);
    }

    content() {
      return m('.AdminConsole', [
        m('.AdminConsole-header', [
          m('span.AdminConsole-eyebrow', 'WARTUNG · ADMINISTRATOR'),
          m('h2', 'Admin Console'),
          m('p', 'Flarum-Befehle direkt im Adminbereich ausführen.'),
          this.status && m('.AdminConsole-badges', [
            m('span', 'PHP-Benutzer-ID: ' + (this.status.uid === null ? 'unbekannt' : this.status.uid)),
            m('span', this.status.imageHelper ? 'CrazyMax-Helfer aktiv' : 'Composer direkt'),
            m('span', 'Konsole 0.2.0-rc.3'),
            ...(this.status.checks || []).map(check => m('span', check.name + ': ' + (check.writable ? 'beschreibbar' : 'nicht beschreibbar')))
          ])
        ]),
        this.error && m('.Alert.Alert--error', { role: 'alert' }, this.error),
        this.status && (!this.status.workerSeen || Date.now() / 1000 - this.status.workerSeen > 180) && m('.Alert.Alert--warning', 'Der Hintergrunddienst meldet sich nicht. Dienst-Einrichtung prüfen; gespeicherte Aufträge bleiben erhalten.'),
        this.status && this.status.catalogError && m('.Alert.Alert--warning', 'Befehlsliste: ' + this.status.catalogError),
        this.commandPicker(),
        m('form.AdminConsole-form', { onsubmit: (event) => this.run(event) }, [
          m('label', { for: 'admin-console-command' }, 'Befehl'),
          m('.AdminConsole-inputRow', [
            m('input.FormControl', { id: 'admin-console-command', value: this.command, disabled: this.busy, autocomplete: 'off', spellcheck: false, placeholder: 'z. B. cache:clear', oninput: (event) => { this.command = event.target.value; } }),
            m('button.Button.Button--primary', { type: 'submit', disabled: this.busy || this.uncertain || !this.status || !this.command.trim(), 'aria-busy': this.busy }, this.busy ? 'Wird ausgeführt …' : 'Ausführen')
          ]),
          m('p.helpText', 'Platzhalter wie <extension-id> oder vendor/paket vor dem Ausführen ersetzen. Nur freigegebene Befehle; keine Shell-Verkettungen.')
        ]),
        m('.AdminConsole-terminalHeader', [
          m('strong', 'Ausgabe'),
          this.result && m('span', this.statusLabel(this.result.status) + (this.result.exitCode == null ? '' : ' · Exit ' + this.result.exitCode) + ' · ' + this.result.seconds + ' s'),
          m('button.Button.Button--text', { type: 'button', onclick: () => { const blob = new Blob([this.output], { type: 'text/plain;charset=utf-8' }); const url = URL.createObjectURL(blob); const a = document.createElement('a'); a.href = url; a.download = 'flarum-console.txt'; a.click(); setTimeout(() => URL.revokeObjectURL(url), 1000); } }, 'Speichern')
        ]),
        m('pre.AdminConsole-output', { tabindex: 0, 'aria-label': 'Befehlsausgabe', 'aria-busy': this.busy }, this.output),
        m('h3', 'Letzte Aufträge'),
        m('p.helpText', 'Befehl, Ergebnis und Ausgabe werden privat gespeichert. Abgeschlossene Aufträge werden nach sieben Tagen oder oberhalb von 100 Einträgen beim nächsten Worker-Lauf gelöscht. Paketsicherungen bleiben erhalten.'),
        this.status && this.status.history.length ? m('.AdminConsole-history', this.status.history.map((item) => m('.AdminConsole-historyItem', [
          m('code', item.command), m('span', new Date(item.time).toLocaleString('de-DE')), m('span', this.statusLabel(item.status) + (item.exitCode == null ? '' : ' · Exit ' + item.exitCode)),
          m('button.Button.Button--text', { type: 'button', disabled: this.busy, onclick: () => { this.command = item.command; } }, 'Übernehmen')
        ]))) : m('p', 'Noch keine Aufträge.'),
        m('p.helpText', 'Befehle laufen im separaten Worker. Du kannst diese Seite schließen und später den Status abrufen. Während Paketänderungen keine weiteren Wartungsarbeiten über SSH oder Portainer starten. Falls Flarum nicht startet, nutze die Serverkonsole.')
      ]);
    }
  }

  return ConsolePage;
}
