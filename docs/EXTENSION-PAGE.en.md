# Admin Console - run maintenance commands from your Flarum dashboard

Admin Console adds a command interface to Flarum's administration area. Run approved maintenance commands, inspect their output, and install or remove Composer packages without switching to a terminal for each action.

## Features

- Searchable command selector based on the commands installed in your forum.
- Common maintenance actions including forum information, cache clearing, and extension activation.
- Composer package installation, removal, and package information.
- A separate background worker, so closing the browser does not cancel a job.
- Saved output, exit codes, warnings, and recent job history.
- Explicit confirmation for changes and snapshots of package configuration.
- Support for native Linux deployments and the tested CrazyMax Docker setup.

## Compatibility and status

This is the first public release candidate, `0.2.0-rc.3`.

The package targets Flarum 1.8 and 2.x. Automated checks use actual Flarum 1.8.21 and 2.0.2 dependencies. Practical testing has been performed on a CrazyMax Flarum 2 installation. Full native Flarum 1.8 deployment testing remains to be completed. The interface is currently in German.

## Installation

After the release is available on Packagist, run in your Flarum directory as the normal installation user:

```bash
composer require el-choco/admin-console:"0.2.0-rc.3"
php flarum extension:enable el-choco-admin-console
php flarum cache:clear
```

**You must also configure the separate CLI worker.** The repository's INSTALL.md includes systemd examples for native and Docker installations. The permanent worker checks for jobs approximately every second. No console-worker cron entry is needed with this setup.

## What you should know

This console is for trusted administrators. It executes a defined set of commands rather than arbitrary shell input. Package installation still executes third-party code with the forum user's rights. Do not run concurrent package maintenance from other tools.

The automatic snapshots contain Composer configuration and, where applicable, the Docker image's extension list. They are not full backups and do not include your database or uploads. Keep normal backups and terminal access for recovery.

Linux, PHP CLI, subprocess support, and a worker under the same non-root UID as web PHP are required. Shared hosting without worker support is unsuitable. After package updates, stale web OPcache may require a PHP-FPM reload.

License: MIT. Feedback and reproducible issue reports are welcome. Please include your Flarum version, PHP version, deployment type, exact command, and relevant redacted logs.


[GitHub](https://github.com/el-choco/flarum-admin-console) | [Issues](https://github.com/el-choco/flarum-admin-console/issues) | [Packagist](https://packagist.org/packages/el-choco/admin-console) | [Installation](https://github.com/el-choco/flarum-admin-console/blob/main/INSTALL.md)
