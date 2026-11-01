<?php

namespace ElChoco\AdminConsole;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

final class CommandCatalog
{
    public static function discover(string $base): array
    {
        try {
            $path = RuntimeDiagnostics::commandPath();
            $binary = (new ExecutableFinder())->find('php', null, explode(PATH_SEPARATOR, $path));
            if (!$binary) { throw new \RuntimeException('PHP CLI nicht gefunden.'); }
            $process = new Process([$binary, $base.'/flarum', 'list', '--format=json', '--no-interaction', '--no-ansi'], $base, ['PATH' => $path]);
            $process->setTimeout(20);
            $process->run();
            $data = json_decode($process->getOutput(), true);
            if (!$process->isSuccessful() || !is_array($data['commands'] ?? null)) {
                throw new \RuntimeException('Die Befehlsliste konnte nicht gelesen werden.');
            }
            return ['commands' => self::normalize($data['commands']), 'catalogError' => null, 'version' => (string) ($data['application']['version'] ?? '')];
        } catch (Throwable $e) {
            return ['commands' => array_map(function ($name) { return ['name' => $name, 'description' => '', 'template' => $name, 'runnable' => $name !== 'help']; }, CommandPolicy::FLARUM_COMMANDS), 'catalogError' => $e->getMessage(), 'version' => ''];
        }
    }

    public static function normalize(array $commands): array
    {
        $result = [];
        foreach ($commands as $command) {
            $name = $command['name'] ?? '';
            if (!is_string($name) || !preg_match('/^[a-zA-Z0-9:_-]+$/D', $name) || !empty($command['hidden'])) { continue; }
            $template = $name;
            foreach (($command['definition']['arguments'] ?? []) as $key => $argument) {
                if (!empty($argument['is_required'])) { $template .= ' <'.($argument['name'] ?? $key).'>'; }
            }
            $result[$name] = ['name' => $name, 'description' => (string) ($command['description'] ?? ''), 'template' => $template, 'runnable' => in_array($name, CommandPolicy::FLARUM_COMMANDS, true)];
        }
        ksort($result);
        return array_values($result);
    }
}
