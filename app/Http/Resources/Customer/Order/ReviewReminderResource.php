<?php

namespace App\Http\Resources\Customer\Order;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class ReviewReminderResource extends BaseResource
{
    public function __construct(mixed $resource, private readonly array $images = [])
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'order_id' => $this->resource?->id,
            'images' => $this->images,
        ]);
    }
}
