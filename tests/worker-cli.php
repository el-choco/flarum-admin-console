<?php
require $argv[1];
foreach (['CommandPolicy', 'RuntimeDiagnostics', 'PackageSnapshot', 'JobStore', 'JobRunner'] as $class) { require_once __DIR__.'/../src/'.$class.'.php'; }
use ElChoco\AdminConsole\RuntimeDiagnostics;
use ElChoco\AdminConsole\JobStore;
use Symfony\Component\Process\Process;
$uid = RuntimeDiagnostics::uid();
if ($uid === null || $uid === 0) { fwrite(STDERR, "Worker-CLI-Test erfordert Linux und einen Benutzer ungleich root.\n"); exit(2); }
$base = sys_get_temp_dir().'/elchoco-cli-'.bin2hex(random_bytes(5));
mkdir($base.'/storage/cache', 0700, true); mkdir($base.'/public/assets', 0700, true); mkdir($base.'/vendor', 0700, true);
file_put_contents($base.'/vendor/autoload.php', '<?php require '.var_export(realpath($argv[1]), true).';');
file_put_contents($base.'/flarum', '<?php usleep(1500000); echo "finished-after-request-exit";');
// Enqueue in another short-lived process. It exits before the worker is started.
$producer = $base.'/producer.php';
file_put_contents($producer, '<?php require '.var_export(realpath(__DIR__.'/../src/JobStore.php'), true).'; (new \\ElChoco\\AdminConsole\\JobStore('.var_export($base.'/storage', true).'))->enqueue("info", "1", '.(int) $uid.', str_repeat("a",32));');
$requestProcess = new Process([PHP_BINARY, $producer]); $requestProcess->mustRun();
$store = new JobStore($base.'/storage');
if ($store->read(str_repeat('a',32))['status'] !== 'queued') { throw new RuntimeException('Auftrag nach Request-Ende verloren.'); }
$worker = new Process([PHP_BINARY, __DIR__.'/../bin/worker.php', '--base='.$base]);
$worker->setTimeout(20); $worker->mustRun();
$job = $store->read(str_repeat('a',32));
if ($job['status'] !== 'succeeded' || strpos($job['output'], 'finished-after-request-exit') === false) { throw new RuntimeException('CLI-Worker fehlgeschlagen: '.json_encode($job)); }
echo "2 worker CLI lifecycle checks passed (real UID $uid)\n";
