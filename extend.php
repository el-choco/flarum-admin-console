<?php

use Flarum\Extend;
use ElChoco\AdminConsole\ConsoleController;

$version = class_exists(\Composer\InstalledVersions::class)
    ? (\Composer\InstalledVersions::getVersion('flarum/core') ?: \Composer\InstalledVersions::getPrettyVersion('flarum/core'))
    : '1.8.0';
$v2 = preg_match('/^(?:v|dev-)?([0-9]+)\./', (string) $version, $matches) && (int) $matches[1] >= 2;

return [
    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin-v'.($v2 ? '2' : '1').'.js')
        ->css(__DIR__.'/less/admin.less'),
    (new Extend\Routes('api'))
        ->get('/admin-console', 'elchoco.admin-console.status', ConsoleController::class)
        ->post('/admin-console', 'elchoco.admin-console.run', ConsoleController::class),
];
