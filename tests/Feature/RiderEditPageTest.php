<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\DeliveryMan;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The rider edit screen threw RelationNotFoundException: `RiderRepository` eager-loaded
 * `translations` onto DeliveryMan, which carries no translation trait — a rider's name is a
 * person's name, not translatable content.
 */
class RiderEditPageTest extends TestCase
{
    use DatabaseTransactions;

    private function makeRider(): DeliveryMan
    {
        $seed = DB::table('delivery_men')->first();
        $this->assertNotNull($seed, 'needs at least one delivery man row to clone');

        $rider = DeliveryMan::withoutGlobalScopes()->find($seed->id)->replicate();
        $rider->is_ride = 1;
        $rider->email = 'rider-'.uniqid().'@example.test';
        $rider->phone = '+8801'.random_int(100000000, 999999999);
        $rider->save();

        return $rider;
    }

    public function test_the_repository_no_longer_loads_a_relation_the_model_lacks(): void
    {
        $rider = $this->makeRider();

        $this->assertFalse(
            (new DeliveryMan)->isRelation('translations'),
            'DeliveryMan gained a translations relation — this guard needs revisiting'
        );

        // Threw RelationNotFoundException before the fix.
        $found = app(\App\Contracts\Repositories\RiderRepositoryInterface::class)
            ->getFirstWithoutGlobalScopeWhere(['id' => $rider->id]);

        $this->assertNotNull($found);
        $this->assertSame($rider->id, $found->id);
        $this->assertTrue($found->relationLoaded('storage'), 'storage is a real relation and must still load');
    }

    /** A delivery man who is not a rider used to fatal in the view; it must refuse cleanly. */
    public function test_a_non_rider_id_is_refused_instead_of_fataling(): void
    {
        $notARider = DB::table('delivery_men')->where('is_ride', '!=', 1)->first();

        if (! $notARider) {
            $this->markTestSkipped('needs a non-rider delivery man');
        }

        $admin = Admin::find(1);

        $this->actingAs($admin, 'admin')
            ->withSession(['current_module' => 1, 'login_remember_token' => $admin->login_remember_token])
            ->get('/admin/users/rider/edit/'.$notARider->id)
            ->assertRedirect();
    }

    public function test_the_edit_screen_renders(): void
    {
        $rider = $this->makeRider();
        $admin = Admin::find(1);

        $this->actingAs($admin, 'admin')
            ->withSession(['current_module' => 1, 'login_remember_token' => $admin->login_remember_token])
            ->get('/admin/users/rider/edit/'.$rider->id)
            ->assertOk();
    }
}
