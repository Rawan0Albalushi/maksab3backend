<?php

namespace App\Http\Resources\Common\Chat;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class ConversationResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'id' => $this->resource->id,
            'sender_type' => $this->resource->sender_type,
            'receiver_type' => $this->resource->receiver_type,
            'unread_message_count' => $this->resource->unread_message_count,
            'last_message_time' => $this->resource->last_message_time,
            'sender' => $this->party('sender'),
            'receiver' => $this->party('receiver'),
            'last_message' => $this->resource->relationLoaded('last_message') && $this->resource->getRelation('last_message')
                ? new MessageResource($this->resource->getRelation('last_message'))
                : null,
        ]);
    }

    private function party(string $relation): mixed
    {
        if (! $this->resource->relationLoaded($relation)) {
            return null;
        }

        $party = $this->resource->getRelation($relation);

        return $party ? new ChatUserResource($party) : null;
    }
}
