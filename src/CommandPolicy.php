<?php

namespace ElChoco\AdminConsole;

use InvalidArgumentException;

final class CommandPolicy
{
    public const FLARUM_COMMANDS = ['list', 'help', 'info', 'cache:clear', 'assets:publish', 'migrate', 'fof:sitemap:build', 'schedule:list', 'extension:enable', 'extension:disable'];

    /** Parse arguments without invoking a shell. */
    public static function tokenize(string $input): array
    {
        if (strlen($input) > 1500 || preg_match('/[\x00-\x1f\x7f]/', $input)) {
            throw new InvalidArgumentException('Befehl zu lang oder unzulässige Steuerzeichen.');
        }
        $tokens = []; $token = ''; $quote = null; $started = false;
        for ($i = 0, $n = strlen($input); $i < $n; $i++) {
            $c = $input[$i];
            if ($quote !== null) {
                if ($c === $quote) { $quote = null; } else { $token .= $c; }
            } elseif ($c === '"' || $c === "'") {
                $quote = $c; $started = true;
            } elseif ($c === ' ') {
                if ($started) { $tokens[] = $token; $token = ''; $started = false; }
            } else { $token .= $c; $started = true; }
        }
        if ($quote !== null) { throw new InvalidArgumentException('Anführungszeichen nicht geschlossen.'); }
        if ($started) { $tokens[] = $token; }
        if (!$tokens) { throw new InvalidArgumentException('Bitte einen Befehl eingeben.'); }
        foreach ($tokens as $part) {
            if ($part === '' || preg_match('/[;&|<>`$\\\\]/', $part)) {
                throw new InvalidArgumentException('Shell-Ausdrücke werden nicht unterstützt.');
            }
        }
        return $tokens;
    }

    public static function plan(string $input, bool $imageHelper): array
    {
        $args = self::tokenize(trim($input));
        if (array_slice($args, 0, 2) === ['php', 'flarum']) { $args = array_slice($args, 2); }
        elseif (($args[0] ?? '') === 'flarum') { array_shift($args); }
        $first = $args[0] ?? '';
        if ($first === 'composer') {
            $verb = $args[1] ?? '';
            if ($verb === 'show' && count($args) <= 3) {
                if (isset($args[2])) { self::package($args[2]); }
                return ['executable' => 'composer', 'args' => array_merge(array_slice($args, 1), ['--no-ansi', '--no-interaction']), 'mutates' => false];
            }
            if (!in_array($verb, ['require', 'remove'], true) || count($args) !== 3) {
                throw new InvalidArgumentException('Composer: show [paket], require paket[:version] oder remove paket.');
            }
            $name = self::package($args[2], $verb === 'require');
            if (in_array($name, ['el-choco/admin-console', 'franc/admin-console', 'flarum/core', 'flarum/flarum'], true)) {
                throw new InvalidArgumentException('Konsole und Flarum-Core werden hier nicht verändert.');
            }
            return $imageHelper
                ? ['executable' => 'extension', 'args' => [$verb, $args[2]], 'mutates' => true]
                : ['executable' => 'composer', 'args' => [$verb, $args[2], '--update-no-dev', '--no-ansi', '--no-interaction', '--no-progress'], 'mutates' => true];
        }
        if (!in_array($first, self::FLARUM_COMMANDS, true)) {
                throw new InvalidArgumentException('Dieser Befehl ist nicht freigegeben. Verfügbare Aktionen: '.implode(', ', self::FLARUM_COMMANDS).'.');
        }
        $rest = array_slice($args, 1);
        if ($first === 'help') {
            if (count($rest) !== 1 || !preg_match('/^[a-zA-Z0-9:_-]+$/', $rest[0])) {
                throw new InvalidArgumentException('Verwendung: help befehlsname');
            }
        } elseif (in_array($first, ['extension:enable', 'extension:disable'], true)) {
            if (count($rest) !== 1 || !preg_match('/^[a-z0-9_-]+$/D', $rest[0])) {
                throw new InvalidArgumentException('Bitte genau eine Erweiterungs-ID angeben.');
            }
            if (in_array($rest[0], ['el-choco-admin-console', 'franc-admin-console'], true)) {
                throw new InvalidArgumentException('Die laufende Konsole kann sich nicht selbst deaktivieren.');
            }
        } elseif ($first === 'cache:clear' && $rest === ['--force']) {
            // Available on Flarum 2; older versions report an unsupported option.
        } elseif ($rest) {
            throw new InvalidArgumentException('Für diesen Befehl sind keine zusätzlichen Argumente freigegeben.');
        }
        return ['executable' => 'php', 'args' => array_merge(['flarum'], $args, ['--no-interaction', '--no-ansi']), 'mutates' => !in_array($first, ['list', 'help', 'info', 'schedule:list'], true)];
    }

    private static function package(string $spec, bool $version = false): string
    {
        $parts = explode(':', $spec, 2);
        if (!preg_match('~^[a-z0-9][a-z0-9_.-]*/[a-z0-9][a-z0-9_.-]*$~D', $parts[0]) ||
            (isset($parts[1]) && (!$version || !preg_match('/^[a-zA-Z0-9.*^~+_=,@-]+$/D', $parts[1])))) {
            throw new InvalidArgumentException('Ungültiger Paketname oder Versionsbereich. Beispiel: vendor/paket:^2.0');
        }
        return $parts[0];
    }
}
