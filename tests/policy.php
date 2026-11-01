<?php
require __DIR__.'/../src/CommandPolicy.php';
use ElChoco\AdminConsole\CommandPolicy;

$checks = 0;
function check($condition, $message) { global $checks; $checks++; if (!$condition) { throw new RuntimeException($message); } }
function rejects($command) {
    try { CommandPolicy::plan($command, false); } catch (InvalidArgumentException $e) { return true; }
    return false;
}
check(CommandPolicy::plan('php flarum cache:clear', false)['mutates'], 'cache confirmation');
check(!CommandPolicy::plan('info', false)['mutates'], 'read only');
check(CommandPolicy::plan('composer remove ernestdefoe/sheaf', true)['executable'] === 'extension', 'image helper');
check(CommandPolicy::plan('composer require vendor/package:^2.0', false)['args'][1] === 'vendor/package:^2.0', 'version constraint');
check(!CommandPolicy::plan('composer show vendor/package', false)['mutates'], 'composer show');
check(CommandPolicy::plan('help cache:clear', false)['args'][2] === 'cache:clear', 'help');
foreach (['cache:clear; id', 'cache:clear && id', 'composer remove -- vendor/pkg', 'composer remove flarum/core', 'composer require el-choco/admin-console', 'composer require vendor/pkg:dev-main;id', 'composer require vendor/pkg:^2.0 --ignore-platform-reqs', 'tinker', 'migrate:reset', 'help "x', "info\nwhoami", 'composer require vendor/pkg:$(id)', 'composer remove vendor/pkg:*'] as $command) {
    check(rejects($command), 'must reject '.$command);
}
check(rejects('composer require franc/admin-console'), 'Legacy self package protected');
check(rejects('composer remove el-choco/admin-console'), 'New self package protected');
check(rejects('composer remove franc/admin-console'), 'Legacy self removal protected');
echo "$checks policy checks passed\n";
