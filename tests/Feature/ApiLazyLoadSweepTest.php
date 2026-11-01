<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SweepsRoutes;
use Tests\TestCase;

class ApiLazyLoadSweepTest extends TestCase
{
    use SweepsRoutes;

    public function test_api_endpoints_have_no_lazy_loading_violations(): void
    {
        $this->ensureMemoryLimit('1024M');
        $this->sweepArmLazyLoadingGuard();

        $targets = $this->sweepTargets(prefixes: ['api/'], withParams: false);

        $this->assertNotEmpty($targets, 'no api routes discovered');

        $credentials = $this->sweepApiCredentials();
        $apiUser = User::find($credentials['auth:api']);
        $zoneId = DB::table('zones')->orderBy('id')->value('id');
        $modules = $this->sweepModuleIds();

        $violations = [];
        $errors = [];
        $skipped = [];
        $probed = 0;

        foreach ($targets as $uri => $route) {
            $guard = $this->sweepGuardFor($route);

            if ($guard !== 'public' && $guard !== 'apiGuestCheck' && empty($credentials[$guard])) {
                $skipped[$guard][] = $uri;

                continue;
            }

            $moduleScoped = in_array('module-check', $route->gatherMiddleware(), true);
            $moduleIds = $moduleScoped ? $modules : ['default' => reset($modules)];

            foreach ($moduleIds as $moduleType => $moduleId) {
                $probed++;

                $result = $this->sweepApiProbeFor($uri, $guard, [
                    'moduleId' => $moduleId,
                    'zoneId' => $zoneId,
                    'credentials' => $credentials,
                    'apiUser' => $apiUser,
                ]);

                if ($result['lazy'] !== null) {
                    $violations[] = $guard.'  '.$moduleType.'  '.$result['lazy'];
                }

                if ($result['error'] !== null) {
                    $errors[$result['error']] = true;
                }
            }
        }

        fwrite(STDERR, PHP_EOL.'probed '.$probed.' api route/module combinations from '
            .count($targets).' routes'.PHP_EOL);

        foreach ($skipped as $guard => $uris) {
            fwrite(STDERR, '  skipped '.count($uris).' '.$guard
                .' routes - no credential row exists in this database'.PHP_EOL);
        }

        $this->reportRuntimeDefects($errors);
        $this->assertNoFullUrlDefects($errors);

        $this->assertSame([], array_values(array_unique($violations)),
            'API endpoints must eager-load every relation their responses serialize.');
    }
}
