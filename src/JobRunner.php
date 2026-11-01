<?php
namespace ElChoco\AdminConsole;

use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

final class JobRunner
{
    private JobStore $store;
    private string $base;
    private string $storage;
    private string $assets;
    private $identity;

    public function __construct(JobStore $store, string $base, string $storage, string $assets, ?callable $identity = null)
    {
        $this->store = $store; $this->base = $base; $this->storage = $storage; $this->assets = $assets;
        $this->identity = $identity ?: [RuntimeDiagnostics::class, 'uid'];
    }

    /** Called only while holding the installation-wide worker lock. */
    public function drain(?string $acknowledge = null): void
    {
        $uid = ($this->identity)();
        if ($uid === null || $uid === 0) { throw new RuntimeException('Worker muss unter dem erkennbaren PHP-Benutzer laufen, niemals als root.'); }
        $this->store->heartbeat();
        foreach ($this->store->all() as $job) {
            if ($job['status'] === 'running') {
                $job['status'] = 'interrupted'; $job['finishedAt'] = gmdate('c');
                $job['output'] .= "\nWorker ohne bestätigten Abschluss beendet. Ein Kindprozess könnte noch laufen. Im Terminal prüfen; keine automatische Wiederholung.";
                $this->store->save($job);
            }
        }
        if ($acknowledge !== null) {
            $job = $this->store->read($acknowledge);
            if (!$job || $job['status'] !== 'interrupted') { throw new RuntimeException('Nur unterbrochene Aufträge können bestätigt werden.'); }
            $job['status'] = 'acknowledged'; $job['finishedAt'] = gmdate('c'); $this->store->save($job);
        }
        foreach ($this->store->all() as $job) {
            if ($job['status'] === 'interrupted') { throw new RuntimeException('Unterbrochener Auftrag '.$job['id'].': erst Prozess- und Paketstatus prüfen, dann --acknowledge verwenden.'); }
        }
        foreach (array_reverse($this->store->all()) as $job) {
            if ($job['status'] === 'queued') { $this->execute($job, $uid); }
        }
        $this->store->cleanup();
    }

    private function execute(array $job, int $uid): void
    {
        $started = microtime(true); $processStarted = false;
        $helper = is_executable('/usr/local/bin/extension') && is_file('/data/extensions/list');
        $job['status'] = 'running'; $job['startedAt'] = gmdate('c'); $this->store->save($job);
        try {
            if ($job['uid'] !== $uid) { throw new RuntimeException('Worker-UID stimmt nicht mit dem Web-PHP-Benutzer überein.'); }
            $plan = CommandPolicy::plan($job['command'], $helper);
            $path = RuntimeDiagnostics::commandPath();
            $binary = $plan['executable'] === 'extension' ? '/usr/local/bin/extension' : (new ExecutableFinder())->find($plan['executable'], null, explode(PATH_SEPARATOR, $path));
            if (!$binary) { throw new RuntimeException('Programm '.$plan['executable'].' wurde nicht gefunden.'); }
            if ($plan['executable'] === 'php' && ($plan['args'][1] ?? '') === 'cache:clear') {
                foreach (RuntimeDiagnostics::inspect($this->storage, $this->assets)['checks'] as $check) {
                    if (!$check['writable']) { throw new RuntimeException($check['name'].' ist nicht beschreibbar.'); }
                }
            }
            if ($plan['mutates'] && $plan['executable'] !== 'php') {
                $job['backup'] = PackageSnapshot::create($this->base, $this->storage, $helper ? '/data/extensions/list' : null);
                $this->store->save($job);
            }
            $argv = array_merge([$binary], $plan['args']);
            if ($plan['executable'] === 'php') { $argv[1] = $this->base.'/flarum'; }
            $env = ['PATH' => $path, 'COMPOSER_NO_INTERACTION' => '1', 'COMPOSER_CACHE_DIR' => $this->store->folder().'/composer-cache', 'TERM' => 'dumb'];
            if ($helper && is_file('/data/extensions/composer-home/config.json')) { $env['COMPOSER_HOME'] = '/data/extensions/composer-home'; }
            $process = new Process($argv, $this->base, $env);
            // CLI outside HTTP: never kill Composer at an arbitrary elapsed time.
            $process->setTimeout(null);
            $process->disableOutput();
            $process->start(function ($type, $chunk) use (&$job) {
                $remaining = max(0, 262144 - strlen($job['output']));
                $job['output'] .= substr($chunk, 0, $remaining);
                if (strlen($chunk) > $remaining) { $job['truncated'] = true; }
            });
            $processStarted = true; $lastSave = 0;
            while ($process->isRunning()) {
                if (microtime(true) - $lastSave >= 1) {
                    $job['seconds'] = round(microtime(true) - $started, 2);
                    $this->store->save($job); $this->store->heartbeat(); $lastSave = microtime(true);
                }
                usleep(100000);
            }
            $job['exitCode'] = $process->wait();
            $job['status'] = $job['exitCode'] === 0 ? 'succeeded' : 'failed';
            $job['needsAutoloadRefresh'] = $plan['mutates'];
        } catch (Throwable $e) {
            $job['output'] = substr($job['output']."\n".$e->getMessage(), 0, 262144);
            $job['status'] = $processStarted ? 'interrupted' : 'failed';
            $job['exitCode'] = null; $job['needsAutoloadRefresh'] = $processStarted;
        }
        $job['seconds'] = round(microtime(true) - $started, 2); $job['finishedAt'] = gmdate('c');
        $job['warnings'] = RuntimeDiagnostics::warnings($job['output']); $job['hasWarnings'] = count($job['warnings']) > 0;
        $this->store->save($job); $this->store->heartbeat();
        if ($job['status'] === 'interrupted') { throw new RuntimeException('Auftrag unterbrochen; keine weiteren Aufträge starten.'); }
    }
}
