const test = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const path = require('node:path');

function create(version, options = {}) {
  let Page;
  const requests = [], initializers = [], beforeMount = [];
  const registry = { for: (id) => { assert.equal(id, 'el-choco-admin-console'); return registry; }, registerPage: (value) => { Page = value; } };
  const app = {
    forum: { attribute: () => '/api' },
    request: async (request) => {
      requests.push(request);
      if (request.url.endsWith('?summary=1') && options.summaryWait) return options.summaryWait;
      if (request.method === 'GET' && request.url.includes('?job=')) {
        if (options.pollError) throw new Error('network offline');
        const command = (requests.filter(r => r.method === 'POST').slice(-1)[0] || { body: { command: options.resumeCommand || 'info' } }).body.command;
        const phase = options.phases && options.phases.length ? options.phases.shift() : 'succeeded';
        return { id: 'a'.repeat(32), command, status: phase, output: '<script>example</script>', exitCode: 0, seconds: 1, backup: 'test-backup', ...(options.result || {}) };
      }
      if (request.method === 'POST') return { id: request.body.requestId, status: 'queued' };
      return { uid: 1000, imageHelper: version === 2, history: [], workerSeen: Date.now() / 1000, activeJob: options.resume ? 'a'.repeat(32) : null, commands: [
        { name: 'info', template: 'info', description: 'Information', runnable: true },
        { name: 'cache:clear', template: 'cache:clear', description: 'Clear cache', runnable: true },
        { name: 'queue:work', template: 'queue:work', description: 'Queue worker', runnable: false }
      ] };
    },
    initializers: { add: (_, fn) => initializers.push(fn) },
    beforeMount: (fn) => beforeMount.push(fn),
    ...(version === 1 ? { extensionData: registry } : { registry })
  };
  class Admin {
    page(value) { this.customPage = value; return this; }
    extend(application, extension) {
      application.beforeMount(() => {
        application.registry.for(extension.name);
        application.registry.registerPage(this.customPage);
      });
    }
  }
  const modules = { 'admin/app': app, 'admin/components/ExtensionPage': class { oninit() {} }, 'common/extenders/Admin': Admin };
  const m = (...args) => args; m.redraw = () => {};
  const window = { m, confirm: () => true, crypto: require('node:crypto').webcrypto };
  if (options.httpStatus) {
    window.location = { pathname: '/admin' };
    window.fetch = async () => options.fetchWait || ({ status: options.httpStatus, ok: options.httpStatus === 200, redirected: false, text: async () => '' });
  }
  const flarum = version === 1 ? { core: { compat: modules } } : { reg: {
    get: (namespace, id) => { assert.equal(namespace, 'core'); assert.ok(id in modules, id); return modules[id]; },
    add: () => {}
  } };
  flarum.extensions = {};
  const context = { module: { exports: {} }, flarum, window, m, console, Blob, URL, Uint8Array, setTimeout: fn => setTimeout(fn, 0) };
  const source = fs.readFileSync(path.join(__dirname, '../js/dist/admin-v' + version + '.js'), 'utf8');
  vm.runInNewContext(source, context);
  // Match Flarum's asset wrapper and bootExtensions sequence. In v2 no compat exists.
  flarum.extensions['el-choco-admin-console'] = context.module.exports;
  Object.keys(flarum.extensions).forEach((name) => {
    const extension = flarum.extensions[name];
    assert.ok(extension, 'Asset must export an extension module');
    if (!extension.extend) return;
    for (const extender of extension.extend.flat(Infinity)) { extender.extend(app, { name, exports: extension }); }
  });
  initializers.forEach(fn => fn(app));
  beforeMount.forEach(fn => fn());
  assert.ok(Page, 'Custom page must be registered');
  return { page: new Page(), requests, window, exported: context.module.exports };
}

for (const version of [1, 2]) {
  test(`Flarum ${version}: compiled bundle exports a module and boots without compat guesses`, async () => {
    const { page, requests, exported } = create(version);
    assert.ok(Array.isArray(exported.extend));
    assert.equal(exported.extend.length, version === 1 ? 0 : 1);
    page.oninit({}); await page.loadStatus();
    assert.equal(page.status.uid, 1000);
    assert.equal(requests[0].url, '/api/admin-console');
    assert.ok(page.content());
  });
  test(`Flarum ${version}: read-only command needs no confirmation and preserves output as text`, async () => {
    const { page, requests, window } = create(version);
    page.oninit({}); await page.loadStatus();
    window.confirm = () => { throw new Error('Unexpected confirmation'); };
    await page.run();
    assert.equal(requests.find(r => r.method === 'POST').body.confirmation, null);
    assert.match(page.output, /<script>example<\/script>/);
    assert.equal(page.busy, false);
  });
  test(`Flarum ${version}: declined mutation never reaches API`, async () => {
    const { page, requests, window } = create(version);
    page.oninit({}); await page.loadStatus();
    page.command = 'cache:clear'; window.confirm = () => false;
    await page.run();
    assert.equal(requests.filter(r => r.method === 'POST').length, 0);
  });
  test(`Flarum ${version}: confirmed mutation sends command and displays snapshot`, async () => {
    const { page, requests } = create(version);
    page.oninit({}); await page.loadStatus();
    page.command = 'composer remove vendor/package'; await page.run();
    assert.equal(requests.find(r => r.method === 'POST').body.confirmation, page.command);
    assert.match(page.output, /test-backup/);
  });
  test(`Flarum ${version}: dropdown includes runtime commands and unsupported ones open help`, async () => {
    const { page } = create(version); page.oninit({}); await page.loadStatus();
    assert.equal(page.commandItems().length, 6);
    page.commandFilter = 'Queue';
    const picker = page.commandPicker();
    assert.equal(picker[0], 'details.AdminConsole-commandPicker');
    function findOption(node) {
      if (!Array.isArray(node)) return null;
      if (node[0] === 'button.AdminConsole-commandOption') return node;
      for (const item of node) { const found = findOption(item); if (found) return found; }
      return null;
    }
    findOption(picker)[1].onclick();
    assert.equal(page.command, 'help queue:work');
    assert.equal(page.pickerOpen, false);
  });
  test(`Flarum ${version}: exit zero with a failed cache step is not shown as clean success`, async () => {
    const { page } = create(version, { result: { output: 'assets could not be pre-built', exitCode: 0, hasWarnings: true, warnings: ['assets failed'] } });
    page.oninit({}); await page.loadStatus(); page.command = 'cache:clear'; await page.run();
    assert.match(page.error, /Warnungen/);
  });
  test(`Flarum ${version}: cache follow-up HTTP 500 is reported`, async () => {
    const { page } = create(version, { httpStatus: 500 });
    page.oninit({}); await page.loadStatus(); page.command = 'cache:clear'; await page.run();
    await new Promise(resolve => setTimeout(resolve, 0));
    assert.match(page.error, /Nachprüfung/); assert.match(page.output, /HTTP 500/);
  });
}

for (const version of [1, 2]) {
  test(`Flarum ${version}: queued/running/completed states are polled using a unique request ID`, async () => {
    const { page, requests } = create(version, { phases: ['queued', 'running', 'succeeded'] });
    page.oninit({}); await page.loadStatus(); await page.run();
    const post = requests.find(r => r.method === 'POST');
    assert.match(post.body.requestId, /^[a-f0-9]{32}$/);
    assert.equal(requests.filter(r => r.url.includes('?job=')).length, 3);
    assert.equal(page.result.status, 'succeeded');
  });
  test(`Flarum ${version}: reload restores existing job without submitting it again`, async () => {
    const { page, requests } = create(version, { resume: true });
    page.oninit({});
    await new Promise(resolve => setTimeout(resolve, 30));
    assert.equal(requests.filter(r => r.method === 'POST').length, 0);
    assert.equal(page.result.status, 'succeeded');
  });
  test(`Flarum ${version}: a lost connection cannot silently resubmit an installation`, async () => {
    const { page, requests } = create(version, { pollError: true });
    page.oninit({}); await page.loadStatus(); await page.run();
    assert.equal(page.uncertain, true);
    await page.run();
    assert.equal(requests.filter(r => r.method === 'POST').length, 1);
  });
}

for (const version of [1, 2]) {
  test(`Flarum ${version}: slow summary never blocks next command or reloads catalog`, async () => {
    let release;
    const wait = new Promise(resolve => { release = resolve; });
    const { page, requests } = create(version, { summaryWait: wait });
    page.oninit({}); await page.loadStatus();
    const commands = page.status.commands;
    const before = requests.length;
    await page.run();
    assert.equal(page.busy, false);
    assert.equal(page.result.status, 'succeeded');
    assert.ok(requests.slice(before).some(r => r.url.endsWith('?summary=1')));
    assert.ok(!requests.slice(before).some(r => r.method === 'GET' && r.url === '/api/admin-console'));
    release({ history: [], workerSeen: 1 });
    await new Promise(resolve => setTimeout(resolve, 0));
    assert.equal(page.status.commands, commands);
  });
  test(`Flarum ${version}: slow cache check never blocks next command or overwrites its output`, async () => {
    let release;
    const wait = new Promise(resolve => { release = resolve; });
    const { page } = create(version, { httpStatus: 500, fetchWait: wait });
    page.oninit({}); await page.loadStatus(); page.command = 'cache:clear';
    await page.run();
    assert.equal(page.busy, false);
    page.command = 'info'; await page.run();
    const output = page.output;
    release({ status: 500, ok: false, redirected: false, text: async () => '' });
    await new Promise(resolve => setTimeout(resolve, 0));
    assert.equal(page.output, output); assert.equal(page.error, '');
  });
}
