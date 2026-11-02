# Changelog

## 0.2.0-rc.4 - prepared for release

- Register Flarum locale resources for English, German, and Spanish (114 keys each).
- Translate console controls, confirmations, statuses, validation errors, and diagnostic labels.
- Persist language-neutral worker diagnostics and translate them when viewed.
- Use the active locale for history dates and numeric durations.
- Preserve raw subprocess output and command syntax.
- Add locale parity, ICU, English fallback, and compiled frontend language checks.
- Keep the permanent worker, lightweight status refresh, and non-blocking cache checks.

This tag has not yet been published. Stop and restart the worker when updating and reload web PHP if OPcache retains old code.

## 0.2.0-rc.3 - 2026-11-01

- Private persistent job queue and separate CLI worker.
- Permanent watch mode with a one-second wake-up interval.
- Flarum 1.8 and 2.x frontend bundles.
- Approved Flarum and Composer commands with administrator checks.
- Confirmation for changes, package snapshots, and interrupted-job handling.
- Lightweight post-job status refresh; no repeated command catalog subprocess.
- Non-blocking cache follow-up check and 0.5-second frontend polling.
- Documented web OPcache reload after upgrades.

Published as the first public prerelease. Test scope and outstanding validation are documented in TEST-REPORT.md.
