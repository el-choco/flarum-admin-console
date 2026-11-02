<?php
// php tests/security-integration.php /absolute/path/to/real/flarum/vendor/autoload.php
require $argv[1];
foreach (['CommandPolicy', 'RuntimeDiagnostics', 'PackageSnapshot', 'JobStore', 'CommandCatalog', 'ConsoleController'] as $class) { require_once __DIR__.'/../src/'.$class.'.php'; }

use ElChoco\AdminConsole\ConsoleController;
use ElChoco\AdminConsole\JobStore;
use Flarum\Foundation\Paths;
use Flarum\Group\Group;
use Flarum\Http\RequestUtil;
use Flarum\Http\Middleware\CheckCsrfToken;
use Flarum\Http\Exception\TokenMismatchException;
use Flarum\User\Access\Gate;
use Flarum\User\Guest;
use Flarum\User\User;
use Flarum\User\Exception\PermissionDeniedException;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Collection;
use Laminas\Diactoros\ServerRequest;

$checks = 0;
function verify($condition, $message) { global $checks; $checks++; if (!$condition) { throw new RuntimeException($message); } }
function denied(callable $call, string $exception): bool { try { $call(); } catch (Throwable $e) { if ($e instanceof $exception) { return true; } throw $e; } return false; }
$base = sys_get_temp_dir().'/elchoco-security-'.bin2hex(random_bytes(5));
mkdir($base, 0700, true);
// Real Flarum User, Group and Gate classes; no overridden authorization methods.
User::setGate(new Gate(new Container(), []));
$member = new User(); $member->id = 2; $member->setRelation('groups', new Collection());
$adminGroup = new Group(); $adminGroup->id = Group::ADMINISTRATOR_ID;
$admin = new User(); $admin->id = 1; $admin->setRelation('groups', new Collection([$adminGroup]));
$guest = new Guest(); $guest->setAttribute('groups', new Collection());
$controller = new ConsoleController(new Paths(['base' => $base, 'public' => $base.'/public', 'storage' => $base.'/storage']));
$csrf = new CheckCsrfToken([]);
$session = new class { public function token() { return 'test-session-token'; } };
function request($actor, $method, $token = null) {
    global $session;
    $r = (new ServerRequest())->withMethod($method)->withAttribute('session', $session)->withAttribute('routeName', 'elchoco.admin-console.run');
    $r = RequestUtil::withActor($r, $actor);
    return $token === null ? $r : $r->withHeader('X-CSRF-Token', $token);
}
foreach ([$guest, $member] as $actor) {
    foreach (['GET', 'POST'] as $method) {
        $r = request($actor, $method, 'test-session-token');
        verify(denied(function () use ($csrf, $r, $controller) { $csrf->process($r, $controller); }, PermissionDeniedException::class), 'Non-admin denied '.$method);
    }
}
verify(!is_dir($base.'/storage/admin-console'), 'Unauthorized requests cannot create state');
foreach ([null, 'wrong-token'] as $token) {
    $r = request($admin, 'POST', $token)->withParsedBody(['command' => 'cache:clear', 'confirmation' => 'cache:clear', 'requestId' => str_repeat('a', 32)]);
    verify(denied(function () use ($csrf, $r, $controller) { $csrf->process($r, $controller); }, TokenMismatchException::class), 'Admin POST requires correct CSRF token');
}
verify(!is_dir($base.'/storage/admin-console'), 'CSRF failures cannot create state');
$r = request($admin, 'POST', 'test-session-token')->withParsedBody(['command' => 'cache:clear']);
verify($csrf->process($r, $controller)->getStatusCode() === 422, 'Valid CSRF reaches confirmation check');
foreach (['composer require flarum/core', 'composer remove el-choco/admin-console', 'composer remove franc/admin-console', 'info; id'] as $command) {
    $r = request($admin, 'POST', 'test-session-token')->withParsedBody(['command' => $command, 'confirmation' => $command]);
    verify($csrf->process($r, $controller)->getStatusCode() === 422, 'Malicious/self/core request rejected');
}
$store = new JobStore($base.'/storage');
$job = $store->enqueue('info', '1', 1000, str_repeat('b', 32));
foreach ([$guest, $member] as $actor) {
    $r = request($actor, 'GET')->withQueryParams(['job' => $job['id']]);
    verify(denied(function () use ($csrf, $r, $controller) { $csrf->process($r, $controller); }, PermissionDeniedException::class), 'Stored output denied to non-admin');
}
$r = request($admin, 'GET')->withQueryParams(['job' => $job['id']]);
$response = $csrf->process($r, $controller);
verify($response->getStatusCode() === 200, 'Admin reads stored job');
verify(strpos($response->getHeaderLine('Cache-Control'), 'no-store') !== false, 'Sensitive output is not cached');
$r = request($admin, 'GET')->withQueryParams(['job' => '../../config.php']);
verify($csrf->process($r, $controller)->getStatusCode() === 422, 'Job path traversal denied');
$errorData = json_decode((string) $csrf->process($r, $controller)->getBody(), true);
verify($errorData['translationKey'] === 'el-choco-admin-console.admin.error_job_id_invalid', 'API provides a translatable error key');
verify($errorData['message'] === 'Invalid job ID.', 'API retains an English fallback');
// Both actual versions must load the PHP extenders and select their own JS bundle.
$extenders = require __DIR__.'/../extend.php';
verify(count($extenders) === 3, 'Extension loads with real Flarum extenders');
$parser = new Less_Parser();
$parser->parseFile(__DIR__.'/../less/admin.less');
verify(strpos($parser->getCss(), '.AdminConsole') !== false, 'Actual installed PHP Less compiler accepts styles');
echo \Composer\InstalledVersions::getPrettyVersion('flarum/core').": $checks security integration checks passed\n";

foreach ([$guest, $member] as $actor) {
    $r = request($actor, 'GET')->withQueryParams(['summary' => '1']);
    verify(denied(function () use ($csrf, $r, $controller) { $csrf->process($r, $controller); }, PermissionDeniedException::class), 'Summary denied to non-admin');
}
$r = request($admin, 'GET')->withQueryParams(['summary' => '1']);
$data = json_decode((string) $csrf->process($r, $controller)->getBody(), true);
verify(isset($data['history']) && !array_key_exists('commands', $data), 'Lightweight summary preserves catalog and skips discovery');
echo "Summary checks passed\n";
