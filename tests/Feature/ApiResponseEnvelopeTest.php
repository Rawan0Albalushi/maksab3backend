<?php

namespace Tests\Feature;

use App\Support\ApiEnvelope;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiResponseEnvelopeTest extends TestCase
{
    /**
     * The envelope is guaranteed by EnforceApiEnvelope, which is registered on the `api`
     * middleware group. A route group added without that group would answer in whatever
     * shape its controller happens to return, and nothing else would notice.
     */
    public function test_every_api_route_is_covered_by_the_envelope_middleware(): void
    {
        $router = app('router');
        $uncovered = [];
        $probed = 0;

        foreach ($router->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/') || $this->isSkippedModule($route->getActionName())) {
                continue;
            }

            $probed++;

            if (! $this->carriesEnvelopeMiddleware($route->middleware(), $router->getMiddlewareGroups())) {
                $uncovered[] = implode('|', $route->methods()).'  '.$route->uri();
            }
        }

        echo "\nchecked {$probed} api routes for envelope middleware\n";

        $this->assertGreaterThan(0, $probed, 'no api routes discovered');
        $this->assertSame([], $uncovered,
            "api routes answering outside the response envelope:\n".implode("\n", $uncovered));
    }

    /**
     * Reaching the endpoints without credentials is the point: the 401/404/422 an
     * unauthenticated call produces travels the same renderer as a success, so it proves
     * the envelope on the error paths that clients hit most.
     */
    public function test_api_endpoints_answer_in_the_envelope(): void
    {
        $failures = [];
        $probed = 0;

        foreach ($this->sampleUris() as $uri) {
            $response = $this->getJson('/'.$uri);
            $probed++;

            if ($response->getStatusCode() >= 500) {
                $failures[] = "GET $uri returned {$response->getStatusCode()}";

                continue;
            }

            $body = $response->json();

            if (! ApiEnvelope::isEnvelope($body)) {
                $keys = is_array($body) ? implode(',', array_keys($body)) : gettype($body);
                $failures[] = "GET $uri is not an envelope (keys: $keys)";
            }
        }

        echo "probed {$probed} api endpoints for envelope shape\n";

        $this->assertGreaterThan(0, $probed);
        $this->assertSame([], $failures, implode("\n", $failures));
    }

    /**
     * Expands group names by hand rather than calling gatherRouteMiddleware(): that
     * resolves each route's controller out of the container, and instantiating several
     * hundred API controllers inside one test process leaves enough state behind to fail
     * unrelated tests later in the run.
     */
    private function carriesEnvelopeMiddleware(array $middleware, array $groups, int $depth = 0): bool
    {
        if ($depth > 3) {
            return false;
        }

        foreach ($middleware as $entry) {
            if (! is_string($entry)) {
                continue;
            }

            if (str_contains($entry, 'EnforceApiEnvelope')) {
                return true;
            }

            if (isset($groups[$entry]) && $this->carriesEnvelopeMiddleware($groups[$entry], $groups, $depth + 1)) {
                return true;
            }
        }

        return false;
    }

    private function isSkippedModule(string $action): bool
    {
        return str_contains($action, 'Modules\\RideShare\\');
    }

    /**
     * One GET per api controller: enough to cover every response builder in use without
     * walking all ~700 routes.
     */
    private function sampleUris(): array
    {
        $seen = [];
        $uris = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();

            if (! str_starts_with($uri, 'api/') || ! in_array('GET', $route->methods(), true)) {
                continue;
            }

            if (str_contains($uri, '{')) {
                continue;
            }

            $action = $route->getActionName();

            if (! str_contains($action, '@') || $this->isSkippedModule($action)) {
                continue;
            }

            [$class] = explode('@', $action);

            if (isset($seen[$class])) {
                continue;
            }

            $seen[$class] = true;
            $uris[] = $uri;
        }

        return $uris;
    }
}
