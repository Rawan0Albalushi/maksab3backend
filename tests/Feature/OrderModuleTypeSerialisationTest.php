<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Http\Resources\Vendor\Report\TaxOrderResource;
use App\Models\Order;
use App\Services\Order\OrderService;
use App\Services\System\ModuleService;
use App\Support\Cache\ApiCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderModuleTypeSerialisationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Model::preventLazyLoading(false);
        ModuleService::forgetMemo();
        ApiCache::bust('module');
    }

    protected function tearDown(): void
    {
        ModuleService::forgetMemo();
        ApiCache::bust('module');

        parent::tearDown();
    }

    private function countQueries(callable $work, ?string $needle = null): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $work();

        $log = DB::getQueryLog();
        DB::disableQueryLog();
        DB::flushQueryLog();

        if ($needle !== null) {
            $log = array_filter($log, fn ($q) => str_contains($q['query'], $needle));
        }

        return count($log);
    }

    public function test_serialising_orders_hits_storages_once_cold_and_never_warm(): void
    {
        $orders = Order::query()->limit(25)->get();

        $this->assertCount(25, $orders, 'this measurement needs 25 orders in the database');

        $cold = $this->countQueries(fn () => $orders->toArray());

        $reread = Order::query()->limit(25)->get();

        $warm = $this->countQueries(fn () => $reread->toArray());

        $this->assertLessThanOrEqual(3, $cold, 'a cold module cache should cost one modules + storages + translations read, not one per order');
        $this->assertSame(0, $warm, 'a warm module cache should serialise 25 orders without a single query');
    }

    public function test_serialisation_is_byte_identical_to_loading_the_relation_directly(): void
    {
        $viaAccessor = Order::query()->limit(50)->get()->toArray();

        $viaRelation = Order::query()->limit(50)->get()
            ->each(fn ($order) => $order->setRelation('module', Module::find($order->module_id)))
            ->toArray();

        $this->assertNotEmpty($viaAccessor);
        $this->assertSame(json_encode($viaRelation), json_encode($viaAccessor));
    }

    public function test_serialised_order_still_carries_the_module_relation(): void
    {
        $order = Order::query()->whereNotNull('module_id')->limit(1)->get()->first();

        $this->assertNotNull($order);

        $payload = $order->toArray();

        $this->assertArrayHasKey('module', $payload);
        $this->assertArrayHasKey('icon_full_url', $payload['module']);
        $this->assertArrayHasKey('thumbnail_full_url', $payload['module']);
        $this->assertSame(
            DB::table('modules')->where('id', $order->module_id)->value('module_type'),
            $payload['module_type']
        );
    }

    public function test_an_eager_loaded_module_is_not_replaced(): void
    {
        $order = Order::with('module')->whereNotNull('module_id')->limit(1)->get()->first();

        $this->assertNotNull($order);

        $eagerLoaded = $order->getRelation('module');
        $type = $order->module_type;

        $this->assertSame($eagerLoaded, $order->getRelation('module'));
        $this->assertSame($eagerLoaded->module_type, $type);
    }

    public function test_a_missing_module_resolves_to_null_without_a_query(): void
    {
        $order = new Order();
        $order->exists = true;
        $order->setRawAttributes(['id' => PHP_INT_MAX, 'module_id' => PHP_INT_MAX], true);

        $order->module_type;

        $count = $this->countQueries(function () use ($order) {
            $this->assertNull($order->module_type);
            $this->assertNull($order->toArray()['module']);
        });

        $this->assertSame(0, $count);
    }

    public function test_the_vendor_tax_report_resolves_its_eager_loaded_module(): void
    {
        $storeId = Order::query()->whereNotNull('store_id')->whereNotNull('module_id')->value('store_id');

        $this->assertNotNull($storeId);

        $orders = app(OrderService::class)->getStoreTaxOrderList(['store_id' => $storeId], ['per_page' => 10, 'page' => 1]);

        if ($orders->isEmpty()) {
            $this->markTestSkipped('no taxable orders for this store');
        }

        $order = $orders->first();

        $this->assertTrue($order->relationLoaded('module'));
        $this->assertNotNull($order->getRelation('module'), 'the select must carry module_id or the eager load cannot resolve');
        $this->assertNotNull($order->module_type);

        $count = $this->countQueries(fn () => TaxOrderResource::collection($orders)->resolve(), 'storages');

        $this->assertSame(0, $count, 'the tax report module must not reach for storage');
    }

    public function test_the_cached_module_follows_the_active_locale(): void
    {
        $moduleId = (int) Order::query()->whereNotNull('module_id')->value('module_id');

        foreach (['en', 'es'] as $locale) {
            app()->setLocale($locale);
            ModuleService::forgetMemo();
            ApiCache::bust('module');

            $cached = Order::where('module_id', $moduleId)->limit(1)->get()->first()->toArray()['module'];

            $this->assertSame(Module::find($moduleId)->module_name, $cached['module_name'], "locale {$locale}");
        }

        app()->setLocale('en');
    }
}
