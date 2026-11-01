<?php
require $argv[1];
foreach (['CommandPolicy', 'RuntimeDiagnostics', 'PackageSnapshot', 'JobStore', 'JobRunner'] as $class) { require_once __DIR__.'/../src/'.$class.'.php'; }
use ElChoco\AdminConsole\JobStore;
use ElChoco\AdminConsole\JobRunner;
$checks = 0;
function verify($condition, $message) { global $checks; $checks++; if (!$condition) { throw new RuntimeException($message); } }
function rejects(callable $call): bool { try { $call(); } catch (RuntimeException $e) { return true; } return false; }
$base = sys_get_temp_dir().'/elchoco-jobs-'.bin2hex(random_bytes(5));
mkdir($base.'/storage/cache', 0700, true); mkdir($base.'/public/assets', 0700, true);
file_put_contents($base.'/flarum', '<?php usleep(1100000); echo "<script>plain output</script>";');
$store = new JobStore($base.'/storage');
$id = str_repeat('a', 32);
$job = $store->enqueue('info', '1', 1000, $id);
verify($job['status'] === 'queued', 'Enqueue returns durable queued job');
verify((new JobStore($base.'/storage'))->read($id)['command'] === 'info', 'New request reads durable state');
verify($store->enqueue('info', '1', 1000, $id)['id'] === $id && count($store->all()) === 1, 'Repeated POST is idempotent');
verify(rejects(function () use ($store, $id) { $store->enqueue('list', '1', 1000, $id); }), 'ID reuse with other command denied');
verify(rejects(function () use ($store, $id) { $store->enqueue('info', '2', 1000, $id); }), 'ID reuse with other actor denied');
verify(rejects(function () use ($store) { $store->enqueue('info', '1', 1000, str_repeat('b', 32)); }), 'Second active job denied');
verify(rejects(function () use ($store) { $store->read('../config'); }), 'Traversal denied');
verify(!isset($store->history()[0]['output']), 'History omits sensitive output');
$lock = $store->lock('worker'); verify($lock !== null, 'Worker takes lock');
verify($store->lock('worker') === null, 'Second worker excluded');
$runner = new JobRunner($store, $base, $base.'/storage', $base.'/public/assets', function () { return 1000; });
$runner->drain();
flock($lock, LOCK_UN); fclose($lock);
$done = $store->read($id);
verify($done['status'] === 'succeeded' && $done['exitCode'] === 0, 'Real CLI child finished');
verify(strpos($done['output'], '<script>plain output</script>') !== false, 'Real child output persisted');
verify($store->workerSeen() !== null, 'Worker heartbeat persisted');
verify($store->enqueue('info', '1', 1000, $id)['status'] === 'succeeded', 'Retry after completion does not rerun');
$second = $store->enqueue('info', '1', 1000, str_repeat('b', 32));
$second['status'] = 'running'; $store->save($second);
verify(rejects(function () use ($runner) { $runner->drain(); }), 'Stale running job blocks automatic restart');
verify($store->read($second['id'])['status'] === 'interrupted', 'Crash becomes explicit interrupted state');
$runner->drain($second['id']);
verify($store->read($second['id'])['status'] === 'acknowledged', 'Manual acknowledgment preserves record');
$third = $store->enqueue('info', '1', 999, str_repeat('c', 32)); $runner->drain();
verify($store->read($third['id'])['status'] === 'failed', 'Wrong UID prevents child execution');
verify(rejects(function () use ($store, $base) { (new JobRunner($store, $base, $base.'/storage', $base.'/public/assets', function () { return 0; }))->drain(); }), 'Root worker denied');
verify(rejects(function () use ($store, $base) { (new JobRunner($store, $base, $base.'/storage', $base.'/public/assets', function () { return null; }))->drain(); }), 'Unknown worker UID denied');
file_put_contents($base.'/flarum', '<?php echo str_repeat("x", 400000); exit(7);');
$fourth = $store->enqueue('info', '1', 1000, str_repeat('d', 32)); $runner->drain(); $fourth = $store->read($fourth['id']);
verify($fourth['status'] === 'failed' && $fourth['exitCode'] === 7, 'Nonzero exit remains failed');
verify(strlen($fourth['output']) === 262144 && $fourth['truncated'], 'Output and process buffers bounded');
$fourth['finishedAt'] = gmdate('c', time()-8*86400); $store->save($fourth); $store->cleanup();
verify($store->read($fourth['id']) === null, 'Old finished output expires');
echo "$checks durable-job checks passed\n";
