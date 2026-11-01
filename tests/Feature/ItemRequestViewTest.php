<?php

namespace Tests\Feature;

use App\CentralLogics\Helpers;
use App\Models\Admin;
use App\Models\Item;
use App\Models\TempProduct;
use App\Scopes\StoreScope;
use App\Services\Item\TempProductService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ItemRequestViewTest extends TestCase
{
    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = Admin::find(1);
        $this->assertNotNull($this->admin, 'admin id 1 must exist');

        if (Helpers::get_business_settings('product_approval') != 1) {
            $this->markTestSkipped('product approval is switched off');
        }
    }

    private function request(): TempProduct
    {
        $request = TempProduct::withoutGlobalScope(StoreScope::class)->with('module')->first();

        if (! $request) {
            $this->markTestSkipped('no pending item request to render');
        }

        return $request;
    }

    public function test_the_page_ships_the_decision_panel_and_the_submission_summary(): void
    {
        $request = $this->request();

        $html = $this->actingAs($this->admin, 'admin')
            ->withSession(['login_remember_token' => $this->admin->login_remember_token])
            ->get('/admin/item/requested/item/view/'.$request->id.'?module_id='.$request->module_id)
            ->assertOk()->getContent();

        $this->assertStringContainsString('idt-decide', $html);
        $this->assertStringContainsString('request_alert', $html);
        $this->assertStringContainsString(route('admin.item.approved', ['id' => $request->id]), $html);
        $this->assertStringContainsString('idt-facts', $html);
        $this->assertStringContainsString('idt-specs', $html);
    }

    public function test_a_first_submission_has_nothing_to_compare(): void
    {
        $this->assertSame([], app(TempProductService::class)->approvalChanges($this->request(), null));
    }

    public function test_the_comparison_reports_the_fields_that_differ(): void
    {
        $request = $this->request();

        $live = (new Item)->setRawAttributes([
            'id' => $request->item_id ?: 1,
            'name' => $request->getRawOriginal('name').' (published)',
            'description' => $request->getRawOriginal('description'),
            'category_ids' => $request->category_ids,
            'price' => $request->price + 5,
            'discount' => $request->discount,
            'discount_type' => $request->discount_type,
            'stock' => $request->stock,
            'unit_id' => $request->unit_id,
            'veg' => $request->veg,
            'organic' => $request->organic,
            'maximum_cart_quantity' => $request->maximum_cart_quantity,
            'available_time_starts' => $request->available_time_starts,
            'available_time_ends' => $request->available_time_ends,
            'image' => $request->image,
            'images' => json_encode($request->images ?? []),
            'food_variations' => $request->food_variations,
            'variations' => $request->variations,
            'add_ons' => $request->add_ons,
        ], true);
        $live->setRelation('tags', collect());
        $live->setRelation('unit', $request->unit);

        $changes = app(TempProductService::class)->approvalChanges($request, $live);
        $labels = array_column($changes, 'label');

        $this->assertContains(translate('messages.Name'), $labels);
        $this->assertContains(translate('messages.Unit Price'), $labels);
        $this->assertNotContains(translate('messages.Description'), $labels, 'an unchanged field is left out');

        foreach ($changes as $change) {
            $this->assertNotSame($change['from'], $change['to'], 'a row is only reported when the two sides differ');
        }
    }

    public function test_the_denial_reason_and_the_empty_comparison_reach_the_page(): void
    {
        $request = $this->request();
        $note = 'Blurry image and the pack size does not match the price.';

        DB::beginTransaction();

        try {
            $rejected = $request->replicate();
            $rejected->is_rejected = 1;
            $rejected->note = $note;
            $rejected->save();

            $html = $this->actingAs($this->admin, 'admin')
                ->withSession(['login_remember_token' => $this->admin->login_remember_token])
                ->get('/admin/item/requested/item/view/'.$rejected->id)
                ->assertOk()->getContent();

            $this->assertStringContainsString('idt-note', $html);
            $this->assertStringContainsString($note, $html);
            $this->assertStringNotContainsString(
                route('admin.item.deny', ['id' => $rejected->id]),
                $html,
                'a denied request offers no second deny'
            );
        } finally {
            DB::rollBack();
        }
    }
}
