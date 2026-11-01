<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Vendor;
use Tests\Concerns\SweepsRoutes;
use Tests\TestCase;

/**
 * Lazy-load sweep over parameterised routes, which nothing currently checks: the shipped
 * ParamRouteSweepTest aborts on the first route whose action cannot be resolved -- there are
 * 20 of those -- and a thrown sweep reports none of the routes it had already cleared.
 *
 * Each route is resolved and probed inside its own try, so a broken action is named and
 * skipped rather than ending the run. One URI per route keeps the pass tractable; the shipped
 * sweep expands every fixture value.
 */
class TempParamLazySliceTest extends TestCase
{
    use SweepsRoutes;

    public function test_slice(): void
    {
        $this->ensureMemoryLimit(getenv('MEM') ?: '1024M');
        $this->sweepArmLazyLoadingGuard();

        $prefix = getenv('PREFIX') ?: 'admin/';
        $isVendor = str_starts_with($prefix, 'vendor');

        $user = $isVendor
            ? Vendor::whereHas('stores')->orderBy('id')->first()
            : Admin::find(1);
        $this->assertNotNull($user);

        $guard = $isVendor ? 'vendor' : 'admin';
        $modules = $this->sweepModuleIds();
        // Both guards check session('login_remember_token') against the row; without it every
        // request redirects to login and the sweep silently measures nothing.
        $session = $isVendor
            ? ['login_remember_token' => $user->login_remember_token]
            : ['current_module' => reset($modules), 'login_remember_token' => $user->login_remember_token];

        $targets = $this->sweepTargets(prefixes: [$prefix], withParams: true);
        $patterns = array_keys($targets);
        sort($patterns);

        [$i, $n] = array_map('intval', explode('/', getenv('SLICE') ?: '1/8'));
        $size = (int) ceil(count($patterns) / $n);
        $slice = array_slice($patterns, ($i - 1) * $size, $size);

        fwrite(STDERR, sprintf("SLICE %d/%d  %d of %d %s routes\n", $i, $n, count($slice), count($patterns), $prefix));

        foreach ($slice as $pattern) {
            try {
                $uris = $this->sweepResolveUris($targets[$pattern]);
            } catch (\Throwable $e) {
                fwrite(STDERR, 'BROKEN  '.$pattern.'  ==>  '.class_basename($e).': '
                    .substr(preg_replace('/\s+/', ' ', $e->getMessage()), 0, 120)."\n");

                continue;
            }

            if ($uris === []) {
                continue;
            }

            $uri = $uris[0];
            fwrite(STDERR, '  ... '.$uri."\n");

            try {
                $result = $this->sweepProbeFor($uri, $user, $guard, $session);
            } catch (\Throwable $e) {
                fwrite(STDERR, 'ERROR   '.$uri.'  ==>  '.class_basename($e)."\n");

                continue;
            }

            if ($result['lazy'] !== null) {
                fwrite(STDERR, 'LAZY  '.$result['lazy']."\n");
            }
        }

        fwrite(STDERR, sprintf("SLICE %d/%d done, peak=%dMB\n", $i, $n, memory_get_peak_usage(true) / 1048576));
        $this->assertTrue(true);
    }
}
