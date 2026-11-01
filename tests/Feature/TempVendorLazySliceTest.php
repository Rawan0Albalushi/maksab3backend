<?php

namespace Tests\Feature;

use App\Models\Vendor;
use Tests\Concerns\SweepsRoutes;
use Tests\TestCase;

/**
 * Lazy-load sweep for the vendor panel, which has none: tests/Feature carries an admin sweep
 * and an api sweep, so 181 param-less vendor-panel routes are checked by nothing. The first
 * violation reported from production was on one of them.
 *
 * Sliced one per process, printing each URI before probing it, so a slice that exhausts the
 * memory limit still names the route that did it -- a fatal writes no report.
 */
class TempVendorLazySliceTest extends TestCase
{
    use SweepsRoutes;

    public function test_slice(): void
    {
        $this->ensureMemoryLimit(getenv('MEM') ?: '1024M');
        $this->sweepArmLazyLoadingGuard();

        $vendor = Vendor::whereHas('stores')->orderBy('id')->first();
        $this->assertNotNull($vendor, 'a vendor owning a store must exist');

        $uris = array_keys($this->sweepTargets(prefixes: ['vendor-panel/'], withParams: false));
        sort($uris);

        [$i, $n] = array_map('intval', explode('/', getenv('SLICE') ?: '1/6'));
        $size = (int) ceil(count($uris) / $n);
        $slice = array_slice($uris, ($i - 1) * $size, $size);

        fwrite(STDERR, sprintf("SLICE %d/%d  %d of %d routes\n", $i, $n, count($slice), count($uris)));

        foreach ($slice as $uri) {
            fwrite(STDERR, '  ... '.$uri."\n");
            // VendorMiddleware compares session('login_remember_token') with the row's own and
            // logs the guard out when they differ, so an empty session redirects every probe
            // to the login page -- a sweep that never renders a page and never sees a violation.
            $result = $this->sweepProbeFor($uri, $vendor, 'vendor', ['login_remember_token' => $vendor->login_remember_token]);

            if ($result['lazy'] !== null) {
                fwrite(STDERR, 'LAZY  '.$result['lazy']."\n");
            }
        }

        fwrite(STDERR, sprintf("SLICE %d/%d done, peak=%dMB\n", $i, $n, memory_get_peak_usage(true) / 1048576));
        $this->assertTrue(true);
    }
}
