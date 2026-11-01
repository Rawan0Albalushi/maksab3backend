<?php

namespace Tests\Unit;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Every service method the codebase CALLS must actually exist.
 *
 * WHY THIS TEST EXISTS. Twice during this port an edit bounded by two anchors quietly removed a
 * method that sat between them — `DeliveryChargeService::quote()` once, and
 * `DeliveryRuleService::unconnectedModuleNames()` again. Both times `php -l` passed, because a
 * class is perfectly valid without a method; both times the unit suite passed, because nothing
 * covered that path; and both times the failure surfaced only when a person clicked the button —
 * the second one as a 500 on submitting a parcel delivery rule.
 *
 * A call to a missing method is a runtime fatal, not a compile error, so nothing else in the
 * pipeline catches it. This walks the real call sites instead.
 *
 * It deliberately checks only the two shapes this repo actually uses to reach a service —
 * `app(XService::class)->method(` and `$this->xService->method(` — which keeps it free of guesses
 * about dynamic dispatch. A method reached some other way is simply not covered, and that is
 * better than a test that reports names it cannot resolve.
 */
class ServiceCallSurfaceTest extends TestCase
{
    /** The services this port owns or leans on. */
    private const SERVICES = [
        \App\Services\Order\DeliveryChargeService::class,
        \App\Services\Zone\DeliveryRuleService::class,
        \App\Services\Zone\DeliveryRuleChargeService::class,
        \App\Services\Zone\DeliveryRuleWeightChargeService::class,
        \App\Services\Zone\DeliveryRuleDimensionChargeService::class,
        \App\Services\Zone\AreaService::class,
        \App\Services\Zone\ZipCodeService::class,
        \App\Services\Zone\ModuleZoneService::class,
        \App\Services\Zone\ZoneService::class,
        \App\Services\Parcel\WeightService::class,
        \App\Services\Parcel\DimensionService::class,
        \App\Services\System\ModuleService::class,
        \App\Services\System\DistanceService::class,
    ];

    private const ROOTS = ['app', 'Modules', 'resources/views', 'routes', 'tests'];

    public function test_every_service_method_called_anywhere_exists(): void
    {
        $byShortName = [];

        foreach (self::SERVICES as $fqcn) {
            $byShortName[class_basename($fqcn)] = $fqcn;
        }

        $missing = [];

        foreach ($this->sourceFiles() as $file) {
            $code = file_get_contents($file);

            foreach ($byShortName as $short => $fqcn) {
                if (! str_contains($code, $short)) {
                    continue;
                }

                foreach ($this->calledMethods($code, $short, $fqcn) as $method) {
                    if (! method_exists($fqcn, $method)) {
                        $missing[] = str_replace(base_path().'/', '', $file)." calls {$short}::{$method}()";
                    }
                }
            }
        }

        $this->assertSame(
            [],
            array_values(array_unique($missing)),
            'These calls would be a runtime fatal — the method does not exist on the service',
        );
    }

    /**
     * @return list<string>
     *
     * Two precision rules, both learned from false positives on the first run:
     *
     *  - the container form is anchored on the left, or `ModuleZoneService::class` matches as
     *    `ZoneService::class` and every one of its methods is reported against the wrong class;
     *  - the property form is only trusted when the file IMPORTS this exact class. Several
     *    modules ship their own `ZoneService` and inject it as `$this->zoneService`, so the
     *    property name alone says nothing about which class is on the other end.
     */
    private function calledMethods(string $code, string $short, string $fqcn): array
    {
        // app(XService::class)->method(
        preg_match_all('/(?<![A-Za-z0-9_\\\\])'.preg_quote($short, '/').'::class\)\s*->\s*(\w+)\s*\(/', $code, $viaContainer);

        $methods = $viaContainer[1];

        // $this->xService->method(  — the constructor-injected panel style.
        if (preg_match('/^use '.preg_quote($fqcn, '/').';$/m', $code)) {
            preg_match_all('/\$this->'.preg_quote(lcfirst($short), '/').'\s*->\s*(\w+)\s*\(/', $code, $viaProperty);
            $methods = array_merge($methods, $viaProperty[1]);
        }

        return $methods;
    }

    /** @return list<string> */
    private function sourceFiles(): array
    {
        $files = [];

        foreach (self::ROOTS as $root) {
            $path = base_path($root);

            if (! is_dir($path)) {
                continue;
            }

            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path)) as $file) {
                if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }
}
