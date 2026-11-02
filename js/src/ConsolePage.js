export default function createConsolePage(app, ExtensionPage, m) {
  // Resources contain plain text only. Normalize Flarum 1/2's string/array return values.
  const text = value => Array.isArray(value) ? value.map(text).join('') : (value == null ? '' : String(value));
  const t = (key, parameters = {}) => text(app.translator.trans('el-choco-admin-console.admin.' + key, parameters));
  const locale = () => (app.translator.getLocale() || 'en').replace(/_/g, '-');
  const diagnostic = data => {
    if (data && typeof data.translationKey === 'string' && data.translationKey.startsWith('el-choco-admin-console.admin.')) {
      const translated = text(app.translator.trans(data.translationKey, data.translationParameters || {}));
      if (translated !== data.translationKey) return translated;
    }
    return (data && data.message) || '';
  };
  class ConsolePage extends ExtensionPage {
    oninit(vnode) {
      super.oninit(vnode);
      this.command = 'info';
      this.output = t('ready');
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
      return diagnostic(error.response) || (error.response && (error.response.message || (error.response.errors && error.response.errors[0] && error.response.errors[0].detail))) || error.message || t('request_failed');
    }

    onremove() { this.disposed = true; }

    statusLabel(status) {
      return ['queued', 'running', 'succeeded', 'failed', 'interrupted', 'acknowledged'].includes(status) ? t('status_' + status) : status;
    }

    async run(event) {
      if (event) event.preventDefault();
      if (this.busy || this.uncertain) return;
      const command = this.command.trim();
      const readOnly = /^(?:(?:php )?flarum )?(?:info$|list$|schedule:list$|help )/.test(command) || /^composer show(?: |$)/.test(command);
      if (!readOnly && !window.confirm(t('confirm', { command }))) return;
      if (!window.crypto || !window.crypto.getRandomValues) {
        this.error = t('crypto_required'); return;
      }
      const bytes = new Uint8Array(16); window.crypto.getRandomValues(bytes);
      const requestId = Array.from(bytes, b => b.toString(16).padStart(2, '0')).join('');
      this.busy = true; this.error = ''; this.result = null;
      this.output = t('saving');
      try {
        const job = await app.request({ method: 'POST', url: app.forum.attribute('apiUrl') + '/admin-console', body: { command, confirmation: readOnly ? null : command, requestId } });
        this.busy = false;
        await this.watchJob(job.id);
      } catch (error) {
        this.error = this.message(error);
        this.output += '\n' + t('unconfirmed');
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
          this.output = job.output || (job.status === 'queued' ? t('queued') : t('running'));
          this.output += '\n\n' + t('job_status', { id: job.id, status: this.statusLabel(job.status) });
          if (job.diagnostic) this.output += '\n' + diagnostic(job.diagnostic);
          for (const notice of job.notices || []) this.output += '\n' + diagnostic(notice);
          if (job.backup) this.output += '\n' + t('backup', { backup: job.backup });
          if (job.truncated) this.output += '\n' + t('truncated');
          if (job.opcache && job.opcache.failed && job.opcache.failed.length) this.error = t('opcache_failed');
          m.redraw();
          if (!['queued', 'running'].includes(job.status)) {
            if (job.status !== 'succeeded') this.error = t('job_failed', { status: this.statusLabel(job.status), code: job.exitCode == null ? t('unknown') : job.exitCode });
            else if (job.hasWarnings || (job.warnings && job.warnings.length)) this.error = t('warnings');
            if (/^(?:(?:php )?flarum )?cache:clear(?: |$)/.test(job.command) && job.status === 'succeeded') void this.checkAdminPage(job);
            void this.loadStatus(true);
            break;
          }
          await new Promise(resolve => setTimeout(resolve, 500));
        }
      } catch (error) {
        this.error = t('poll_failed', { error: this.message(error) });
        this.uncertain = true;
      } finally { this.busy = false; this.watching = false; m.redraw(); }
    }

    async checkAdminPage(job = this.result) {
      if (typeof window.fetch !== 'function') return;
      try {
        const response = await window.fetch(window.location.pathname, { credentials: 'same-origin', cache: 'no-store' });
        await response.text();
        if (this.disposed || this.result !== job) return;
        this.output += '\n\n' + t('admin_http', { status: response.status });
        if (!response.ok || response.redirected) {
          this.error = t('admin_check_failed');
        }
      } catch (error) {
        if (this.disposed || this.result !== job) return;
        this.error = t('admin_check_unavailable');
      }
      m.redraw();
    }

    checkLabel(check) {
      if (check.translationKey) return diagnostic({ ...check, message: check.name });
      return check.name;
    }

    commandItems() {
      const flarum = this.status && Array.isArray(this.status.commands) ? this.status.commands : [];
      const items = flarum.map(item => typeof item === 'string' ? { name: item, template: item, description: '', runnable: true } : item);
      return items.concat([
        { name: 'composer show', template: 'composer show', description: t('composer_show'), runnable: true },
        { name: 'composer require', template: 'composer require vendor/package:*', description: t('composer_require'), runnable: true },
        { name: 'composer remove', template: 'composer remove vendor/package', description: t('composer_remove'), runnable: true }
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
        m('summary', t('picker', { count: items.length })),
        m('.AdminConsole-pickerBody', [
          m('input.FormControl', { 'aria-label': t('search_label'), placeholder: t('search_placeholder'), value: this.commandFilter, oninput: event => { this.commandFilter = event.target.value; } }),
          m('p.helpText', t('picker_help')),
          m('.AdminConsole-commandList', shown.map(item => m('button.AdminConsole-commandOption', {
            type: 'button', disabled: this.busy,
            onclick: () => { this.command = item.runnable ? item.template : 'help ' + item.name; this.pickerOpen = false; }
          }, [m('code', item.name), m('span', item.description), !item.runnable && m('small', t('help_only'))]))),
          !shown.length && m('p', t('no_commands'))
        ])
      ]);
    }

    content() {
      return m('.AdminConsole', [
        m('.AdminConsole-header', [
          m('span.AdminConsole-eyebrow', t('eyebrow')),
          m('h2', t('title')),
          m('p', t('description')),
          this.status && m('.AdminConsole-badges', [
            m('span', t('uid', { uid: this.status.uid === null ? t('unknown') : this.status.uid })),
            m('span', this.status.imageHelper ? t('helper') : t('composer_direct')),
            m('span', t('version', { version: '0.2.0-rc.4' })),
            ...(this.status.checks || []).map(check => m('span', t('check', { name: this.checkLabel(check), state: t(check.writable ? 'writable' : 'not_writable') })))
          ])
        ]),
        this.error && m('.Alert.Alert--error', { role: 'alert' }, this.error),
        this.status && (!this.status.workerSeen || Date.now() / 1000 - this.status.workerSeen > 180) && m('.Alert.Alert--warning', t('worker_missing')),
        this.status && this.status.catalogError && m('.Alert.Alert--warning', t('catalog_error', { error: diagnostic(this.status.catalogDiagnostic) || this.status.catalogError })),
        this.commandPicker(),
        m('form.AdminConsole-form', { onsubmit: (event) => this.run(event) }, [
          m('label', { for: 'admin-console-command' }, t('command')),
          m('.AdminConsole-inputRow', [
            m('input.FormControl', { id: 'admin-console-command', value: this.command, disabled: this.busy, autocomplete: 'off', spellcheck: false, placeholder: t('command_placeholder'), oninput: (event) => { this.command = event.target.value; } }),
            m('button.Button.Button--primary', { type: 'submit', disabled: this.busy || this.uncertain || !this.status || !this.command.trim(), 'aria-busy': this.busy }, this.busy ? t('executing') : t('execute'))
          ]),
          m('p.helpText', t('command_help', { extension: '<extension-id>', package: 'vendor/package' }))
        ]),
        m('.AdminConsole-terminalHeader', [
          m('strong', t('output')),
          this.result && m('span', this.statusLabel(this.result.status) + (this.result.exitCode == null ? '' : ' · ' + t('exit', { code: this.result.exitCode })) + ' · ' + t('duration', { seconds: new Intl.NumberFormat(locale()).format(this.result.seconds) })),
          m('button.Button.Button--text', { type: 'button', onclick: () => { const blob = new Blob([this.output], { type: 'text/plain;charset=utf-8' }); const url = URL.createObjectURL(blob); const a = document.createElement('a'); a.href = url; a.download = 'flarum-console.txt'; a.click(); setTimeout(() => URL.revokeObjectURL(url), 1000); } }, t('save'))
        ]),
        m('pre.AdminConsole-output', { tabindex: 0, 'aria-label': t('output_label'), 'aria-busy': this.busy }, this.output),
        m('h3', t('history')),
        m('p.helpText', t('history_help')),
        this.status && this.status.history.length ? m('.AdminConsole-history', this.status.history.map((item) => m('.AdminConsole-historyItem', [
          m('code', item.command), m('span', new Date(item.time).toLocaleString(locale())), m('span', this.statusLabel(item.status) + (item.exitCode == null ? '' : ' · ' + t('exit', { code: item.exitCode }))),
          m('button.Button.Button--text', { type: 'button', disabled: this.busy, onclick: () => { this.command = item.command; } }, t('use_command'))
        ]))) : m('p', t('no_history')),
        m('p.helpText', t('worker_help'))
      ]);
    }
  }

  return ConsolePage;
}
