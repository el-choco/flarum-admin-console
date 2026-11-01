# Validation report

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

The supplied GitHub Actions workflow has not been run from this workstation. It includes real non-root Linux CLI and CrazyMax container tests. A full native Flarum 1.8 browser/database deployment test, independent clean Packagist install, and worker service setup must still be verified. Tests of middleware objects are not complete browser/session/database end-to-end tests. Do not describe this release as independently security-audited or certified for all production environments.

The isolated legacy core fixture encountered an existing Flysystem advisory (PKSA-w9tt-7782-78jx). The workflow ignores only that advisory for the compatibility fixture, not the user's production Composer settings. Docker and systemd execution were not available on the local Windows workstation.
