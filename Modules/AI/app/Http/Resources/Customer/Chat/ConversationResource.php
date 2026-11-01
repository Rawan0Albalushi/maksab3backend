<?php

namespace Modules\AI\app\Http\Resources\Customer\Chat;

use App\Http\Resources\BaseResource;

class ConversationResource extends BaseResource
{
    public function toArray($request): array
    {
        return array_merge(parent::toArray($request), [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'messages_count' => (int) $this->resource->messages_count,
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ]);
    }
}
