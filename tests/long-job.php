<?php
require $argv[1];
foreach (['CommandPolicy', 'RuntimeDiagnostics', 'PackageSnapshot', 'JobStore', 'JobRunner'] as $class) { require_once __DIR__.'/../src/'.$class.'.php'; }
use ElChoco\AdminConsole\JobStore;
use ElChoco\AdminConsole\JobRunner;
use ElChoco\AdminConsole\RuntimeDiagnostics;
$seconds = isset($argv[2]) ? (int) $argv[2] : 185;
if ($seconds < 181 || $seconds > 240) { throw new RuntimeException('Testdauer muss 181–240 Sekunden sein.'); }
$base = sys_get_temp_dir().'/elchoco-long-'.bin2hex(random_bytes(5));
mkdir($base.'/storage/cache', 0700, true); mkdir($base.'/public/assets', 0700, true);
file_put_contents($base.'/flarum', '<?php echo "started\n"; sleep('.$seconds.'); echo "finished-after-old-timeout\n";');
$store = new JobStore($base.'/storage');
// Windows has no Unix UID. Only this isolated test injects an identity; the
// production CLI entry point never accepts an identity override.
$uid = RuntimeDiagnostics::uid(); if ($uid === null || $uid === 0) { $uid = 1000; }
$job = $store->enqueue('info', '1', $uid, str_repeat('e',32));
$lock = $store->lock('worker');
try { (new JobRunner($store, $base, $base.'/storage', $base.'/public/assets', function () use ($uid) { return $uid; }))->drain(); }
finally { flock($lock, LOCK_UN); fclose($lock); }
$job = $store->read($job['id']);
if ($job['status'] !== 'succeeded' || $job['seconds'] < 180 || strpos($job['output'], 'finished-after-old-timeout') === false) { throw new RuntimeException(json_encode($job)); }
echo 'Long job survived old 180-second limit: '.$job['seconds']." seconds, exit ".$job['exitCode']."\n";
