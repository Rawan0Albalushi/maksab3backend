<?php

namespace Modules\AI\app\Http\Controllers\Api\V1\Customer\Chat;

use App\Http\Controllers\Api\BaseApiController;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AI\app\Http\Resources\Customer\Chat\ConversationResource;
use Modules\AI\app\Services\Chat\ConversationService;
use Modules\AI\app\Traits\ResolvesChatContext;

class ConversationController extends BaseApiController
{
    use ApiRequestContextTrait;
    use ResolvesChatContext;

    public function __construct(
        private readonly ConversationService $conversationService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $identity = $this->chatIdentity($request);

        return $this->chatIdentityError($identity) ?? $this->pagedResponse(
            $this->conversationService->getList($identity, $this->pageParams($request)),
            ConversationResource::class
        );
    }

    public function destroy(Request $request, mixed $id): JsonResponse
    {
        $identity = $this->chatIdentity($request);

        if ($error = $this->chatIdentityError($identity)) {
            return $error;
        }

        $conversation = $this->conversationService->find($id, $identity);

        if (! $conversation) {
            return $this->errorResponse(config('response.default_404'), 'Conversation not found.', 'not_found');
        }

        $this->conversationService->archive($conversation);

        return $this->responseFormatter(config('response.default_delete_200'));
    }
}
