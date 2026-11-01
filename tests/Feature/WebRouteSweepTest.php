<?php

namespace Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SweepsRoutes;
use Tests\TestCase;

class WebRouteSweepTest extends TestCase
{
    use SweepsRoutes;

    /**
     * Payment gateway callbacks are excluded: they are entered with signed provider state and
     * dispatching them blind either redirects immediately or writes a transaction row.
     */
    private array $excludedPrefixes = [
        'payment', 'payment-mobile', 'oauth', '_debugbar', '_inertia', 'broadcasting',
    ];

    public function test_public_web_pages_have_no_lazy_loading_violations(): void
    {
        $this->ensureMemoryLimit('512M');
        $this->sweepArmLazyLoadingGuard();

        $targets = array_filter(
            $this->sweepTargets(prefixes: [''], withParams: false),
            fn ($route, $uri) => ! $this->isExcluded($uri)
                && ! str_starts_with($uri, 'admin')
                && ! str_starts_with($uri, 'vendor-panel')
                && ! str_starts_with($uri, 'api/'),
            ARRAY_FILTER_USE_BOTH
        );

        $this->assertNotEmpty($targets, 'no public web routes discovered');

        $zoneId = DB::table('zones')->orderBy('id')->value('id');
        $violations = [];
        $errors = [];

        foreach ($targets as $uri => $route) {
            DB::beginTransaction();

            try {
                $this->withoutExceptionHandling();
                $this->withSession(['zone_id' => $zoneId])->get('/'.ltrim($uri, '/'));
            } catch (\Throwable $e) {
                $message = $uri.'  ==>  '.str_replace(base_path().'/', '', substr($e->getMessage(), 0, 200));

                if (str_contains($e->getMessage(), 'lazy load')) {
                    $violations[] = $message;
                } elseif ($this->sweepIsRuntimeDefect($e)) {
                    $errors[$message] = true;
                }
            } finally {
                while (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }

                $this->app->forgetInstance('view');
                gc_collect_cycles();
            }
        }

        fwrite(STDERR, PHP_EOL.'probed '.count($targets).' public web routes'.PHP_EOL);

        $this->reportRuntimeDefects($errors);
        $this->assertNoFullUrlDefects($errors);

        $this->assertSame([], array_values(array_unique($violations)),
            'Public web pages must eager-load every relation their views render.');
    }

    private function isExcluded(string $uri): bool
    {
        foreach ($this->excludedPrefixes as $prefix) {
            if ($uri === $prefix || str_starts_with($uri, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }
}
