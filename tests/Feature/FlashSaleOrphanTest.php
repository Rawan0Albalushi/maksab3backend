<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FlashSaleOrphanTest extends TestCase
{
    public function test_flash_sale_product_list_renders_when_a_product_row_is_gone(): void
    {
        Model::preventLazyLoading(true);
        $admin = Admin::find(1);

        $flashSaleId = DB::table('flash_sale_items')
            ->leftJoin('items', 'items.id', '=', 'flash_sale_items.item_id')
            ->whereNull('items.id')
            ->value('flash_sale_items.flash_sale_id');

        if ($flashSaleId === null) {
            $this->markTestSkipped('no flash_sale_items row points at a deleted product in this dataset');
        }

        $this->withoutExceptionHandling();

        $response = $this->actingAs($admin, 'admin')
            ->withSession(['current_module' => 3, 'login_remember_token' => $admin->login_remember_token])
            ->get('/admin/flash-sale/add-product/'.$flashSaleId);

        $response->assertStatus(200);
        $response->assertSee(translate('messages.item deleted!'), false);
    }
}
