<?php
require __DIR__.'/../src/CommandPolicy.php';
require __DIR__.'/../src/CommandCatalog.php';
require __DIR__.'/../src/RuntimeDiagnostics.php';
use ElChoco\AdminConsole\CommandPolicy;
use ElChoco\AdminConsole\CommandCatalog;
use ElChoco\AdminConsole\RuntimeDiagnostics;

$checks = 0;
function expect($condition, $message) { global $checks; $checks++; if (!$condition) { throw new RuntimeException($message); } }
expect(RuntimeDiagnostics::uidFromStatus("Name:\tphp-fpm\nUid:\t0\t1000\t1000\t1000\n") === 1000, 'Use effective uid, not real uid');
expect(RuntimeDiagnostics::uidFromStatus("Uid:\t0\t0\t0\t0\n") === 0, 'Detect root');
expect(RuntimeDiagnostics::uidFromStatus('no uid') === null, 'Unknown uid remains unknown');
expect(count(RuntimeDiagnostics::warnings('assets could not be pre-built: file missing')) === 1, 'Exit zero cache warning');
expect(count(RuntimeDiagnostics::warnings('PHP Warning: file_put_contents: Permission denied')) === 1, 'Permissions warning');
expect(RuntimeDiagnostics::warnings("Cleared\ncache store\nRebuilt assets") === [], 'Normal output');
$catalog = CommandCatalog::normalize([
    ['name' => 'queue:work', 'description' => 'Long running worker'],
    ['name' => 'info', 'description' => 'Forum info'],
    ['name' => 'extension:enable', 'definition' => ['arguments' => ['extension-id' => ['name' => 'extension-id', 'is_required' => true]]]],
    ['name' => 'hidden:example', 'hidden' => true],
    ['name' => 'bad;command'],
]);
expect(count($catalog) === 3, 'Only valid visible commands');
expect($catalog[0]['template'] === 'extension:enable <extension-id>', 'Required argument template');
expect($catalog[1]['name'] === 'info' && $catalog[1]['runnable'], 'Approved command');
expect($catalog[2]['name'] === 'queue:work' && !$catalog[2]['runnable'], 'Discover but do not execute unbounded command');
expect(CommandPolicy::plan('cache:clear --force', false)['mutates'], 'Force cache flag supported');
expect(CommandPolicy::plan('extension:enable linkrobins-shoutbox', true)['mutates'], 'Enable extension supported');
$denied = false;
try { CommandPolicy::plan('extension:disable el-choco-admin-console', true); } catch (InvalidArgumentException $e) { $denied = true; }
expect($denied, 'Self disable forbidden');
expect(!CommandPolicy::plan('schedule:list', false)['mutates'], 'Scheduler list read only');
expect(RuntimeDiagnostics::normalizePath('', ['/usr/bin', '/bin'], ':') === '/usr/bin:/bin', 'Missing FPM PATH gets system defaults');
expect(RuntimeDiagnostics::normalizePath('/custom/bin:', ['/usr/bin', '/bin'], ':') === '/custom/bin:/usr/bin:/bin', 'Composer subcommands retain system shell path');
expect(RuntimeDiagnostics::normalizePath('.:vendor/bin:/bin', ['/usr/bin', '/bin'], ':') === '/bin:/usr/bin', 'No relative executable search or duplicates');
expect(RuntimeDiagnostics::normalizePath('C:\\php;', ['C:\\Windows\\System32'], ';') === 'C:\\php;C:\\Windows\\System32', 'Windows path entries');
expect(RuntimeDiagnostics::commandPath() !== '', 'Actual subprocess path populated');
echo "$checks runtime/catalog checks passed\n";
