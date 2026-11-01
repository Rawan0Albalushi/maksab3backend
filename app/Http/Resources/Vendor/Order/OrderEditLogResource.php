<?php

namespace App\Http\Resources\Vendor\Order;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class OrderEditLogResource extends BaseResource
{
    private const REMARK_KEYS = [
        'edited_item_quantity' => 'messages.edited_item_quantity',
        'add_new_item' => 'messages.added_new_item',
        'delete_item' => 'messages.removed_item',
    ];

    public function toArray(Request $request): array
    {
        $editedBy = $this->resource->edited_by ?? 'admin';

        return [
            'id' => $this->resource->id,
            'log' => $this->resource->log,
            'remark' => isset(self::REMARK_KEYS[$this->resource->log])
                ? translate(self::REMARK_KEYS[$this->resource->log])
                : translate(str_replace('_', ' ', $this->resource->log ?? 'edited')),
            'edited_by' => $editedBy,
            'edited_by_label' => translate('messages.'.$editedBy),
            'created_at' => $this->resource->created_at,
        ];
    }
}
