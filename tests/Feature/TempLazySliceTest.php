<?php

namespace Tests\Feature;

use App\Models\Admin;
use Tests\Concerns\SweepsRoutes;
use Tests\TestCase;

/**
 * Slice runner for the admin lazy-load sweep. The shipped sweep cannot finish against this
 * dataset -- it exhausts the memory limit partway through -- and a fatal kills the process
 * before any report is written. Running one slice per process, and printing each URI before
 * probing it, means a slice that dies names the route that killed it.
 */
class TempLazySliceTest extends TestCase
{
    use SweepsRoutes;

    public function test_slice(): void
    {
        $this->ensureMemoryLimit(getenv('MEM') ?: '1024M');
        $this->sweepArmLazyLoadingGuard();

        $admin = Admin::find(1);
        $this->assertNotNull($admin);

        $uris = array_keys($this->sweepTargets(prefixes: ['admin/'], withParams: false));
        sort($uris);

        [$i, $n] = array_map('intval', explode('/', getenv('SLICE') ?: '1/12'));
        $size = (int) ceil(count($uris) / $n);
        $slice = array_slice($uris, ($i - 1) * $size, $size);

        $modules = $this->sweepModuleIds();
        $session = ['current_module' => reset($modules), 'login_remember_token' => $admin->login_remember_token];

        fwrite(STDERR, sprintf("SLICE %d/%d  %d of %d routes\n", $i, $n, count($slice), count($uris)));

        foreach ($slice as $uri) {
            fwrite(STDERR, '  ... '.$uri."\n");
            $result = $this->sweepProbeFor($uri, $admin, 'admin', $session);

            if ($result['lazy'] !== null) {
                fwrite(STDERR, 'LAZY  '.$result['lazy']."\n");
            }
        }

        fwrite(STDERR, sprintf("SLICE %d/%d done, peak=%dMB\n", $i, $n, memory_get_peak_usage(true) / 1048576));
        $this->assertTrue(true);
    }
}
