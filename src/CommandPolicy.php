<?php

namespace ElChoco\AdminConsole;

require_once __DIR__.'/CommandMessage.php';


final class CommandPolicy
{
    public const FLARUM_COMMANDS = ['list', 'help', 'info', 'cache:clear', 'assets:publish', 'migrate', 'fof:sitemap:build', 'schedule:list', 'extension:enable', 'extension:disable'];

    /** Parse arguments without invoking a shell. */
    public static function tokenize(string $input): array
    {
        if (strlen($input) > 1500 || preg_match('/[\x00-\x1f\x7f]/', $input)) {
            throw new CommandMessage('error_input_invalid', 'Command too long or contains unsupported control characters.');
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
        if ($quote !== null) { throw new CommandMessage('error_quotes_unclosed', 'Unclosed quotation marks.'); }
        if ($started) { $tokens[] = $token; }
        if (!$tokens) { throw new CommandMessage('error_command_required', 'Enter a command.'); }
        foreach ($tokens as $part) {
            if ($part === '' || preg_match('/[;&|<>`$\\\\]/', $part)) {
                throw new CommandMessage('error_shell_unsupported', 'Shell expressions are not supported.');
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
                throw new CommandMessage('error_composer_usage', 'Composer: show [package], require package[:version], or remove package.');
            }
            $name = self::package($args[2], $verb === 'require');
            if (in_array($name, ['el-choco/admin-console', 'franc/admin-console', 'flarum/core', 'flarum/flarum'], true)) {
                throw new CommandMessage('error_protected_packages', 'The console and Flarum core cannot be changed here.');
            }
            return $imageHelper
                ? ['executable' => 'extension', 'args' => [$verb, $args[2]], 'mutates' => true]
                : ['executable' => 'composer', 'args' => [$verb, $args[2], '--update-no-dev', '--no-ansi', '--no-interaction', '--no-progress'], 'mutates' => true];
        }
        if (!in_array($first, self::FLARUM_COMMANDS, true)) {
                throw new CommandMessage('error_command_forbidden', 'This command is not allowed. Available actions: {commands}.', ['commands' => implode(', ', self::FLARUM_COMMANDS)]);
        }
        $rest = array_slice($args, 1);
        if ($first === 'help') {
            if (count($rest) !== 1 || !preg_match('/^[a-zA-Z0-9:_-]+$/', $rest[0])) {
                throw new CommandMessage('error_help_usage', 'Usage: help command-name');
            }
        } elseif (in_array($first, ['extension:enable', 'extension:disable'], true)) {
            if (count($rest) !== 1 || !preg_match('/^[a-z0-9_-]+$/D', $rest[0])) {
                throw new CommandMessage('error_extension_required', 'Specify exactly one extension ID.');
            }
            if (in_array($rest[0], ['el-choco-admin-console', 'franc-admin-console'], true)) {
                throw new CommandMessage('error_self_disable', 'The running console cannot disable itself.');
            }
        } elseif ($first === 'cache:clear' && $rest === ['--force']) {
            // Available on Flarum 2; older versions report an unsupported option.
        } elseif ($rest) {
            throw new CommandMessage('error_arguments_forbidden', 'Additional arguments are not allowed for this command.');
        }
        return ['executable' => 'php', 'args' => array_merge(['flarum'], $args, ['--no-interaction', '--no-ansi']), 'mutates' => !in_array($first, ['list', 'help', 'info', 'schedule:list'], true)];
    }

    private static function package(string $spec, bool $version = false): string
    {
        $parts = explode(':', $spec, 2);
        if (!preg_match('~^[a-z0-9][a-z0-9_.-]*/[a-z0-9][a-z0-9_.-]*$~D', $parts[0]) ||
            (isset($parts[1]) && (!$version || !preg_match('/^[a-zA-Z0-9.*^~+_=,@-]+$/D', $parts[1])))) {
            throw new CommandMessage('error_package_invalid', 'Invalid package name or version constraint. Example: vendor/package:^2.0');
        }
        return $parts[0];
    }
}
