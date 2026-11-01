<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * QA case TC_702 — Happy Hour and BOGO had no role-permission gate.
 *
 * Their routes carried only `promotion-module`, which asks whether the MODULE TYPE can run
 * promotions, not whether this admin may see them. A role with no promotion permission reached a
 * fully working list and create page. The sidebar already gated both entries on the `coupon`
 * permission, so the intended rule was clear — the routes just did not enforce it.
 */
class PromotionPermissionGateTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array<int, string> */
    private function middlewareFor(string $routeName): array
    {
        $route = Route::getRoutes()->getByName($routeName);

        $this->assertNotNull($route, "route {$routeName} must exist");

        return $route->gatherMiddleware();
    }

    public function test_the_happy_hour_routes_carry_a_permission_gate(): void
    {
        $middleware = $this->middlewareFor('admin.happy-hour.list');

        $this->assertContains('module:coupon', $middleware, 'Happy Hour must gate on the permission its sidebar entry already checks');
        $this->assertContains('promotion-module', $middleware, 'the module-capability check stays alongside it');
    }

    public function test_the_bogo_routes_carry_a_permission_gate(): void
    {
        $middleware = $this->middlewareFor('admin.bogo-offer.list');

        $this->assertContains('module:coupon', $middleware);
        $this->assertContains('promotion-module', $middleware);
    }

    /**
     * The capability check alone is not a permission check — this guards against someone
     * "simplifying" the pair back down to one middleware.
     */
    public function test_the_capability_check_is_not_treated_as_a_permission_check(): void
    {
        foreach (['admin.happy-hour.list', 'admin.happy-hour.store', 'admin.bogo-offer.list'] as $name) {
            $middleware = $this->middlewareFor($name);

            $this->assertTrue(
                (bool) preg_grep('/^module:/', $middleware),
                "{$name} must carry a module:<permission> gate, not only promotion-module",
            );
        }
    }
}
