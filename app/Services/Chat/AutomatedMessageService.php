<?php

namespace App\Services\Chat;

use App\Models\AutomatedMessage;
use App\Services\BaseService;
use Illuminate\Pagination\LengthAwarePaginator;

class AutomatedMessageService extends BaseService
{
    public function getList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return AutomatedMessage::where('status', 1)
            ->orderByDesc('id')
            ->select(['id', 'message'])
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function getRiderFaqs(): mixed
    {
        return AutomatedMessage::rider()->get();
    }

    public function findForRider(mixed $questionId): ?AutomatedMessage
    {
        return AutomatedMessage::rider()->where('id', $questionId)->first();
    }
}
