# Worker installation and updates

Replace all example paths, container names, users, groups, and executable locations with your installation's values. This guide assumes installation from Packagist, not a local Composer path repository.

## Linux without Docker

Example: Flarum `/var/www/flarum`, web PHP user/group `www-data`.

Test once as that user:

```bash
sudo -u www-data /usr/bin/php /var/www/flarum/vendor/el-choco/admin-console/bin/worker.php --base=/var/www/flarum
```

Review `deploy/el-choco-admin-console-native.service`, then install it as a host administrator:

```bash
sudo install -m 644 /var/www/flarum/vendor/el-choco/admin-console/deploy/el-choco-admin-console-native.service /etc/systemd/system/el-choco-admin-console.service
sudo systemctl daemon-reload
sudo systemctl enable --now el-choco-admin-console.service
sudo systemctl status el-choco-admin-console.service --no-pager
```

## Existing Docker container

Example: CrazyMax container `flarum2-flarum-1`, forum `/opt/flarum`, container user `flarum`. The host requires systemd and Docker CLI. First test:

```bash
docker exec --user flarum --workdir /opt/flarum flarum2-flarum-1 php /opt/flarum/vendor/el-choco/admin-console/bin/worker.php --base=/opt/flarum
```

Copy the service template out of the installed package, review it, and install it:

```bash
docker cp flarum2-flarum-1:/opt/flarum/vendor/el-choco/admin-console/deploy/el-choco-admin-console.service ./el-choco-admin-console.service
sudo install -m 644 ./el-choco-admin-console.service /etc/systemd/system/el-choco-admin-console.service
sudo systemctl daemon-reload
sudo systemctl enable --now el-choco-admin-console.service
sudo systemctl status el-choco-admin-console.service --no-pager
```

The host's service invokes Docker; PHP inside the container runs as the non-root forum user. Persist packages using your image's supported mechanism. For CrazyMax, synchronize its extension list only after successful package operations. Do not recreate the container just to configure this service. Portainer users should manage the existing stack consistently through Portainer.

## Paths and operation

Custom storage/public paths must be supplied explicitly with `--storage=/private/storage --public=/forum/public`. Use these on both ExecStart and ExecStop. The worker does not boot your custom `site.php` to discover them.

Use the permanent `--watch` service for interactive response. Do not also schedule the console worker through cron. Flarum's separate `php flarum schedule:run` scheduler remains unchanged.

Logs:

```bash
journalctl -u el-choco-admin-console.service -n 60 --no-pager
```

Stopping the service requests a graceful stop and waits for an active command to finish. It deliberately has no fixed kill deadline for Composer. Inspect stuck processes in the terminal before intervening.

After a worker interruption, inspect the processes, Composer state, extension list, and backups. Only then acknowledge the interrupted job with the worker's `--acknowledge=JOB_ID` option without `--watch`. Acknowledgement does not rerun the command.

## Updates and web OPcache

1. Finish active jobs, then stop the service.
2. Make normal file/database backups and update the package from the terminal as the forum installation user.
3. For supported Docker images, update their package persistence list only after success.
4. Clear Flarum's cache as the forum user.
5. Reload web PHP using your deployment's normal PHP-FPM/service controls if it retains old OPcache code. A CLI invocation cannot reset the separate web PHP cache. Do not reset permissions or recreate containers for this purpose.
6. Start the worker and reload the browser. Run `info` as a test.

On the tested CrazyMax deployment, reloading PHP-FPM resolved old web code that did not create `storage/admin-console/pending.json`; jobs had otherwise waited for the worker's 60-second fallback sweep. If your image exposes `/run/service/php-fpm`, its supervised PHP-FPM service can be restarted with `s6-svc -r /run/service/php-fpm` inside the container. Verify that path for your image first. Expect brief request interruption during reload.
