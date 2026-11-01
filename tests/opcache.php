<?php
require __DIR__.'/../src/RuntimeDiagnostics.php';
use ElChoco\AdminConsole\RuntimeDiagnostics;

if (!function_exists('opcache_compile_file') || !filter_var(ini_get('opcache.enable_cli'), FILTER_VALIDATE_BOOLEAN)) {
    echo "OPcache integration skipped: CLI OPcache unavailable\n";
    exit(0);
}
$root = sys_get_temp_dir().'/flarum-opcache-test-'.bin2hex(random_bytes(6));
mkdir($root.'/vendor/composer', 0700, true);
$file = $root.'/vendor/composer/autoload_test.php';
file_put_contents($file, '<?php return "old-mapping";');
if (!opcache_compile_file($file)) { throw new RuntimeException('Fixture could not be cached'); }
file_put_contents($file, '<?php return "new-mapping";');
$result = RuntimeDiagnostics::invalidateAutoload($root);
if ($result['failed'] || $result['invalidated'] !== 1 || (require $file) !== 'new-mapping') {
    throw new RuntimeException('Stale Composer autoload mapping was not invalidated');
}
unlink($file); rmdir($root.'/vendor/composer'); rmdir($root.'/vendor'); rmdir($root);
echo "OPcache stale-autoload integration passed\n";
