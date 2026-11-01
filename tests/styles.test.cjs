const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const less = require(process.env.FLARUM_CONSOLE_LESS_MODULE || 'less');
const source = fs.readFileSync(path.join(__dirname, '../less/admin.less'), 'utf8');

test('admin style compiles without Flarum Less theme variables', async () => {
  const result = await less.render(source);
  assert.match(result.css, /\.AdminConsole-header p/);
  assert.match(result.css, /var\(--muted-color, #66788a\)/);
  assert.match(result.css, /var\(--control-bg, #eef2f5\)/);
  assert.match(result.css, /@media/);
});
test('style also compiles when legacy Flarum Less variables are supplied', async () => {
  const result = await less.render(source, { modifyVars: { 'muted-color': '#123456', 'control-bg': '#112233', 'control-color': '#445566' } });
  assert.match(result.css, /\.AdminConsole-historyItem/);
  assert.doesNotMatch(result.css, /@muted-color|@control-bg|@control-color/);
});
