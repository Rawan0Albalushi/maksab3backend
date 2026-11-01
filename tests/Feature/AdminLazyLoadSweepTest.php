<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Database\Eloquent\Model;
use Tests\Concerns\SweepsRoutes;
use Tests\TestCase;

class AdminLazyLoadSweepTest extends TestCase
{
    use SweepsRoutes;

    public function test_admin_pages_have_no_lazy_loading_violations(): void
    {
        $this->ensureMemoryLimit('512M');
        $this->sweepArmLazyLoadingGuard();

        $admin = Admin::find(1);
        $this->assertNotNull($admin, 'admin id 1 must exist');

        $targets = $this->sweepTargets(prefixes: ['admin/'], withParams: false);

        $this->assertNotEmpty($targets, 'no admin routes discovered');

        $modules = $this->sweepModuleIds();
        $violations = [];
        $errors = [];
        $probed = 0;

        foreach ($modules as $moduleType => $moduleId) {
            $session = ['current_module' => $moduleId, 'login_remember_token' => $admin->login_remember_token];

            foreach ($targets as $uri => $route) {
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

        fwrite(STDERR, PHP_EOL.'probed '.$probed.' admin route/module combinations ('
            .count($targets).' routes x '.count($modules).' module types)'.PHP_EOL);

        $this->reportRuntimeDefects($errors);
        $this->assertNoFullUrlDefects($errors);

        $this->assertSame([], array_values(array_unique($violations)),
            'Admin pages must eager-load every relation their views render.');
    }
}
