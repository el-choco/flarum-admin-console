# Admin Console 0.2.0

Admin Console 0.2.0 is the first stable release of `el-choco/admin-console` for Flarum 1.8 and 2.x.

- Update an individual extension from the admin dashboard with `composer update vendor/package`. The admin interface asks for confirmation and the worker saves a snapshot of the Composer files before running the update.
- Run approved maintenance commands such as `info` and `cache:clear` through a dedicated CLI worker, with saved output and job history.
- Use the console in English, German, or Spanish.

The targeted update respects the version constraint in your forum's `composer.json`. To upgrade the Admin Console itself from an exact RC version, stop its worker after active jobs finish and run the following in the forum directory as the forum user.

```bash
composer require el-choco/admin-console:"0.2.0" --update-no-dev --minimal-changes --no-scripts --no-interaction
```

On CrazyMax, run `extension sync` after Composer succeeds. Clear the Flarum cache, reload PHP-FPM if it still serves old code, then restart the worker. See the [installation and update guide](https://github.com/el-choco/flarum-admin-console/blob/v0.2.0/INSTALL.md).
