<?php

namespace Modules\AI\app\Services\Chat;

use App\Services\BaseService;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\AI\app\Models\AiMessage;

class MessageService extends BaseService
{
    private const VISIBLE_ROLES = ['user', 'assistant'];

    private const LIST_COLUMNS = ['id', 'conversation_id', 'role', 'content', 'tool_name', 'metadata', 'created_at'];

    public function getList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return AiMessage::where('conversation_id', $filters['conversation_id'])
            ->whereIn('role', self::VISIBLE_ROLES)
            ->orderBy('id')
            ->select(self::LIST_COLUMNS)
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }
}
