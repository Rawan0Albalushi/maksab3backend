<?php

namespace App\Http\Controllers\Api\V1\Vendor\Chat;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Common\Chat\ConversationSearchRequest;
use App\Http\Resources\Common\Chat\ConversationResource;
use App\Http\Resources\Common\Chat\MessageResource;
use App\Services\Chat\ConversationService;
use App\Services\Chat\MessageService;
use App\Services\Chat\UserInfoService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Support\Notification\SendNotification;
use App\Support\Notification\NotificationMessages;

class ConversationController extends BaseApiController
{
    use ApiRequestContextTrait;

    private const VIEWER_TYPE = UserInfoService::TYPE_VENDOR;

    public function __construct(
        private readonly ConversationService $conversationService,
        private readonly MessageService $messageService,
        private readonly UserInfoService $userInfoService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $conversations = $this->conversationService->getList(
            filters: $this->listFilters($request, null),
            paginate: ['per_page' => $this->perPage($request), 'page' => $this->page($request)],
        );

        return $this->responseFormatter(config('response.default_200'), [
            'data' => ConversationResource::collection($conversations),
            'pagination' => $this->paginateFormatter($conversations),
        ]);
    }

    public function search(ConversationSearchRequest $request): JsonResponse
    {
        $filters = $this->listFilters($request, null);
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
        $viewer = $this->viewer($request);
        $conversation = $this->resolveThread($request, $viewer->id);

        if (! $conversation) {
            return $this->emptyThread($request);
        }

        $status = $this->engagement($request, $conversation) > 0;

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
        $sender = $this->viewer($request);
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
        $this->notify($sender, $message, $conversation, $receiverId);

        $thread = $this->conversationService->findThread($conversation->id);

        $messages = $this->messageService->getList(
            filters: ['conversation_id' => $conversation->id],
            paginate: ['per_page' => $this->perPage($request), 'page' => $this->page($request)],
        );

        return $this->responseFormatter(config('response.default_store_201'), [
            'status' => $this->engagement($request, $thread) > 0,
            'conversation' => new ConversationResource($thread),
            'data' => MessageResource::collection($messages),
            'pagination' => $this->paginateFormatter($messages),
        ]);
    }

    private function viewer(Request $request): mixed
    {
        return $this->userInfoService->findByOwner(self::VIEWER_TYPE, $this->vendorId($request));
    }

    private function engagement(Request $request, mixed $conversation): int
    {
        return $this->conversationService->vendorEngagementCount(
            $conversation,
            $this->vendorStoreId($request),
            $this->vendorStore($request)?->module_type
        );
    }

    private function listFilters(Request $request, ?string $type): array
    {
        return [
            'viewer_info_id' => $this->viewer($request)->id,
            'viewer_type' => self::VIEWER_TYPE,
            'type' => $type,
        ];
    }

    private function resolveThread(Request $request, mixed $viewerInfoId): mixed
    {
        if ($request->query('conversation_id')) {
            return $this->conversationService->findThread($request->query('conversation_id'), $viewerInfoId);
        }

        foreach ([UserInfoService::TYPE_DELIVERY_MAN => 'delivery_man_id', UserInfoService::TYPE_CUSTOMER => 'user_id'] as $type => $param) {
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

        if (! $this->mayStartThreadWith($request, $receiverType, $request->input('receiver_id'))) {
            return [null, $receiverType];
        }

        $party = $this->userInfoService->findByOwner($receiverType, $request->input('receiver_id'));

        return [$party?->id, $receiverType];
    }

    /**
     * Who a store may open a new thread with. findByOwner() resolves anyone on the platform by id,
     * and the reply carries the counterparty's name and phone -- walking receiver_id listed every
     * customer's number. A customer needs an order (or booking) with this store, a delivery man must
     * be the store's own or have carried its orders; admin stays reachable for support. Replying in
     * an existing thread goes through conversation_id and is not affected.
     */
    private function mayStartThreadWith(Request $request, ?string $receiverType, mixed $receiverId): bool
    {
        $storeId = $this->vendorStoreId($request);

        return match ($receiverType) {
            UserInfoService::TYPE_ADMIN => true,
            UserInfoService::TYPE_CUSTOMER => \App\Models\Order::where(['store_id' => $storeId, 'user_id' => $receiverId])->exists()
                || (addon_published_status('Service')
                    && \Modules\Service\Entities\ServiceBooking::where(['provider_id' => $storeId, 'user_id' => $receiverId])->exists()),
            UserInfoService::TYPE_DELIVERY_MAN => \App\Models\DeliveryMan::whereKey($receiverId)
                ->where(fn ($query) => $query->where('store_id', $storeId)
                    ->orWhereHas('orders', fn ($orders) => $orders->where('store_id', $storeId)))
                ->exists(),
            default => false,
        };
    }

    private function uploadedFiles(Request $request): array
    {
        return $request->hasFile('image')
            ? $this->messageService->storeAttachments((array) $request->file('image'))
            : [];
    }

    private function notify(mixed $sender, mixed $message, mixed $conversation, mixed $receiverId): void
    {
        $target = $this->userInfoService->getPushTarget($this->userInfoService->find($receiverId));

        SendNotification::sendToDevice($target['token'], NotificationMessages::make(
            translate('messages.Message from').' '.$sender->f_name,
            $message->message ?? translate('Attachment'),
            ['type' => 'message', 'conversation_id' => $conversation->id, 'sender_type' => self::VIEWER_TYPE],
        ));
    }

    private function emptyThread(Request $request): JsonResponse
    {
        $empty = new LengthAwarePaginator([], 0, $this->perPage($request), $this->page($request));

        return $this->responseFormatter(config('response.default_200'), [
            'status' => false,
            'conversation' => null,
            'data' => [],
            'pagination' => $this->paginateFormatter($empty),
        ]);
    }
}
