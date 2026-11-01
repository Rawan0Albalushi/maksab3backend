<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\SweepsRoutes;
use Tests\TestCase;

class ExportSweepTest extends TestCase
{
    use SweepsRoutes;

    private const QUERY_CEILING = 100;

    public function test_exports_have_no_lazy_loading_violations_and_do_not_scale_queries_per_row(): void
    {
        $this->ensureMemoryLimit('1024M');
        $this->sweepArmLazyLoadingGuard();

        $admin = Admin::find(1);
        $this->assertNotNull($admin, 'admin id 1 must exist');

        $targets = $this->exportTargets();
        $this->assertNotEmpty($targets, 'no export routes discovered');

        $modules = $this->sweepModuleIds();
        $violations = [];
        $failures = [];
        $runaway = [];
        $unresolved = [];
        $counts = [];

        foreach ($modules as $moduleType => $moduleId) {
            $session = ['current_module' => $moduleId, 'login_remember_token' => $admin->login_remember_token];

            foreach ($targets as $pattern => $route) {
                $uri = $this->sweepResolveUri($route);

                if ($uri === null) {
                    $unresolved[] = $pattern;

                    continue;
                }

                DB::flushQueryLog();
                DB::enableQueryLog();

                $probe = $this->sweepProbe($uri, $admin, 'admin', $session);
                $violation = $probe['lazy'] ?? null;

                // The runtime error, not only the lazy load. This sweep rendered 1728 export
                // combinations and asserted nothing about whether any of them SUCCEEDED, so nine
                // exports that threw on every request passed it for weeks -- each one handed
                // FastExcel an absolute path where it wanted a download filename, and declared a
                // BinaryFileResponse where it returns a StreamedResponse.
                if ($this->isExportDefect($probe['threw'] ?? null)) {
                    $failures[] = $moduleType.'  '.$probe['threw'];
                }

                $queries = count(DB::getQueryLog());
                DB::disableQueryLog();
                DB::flushQueryLog();

                $counts[$moduleType.'  '.$uri] = $queries;

                if ($violation !== null) {
                    $violations[] = $moduleType.'  '.$violation;
                }

                if ($queries > self::QUERY_CEILING) {
                    $runaway[] = $moduleType.'  '.$uri.'  ==>  '.$queries.' queries';
                }
            }
        }

        $unresolved = array_values(array_unique($unresolved));

        arsort($counts);

        fwrite(STDERR, PHP_EOL.'probed '.count($counts).' export route/module combinations across '
            .count($modules).' module types'.PHP_EOL);

        foreach (array_slice($counts, 0, 15, true) as $uri => $queries) {
            fwrite(STDERR, '  '.str_pad((string) $queries, 6, ' ', STR_PAD_LEFT).' queries  '.$uri.PHP_EOL);
        }

        foreach ($unresolved as $pattern) {
            fwrite(STDERR, '  unresolved (no seed row for its parameters): '.$pattern.PHP_EOL);
        }

        $this->assertSame([], array_values(array_unique($failures)),
            'Every export must return a file rather than throw.');

        $this->assertSame([], array_values(array_unique($violations)),
            'Exports must eager-load every relation their views render.');

        $this->assertSame([], $runaway,
            'Exports must not issue a query per row. Eager-load the relations the export view reads.');
    }

    /**
     * Does this throwable mean the export is BROKEN, or that the probe could not feed it?
     *
     * The sweep invents its parameters, so three families say nothing about the code: a model id
     * it guessed that does not exist, a required query parameter it did not send, and a route that
     * needs module context it is not in. Everything else — a missing view, a missing controller
     * method, a bad download filename, a type error — is wrong whatever the input, and is what
     * this assertion is for.
     */
    private function isExportDefect(?string $threw): bool
    {
        if ($threw === null) {
            return false;
        }

        foreach ([
            'ModelNotFoundException',      // the id the sweep guessed has no row
            'ValidationException',         // a required parameter the sweep did not send
            'NotFoundHttpException',       // needs module context the sweep is not in
            'AuthorizationException',
        ] as $probeArtifact) {
            if (str_contains($threw, $probeArtifact)) {
                return false;
            }
        }

        return true;
    }

    private function exportTargets(): array
    {
        $targets = [];

        foreach (Route::getRoutes() as $route) {
            $uri = strtolower($route->uri());
            $name = strtolower((string) $route->getName());

            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            if (! str_starts_with($uri, 'admin/') && ! str_starts_with($uri, 'vendor-panel/')) {
                continue;
            }

            if (! str_contains($uri, 'export') && ! str_contains($name, 'export')) {
                continue;
            }

            if ($this->sweepLooksMutating($uri, $name)) {
                continue;
            }

            $targets[$route->uri()] = $route;
        }

        return $targets;
    }
}
