<?php

namespace Modules\AI\app\Services\Chat;

use App\Services\BaseService;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\AI\app\Models\AiConversation;

class ConversationService extends BaseService
{
    private const LIST_COLUMNS = ['id', 'title', 'created_at', 'updated_at'];

    private const ACTIVE = 'active';

    private const ARCHIVED = 'archived';

    public function getList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return $this->ownedQuery($filters)
            ->where('status', self::ACTIVE)
            ->select(self::LIST_COLUMNS)
            ->withCount('messages')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function find(mixed $id, array $filters = []): ?AiConversation
    {
        return $this->ownedQuery($filters)->find($id);
    }

    public function create(array $data): AiConversation
    {
        return AiConversation::create([
            'user_id' => $data['user_id'] ?? null,
            'guest_id' => ($data['user_id'] ?? null) ? null : ($data['guest_id'] ?? null),
            'module_id' => $data['module_id'] ?? null,
            'zone_id' => $data['zone_id'] ?? null,
            'status' => self::ACTIVE,
        ]);
    }

    public function archive(AiConversation $conversation): bool
    {
        return $conversation->update(['status' => self::ARCHIVED]);
    }

    public function resolve(array $data): AiConversation
    {
        $existing = $data['conversation_id']
            ? $this->find($data['conversation_id'], $data)
            : null;

        return $existing?->status === self::ACTIVE ? $existing : $this->create($data);
    }

    private function ownedQuery(array $filters)
    {
        $userId = $filters['user_id'] ?? null;
        $guestId = $filters['guest_id'] ?? null;

        return AiConversation::query()
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->when(! $userId && $guestId, fn ($query) => $query->where('guest_id', $guestId))
            ->when(! $userId && ! $guestId, fn ($query) => $query->whereRaw('1 = 0'));
    }
}
