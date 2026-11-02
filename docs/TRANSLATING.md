# Translating Admin Console

All console UI resources live in `locale/` and are registered with Flarum's `Extend\Locales` extender. The namespace is `el-choco-admin-console.admin`. English (`en.yml`) is the source, with bundled German (`de.yml`) and Spanish (`es.yml`) resources.

Flarum language packs make languages available to the forum. These extension files provide translations for the console; they do not register complete language packs. Flarum chooses the active display locale and applies its normal fallback chain.

## Adding or changing translations

Keep the same keys in all locale files. Preserve parameters such as `{command}`, `{id}`, `{status}`, and `{error}`. Keep commands, executable names, package names, and option flags unchanged. Keys describe the message meaning; do not change keys for a wording adjustment.

Console translations are plain text, including terminal output and browser confirmations. Do not add HTML or rich-text nodes. Include complete sentences rather than concatenating sentence fragments. Locale files are UTF-8 YAML, using JSON-compatible double-quoted strings so the lightweight frontend test helper can also read them. Use `\n` for embedded line breaks, not multiline blocks.

Structured backend errors include an English `message` fallback plus `translationKey` and `translationParameters`. The CLI worker stores these fields without booting a Flarum application. The frontend resolves them when the job is viewed, so a stored diagnostic is not fixed to the language used to submit it. Command output from PHP, Composer, Flarum, and third-party extensions is never translated or parsed for substitution. Old rc.3 output remains as originally stored.

## Weblate

Shipping locale files does not automatically create a Weblate component. A project maintainer needs to create or request a component with:

- Repository: `https://github.com/el-choco/flarum-admin-console`
- Branch: `main`
- Source language: English
- Monolingual source/base file: `locale/en.yml`
- Translation file mask: `locale/*.yml`
- File format: YAML

Use the existing German and Spanish files as initial translations. Give the Weblate project its own repository/translation workflow appropriate to that instance. The existing Packagist webhook updates package metadata only; it does not connect Weblate or deploy translations.

## Validation

Run `npm test` after building both frontend bundles. Run `php tests/locales.php /path/to/flarum/vendor/autoload.php` against actual Flarum dependencies with PHP's intl extension enabled. The test checks matching locale keys, placeholders, ICU syntax, real Flarum translation/fallback, and English CLI fallbacks. New resources must also be covered by the compiled frontend tests. Update the expected entry count if adding keys.
