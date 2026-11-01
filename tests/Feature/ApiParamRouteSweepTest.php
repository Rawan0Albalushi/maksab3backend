<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SweepsRoutes;
use Tests\TestCase;

class ApiParamRouteSweepTest extends TestCase
{
    use SweepsRoutes;

    public function test_api_param_routes_have_no_lazy_loading_violations(): void
    {
        $this->ensureMemoryLimit('1024M');
        $this->sweepArmLazyLoadingGuard();

        $targets = $this->sweepTargets(prefixes: ['api/'], withParams: true);

        $this->assertNotEmpty($targets, 'no parameterised api routes discovered');

        $credentials = $this->sweepApiCredentials();
        $apiUser = User::find($credentials['auth:api']);
        $zoneId = DB::table('zones')->orderBy('id')->value('id');
        $modules = $this->sweepModuleIds();
        $defaultModule = reset($modules);

        $violations = [];
        $errors = [];
        $unresolved = 0;
        $probed = 0;

        foreach ($targets as $route) {
            $guard = $this->sweepGuardFor($route);

            if ($guard !== 'public' && $guard !== 'apiGuestCheck' && empty($credentials[$guard])) {
                continue;
            }

            $uris = $this->sweepResolveUris($route);

            if ($uris === []) {
                $unresolved++;

                continue;
            }

            foreach ($uris as $uri) {
                $probed++;

                $result = $this->sweepApiProbeFor($uri, $guard, [
                    'moduleId' => $defaultModule,
                    'zoneId' => $zoneId,
                    'credentials' => $credentials,
                    'apiUser' => $apiUser,
                ]);

                if ($result['lazy'] !== null) {
                    $violations[] = $guard.'  '.$result['lazy'];
                }

                if ($result['error'] !== null) {
                    $errors[$result['error']] = true;
                }
            }
        }

        fwrite(STDERR, PHP_EOL.'probed '.$probed.' parameterised api uris from '
            .count($targets).' routes ('.$unresolved.' had no resolvable fixture row)'.PHP_EOL);

        $this->reportRuntimeDefects($errors);
        $this->assertNoFullUrlDefects($errors);

        $this->assertSame([], array_values(array_unique($violations)),
            'Parameterised API endpoints must eager-load every relation their responses serialize.');
    }
}
