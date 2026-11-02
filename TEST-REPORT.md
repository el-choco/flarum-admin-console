# Validation for 0.2.0-rc.4

- 114 entries per language (English/German/Spanish), identical keys and parameter sets.
- 1,087 locale checks passed with each of actual Flarum 1.8.21 and 2.0.2 dependencies: real YAML loader, ICU validity, Flarum translator, English fallback, and matching CLI fallback text.
- 42 Node tests passed: compiled Flarum 1/2 frontend bundles, all three languages, text/array translation return values, retained worker diagnostics, original subprocess output, non-blocking status/cache checks, and stylesheet compilation.
- Policy, snapshot, asset selection, runtime/catalog, actual authorization/CSRF, durable jobs, and watch lifecycle checks passed. A Windows-only atomic rename contention occurred on the first legacy-core watch run; rerunning that test passed (1.19-second first-job completion). The v2 watch run passed on the first attempt (1.21 seconds). No production queue/file-lock behavior was changed to work around this local filesystem issue.
- PHP syntax checks and strict Composer manifest validation passed.
- Linux non-root CLI, Docker, and the GitHub workflow for rc.4 have not run locally. The supplied workflow includes the new locale checks. Live browser language switching on the user's installations remains to be confirmed after upgrade.
- rc.3 practical native and Docker results below are historical; they do not establish a live rc.4 deployment test.

## Historical rc.3 validation

## Current publication preparation

The public package is based on the locally tested 0.2.0-rc.3 source. The Composer version field was removed so Git tags determine package versions. Public documentation and worker service examples now use the installed vendor package path. Runtime code and compiled frontend assets remain unchanged from rc.3.

- Composer manifest: strict validation passed after publication metadata changes.
- Compiled frontend: 24 tests passed across Flarum 1 and 2 module bootstraps, including deliberately delayed status/HTTP checks that must not hold the command button.
- Actual Flarum dependencies: 23 authorization, CSRF, asset, and lightweight-summary checks passed with each of core 1.8.21 and 2.0.2 on Windows PHP 8.4.
- Practical maintainer feedback: console commands and extension installation work on the existing CrazyMax Flarum 2 deployment. Reloading web PHP resolved delayed queue execution caused by absent pending signals while updated source files were present. This does not establish identical behavior on other hosts.

## Earlier queue/worker validation

Before rc.3, unchanged worker functions passed local checks with actual Symfony Process dependencies from both core generations: queue persistence, locks, duplicate request IDs, interrupted jobs, UID checks, bounded output, exit status, watch wake-up, graceful stop/restart, and a 185-second job beyond the old timeout. Test identity injection was used on Windows; it is not present in the production CLI entry point.

An earlier rc.1 path package was installed in isolated core fixtures without core updates/removals. This does not count as a fresh Packagist installation of the current release.

## Still requiring target validation

The supplied GitHub Actions workflow was not run from this workstation; the maintainer reports completing the GitHub test step. It includes non-root Linux CLI and CrazyMax container tests. Native Flarum 1.8.20 / PHP 8.4.24 testing subsequently confirmed Packagist installation, activation, the separate worker, `info`, `cache:clear` with HTTP 200 admin follow-up, and `composer show el-choco/admin-console`. The Composer output confirms v0.2.0-rc.3 from GitHub commit 82d2b42b73ed4f968bb6e6bad0f8881cb2347d5e under the native vendor path. This was a new package installation in an existing forum, not a completely fresh Flarum deployment. Package installation/removal through the console on native 1.8 remains unverified. Tests of middleware objects are not complete browser/session/database end-to-end tests. Do not describe this release as independently security-audited or certified for all production environments.

The isolated legacy core fixture encountered an existing Flysystem advisory (PKSA-w9tt-7782-78jx). The workflow ignores only that advisory for the compatibility fixture, not the user's production Composer settings. Docker and systemd execution were not available on the local Windows workstation.
