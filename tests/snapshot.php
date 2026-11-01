<?php
require __DIR__.'/../src/PackageSnapshot.php';
use ElChoco\AdminConsole\PackageSnapshot;

$root = sys_get_temp_dir().'/flarum-console-test-'.bin2hex(random_bytes(6));
mkdir($root, 0700);
mkdir($root.'/forum'); mkdir($root.'/storage');
file_put_contents($root.'/forum/composer.json', '{"require":{"fof/blog":"2.0.0-beta.5"}}');
file_put_contents($root.'/forum/composer.lock', 'LOCK-CONTENT');
file_put_contents($root.'/list', "fof/blog:2.0.0-beta.5\nfof/sitemap:*@beta\n");
$id = PackageSnapshot::create($root.'/forum', $root.'/storage', $root.'/list');
$backup = $root.'/storage/admin-console/backups/'.$id;
foreach (['composer.json', 'composer.lock'] as $name) {
    if (file_get_contents($backup.'/'.$name) !== file_get_contents($root.'/forum/'.$name)) { throw new RuntimeException('Snapshot differs: '.$name); }
}
if (file_get_contents($backup.'/extensions-list') !== file_get_contents($root.'/list')) { throw new RuntimeException('Extension list differs'); }
$failed = false;
try { PackageSnapshot::create($root.'/forum', $root.'/storage', $root.'/missing-list'); }
catch (RuntimeException $e) { $failed = true; }
if (!$failed) { throw new RuntimeException('Missing list must abort'); }
$classic = PackageSnapshot::create($root.'/forum', $root.'/storage', null);
if (!is_file($root.'/storage/admin-console/backups/'.$classic.'/composer.json')) { throw new RuntimeException('Classic snapshot failed'); }
function cleanup($path) {
    foreach (scandir($path) as $entry) {
        if ($entry === '.' || $entry === '..') { continue; }
        $child = $path.'/'.$entry;
        if (is_dir($child)) { cleanup($child); } else { unlink($child); }
    }
    rmdir($path);
}
cleanup($root);
echo "5 snapshot checks passed\n";
