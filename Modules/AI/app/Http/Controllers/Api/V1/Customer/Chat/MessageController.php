<?php

namespace Modules\AI\app\Http\Controllers\Api\V1\Customer\Chat;

use App\Http\Controllers\Api\BaseApiController;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Modules\AI\app\Http\Requests\Customer\Chat\MessageListRequest;
use Modules\AI\app\Http\Requests\Customer\Chat\SendMessageRequest;
use Modules\AI\app\Http\Resources\Customer\Chat\MessageResource;
use Modules\AI\app\Services\AiChatService;
use Modules\AI\app\Services\Chat\ConversationService;
use Modules\AI\app\Services\Chat\MessageService;
use Modules\AI\app\Traits\ResolvesChatContext;

class MessageController extends BaseApiController
{
    use ApiRequestContextTrait;
    use ResolvesChatContext;

    public function __construct(
        private readonly ConversationService $conversationService,
        private readonly MessageService $messageService,
    ) {
    }

    public function index(MessageListRequest $request): JsonResponse
    {
        $identity = $this->chatIdentity($request);

        if ($error = $this->chatIdentityError($identity)) {
            return $error;
        }

        $conversation = $this->conversationService->find($request->input('conversation_id'), $identity);

        if (! $conversation) {
            return $this->errorResponse(config('response.default_404'), 'Conversation not found.', 'not_found');
        }

        return $this->pagedResponse(
            $this->messageService->getList(['conversation_id' => $conversation->id], $this->pageParams($request)),
            MessageResource::class
        );
    }

    public function store(SendMessageRequest $request): JsonResponse
    {
        if ($this->demoLimitReached('restricted_ai_chat_ip_')) {
            return $this->errorResponse(
                config('response.forbidden_403'),
                translate('Demo mode restriction: AI chat can only be used a limited number of times in demo mode. Further attempts are disabled to maintain a fair demo experience.') . ' ' . translate('Usage limit') . ': 10',
                'demo_limit'
            );
        }

        $identity = $this->chatIdentity($request);

        if ($error = $this->chatIdentityError($identity)) {
            return $error;
        }

        $zoneIds = $this->chatZoneIds($request);
        $moduleId = $this->chatModuleId($request);
        $conversation = $this->conversationService->resolve($identity + [
            'conversation_id' => $request->input('conversation_id'),
            'module_id' => $moduleId,
            'zone_id' => $zoneIds[0] ?? null,
        ]);

        $reply = (new AiChatService(
            $this->chatUser(),
            $moduleId ?: $conversation->module_id,
            $zoneIds ?: array_filter([$conversation->zone_id]),
            $identity['guest_id'],
            $this->chatCoordinate($request, 'latitude'),
            $this->chatCoordinate($request, 'longitude'),
        ))->chat($conversation, $request->input('message'));

        return $this->responseFormatter(config('response.default_store_201'), new MessageResource($reply));
    }

}
