<?php

namespace App\Traits\Item;

use App\CentralLogics\Helpers;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use App\Services\Item\AddonService;
use App\Services\Item\CategoryService;
use Modules\TaxModule\Services\TaxService;

trait ProductPayloadTrait
{
    protected const PRODUCT_PAYLOAD_RELATIONS = [
        'storage', 'module.storage', 'unit', 'store.storage', 'store.discount',
        'storeCategory.storage', 'pharmacy_item_details',
        'ecommerce_item_details.brand.storage', 'nutritions', 'allergies', 'generic',
        'taxVats', 'seoData',
    ];

    public function loadProductRelations(EloquentCollection $models): void
    {
        if ($models->isEmpty()) {
            return;
        }

        $this->loadMissingSupported($models, self::PRODUCT_PAYLOAD_RELATIONS);
        $this->loadMissingSupported($models, [
            'store.storeConfig',
            'store.store_sub',
            'rating' => fn ($query) => $query->where('status', 1),
            'flashSaleItems' => fn ($query) => $query->active()->whereHas('flashSale', fn ($sale) => $sale->active()->running()),
        ]);

        $this->attachAddOns($models);
        $this->attachCategoryNames($models);
        $this->attachTaxes($models);
    }

    public function loadCartProductRelations(EloquentCollection $models): void
    {
        if ($models->isEmpty()) {
            return;
        }

        // the order below mirrors the order the legacy formatter touched each relation, because
        // relations serialize in insertion order and the payload is compared key-for-key
        $this->loadMissingSupported($models, [
            'storeCategory.storage', 'module.storage', 'store.storage', 'store.discount',
            'store.storeConfig', 'store.store_sub', 'pharmacy_item_details',
            'ecommerce_item_details.brand.storage', 'nutritions', 'allergies', 'generic', 'taxVats',
        ]);

        foreach ($models as $model) {
            if ($model->module?->module_type === 'ecommerce' && $model->isRelation('seoData')) {
                $model->loadMissing('seoData');
            }

            if ($model->video && $model->isRelation('storage')) {
                $model->loadMissing('storage');
            }
        }

        $this->loadMissingSupported($models, ['translations', 'unit', 'storage']);
        $this->loadMissingSupported($models, [
            'rating' => fn ($query) => $query->where('status', 1),
            'flashSaleItems' => fn ($query) => $query->active()->whereHas('flashSale', fn ($sale) => $sale->active()->running()),
        ]);

        $this->attachAddOns($models);
        $this->attachCategoryNames($models);
        $this->attachTaxes($models);
    }

    /**
     * A campaign item reaches these loaders through the same banner and order payload paths as a
     * product, but App\Models\ItemCampaign defines only a subset of the relations -- no
     * storeCategory, rating, flashSaleItems or seoData -- and loadMissing() throws
     * RelationNotFoundException on the first one it cannot resolve. Loading per model class, and
     * only what that class actually declares, keeps the relation ORDER the payload depends on.
     */
    protected function loadMissingSupported(EloquentCollection $models, array $relations): void
    {
        foreach ($models->groupBy(fn ($model) => get_class($model)) as $group) {
            $collection = new EloquentCollection($group->all());
            $first = $collection->first();
            $supported = [];

            foreach ($relations as $key => $value) {
                $name = is_int($key) ? $value : $key;

                if (! $first->isRelation(explode('.', $name)[0])) {
                    continue;
                }

                if (is_int($key)) {
                    $supported[] = $value;
                } else {
                    $supported[$key] = $value;
                }
            }

            if ($supported) {
                $collection->loadMissing($supported);
            }
        }
    }

    protected function attachAddOns(EloquentCollection $models): void
    {
        $idsPerItem = [];
        foreach ($models as $item) {
            $idsPerItem[$item->id] = array_values(array_filter(
                array_map('intval', Helpers::decodeJsonToArray($item->add_ons))
            ));
        }

        $allIds = array_values(array_unique(array_merge(...array_values($idsPerItem) ?: [[]])));

        $addOns = $allIds
            ? app(AddonService::class)->getActiveByIdsWithTaxes($allIds)
            : collect();

        foreach ($models as $item) {
            $item->resolved_add_ons = collect($idsPerItem[$item->id] ?? [])
                ->map(fn ($id) => $addOns->get($id))
                ->filter()
                ->map(fn ($addOn) => clone $addOn)
                ->values();
        }
    }

    protected function attachCategoryNames(EloquentCollection $models): void
    {
        $idsPerItem = [];
        foreach ($models as $item) {
            $idsPerItem[$item->id] = collect(Helpers::decodeJsonToArray($item->category_ids))
                ->map(fn ($value) => (string) data_get($value, 'id'))
                ->filter()
                ->values()
                ->all();
        }

        $allIds = array_values(array_unique(array_merge(...array_values($idsPerItem) ?: [[]])));

        $names = $allIds
            ? app(CategoryService::class)->getNamesByIds($allIds)
            : collect();

        foreach ($models as $item) {
            $item->resolved_category_names = $names;
        }
    }

    protected function attachTaxes(EloquentCollection $models): void
    {
        $idsPerItem = [];
        foreach ($models as $item) {
            $idsPerItem[$item->id] = $item->relationLoaded('taxVats')
                ? $item->getRelation('taxVats')->pluck('tax_id')->filter()->values()->all()
                : [];
        }

        $allIds = array_values(array_unique(array_merge(...array_values($idsPerItem) ?: [[]])));
        $taxes = $allIds ? app(TaxService::class)->getByIds($allIds) : collect();

        foreach ($models as $item) {
            $item->resolved_taxes = collect($idsPerItem[$item->id] ?? [])
                ->map(fn ($id) => $taxes->get($id))
                ->filter()
                ->values();
        }
    }
}
