<?php

namespace App\Http\Resources\Customer\Promotion;

use App\Http\Resources\Customer\Store\StoreResource;
use Illuminate\Http\Request;

/**
 * A store on the happy hour list: the ordinary store card, plus what this screen alone needs.
 *
 * Extends StoreResource rather than restating it so this screen cannot drift from every other
 * place a store is rendered -- a card missing `open` or `delivery_time` here would be a bug only
 * this endpoint had.
 *
 * The parent already carries `is_happy_hour_running`, `happy_hour` and `active_discount`, since
 * every store card wants them. Only the full enrolled set is particular to this screen: it lists
 * tonight's window beside the ones still to come, which no other card does.
 */
class HappyHourStoreResource extends StoreResource
{
    public function toArray(Request $request): array
    {
        // Primed by HappyHourCustomerService::primeStoreRows() -- ['ids' => .., 'names' => ..,
        // 'pairs' => ..], same shape StoreListResource builds for /stores/latest and friends, off
        // the same StoreDataTrait::topCategories() query.
        $categories = $this->resource->category_data ?? [];

        return array_merge(parent::toArray($request), [
            'category_ids' => $categories['ids'] ?? [],
            'category_names' => $categories['names'] ?? [],
            'categories' => $categories['pairs'] ?? [],
            // Every window this store is approved on, running or not.
            'happy_hours' => array_map(
                fn ($window) => (new HappyHourResource($window))->render(),
                $this->resource->enrolled_happy_hours ?? []
            ),
        ]);
    }
}
