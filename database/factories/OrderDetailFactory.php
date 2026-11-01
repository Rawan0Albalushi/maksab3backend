<?php

namespace Database\Factories;

use App\Http\Resources\Common\Item\ProductResource;
use App\Models\Item;
use App\Models\OrderDetail;
use App\Services\Item\ItemService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderDetailFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = OrderDetail::class;
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        $item_id = $this->faker->numberBetween(1,162177);
        $food = Item::find($item_id);
        if($food)
        {
            app(ItemService::class)->hydrateProductPayload(new EloquentCollection([$food]));
            $product = (new ProductResource($food))->toArray(request());
            return [
                'item_id' => $product['id'],
                'order_id'=> $this->faker->numberBetween(100029,298425),
                'item_campaign_id' => null,
                'food_details' => json_encode($product),
                'quantity' => $this->faker->numberBetween(1,100),
                'created_at' => now(),
                'updated_at' => now()
            ];
        }

    }
}
