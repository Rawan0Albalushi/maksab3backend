<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Delivery Management moved from `admin/business-settings/zone/*` to `admin/delivery-management/*`.
 *
 * The route NAMES did not move, so this pins both halves: the new URL is what the names resolve
 * to, and the old URL still redirects there for bookmarks.
 */
class DeliveryManagementUrlMoveTest extends TestCase
{
    private const SECTIONS = [
        'delivery-rule', 'area', 'zip-code', 'weight', 'dimension', 'vehicle-category',
        'free-delivery', 'additional-delivery-charge', 'eta-configuration', 'surge-price',
    ];

    public function test_every_section_list_resolves_to_the_new_prefix(): void
    {
        foreach (self::SECTIONS as $section) {
            $url = route("admin.business-settings.zone.{$section}.list");

            $this->assertStringContainsString("/admin/delivery-management/{$section}", $url, $section);
            $this->assertStringNotContainsString('business-settings/zone', $url, $section);
        }
    }

    public function test_no_delivery_management_route_is_registered_under_the_old_prefix(): void
    {
        $stale = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();

            foreach (self::SECTIONS as $section) {
                if (str_starts_with($uri, "admin/business-settings/zone/{$section}")) {
                    $stale[] = $uri;
                }
            }
        }

        $this->assertSame([], $stale);
    }

    public function test_old_urls_redirect_to_the_new_ones(): void
    {
        $admin = Admin::find(1);
        $this->assertNotNull($admin);

        $session = ['current_module' => 1, 'login_remember_token' => $admin->login_remember_token];

        // The keys are deliberately the OLD paths — a find-and-replace over the test suite will
        // want to "fix" them, and doing so turns this into an assertion that a URL redirects to
        // itself, which passes for the wrong reason and stops guarding anything.
        foreach ([
            'admin/business-settings/zone/area' => 'admin/delivery-management/area',
            'admin/business-settings/zone/surge-price/edit/1' => 'admin/delivery-management/surge-price/edit/1',
            'admin/business-settings/zone/weight' => 'admin/delivery-management/weight',
        ] as $old => $new) {
            $this->actingAs($admin, 'admin')->withSession($session)->get('/'.$old)
                ->assertRedirect(url($new));
        }
    }

    /** Zone Setup and the zone-scoped Smart Banner are Business Setup screens; they did not move. */
    public function test_zone_setup_did_not_move(): void
    {
        $this->assertStringContainsString(
            '/admin/business-settings/zone',
            route('admin.business-settings.zone.home')
        );

        $admin = Admin::find(1);
        $this->actingAs($admin, 'admin')
            ->withSession(['current_module' => 1, 'login_remember_token' => $admin->login_remember_token])
            ->get('/admin/business-settings/zone')
            ->assertOk();
    }
}
