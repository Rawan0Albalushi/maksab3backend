<?php

namespace Tests\Feature;

use App\Models\Vendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SweepsRoutes;
use Tests\TestCase;

class ModulePanelSweepTest extends TestCase
{
    use SweepsRoutes;

    public function test_module_provider_panels_have_no_lazy_loading_violations(): void
    {
        $this->ensureMemoryLimit('1024M');
        $this->sweepArmLazyLoadingGuard();

        $probedTotal = 0;
        $violations = [];
        $unresolved = [];
        $skipped = [];

        foreach (['rental', 'service'] as $moduleType) {
            $vendor = $this->providerVendorFor($moduleType);

            if ($vendor === null) {
                $skipped[] = $moduleType;

                continue;
            }

            $session = ['login_remember_token' => $vendor->login_remember_token];

            foreach ([false, true] as $withParams) {
                $targets = $this->sweepTargets(
                    prefixes: ['vendor-panel/'],
                    withParams: $withParams,
                    namespace: 'Modules\\'
                );

                foreach ($targets as $pattern => $route) {
                    $uri = $withParams ? $this->sweepResolveUri($route) : $pattern;

                    if ($uri === null) {
                        $unresolved[] = $pattern;

                        continue;
                    }

                    $probedTotal++;
                    $violation = $this->sweepProbe($uri, $vendor, 'vendor', $session)['lazy'] ?? null;

                    if ($violation !== null) {
                        $violations[] = $violation;
                    }
                }
            }
        }

        fwrite(STDERR, PHP_EOL.'probed '.$probedTotal.' module provider routes'.PHP_EOL);

        foreach (array_unique($unresolved) as $pattern) {
            fwrite(STDERR, '  unresolved (no seed row for its parameters): '.$pattern.PHP_EOL);
        }

        foreach ($skipped as $moduleType) {
            fwrite(STDERR, '  skipped (no active provider seeded): '.$moduleType.PHP_EOL);
        }

        $this->assertNotSame(0, $probedTotal, 'no module provider routes were probed');

        $this->assertSame([], array_values(array_unique($violations)),
            'Module provider pages must eager-load every relation their views render.');
    }

    /**
     * A switched-on vendor owning a store of this module type.
     *
     * The token is deliberately NOT required to be set. VendorMiddleware compares the session
     * value against the vendor's own with !==, so a vendor that has never logged in matches on
     * null === null and gets through -- and the caller seeds the session from this same row, so
     * the two always agree. Demanding a non-null token excluded all 15 rental and service
     * vendors in this dataset, which skipped both module types, left probedTotal at zero and
     * failed the sweep on "no module provider routes were probed" -- a sweep reporting a dataset
     * gap as a defect while covering nothing.
     */
    private function providerVendorFor(string $moduleType): ?Vendor
    {
        $vendorId = DB::table('vendors')
            ->join('stores', 'stores.vendor_id', '=', 'vendors.id')
            ->join('modules', 'stores.module_id', '=', 'modules.id')
            ->where('modules.module_type', $moduleType)
            ->where('vendors.status', 1)
            ->value('vendors.id');

        return $vendorId ? Vendor::find($vendorId) : null;
    }
}
