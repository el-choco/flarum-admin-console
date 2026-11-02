const fs = require('node:fs');
const path = require('node:path');

// Locale files use a deliberately simple YAML subset: one namespace, one
// section, JSON-quoted scalar values. PHP tests also load them with YAML.
function translations(locale) {
  const result = {};
  const source = fs.readFileSync(path.join(__dirname, '../locale', locale + '.yml'), 'utf8');
  for (const line of source.split(/\r?\n/)) {
    const match = /^    ([a-z_]+): (".*")$/.exec(line);
    if (!match) continue;
    const key = 'el-choco-admin-console.admin.' + match[1];
    if (key in result) throw new Error('Duplicate key: ' + key);
    result[key] = JSON.parse(match[2]);
  }
  return result;
}

function makeTranslator(initial, asArray) {
  let locale = initial;
  return {
    getLocale: () => locale,
    setLocale: value => { locale = value; },
    trans(key, parameters = {}) {
      const en = translations('en');
      const language = locale.split(/[-_]/)[0];
      const local = ['en', 'de', 'es'].includes(language) ? translations(language) : {};
      const template = local[key] || en[key];
      if (template == null) throw new Error('Missing translation: ' + key);
      const value = template.replace(/\{([a-z_]+)\}/g, (_, name) => {
        if (!(name in parameters)) throw new Error('Missing parameter: ' + name);
        return String(parameters[name]);
      });
      return asArray ? [value] : value;
    }
  };
}

module.exports = { makeTranslator, translations };
