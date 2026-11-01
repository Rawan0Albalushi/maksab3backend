<?php

namespace App\Http\Resources\Customer\Order;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class ReorderResultResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $result = $this->resource;

        return array_merge(parent::toArray($request), [
            'cart_count' => (int) $result['cart_count'],
            'added_count' => count($result['added'] ?? []),
            'skipped_count' => count($result['skipped'] ?? []),
            'unavailable_items' => $this->rows($result['unavailable'] ?? []),
            'skipped_items' => $this->rows($result['skipped'] ?? []),
        ]);
    }

    private function rows(array $rows): array
    {
        return array_map(fn ($row) => [
            'id' => $row['item_id'],
            'name' => $row['item_name'],
            'code' => $row['code'],
            'message' => $row['message'],
        ], $rows);
    }
}
