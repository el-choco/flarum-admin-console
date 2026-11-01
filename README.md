# Admin Console for Flarum

Run approved Flarum and Composer commands from the administration dashboard.

Package: `el-choco/admin-console` · License: MIT · UI language: German

**Release candidate:** intended for Linux installations running Flarum 1.8 or 2.x, with or without Docker. A separate CLI worker is required. This is not a general-purpose shell or a replacement for server access.

## Features

- Select commands from the installed Flarum command catalog, or enter an approved command.
- Run commands such as `info`, `cache:clear`, `list`, and extension enable/disable actions.
- Install or remove one Composer package per request, with explicit confirmation.
- Execute jobs outside the web request; closing the browser does not cancel them.
- View saved output, exit codes, warnings, and recent job history.
- Run a permanent worker that checks for new jobs approximately every second.
- Use CrazyMax's extension helper when available, to preserve its extension list.

## Requirements

- Linux and a supported Flarum 1.8 / 2.x installation.
- PHP CLI and permission to start subprocesses (`proc_open`).
- Composer on the worker's PATH, or the supported CrazyMax extension helper.
- A permanent worker running as the **same non-root UID as web PHP**.
- Private storage outside the publicly served document root.
- Server access for worker setup and recovery.

Shared hosting without CLI/background process support is not supported. Docker support has been exercised on a CrazyMax Flarum 2 installation; do not assume every container layout uses the same paths.

## Installation

After this release has been published to Packagist, run in the Flarum directory as the normal installation/PHP user:

```bash
composer require el-choco/admin-console:"0.2.0-rc.3"
php flarum extension:enable el-choco-admin-console
php flarum cache:clear
```

This is a prerelease, so use the explicit version. Do not run the maintenance commands as root.

Next, configure the worker as described in [INSTALL.md](INSTALL.md). Installation alone does not start it. Use `info` as the first test before performing package changes.

## Approved commands

Flarum: `info`, `list`, `help <command>`, `cache:clear`, `assets:publish`, `migrate`, `schedule:list`, `fof:sitemap:build`, and `extension:enable|disable <extension-id>`. A command must also exist in your installation. Other discovered commands provide a help template rather than unrestricted execution.

Composer: `composer show [vendor/package]`, `composer require vendor/package[:version]`, and `composer remove vendor/package`. The console protects its own package and Flarum core from changes through this interface. Arbitrary flags, shell expressions, and command chaining are rejected.

## Permissions and recovery

Only trusted Flarum administrators may access the console. Composer package installation can run third-party code with the forum user's permissions; the command allowlist does not make untrusted packages safe. Flarum session authentication and CSRF protection remain in effect.

Jobs and output are stored under `storage/admin-console` with private directory/file permissions. Completed output is cleaned after seven days or beyond 100 stored entries during a worker sweep. Package snapshots are retained until removed manually.

A package snapshot contains Composer files and, when applicable, the CrazyMax extension list. **It is not a database, uploads, or complete vendor backup.** There is no automatic rollback. Maintain your normal full backups.

Do not run concurrent package maintenance from SSH, Portainer, or another extension manager. Those processes do not share this console's lock. After an interrupted worker, inspect child processes and package state before acknowledging the job in the terminal. A failed extension can prevent Flarum and this console from opening; retain normal server access.

## Updates

Stop the worker after active jobs have finished, update the package from the terminal, clear the cache as the forum user, reload web PHP if it retains old OPcache bytecode, and start the worker again. See [INSTALL.md](INSTALL.md). CLI cache clearing alone does not flush the separate web PHP OPcache.

## Development and validation

```bash
npm ci
npm --prefix js ci
npm --prefix js run build
npm test
```

Commit the generated `js/dist` assets; end users do not need Node.js. The GitHub workflow checks actual Flarum 1.8.21 and 2.0.2 dependencies and the CrazyMax image. See [TEST-REPORT.md](TEST-REPORT.md) for the difference between tests performed locally and tests still requiring CI/target environments.
