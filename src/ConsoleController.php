<?php
namespace ElChoco\AdminConsole;

use Flarum\Foundation\Paths;
use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

class ConsoleController implements RequestHandlerInterface
{
    private Paths $paths;
    public function __construct(Paths $paths) { $this->paths = $paths; }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertPermission($actor->isAdmin());
        $actor->assertAdmin();
        if (!in_array($request->getMethod(), ['GET', 'POST'], true)) { return $this->respond(['message' => 'Methode nicht erlaubt.'], 405); }
        try { $store = new JobStore($this->paths->storage); }
        catch (Throwable $e) { return $this->respond(['message' => $e->getMessage()], 503); }
        if ($request->getMethod() === 'GET') {
            $query = $request->getQueryParams();
            if (isset($query['job'])) {
                try { $job = is_string($query['job']) ? $store->read($query['job']) : null; }
                catch (Throwable $e) { return $this->respond(['message' => $e->getMessage()], 422); }
                if (!$job) { return $this->respond(['message' => 'Auftrag nicht gefunden.'], 404); }
                if ($job['needsAutoloadRefresh']) { $job['opcache'] = RuntimeDiagnostics::invalidateAutoload($this->paths->base); }
                return $this->respond($job);
            }
            $jobs = $store->all(); $active = null;
            foreach ($jobs as $job) {
                if (in_array($job['status'], ['queued', 'running', 'interrupted'], true)) { $active = $job['id']; break; }
            }
            if (($query['summary'] ?? null) === '1') {
                return $this->respond(['history' => $store->history(), 'activeJob' => $active,
                    'latestJob' => $jobs[0]['id'] ?? null, 'workerSeen' => $store->workerSeen()]);
            }
            $uid = RuntimeDiagnostics::uid();
            $catalog = $active || $uid === 0 || $uid === null
                ? ['commands' => [], 'catalogError' => null, 'version' => ''] : CommandCatalog::discover($this->paths->base);
            $helper = is_executable('/usr/local/bin/extension') && is_file('/data/extensions/list');
            return $this->respond(array_merge($catalog, RuntimeDiagnostics::inspect($this->paths->storage, $this->paths->public.'/assets'),
                ['history' => $store->history(), 'imageHelper' => $helper, 'activeJob' => $active,
                    'latestJob' => $jobs[0]['id'] ?? null, 'workerSeen' => $store->workerSeen(), 'consoleVersion' => '0.2.0-rc.3']));
        }
        $body = $request->getParsedBody();
        $command = is_array($body) && is_string($body['command'] ?? null) ? trim($body['command']) : '';
        $helper = is_executable('/usr/local/bin/extension') && is_file('/data/extensions/list');
        try { $plan = CommandPolicy::plan($command, $helper); }
        catch (\InvalidArgumentException $e) { return $this->respond(['message' => $e->getMessage()], 422); }
        if ($plan['mutates'] && ($body['confirmation'] ?? null) !== $command) {
            return $this->respond(['message' => 'Änderung muss ausdrücklich bestätigt werden.'], 422);
        }
        $uid = RuntimeDiagnostics::uid();
        if ($uid === null || $uid === 0) { return $this->respond(['message' => 'Der Web-PHP-Benutzer muss erkennbar sein und darf nicht root sein.'], 503); }
        try {
            $job = $store->enqueue($command, (string) $actor->id, $uid, is_string($body['requestId'] ?? null) ? $body['requestId'] : '');
        } catch (Throwable $e) { return $this->respond(['message' => $e->getMessage()], 409); }
        return $this->respond($job, 202);
    }
    private function respond(array $data, int $status = 200): ResponseInterface
    {
        return new JsonResponse($data, $status, ['Cache-Control' => 'no-store, private', 'Pragma' => 'no-cache']);
    }

}
