<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Vendor;
use Illuminate\Routing\Route as RoutingRoute;
use ReflectionMethod;
use Tests\Concerns\SweepsRoutes;
use Tests\TestCase;

/**
 * The other param sweeps resolve one placeholder value per parameter, so a route like
 * admin/store/view/{store}/{tab?}/{sub_tab?} is only ever probed as its default overview - every
 * tab blade behind it stays unrendered. This sweep reads the branch literals the controller
 * compares the optional parameter against ($tab == 'reviews', match ($sub_tab), in_array(...))
 * and probes each one.
 */
class OptionalParamSweepTest extends TestCase
{
    use SweepsRoutes;

    public function test_optional_param_branches_have_no_lazy_loading_violations(): void
    {
        $this->ensureMemoryLimit('2048M');
        $this->sweepArmLazyLoadingGuard();

        $admin = Admin::find(1);
        $vendor = Vendor::whereHas('stores')->first();

        $this->assertNotNull($admin, 'admin id 1 must exist');

        $violations = [];
        $errors = [];
        $probed = 0;

        foreach ($this->optionalParamRoutes() as $route) {
            $isVendor = str_starts_with($route->uri(), 'vendor-panel');
            $user = $isVendor ? $vendor : $admin;
            $guard = $isVendor ? 'vendor' : 'admin';

            if ($user === null) {
                continue;
            }

            $uris = $this->expandOptionalParams($route);

            foreach ($this->sweepModuleIds() as $moduleId) {
                $session = ['current_module' => $moduleId];
                $session[$isVendor ? 'vendor_login_remember_token' : 'login_remember_token'] = $user->login_remember_token;

                foreach ($uris as $uri) {
                    $probed++;
                    $result = $this->sweepProbeFor($uri, $user, $guard, $session);

                    if ($result['lazy'] !== null) {
                        $violations[preg_replace('/\/\d+/', '/{id}', $result['lazy'])] = true;
                    }

                    if ($result['error'] !== null) {
                        $errors[preg_replace('/\/\d+/', '/{id}', $result['error'])] = true;
                    }
                }
            }
        }

        fwrite(STDERR, PHP_EOL.'probed '.$probed.' optional-parameter branch URIs'.PHP_EOL);

        $this->reportRuntimeDefects($errors);
        $this->assertNoFullUrlDefects($errors);

        $this->assertSame([], array_keys($violations),
            'Every branch behind an optional route parameter must eager-load the relations its view renders.');
    }

    /**
     * @return array<int, RoutingRoute>
     */
    private function optionalParamRoutes(): array
    {
        return collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn (RoutingRoute $route) => in_array('GET', $route->methods(), true))
            ->filter(fn (RoutingRoute $route) => str_contains($route->uri(), '?}'))
            ->filter(fn (RoutingRoute $route) => str_starts_with($route->uri(), 'admin')
                || str_starts_with($route->uri(), 'vendor-panel'))
            ->filter(fn (RoutingRoute $route) => ! str_contains($route->getActionName(), 'RideShare'))
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function expandOptionalParams(RoutingRoute $route): array
    {
        $uris = $this->sweepResolveUris($route);

        if ($uris === []) {
            return [];
        }

        preg_match_all('/\{([a-zA-Z_]+)\?\}/', $route->uri(), $optional);
        $segments = explode('/', $route->uri());

        foreach ($optional[1] as $param) {
            $values = $this->branchValues($route, $param);
            $position = array_search('{'.$param.'?}', $segments, true);

            if ($values === [] || $position === false) {
                continue;
            }

            $expanded = $uris;

            foreach ($uris as $uri) {
                foreach ($values as $value) {
                    $pieces = explode('/', $uri);

                    if (! isset($pieces[$position])) {
                        continue;
                    }

                    $pieces[$position] = $value;
                    $expanded[] = implode('/', $pieces);
                }
            }

            $uris = array_values(array_unique($expanded));
        }

        return $uris;
    }

    /**
     * @return array<int, string>
     */
    private function branchValues(RoutingRoute $route, string $param): array
    {
        $action = $route->getActionName();

        if (! str_contains($action, '@')) {
            return [];
        }

        [$class, $method] = explode('@', $action);

        if (! class_exists($class) || ! method_exists($class, $method)) {
            return [];
        }

        $reflection = new ReflectionMethod($class, $method);
        $lines = file($reflection->getFileName());
        $body = implode('', array_slice($lines, $reflection->getStartLine() - 1,
            $reflection->getEndLine() - $reflection->getStartLine() + 1));

        $var = preg_quote($param, '/');
        $values = [];

        preg_match_all('/\$'.$var.'\s*(?:===|==)\s*\'([^\']+)\'/', $body, $matches);
        $values = array_merge($values, $matches[1]);

        preg_match_all('/\'([^\']+)\'\s*(?:===|==)\s*\$'.$var.'\b/', $body, $matches);
        $values = array_merge($values, $matches[1]);

        preg_match_all('/in_array\(\s*\$'.$var.'\s*,\s*\[([^\]]*)\]/', $body, $matches);

        foreach ($matches[1] as $list) {
            preg_match_all('/\'([^\']+)\'/', $list, $inner);
            $values = array_merge($values, $inner[1]);
        }

        if (preg_match('/match\s*\(\s*\$'.$var.'\s*\)\s*\{(.*?)\}/s', $body, $matches)) {
            preg_match_all('/\'([^\']+)\'\s*=>/', $matches[1], $inner);
            $values = array_merge($values, $inner[1]);
        }

        return array_values(array_unique(array_filter(
            $values,
            fn ($value) => $value !== '' && ! str_contains($value, ' ') && ! str_contains($value, '/')
        )));
    }
}
