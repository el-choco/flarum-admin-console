<?php

namespace ElChoco\AdminConsole;

use RuntimeException;

final class PackageSnapshot
{
    public static function create(string $base, string $storage, ?string $extensionList): string
    {
        $id = gmdate('Ymd-His').'-'.bin2hex(random_bytes(4));
        $destination = $storage.'/admin-console/backups/'.$id;
        if (!@mkdir($destination, 0700, true) && !is_dir($destination)) {
            throw new RuntimeException('Sicherung konnte nicht angelegt werden. Paketänderung wurde nicht gestartet.');
        }
        $sources = ['composer.json' => $base.'/composer.json'];
        if (is_file($base.'/composer.lock')) { $sources['composer.lock'] = $base.'/composer.lock'; }
        if ($extensionList !== null) {
            if (!is_file($extensionList)) { throw new RuntimeException('Die bisherige Erweiterungsliste fehlt. Paketänderung wurde nicht gestartet.'); }
            $sources['extensions-list'] = $extensionList;
        }
        foreach ($sources as $name => $source) {
            if (!@copy($source, $destination.'/'.$name)) {
                throw new RuntimeException('Sicherung von '.$name.' fehlgeschlagen. Paketänderung wurde nicht gestartet.');
            }
            @chmod($destination.'/'.$name, 0600);
        }
        return $id;
    }
}
