<?php
namespace Composer {
    class InstalledVersions {
        public static string $version = '1.8.20.0';
        public static function getVersion($package) { return self::$version; }
        public static function getPrettyVersion($package) { return self::$version; }
    }
}
namespace Flarum\Extend {
    class Locales { public function __construct($directory) {} }
    class Frontend {
        public string $script = '';
        public function __construct($name) {}
        public function js($path) { $this->script = $path; return $this; }
        public function css($path) { return $this; }
    }
    class Routes {
        public function __construct($name) {}
        public function get(...$args) { return $this; }
        public function post(...$args) { return $this; }
    }
}
namespace {
    foreach (['1.8.20.0' => 1, '2.0.2.0' => 2, '2.0.0.0-beta5' => 2, '2.x-dev' => 2, 'dev-2.x' => 2] as $version => $target) {
        \Composer\InstalledVersions::$version = $version;
        $extenders = require __DIR__.'/../extend.php';
        $script = $extenders[0]->script;
        if (basename($script) !== 'admin-v'.$target.'.js' || !is_file($script)) { throw new \RuntimeException('Asset selection failed for '.$version); }
    }
    echo "5 asset-selection checks passed\n";
}
