<?php

namespace App\Services\Chat;

use App\Models\Message;
use App\Services\BaseService;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Support\Storage\FileStorage;

class MessageService extends BaseService
{

    private const ORDER_COLUMNS = ['id', 'order_amount', 'order_status', 'created_at', 'delivery_address'];

    public function storeAttachments(array $files): array
    {
        return array_map(
            fn ($file) => ['img' => FileStorage::upload('conversation/', $file), 'storage' => FileStorage::getDisk()],
            array_values(array_filter($files))
        );
    }

    public function getList(
        array $filters = [],
        array $paginate = []
    ): LengthAwarePaginator {
        return Message::where('conversation_id', $filters['conversation_id'] ?? null)
            ->when($filters['with_order'] ?? false, fn ($query) => $query->with([
                'order' => fn ($order) => $order->select(self::ORDER_COLUMNS),
            ]))
            ->latest()
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function markSeenForViewer(mixed $conversationId, mixed $viewerInfoId): void
    {
        Message::where('conversation_id', $conversationId)
            ->where('sender_id', '!=', $viewerInfoId)
            ->update(['is_seen' => 1]);
    }

    public function send(array $payload): Message
    {
        $message = new Message();
        $message->conversation_id = $payload['conversation_id'];
        $message->sender_id = $payload['sender_id'];
        $message->message = $payload['message'] ?? null;

        if (array_key_exists('order_id', $payload)) {
            $message->order_id = $payload['order_id'];
        }

        if (! empty($payload['files'])) {
            $message->file = json_encode($payload['files'], JSON_UNESCAPED_SLASHES);
        }

        if (! empty($payload['sent_at'])) {
            $message->created_at = $payload['sent_at'];
            $message->updated_at = $payload['sent_at'];
        }

        $message->save();

        return $message;
    }
}
