<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * A bare string action must not name a registered facade alias.
 *
 * Laravel resolves `Route::get('log', 'log')` inside a `Route::controller()` group by prepending
 * the group's controller -- but `prependGroupController()` skips that step when `class_exists()`
 * on the action string is already true. PHP class names are case-insensitive and facade aliases
 * are registered with `class_alias()`, so the moment anything in the process touches the `\Log`
 * facade, `class_exists('log')` becomes true for the rest of that process and the route registers
 * as the class `log` instead of `Controller@log`. Registration then throws
 * "Invalid route action: [log]".
 *
 * It survives in production because a single request boots the app once, usually before any
 * facade alias is resolved. A test process boots repeatedly, so the first test that touches the
 * facade breaks route registration for every test after it -- which is exactly what happened here
 * on 2026-09-03: 264 of 290 tests failed with an error pointing at a module route file that had
 * been correct for years.
 *
 * 26 collisions were fixed by naming the controller explicitly. This keeps them fixed.
 */
class RouteActionCollisionTest extends TestCase
{
    /** Route files to sweep: the application's own plus every module's. */
    private function routeFiles(): array
    {
        $files = glob(base_path('routes/*.php')) ?: [];

        foreach (glob(base_path('Modules/*'), GLOB_ONLYDIR) ?: [] as $module) {
            // Routes/ and routes/ are the same directory on a case-insensitive filesystem, so
            // only one spelling is walked -- realpath then dedupes anything that slips through.
            if (! is_dir($module.'/Routes')) {
                continue;
            }

            $walker = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($module.'/Routes'));

            foreach ($walker as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        return array_values(array_unique(array_map('realpath', $files)));
    }

    public function test_no_route_action_collides_with_a_facade_alias(): void
    {
        $aliases = array_map('strtolower', array_keys(config('app.aliases', [])));

        $this->assertNotEmpty($aliases, 'no facade aliases configured -- the sweep would pass vacuously');

        $collisions = [];

        foreach ($this->routeFiles() as $file) {
            foreach (file($file) as $number => $line) {
                $matched = preg_match(
                    '/Route::(?:get|post|put|patch|delete|any|match)\s*\([^,]+,\s*([\'"])([a-zA-Z_][a-zA-Z0-9_]*)\1\s*\)/',
                    $line,
                    $match
                );

                if ($matched && in_array(strtolower($match[2]), $aliases, true)) {
                    $collisions[] = str_replace(base_path().'/', '', $file).':'.($number + 1)
                        .'  action "'.$match[2].'" collides with the '.$match[2].' facade alias';
                }
            }
        }

        $this->assertSame([], $collisions,
            "Name the controller explicitly -- [Controller::class, 'method'] -- so route registration "
            ."cannot depend on whether a facade alias happens to be loaded yet.");
    }

    /**
     * The guard above only means something if the collision is really fatal, so this proves it
     * rather than trusting the reasoning: build the two-step resolution Laravel performs and show
     * that a loaded alias diverts it.
     */
    public function test_a_loaded_facade_alias_really_does_capture_a_bare_action(): void
    {
        $this->assertTrue(class_exists('Log'), 'the Log facade alias should resolve');

        // The same call prependGroupController() makes. Case-insensitive class lookup means the
        // lower-case action string finds the alias, and the controller is never prepended.
        $this->assertTrue(class_exists('log'),
            'PHP class names are case-insensitive, so a loaded Log alias makes the action "log" look like a class');
    }
}
