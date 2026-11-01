<?php

namespace Tests\Feature;

use App\Models\Vendor;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SweepsRoutes;
use Tests\TestCase;

/**
 * ModulePanelSweepTest filters on the Modules\ namespace, so it only ever covered the Rental and
 * Service provider panels. The core vendor panel — App\Http\Controllers\Vendor\*, the larger half —
 * had no sweep at all.
 */
class VendorPanelSweepTest extends TestCase
{
    use SweepsRoutes;

    public function test_core_vendor_panel_has_no_lazy_loading_violations(): void
    {
        $this->ensureMemoryLimit('1024M');
        $this->sweepArmLazyLoadingGuard();

        $violations = [];
        $errors = [];
        $unresolved = [];
        $probed = 0;
        $skipped = [];

        foreach ($this->vendorPerModuleType() as $moduleType => $vendor) {
            $session = ['login_remember_token' => $vendor->login_remember_token];

            foreach ([false, true] as $withParams) {
                $targets = $this->sweepTargets(
                    prefixes: ['vendor-panel/'],
                    withParams: $withParams,
                    namespace: 'App\\Http\\Controllers\\Vendor\\'
                );

                foreach ($targets as $pattern => $route) {
                    $uris = $withParams ? $this->sweepResolveUris($route) : [$pattern];

                    if ($uris === []) {
                        $unresolved[] = $pattern;

                        continue;
                    }

                    foreach ($uris as $uri) {
                        $probed++;
                        $result = $this->sweepProbeFor($uri, $vendor, 'vendor', $session);

                        if ($result['lazy'] !== null) {
                            $violations[] = $moduleType.'  '.$result['lazy'];
                        }

                        if ($result['error'] !== null) {
                            $errors[$result['error']] = true;
                        }
                    }
                }
            }
        }

        if ($skipped !== []) {
            fwrite(STDERR, '  skipped module types with no seeded vendor: '.implode(', ', $skipped).PHP_EOL);
        }

        fwrite(STDERR, PHP_EOL.'probed '.$probed.' core vendor panel route/module combinations'.PHP_EOL);

        foreach (array_unique($unresolved) as $pattern) {
            fwrite(STDERR, '  unresolved (no seed row for its parameters): '.$pattern.PHP_EOL);
        }

        $this->assertNotSame(0, $probed, 'no core vendor panel routes were probed');

        $this->reportRuntimeDefects($errors);
        $this->assertNoFullUrlDefects($errors);

        $this->assertSame([], array_values(array_unique($violations)),
            'Vendor panel pages must eager-load every relation their views render.');
    }

    /**
     * One vendor per module type: the panel branches heavily on the store's module, so a single
     * vendor leaves every other module's blades unexercised.
     */
    private function vendorPerModuleType(): array
    {
        $rows = DB::table('vendors')
            ->join('stores', 'stores.vendor_id', '=', 'vendors.id')
            ->join('modules', 'stores.module_id', '=', 'modules.id')
            ->where('vendors.status', 1)
            ->whereNotNull('vendors.login_remember_token')
            ->orderBy('vendors.id')
            ->get(['vendors.id as vendor_id', 'modules.module_type']);

        $vendors = [];

        foreach ($rows as $row) {
            if (isset($vendors[$row->module_type])) {
                continue;
            }

            $vendor = Vendor::find($row->vendor_id);

            if ($vendor !== null) {
                $vendors[$row->module_type] = $vendor;
            }
        }

        return $vendors;
    }
}
