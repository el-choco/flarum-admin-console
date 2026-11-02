# Admin Console 0.2.0-rc.4

This prerelease adds Flarum language resources to `el-choco/admin-console`.

- English, German, and Spanish translations: 114 keys per language.
- Translated controls, confirmations, job status, validation errors, and diagnostic labels.
- Language-neutral stored worker diagnostics, translated when viewed.
- Dates and numeric durations follow the active display locale.
- Original commands and Composer/PHP/Flarum output remain unchanged.
- The permanent worker and non-blocking post-job checks retain their existing behavior.

The appropriate Flarum language pack must be installed and enabled to select a language. Bundled extension translations do not install complete language packs. Weblate integration is documented but has not been configured.

## Updating

After this tag appears on Packagist, finish active jobs and stop the console worker. Update from the terminal as the forum installation user:

```bash
composer require el-choco/admin-console:"0.2.0-rc.4" --update-no-dev --minimal-changes --no-scripts --no-interaction
php flarum cache:clear
```

Restart the worker so it loads the new classes. Reload web PHP if OPcache retains old code, then reload the browser. For Docker, preserve the image's extension list only after successful package operations. Existing local path installations need a separate migration plan rather than blindly applying a vendor-path service template.

Read [INSTALL.md](https://github.com/el-choco/flarum-admin-console/blob/main/INSTALL.md) and [the rc.4 guide](https://github.com/el-choco/flarum-admin-console/blob/main/docs/RELEASE-RC4.md).

Local tests include compiled frontend behavior and actual Flarum 1.8.21 / 2.0.2 translation and authorization dependencies. Live language switching on target forums and the rc.4 GitHub/Linux/Docker workflow still require confirmation. Historical rc.3 native tests cover Flarum 1.8.20 installation from Packagist, activation, worker execution, info, cache clearing with HTTP 200 follow-up, and composer show. This remains a release candidate.
