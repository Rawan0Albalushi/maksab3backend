<?php

namespace App\Http\Controllers\Api\V1\DeliveryMan\Chat;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Common\Chat\ConversationSearchRequest;
use App\Http\Requests\DeliveryMan\Chat\AutoMessageStoreRequest;
use App\Http\Resources\Common\Chat\ConversationResource;
use App\Http\Resources\Common\Chat\MessageResource;
use App\Services\Chat\AutomatedMessageService;
use App\Services\Chat\ConversationService;
use App\Services\Chat\MessageService;
use App\Services\Chat\UserInfoService;
use App\Traits\Api\ApiRequestContextTrait;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Support\Notification\SendNotification;
use App\Support\Notification\NotificationMessages;

class ConversationController extends BaseApiController
{
    use ApiRequestContextTrait;

    private const VIEWER_TYPE = UserInfoService::TYPE_DELIVERY_MAN;

    public function __construct(
        private readonly ConversationService $conversationService,
        private readonly MessageService $messageService,
        private readonly UserInfoService $userInfoService,
        private readonly AutomatedMessageService $automatedMessageService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $conversations = $this->conversationService->getList(
            filters: $this->listFilters($request, $request->query('type')),
            paginate: ['per_page' => $this->perPage($request), 'page' => $this->page($request)],
        );

        return $this->responseFormatter(config('response.default_200'), [
            'data' => ConversationResource::collection($conversations),
            'pagination' => $this->paginateFormatter($conversations),
        ]);
    }

    public function search(ConversationSearchRequest $request): JsonResponse
    {
        $filters = $this->listFilters($request, $request->filters()['type']);
        $filters['name'] = $request->filters()['name'];

        $conversations = $this->conversationService->getSearchList(
            filters: $filters,
            paginate: ['per_page' => $this->perPage($request), 'page' => $this->page($request)],
        );

        return $this->responseFormatter(config('response.default_200'), [
            'data' => ConversationResource::collection($conversations),
            'pagination' => $this->paginateFormatter($conversations),
        ]);
    }

    public function show(Request $request): JsonResponse
    {
        $viewer = $this->viewer();
        $conversation = $this->resolveThread($request, $viewer->id);

        if (! $conversation) {
            return $this->emptyThread($request, true);
        }

        $status = $this->conversationService->deliveryManEngagementCount($conversation, $this->deliveryManId()) > 0;

        $this->conversationService->clearUnread($conversation, $viewer->id);
        $this->messageService->markSeenForViewer($conversation->id, $viewer->id);

        $messages = $this->messageService->getList(
            filters: ['conversation_id' => $conversation->id],
            paginate: ['per_page' => $this->perPage($request), 'page' => $this->page($request)],
        );

        return $this->responseFormatter(config('response.default_200'), [
            'status' => $status,
            'conversation' => new ConversationResource($conversation),
            'data' => MessageResource::collection($messages),
            'pagination' => $this->paginateFormatter($messages),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $sender = $this->viewer();
        [$receiverId, $receiverType] = $this->resolveReceiver($request, $sender->id);

        if ($receiverId === null) {
            return $this->errorResponse(
                config('response.unprocessable_entity_422'),
                translate('messages.Invalid receiver'),
                'receiver_id'
            );
        }

        $conversation = $this->conversationService->openThread(
            $sender->id,
            self::VIEWER_TYPE,
            $receiverId,
            $receiverType
        );

        $message = $this->messageService->send([
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'message' => $request->input('message'),
            'files' => $this->uploadedFiles($request),
        ]);

        $this->conversationService->registerMessage($conversation, $message->id);
        $this->notify($sender, $message, $conversation, $receiverId, $receiverType);

        $thread = $this->conversationService->findThread($conversation->id);

        $messages = $this->messageService->getList(
            filters: ['conversation_id' => $conversation->id],
            paginate: ['per_page' => $this->perPage($request), 'page' => $this->page($request)],
        );

        return $this->responseFormatter(config('response.default_store_201'), [
            'status' => $this->conversationService->deliveryManEngagementCount($thread, $this->deliveryManId()) > 0,
            'conversation' => new ConversationResource($thread),
            'data' => MessageResource::collection($messages),
            'pagination' => $this->paginateFormatter($messages),
        ]);
    }

    public function storeAutoMessage(AutoMessageStoreRequest $request): JsonResponse
    {
        $sender = $this->viewer();
        $admin = $this->userInfoService->findOrCreateForAdmin();

        $conversation = $this->conversationService->openThread(
            $sender->id,
            self::VIEWER_TYPE,
            ConversationService::ADMIN_RECEIVER_ID,
            UserInfoService::TYPE_ADMIN
        );

        $automated = $this->automatedMessageService->findForRider($request->input('question_id'));

        $this->messageService->send([
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'message' => $automated?->question,
        ]);

        $replyTime = Carbon::now()->addSeconds(2);
        $reply = $this->messageService->send([
            'conversation_id' => $conversation->id,
            'sender_id' => $admin?->id,
            'message' => $automated?->message,
            'sent_at' => $replyTime,
        ]);

        $this->conversationService->registerMessage($conversation, $reply->id, $replyTime);
        $this->notifyAutoReply($reply, $conversation);

        $messages = $this->messageService->getList(
            filters: ['conversation_id' => $conversation->id],
            paginate: ['per_page' => $this->perPage($request), 'page' => $this->page($request)],
        );

        return $this->responseFormatter(config('response.default_store_201'), [
            'status' => true,
            'conversation' => new ConversationResource($this->conversationService->findThread($conversation->id)),
            'data' => MessageResource::collection($messages),
            'pagination' => $this->paginateFormatter($messages),
        ]);
    }



    private function viewer(): mixed
    {
        return $this->userInfoService->findByOwner(self::VIEWER_TYPE, $this->deliveryManId());
    }

    private function listFilters(Request $request, ?string $type): array
    {
        return [
            'viewer_info_id' => $this->viewer()->id,
            'viewer_type' => self::VIEWER_TYPE,
            'type' => $type,
        ];
    }

    private function resolveThread(Request $request, mixed $viewerInfoId): mixed
    {
        if ($request->query('conversation_id')) {
            return $this->conversationService->findThread($request->query('conversation_id'), $viewerInfoId);
        }

        if ($request->has('admin_id')) {
            return $this->conversationService->findThreadBetween($viewerInfoId, ConversationService::ADMIN_RECEIVER_ID);
        }

        foreach ([UserInfoService::TYPE_VENDOR => 'vendor_id', UserInfoService::TYPE_CUSTOMER => 'user_id'] as $type => $param) {
            if ($request->query($param)) {
                $party = $this->userInfoService->findByOwner($type, $request->query($param));

                return $party ? $this->conversationService->findThreadBetween($viewerInfoId, $party->id) : null;
            }
        }

        return null;
    }

    private function resolveReceiver(Request $request, mixed $senderInfoId): array
    {
        if ($request->input('conversation_id')) {
            return [
                $this->conversationService->counterpartyInfoId($request->input('conversation_id'), $senderInfoId),
                $request->input('receiver_type'),
            ];
        }

        $receiverType = $request->input('receiver_type');

        if ($receiverType === UserInfoService::TYPE_ADMIN) {
            return [ConversationService::ADMIN_RECEIVER_ID, $receiverType];
        }

        $party = $this->userInfoService->findByOwner($receiverType, $request->input('receiver_id'));

        return [$party?->id, $receiverType];
    }

    private function uploadedFiles(Request $request): array
    {
        return $request->hasFile('image')
            ? $this->messageService->storeAttachments((array) $request->file('image'))
            : [];
    }

    private function notify(mixed $sender, mixed $message, mixed $conversation, mixed $receiverId, ?string $receiverType): void
    {
        if ($receiverType === UserInfoService::TYPE_ADMIN || $receiverId == ConversationService::ADMIN_RECEIVER_ID) {
            SendNotification::pushToTopic(
                NotificationMessages::chatMessage(translate('messages.message'), $message),
                'admin_message',
                'message',
            );

            return;
        }

        $payload = NotificationMessages::chatMessage(
            translate('messages.Message from').' '.$sender->f_name,
            $message,
            ['conversation_id' => $conversation->id, 'sender_type' => self::VIEWER_TYPE],
        );

        $target = $this->userInfoService->getPushTarget($this->userInfoService->find($receiverId));
        SendNotification::sendToDevice($target['token'], $payload);

        if ($target['web_topic']) {
            SendNotification::pushToTopic($payload, $target['web_topic'], 'message');
        }
    }

    private function notifyAutoReply(mixed $reply, mixed $conversation): void
    {
        $base = NotificationMessages::chatMessage(translate('messages.message'), $reply);

        SendNotification::pushToTopic($base, 'admin_message', 'message');

        SendNotification::sendToDevice($this->deliveryMan()?->fcm_token, array_merge($base, [
            'title' => translate('messages.Message from admin'),
            'conversation_id' => $conversation->id,
            'sender_type' => UserInfoService::TYPE_ADMIN,
        ]));
    }

    private function emptyThread(Request $request, bool $status): JsonResponse
    {
        $empty = new LengthAwarePaginator([], 0, $this->perPage($request), $this->page($request));

        return $this->responseFormatter(config('response.default_200'), [
            'status' => $status,
            'conversation' => null,
            'data' => [],
            'pagination' => $this->paginateFormatter($empty),
        ]);
    }
}
