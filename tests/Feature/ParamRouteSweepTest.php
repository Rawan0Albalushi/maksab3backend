<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Database\Eloquent\Model;
use Tests\Concerns\SweepsRoutes;
use Tests\TestCase;

class ParamRouteSweepTest extends TestCase
{
    use SweepsRoutes;

    public function test_admin_param_routes_have_no_lazy_loading_violations(): void
    {
        $this->ensureMemoryLimit('1024M');
        $this->sweepArmLazyLoadingGuard();

        $admin = Admin::find(1);
        $this->assertNotNull($admin, 'admin id 1 must exist');

        $targets = $this->sweepTargets(prefixes: ['admin/'], withParams: true);

        $this->assertNotEmpty($targets, 'no admin param routes discovered');

        $modules = $this->sweepModuleIds();
        $violations = [];
        $errors = [];
        $unresolved = [];
        $probed = 0;

        foreach ($modules as $moduleType => $moduleId) {
            $session = ['current_module' => $moduleId, 'login_remember_token' => $admin->login_remember_token];

            foreach ($targets as $pattern => $route) {
                $uris = $this->sweepResolveUris($route);

                if ($uris === []) {
                    $unresolved[] = $pattern;

                    continue;
                }

                foreach ($uris as $uri) {
                    $probed++;
                    $result = $this->sweepProbeFor($uri, $admin, 'admin', $session);

                    if ($result['lazy'] !== null) {
                        $violations[] = $moduleType.'  '.$result['lazy'];
                    }

                    if ($result['error'] !== null) {
                        $errors[$result['error']] = true;
                    }
                }
            }
        }

        $unresolved = array_values(array_unique($unresolved));

        fwrite(STDERR, PHP_EOL.'probed '.$probed.' admin param route/module combinations across '
            .count($modules).' module types'.PHP_EOL);

        foreach ($unresolved as $pattern) {
            fwrite(STDERR, '  unresolved (no seed row for its parameters): '.$pattern.PHP_EOL);
        }

        $this->reportRuntimeDefects($errors);
        $this->assertNoFullUrlDefects($errors);

        $this->assertSame([], array_values(array_unique($violations)),
            'Admin param pages must eager-load every relation their views render.');
    }
}
