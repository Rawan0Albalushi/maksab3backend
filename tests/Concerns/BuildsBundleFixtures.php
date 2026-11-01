<?php

namespace Tests\Concerns;

use App\Models\Bundle;
use App\Models\BundleItem;
use App\Models\Item;
use App\Models\Store;
use App\Services\Promotion\BogoOfferCustomerService;
use App\Services\Promotion\BundleService;
use App\Services\System\BusinessSettingService;
use App\Support\Promotion\BundleSettings;

trait BuildsBundleFixtures
{
    protected function enableBundlesFor(Store $store): void
    {
        $settings = app(BusinessSettingService::class);

        $settings->saveValue(BundleSettings::STATUS_KEY, 1);
        $settings->saveValue(
            BundleSettings::MODULES_KEY,
            json_encode([$store->module->module_type => 1])
        );
    }

    protected function bundleFixtureStore(int $items = 2, ?callable $constrain = null, bool $withoutHappyHour = true): ?Store
    {
        $query = Store::withoutGlobalScopes()
            ->with(['module', 'happyHourEnrollments.happyHour'])
            ->whereHas('module', fn ($q) => $q->whereIn('module_type', BundleSettings::moduleTypes())->where('status', 1))
            ->where('status', 1);

        if ($constrain) {
            $constrain($query);
        }

        foreach ($query->get() as $store) {
            if ($withoutHappyHour && $this->storeHasRunningHappyHour($store)) {
                continue;
            }

            if ($this->sellableFixtureItems($store, $items)->count() >= $items) {
                return $store;
            }
        }

        return null;
    }

    protected function storeHasRunningHappyHour(Store $store): bool
    {
        $store->loadMissing('happyHourEnrollments.happyHour');

        return app(BogoOfferCustomerService::class)->happyHourPercentageFor($store) !== null;
    }

    protected function sellableFixtureItems(Store $store, int $limit = 2)
    {
        $tracksStock = (bool) config('module.'.($store->module?->module_type ?? '').'.stock');

        return Item::withoutGlobalScopes()
            ->with('module')
            ->active()
            ->where('store_id', $store->id)
            ->where('price', '>', 0)
            ->when($tracksStock, fn ($q) => $q->where('stock', '>', 10))
            ->where(fn ($q) => $q->whereNull('available_time_starts')->orWhere('available_time_starts', '<=', now()->format('H:i:s')))
            ->where(fn ($q) => $q->whereNull('available_time_ends')->orWhere('available_time_ends', '>=', now()->format('H:i:s')))
            ->whereRaw("COALESCE(variations, '[]') IN ('[]', '')")
            ->whereRaw("COALESCE(food_variations, '[]') IN ('[]', '')")
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    protected function makeFixtureBundle(Store $store, $items, float $discountPercentage = 10, array $overrides = []): Bundle
    {
        $bundle = Bundle::create(array_merge([
            'store_id' => $store->id,
            'module_id' => $store->module_id,
            'name' => 'Fixture Bundle '.uniqid(),
            'start_date' => now()->subHour(),
            'end_date' => now()->addWeek(),
            'discount_percentage' => $discountPercentage,
            'created_by' => 'vendor',
        ], $overrides));

        $base = 0.0;

        foreach ($items as $item) {
            BundleItem::create([
                'bundle_id' => $bundle->id,
                'item_id' => $item->id,
                'quantity' => 1,
                'item_name' => $item->getRawOriginal('name'),
                'item_image' => $item->image,
                'unit_price' => $item->price,
                'item_price' => $item->price,
            ]);

            $base += (float) $item->price;
        }

        $bundle->forceFill(app(BundleService::class)->prices($base, $discountPercentage))->save();

        return $bundle->fresh(['items.item.module', 'store', 'module']);
    }

    protected function openFixtureStore(Store $store): void
    {
        $store->forceFill(['status' => 1, 'active' => 1, 'off_day' => ''])->save();

        $store->schedules()->delete();

        foreach (range(0, 6) as $day) {
            $store->schedules()->create([
                'day' => $day,
                'opening_time' => '00:00:00',
                'closing_time' => '23:59:59',
            ]);
        }

        $store->refresh();
    }
}
