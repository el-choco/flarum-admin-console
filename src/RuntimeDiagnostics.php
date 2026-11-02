<?php

namespace ElChoco\AdminConsole;

final class RuntimeDiagnostics
{
    public static function commandPath(): string
    {
        $defaults = DIRECTORY_SEPARATOR === '/'
            ? ['/usr/local/sbin', '/usr/local/bin', '/usr/sbin', '/usr/bin', '/sbin', '/bin']
            : [dirname(PHP_BINARY), (getenv('SystemRoot') ?: 'C:\\Windows').'\\System32', getenv('SystemRoot') ?: 'C:\\Windows'];
        return self::normalizePath((string) getenv('PATH'), $defaults, PATH_SEPARATOR);
    }

    public static function normalizePath(string $inherited, array $defaults, string $separator): string
    {
        $paths = [];
        foreach (array_merge(explode($separator, $inherited), $defaults) as $path) {
            // Never add the working directory through an empty or relative entry.
            if ($path !== '' && preg_match('~^(?:/|[A-Za-z]:[\\\\/])~', $path) && !in_array($path, $paths, true)) {
                $paths[] = $path;
            }
        }
        return implode($separator, $paths);
    }

    public static function uid(): ?int
    {
        if (function_exists('posix_geteuid')) { return posix_geteuid(); }
        $status = @file_get_contents('/proc/self/status');
        return is_string($status) ? self::uidFromStatus($status) : null;
    }

    public static function uidFromStatus(string $status): ?int
    {
        return preg_match('/^Uid:\s+\d+\s+(\d+)/m', $status, $matches) ? (int) $matches[1] : null;
    }

    public static function inspect(string $storage, string $assets): array
    {
        $paths = ['Cache' => $storage.'/cache', 'Assets' => $assets];
        $checks = [];
        foreach ($paths as $label => $path) {
            clearstatcache(true, $path);
            $checks[] = ['name' => $label, 'translationKey' => 'el-choco-admin-console.admin.check_'.strtolower($label), 'writable' => is_dir($path) && is_writable($path)];
        }
        if (is_dir($storage.'/cache')) {
            try {
                $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($storage.'/cache', \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
                $count = 0;
                foreach ($items as $item) {
                    if (++$count > 2000) { break; }
                    if ($item->isDir() && !$item->isLink() && !is_writable($item->getPathname())) {
                        $checks[] = ['name' => 'Cache subdirectory '.substr($item->getPathname(), strlen($storage.'/cache/')), 'translationKey' => 'el-choco-admin-console.admin.check_cache_directory', 'translationParameters' => ['path' => substr($item->getPathname(), strlen($storage.'/cache/'))], 'writable' => false];
                        if (count($checks) >= 7) { break; }
                    }
                }
            } catch (\UnexpectedValueException $e) {
                $checks[] = ['name' => 'Cache directory tree (not fully readable)', 'translationKey' => 'el-choco-admin-console.admin.check_cache_tree', 'writable' => false];
            }
        }
        return ['uid' => self::uid(), 'checks' => $checks];
    }

    public static function warnings(string $output): array
    {
        $warnings = [];
        foreach (preg_split('/\r?\n/', $output) as $line) {
            if (preg_match('/could not be pre-built|permission denied|failed to open stream|PHP (?:Warning|Fatal error)|could not clear contents|Fatal error:|Warning:/i', $line)) {
                $warnings[] = trim(substr($line, 0, 700));
            }
        }
        return array_slice(array_values(array_unique($warnings)), 0, 12);
    }

    public static function invalidateAutoload(string $base): array
    {
        $enabled = filter_var(ini_get('opcache.enable'), FILTER_VALIDATE_BOOLEAN);
        $report = ['enabled' => $enabled, 'revalidateSeconds' => (int) ini_get('opcache.revalidate_freq'), 'invalidated' => 0, 'failed' => []];
        if (!$enabled) { return $report; }
        if (!function_exists('opcache_invalidate')) {
            $report['failed'][] = 'opcache_invalidate is not available.';
            return $report;
        }
        // Run in the web request, not the CLI child: each SAPI has its own cache.
        $files = array_merge([$base.'/vendor/autoload.php'], glob($base.'/vendor/composer/autoload_*.php') ?: [], [$base.'/vendor/composer/installed.php']);
        clearstatcache(true);
        foreach ($files as $file) {
            if (!is_file($file)) { continue; }
            if (function_exists('opcache_is_script_cached') && !@opcache_is_script_cached($file)) { continue; }
            if (@opcache_invalidate($file, true)) { $report['invalidated']++; }
            else { $report['failed'][] = basename($file); }
        }
        return $report;
    }
}
