<?php
require $argv[1];
foreach (['CommandPolicy', 'RuntimeDiagnostics', 'PackageSnapshot', 'JobStore', 'JobRunner', 'WorkerLoop'] as $class) { require_once __DIR__.'/../src/'.$class.'.php'; }
use ElChoco\AdminConsole\JobStore;
use ElChoco\AdminConsole\JobRunner;
use Symfony\Component\Process\Process;
$checks = 0;
function verify($condition, $message) { global $checks; $checks++; if (!$condition) { throw new RuntimeException($message); } }
function waitFor(callable $test, float $seconds = 8): bool {
    global $process;
    $until = microtime(true) + $seconds;
    do { if (isset($process)) { $process->isRunning(); } if ($test()) { return true; } usleep(100000); } while (microtime(true) < $until);
    return false;
}
$base = sys_get_temp_dir().'/elchoco-watch-'.bin2hex(random_bytes(5));
mkdir($base.'/storage/cache', 0700, true); mkdir($base.'/public/assets', 0700, true);
file_put_contents($base.'/flarum', '<?php echo "instant-worker-result";');
$store = new JobStore($base.'/storage');
$store->requestStop(); // An earlier service stop must not stop a new daemon.
$files = array_map(function ($class) { return realpath(__DIR__.'/../src/'.$class.'.php'); }, ['CommandPolicy', 'RuntimeDiagnostics', 'PackageSnapshot', 'JobStore', 'JobRunner', 'WorkerLoop']);
$script = '<?php require '.var_export(realpath($argv[1]), true).'; foreach ('.var_export($files, true).' as $file) { require_once $file; }'
    .'$base='.var_export($base, true).'; $store=new \\ElChoco\\AdminConsole\\JobStore($base."/storage");'
    .'$runner=new \\ElChoco\\AdminConsole\\JobRunner($store,$base,$base."/storage",$base."/public/assets",function(){return 1000;});'
    .'(new \\ElChoco\\AdminConsole\\WorkerLoop($store,$runner))->run();';
file_put_contents($base.'/daemon.php', $script);
$process = new Process([PHP_BINARY, $base.'/daemon.php']); $process->setTimeout(null); $process->start();
try {
    verify(waitFor(function () use ($store) { return $store->workerSeen() !== null; }), 'Daemon ready despite old stop marker');
    $start = microtime(true); $id = str_repeat('f',32); $store->enqueue('info','1',1000,$id);
    verify(waitFor(function () use ($store,$id) { return ($store->read($id)['status'] ?? '') === 'succeeded'; }), 'New job executes without minute cron');
    $elapsed = round(microtime(true)-$start,2);
    verify($elapsed < 5, 'Start/finish latency less than five seconds');
    verify(strpos($store->read($id)['output'],'instant-worker-result') !== false, 'Daemon output persisted');
    $id2 = str_repeat('a',32); $store->enqueue('info','1',1000,$id2);
    verify(waitFor(function () use ($store,$id2) { return ($store->read($id2)['status'] ?? '') === 'succeeded'; }), 'Same daemon executes next job');
    $stale = $store->enqueue('info','1',1000,str_repeat('b',32)); $stale['status']='running'; $store->save($stale);
    verify(waitFor(function () use ($store,$stale) { return $store->read($stale['id'])['status']==='interrupted'; }), 'Interrupted job is not rerun by daemon');
    $store->requestStop();
    verify(waitFor(function () use ($process) { return !$process->isRunning(); }), 'Stop marker ends idle daemon');
    verify($process->wait()===0, 'Graceful stop exits zero');
    (new JobRunner($store,$base,$base.'/storage',$base.'/public/assets',function(){return 1000;}))->drain($stale['id']);
    file_put_contents($base.'/flarum','<?php echo "started"; sleep(2); echo "finished-without-kill";');
    $process = new Process([PHP_BINARY,$base.'/daemon.php']); $process->setTimeout(null); $process->start();
    $id3=str_repeat('c',32); $store->enqueue('info','1',1000,$id3);
    verify(waitFor(function() use($store,$id3){return $store->read($id3)['status']==='running';}), 'Restarted daemon accepts new job');
    $store->requestStop();
    verify(waitFor(function() use($store,$id3){return $store->read($id3)['status']==='succeeded';}), 'Service stop allows active child to finish');
    verify(waitFor(function() use($process){return !$process->isRunning();}), 'Daemon exits after active child finished');
    verify($process->wait()===0, 'Busy graceful stop exits zero');
    echo "$checks watch checks passed; first job completed after $elapsed seconds\n";
} finally {
    $store->requestStop();
    waitFor(function () use ($process) { return !$process->isRunning(); }, 3);
    if ($process->isRunning()) { $process->stop(); }
    if ($process->getErrorOutput()) { fwrite(STDERR, $process->getErrorOutput()); }
}
