<?php
require $argv[1];
require_once __DIR__.'/../src/ConsoleMessage.php';
require_once __DIR__.'/../src/CommandMessage.php';
require_once __DIR__.'/../src/CommandPolicy.php';

use ElChoco\AdminConsole\ConsoleMessage;
use ElChoco\AdminConsole\CommandPolicy;
use Flarum\Locale\Translator;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Yaml\Yaml;

$checks = 0;
function verify($condition, $label) { global $checks; $checks++; if (!$condition) { throw new RuntimeException($label); } }
$resources = [];
foreach (['en', 'de', 'es'] as $locale) {
    $data = Yaml::parse(file_get_contents(__DIR__.'/../locale/'.$locale.'.yml'));
    $resources[$locale] = $data['el-choco-admin-console']['admin'];
    verify(count($resources[$locale]) === 114, 'All locale entries loaded by real YAML parser');
    verify(array_keys($resources['en']) === array_keys($resources[$locale]), 'Identical keys and ordering');
    foreach ($resources[$locale] as $key => $text) {
        verify(is_string($text) && trim($text) !== '', 'Nonempty translation '.$key);
        preg_match_all('/\{([a-z_]+)\}/', $resources['en'][$key], $english);
        preg_match_all('/\{([a-z_]+)\}/', $text, $translated);
        sort($english[1]); sort($translated[1]);
        verify($english[1] === $translated[1], 'Same parameters '.$locale.' '.$key);
        if (class_exists('MessageFormatter')) {
            verify(new MessageFormatter($locale, $text) !== false, 'Valid ICU '.$locale.' '.$key);
        }
    }
}

$translator = new Translator('en');
$translator->addLoader('array', new ArrayLoader());
foreach ($resources as $locale => $strings) {
    $flat = [];
    foreach ($strings as $key => $value) { $flat['el-choco-admin-console.admin.'.$key] = $value; }
    $translator->addResource('array', $flat, $locale, 'messages+intl-icu');
}
$translator->setFallbackLocales(['en']);
foreach (['en' => 'Completed', 'de' => 'Abgeschlossen', 'es' => 'Finalizada'] as $locale => $expected) {
    $translator->setLocale($locale);
    verify($translator->trans('el-choco-admin-console.admin.status_succeeded') === $expected, 'Actual Flarum translator '.$locale);
    $value = $translator->trans('el-choco-admin-console.admin.job_status', ['id' => 'abc123', 'status' => $expected]);
    verify(strpos($value, 'abc123') !== false && strpos($value, $expected) !== false, 'Actual ICU parameters '.$locale);
}
$translator->setLocale('fr');
verify($translator->trans('el-choco-admin-console.admin.status_succeeded') === 'Completed', 'English fallback');

try { CommandPolicy::plan('info; id', false); throw new RuntimeException('Policy failed'); }
catch (InvalidArgumentException $error) {
    $payload = ConsoleMessage::payload($error);
    verify(($payload['translationKey'] ?? '') === 'el-choco-admin-console.admin.error_shell_unsupported', 'Policy exception retains its contract and translation key');
    verify($payload['message'] === $resources['en']['error_shell_unsupported'], 'English fallback for CLI');
}
foreach (glob(__DIR__.'/../src/*.php') as $file) {
    $source = file_get_contents($file);
    preg_match_all('/new (?:ConsoleMessage|CommandMessage)\(\'([a-z_]+)\', (\'(?:\\\\.|[^\'\\\\])*\')/', $source, $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
        $fallback = str_replace(["\\'", "\\\\"], ["'", "\\"], substr($match[2], 1, -1));
        verify(($resources['en'][$match[1]] ?? null) === $fallback, 'CLI fallback equals English locale: '.$match[1]);
    }
}
echo "$checks locale checks passed with ".\Composer\InstalledVersions::getPrettyVersion('flarum/core')."\n";
