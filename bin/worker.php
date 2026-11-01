#!/usr/bin/env php
<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$options = getopt('', ['base:', 'storage:', 'public:', 'acknowledge:', 'watch', 'stop']);
$base = isset($options['base']) ? realpath($options['base']) : false;
if (!$base || !is_file($base.'/flarum') || !is_file($base.'/vendor/autoload.php')) {
    fwrite(STDERR, "Verwendung: php worker.php --base=/absoluter/flarum/pfad [--acknowledge=AUFTRAGS-ID]\n"); exit(1);
}
require $base.'/vendor/autoload.php';
foreach (['CommandPolicy', 'RuntimeDiagnostics', 'PackageSnapshot', 'JobStore', 'JobRunner', 'WorkerLoop'] as $class) { require_once __DIR__.'/../src/'.$class.'.php'; }
use ElChoco\AdminConsole\JobStore;
use ElChoco\AdminConsole\JobRunner;
use ElChoco\AdminConsole\RuntimeDiagnostics;
use ElChoco\AdminConsole\WorkerLoop;
try {
    $uid = RuntimeDiagnostics::uid();
    if ($uid === null || $uid === 0) { throw new RuntimeException('Worker nicht als root starten. Derselbe Benutzer wie PHP-FPM ist erforderlich.'); }
    chdir($base);
    // No application boot: an enabled broken extension must not prevent reading
    // queued state. Custom site.php paths can be supplied explicitly by the operator.
    $storage = realpath($options['storage'] ?? $base.'/storage');
    $public = realpath($options['public'] ?? (is_dir($base.'/public') ? $base.'/public' : $base));
    if (!$storage || !$public) { throw new RuntimeException('Storage/Public-Pfade fehlen. Bei eigener site.php --storage und --public angeben.'); }
    $store = new JobStore($storage);
    if (array_key_exists('stop', $options)) { $store->requestStop(); exit(0); }
    $runner = new JobRunner($store, $base, $storage, $public.'/assets');
    if (array_key_exists('watch', $options)) {
        if (isset($options['acknowledge'])) { throw new RuntimeException('--acknowledge separat ohne --watch verwenden.'); }
        (new WorkerLoop($store, $runner))->run(); exit(0);
    }
    $lock = $store->lock('worker');
    if (!$lock) {
        if (isset($options['acknowledge'])) { throw new RuntimeException('Worker ist noch beschäftigt. Erst laufenden Auftrag prüfen.'); }
        exit(0);
    }
    try { $runner->drain($options['acknowledge'] ?? null); }
    finally { flock($lock, LOCK_UN); fclose($lock); }
} catch (Throwable $e) { fwrite(STDERR, $e->getMessage()."\n"); exit(1); }
