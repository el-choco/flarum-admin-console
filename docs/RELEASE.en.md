# Admin Console 0.2.0-rc.3

First public release candidate of `el-choco/admin-console`, an administrator command interface for Flarum 1.8 and 2.x.

- Approved Flarum maintenance commands and single-package Composer installation/removal.
- Durable jobs executed by a separate CLI worker, with saved output and exit status.
- Permanent worker mode for responsive interactive use.
- Administrator/CSRF checks, explicit confirmation for changes, and package configuration snapshots.
- Lightweight status refresh and non-blocking cache follow-up checks.
- Worker setup guides for native Linux and the tested CrazyMax Docker deployment.

The interface is currently in German. A separately configured, non-root worker is required. Package snapshots are not full backups. Keep terminal access for recovery.

Practical testing has been performed on a CrazyMax Flarum 2 installation. Automated checks target Flarum 1.8.21 and 2.0.2; a complete native Flarum 1.8 deployment test remains outstanding. This is a prerelease.

After the tag is available on Packagist:

```bash
composer require el-choco/admin-console:"0.2.0-rc.3"
```

Then enable the extension and follow [INSTALL.md](https://github.com/el-choco/flarum-admin-console/blob/main/INSTALL.md) to configure the worker. For upgrades, finish active jobs, stop the worker, update from the terminal, clear Flarum's cache, reload web PHP if necessary to release stale OPcache bytecode, and restart the worker.
