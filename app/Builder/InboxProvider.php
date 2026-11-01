<?php

namespace App\Builder;

use App\Models\Conversation;
use App\Models\DeliveryMan;
use App\Models\Message;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use App\Models\UserInfo;
use App\Models\Vendor;
use Illuminate\Support\Carbon;
use Modules\Builder\Contracts\InboxProvider as InboxProviderContract;
use Modules\Builder\ValueObjects\Storefront\ConversationDTO;
use Modules\Builder\ValueObjects\Storefront\MessageDTO;
use App\Support\Storage\FileStorage;

class InboxProvider implements InboxProviderContract
{
    public function conversations(int $customerId, ?int $storeId, ?string $search = null, int $limit = 30, int $offset = 1, ?string $openWith = null): array
    {
        $me = $this->ensureUserInfo($customerId);
        if (!$me) {
            return ['conversations' => [], 'total' => 0];
        }

        $allowedOtherIds = $this->reachableUserInfoIds($customerId, $storeId);

        $query = Conversation::query()
            ->with(['sender', 'receiver', 'last_message'])
            ->where(function ($q) use ($me, $allowedOtherIds) {
                $q->where(function ($s) use ($me, $allowedOtherIds) {
                    $s->where('sender_id', $me->id)->whereIn('receiver_id', $allowedOtherIds);
                })->orWhere(function ($s) use ($me, $allowedOtherIds) {
                    $s->where('receiver_id', $me->id)->whereIn('sender_id', $allowedOtherIds);
                });
            })
            ->orderByDesc('last_message_time');

        if ($search !== null && $search !== '') {
            $tokens = preg_split('/\s+/', trim($search)) ?: [];
            $query->where(function ($outer) use ($tokens) {
                $outer
                    ->whereHas('sender', function ($q) use ($tokens) {
                        foreach ($tokens as $t) {
                            $q->where(function ($w) use ($t) {
                                $w->where('f_name', 'like', "%$t%")->orWhere('l_name', 'like', "%$t%");
                            });
                        }
                    })
                    ->orWhereHas('receiver', function ($q) use ($tokens) {
                        foreach ($tokens as $t) {
                            $q->where(function ($w) use ($t) {
                                $w->where('f_name', 'like', "%$t%")->orWhere('l_name', 'like', "%$t%");
                            });
                        }
                    });
            });
        }

        $paginator = $query->paginate(perPage: $limit, page: $offset);
        $rawItems  = $paginator->items();

        $conversations = collect($rawItems)
            ->map(fn (Conversation $c) => $this->mapConversationSummary($c, $me))
            ->filter(fn (array $c) => in_array($c['type'], ['vendor', 'delivery'], true))
            ->values()
            ->all();

        if ($storeId) {
            $vendorInfo = $this->storeVendorUserInfo($storeId);
            if ($vendorInfo) {
                $vendorInfoId = (int) $vendorInfo->id;
                $alreadyInList = collect($rawItems)->contains(
                    fn (Conversation $c) => (int) $c->sender_id === $vendorInfoId
                        || (int) $c->receiver_id === $vendorInfoId,
                );
                $vendorName = $this->fullName($vendorInfo);
                $matchesSearch = $search === null
                    || $search === ''
                    || stripos($vendorName, $search) !== false;

                if (!$alreadyInList && $matchesSearch) {
                    array_unshift($conversations, ConversationDTO::fromArray([
                        'id'          => 0,
                        'type'        => 'vendor',
                        'name'        => $vendorName,
                        'avatar'      => $this->safeImage($vendorInfo),
                        'initials'    => $this->initials($vendorName),
                        'unread'      => 0,
                        'lastMessage' => '',
                        'lastTime'    => '',
                        'pristine'    => true,
                    ])->toArray());
                }
            }
        }

        $dmId = $this->parseOpenWithDmId($openWith);
        if ($dmId !== null && $storeId) {
            $dmInfo = $this->ensureDeliveryManUserInfo($dmId);
            if ($dmInfo && in_array((int) $dmInfo->id, $allowedOtherIds, true)) {
                $dmInfoId = (int) $dmInfo->id;
                $alreadyInList = collect($rawItems)->contains(
                    fn (Conversation $c) => (int) $c->sender_id === $dmInfoId
                        || (int) $c->receiver_id === $dmInfoId,
                );
                if (!$alreadyInList) {
                    $dmName = $this->fullName($dmInfo);
                    array_unshift($conversations, ConversationDTO::fromArray([
                        'id'          => -$dmId,
                        'type'        => 'delivery',
                        'name'        => $dmName,
                        'avatar'      => $this->safeImage($dmInfo),
                        'initials'    => $this->initials($dmName),
                        'unread'      => 0,
                        'lastMessage' => '',
                        'lastTime'    => '',
                        'pristine'    => true,
                    ])->toArray());
                }
            }
        }

        return [
            'conversations' => $conversations,
            'total'         => (int) $paginator->total(),
        ];
    }

    public function conversation(int $customerId, ?int $storeId, int $conversationId, int $limit = 30, int $offset = 1): ?array
    {
        $me = $this->ensureUserInfo($customerId);
        if (!$me) return null;

        if ($conversationId === 0) {
            if (!$storeId) return null;
            $vendorInfo = $this->storeVendorUserInfo($storeId);
            if (!$vendorInfo) return null;
            $name = $this->fullName($vendorInfo);
            return ConversationDTO::fromArray([
                'id'          => 0,
                'type'        => 'vendor',
                'name'        => $name,
                'avatar'      => $this->safeImage($vendorInfo),
                'initials'    => $this->initials($name),
                'unread'      => 0,
                'lastMessage' => '',
                'lastTime'    => '',
                'pristine'    => true,
            ])->toArray() + [
                'messages'    => [],
                'pagination'  => [
                    'page'    => 1,
                    'perPage' => $limit,
                    'total'   => 0,
                    'hasMore' => false,
                ],
            ];
        }

        if ($conversationId < 0) {
            if (!$storeId) return null;
            $dmId = -$conversationId;
            $dmInfo = $this->ensureDeliveryManUserInfo($dmId);
            if (!$dmInfo) return null;
            $allowed = $this->reachableUserInfoIds($customerId, $storeId);
            if (!in_array((int) $dmInfo->id, $allowed, true)) return null;
            $name = $this->fullName($dmInfo);
            return ConversationDTO::fromArray([
                'id'          => $conversationId,
                'type'        => 'delivery',
                'name'        => $name,
                'avatar'      => $this->safeImage($dmInfo),
                'initials'    => $this->initials($name),
                'unread'      => 0,
                'lastMessage' => '',
                'lastTime'    => '',
                'pristine'    => true,
            ])->toArray() + [
                'messages'    => [],
                'pagination'  => [
                    'page'    => 1,
                    'perPage' => $limit,
                    'total'   => 0,
                    'hasMore' => false,
                ],
            ];
        }

        $allowedOtherIds = $this->reachableUserInfoIds($customerId, $storeId);
        if (empty($allowedOtherIds)) return null;

        $convo = Conversation::query()
            ->with(['sender', 'receiver', 'last_message'])
            ->where('id', $conversationId)
            ->where(function ($q) use ($me, $allowedOtherIds) {
                $q->where(function ($s) use ($me, $allowedOtherIds) {
                    $s->where('sender_id', $me->id)->whereIn('receiver_id', $allowedOtherIds);
                })->orWhere(function ($s) use ($me, $allowedOtherIds) {
                    $s->where('receiver_id', $me->id)->whereIn('sender_id', $allowedOtherIds);
                });
            })
            ->first();

        if (!$convo) return null;

        
        $lastFromOther = $convo->last_message
            && (int) $convo->last_message->sender_id !== (int) $me->id;
        if ($lastFromOther && $convo->unread_message_count > 0) {
            $convo->unread_message_count = 0;
            $convo->save();
        }
        Message::query()
            ->where('conversation_id', $convo->id)
            ->where('sender_id', '!=', $me->id)
            ->where('is_seen', '!=', 1)
            ->update(['is_seen' => 1]);

        $messagePage = Message::query()
            ->where('conversation_id', $convo->id)
            ->with('order')
            ->latest('id')
            ->paginate(perPage: $limit, page: $offset);

        $summary = $this->mapConversationSummary($convo, $me);

        return array_merge($summary, [
            'messages'   => collect($messagePage->items())
                ->reverse()
                ->values()
                ->map(fn (Message $m) => $this->mapMessage($m, $me))
                ->all(),
            'pagination' => [
                'page'    => (int) $messagePage->currentPage(),
                'perPage' => (int) $messagePage->perPage(),
                'total'   => (int) $messagePage->total(),
                'hasMore' => $messagePage->hasMorePages(),
            ],
        ]);
    }

    public function sendMessage(int $customerId, ?int $storeId, array $input, ?array $files = null): array
    {
        $me = $this->ensureUserInfo($customerId);
        if (!$me) return ['error' => 'Customer not found.'];

        $messageText = trim((string) ($input['message'] ?? ''));
        $hasFiles    = is_array($files) && count(array_filter($files)) > 0;

        if ($messageText === '' && !$hasFiles) {
            return ['error' => 'Message cannot be empty.'];
        }

        $allowedOtherIds = $this->reachableUserInfoIds($customerId, $storeId);
        if (empty($allowedOtherIds)) {
            return ['error' => 'No one is available to chat with from this storefront.'];
        }

        $convo = null;
        $receiverId = null;

        $cid = (int) ($input['conversationId'] ?? 0);
        if ($cid > 0) {
            $convo = Conversation::query()
                ->where('id', $cid)
                ->where(function ($q) use ($me, $allowedOtherIds) {
                    $q->where(function ($s) use ($me, $allowedOtherIds) {
                        $s->where('sender_id', $me->id)->whereIn('receiver_id', $allowedOtherIds);
                    })->orWhere(function ($s) use ($me, $allowedOtherIds) {
                        $s->where('receiver_id', $me->id)->whereIn('sender_id', $allowedOtherIds);
                    });
                })
                ->first();

            if (!$convo) return ['error' => 'Conversation not found or not allowed from this storefront.'];

            $receiverId = $convo->sender_id === $me->id ? $convo->receiver_id : $convo->sender_id;
        } else {
            $type = $input['receiverType'] ?? null;
            $rid  = (int) ($input['receiverId'] ?? 0);

            if ($cid === 0 && !$type && !$rid && $storeId) {
                $vendorInfo = $this->storeVendorUserInfo($storeId);
                if ($vendorInfo) {
                    $type = 'vendor';
                    $receiverId = (int) $vendorInfo->id;
                }
            }

            if ($cid < 0 && !$type && !$rid && $storeId) {
                $dmId = -$cid;
                $dmInfo = $this->ensureDeliveryManUserInfo($dmId);
                if ($dmInfo) {
                    $type = 'delivery_man';
                    $receiverId = (int) $dmInfo->id;
                }
            }

            if ($receiverId === null) {
                if ($type === 'vendor' && $rid > 0) {
                    $receiverId = $this->ensureVendorUserInfo($rid)?->id;
                } elseif ($type === 'delivery_man' && $rid > 0) {
                    $receiverId = $this->ensureDeliveryManUserInfo($rid)?->id;
                } else {
                    return ['error' => 'Invalid recipient.'];
                }

                if ($receiverId === null) return ['error' => 'Recipient not found.'];
            }

            if (!in_array($receiverId, $allowedOtherIds, true)) {
                return ['error' => 'You can only chat with this storefront\'s vendor or delivery men assigned to your orders.'];
            }

            $convo = Conversation::WhereConversation($me->id, $receiverId)->first();
        }

        if (!$convo) {
            $convo = new Conversation();
            $convo->sender_id           = $me->id;
            $convo->sender_type         = 'customer';
            $convo->receiver_id         = $receiverId;
            $convo->receiver_type       = $type ?: ($input['receiverType'] ?? 'admin');
            $convo->unread_message_count = 0;
            $convo->last_message_time   = Carbon::now();
            $convo->save();
        }

        $imagePayload = null;
        $uploadFailures = 0;
        if ($hasFiles) {
            $imagePayload = [];
            foreach ($files as $file) {
                if (!$file) continue;
                try {
                    $name = FileStorage::upload('conversation/', $file);
                    $imagePayload[] = ['img' => $name, 'storage' => FileStorage::getDisk()];
                } catch (\Throwable $e) {
                    $uploadFailures++;
                    \Log::warning('Inbox attachment upload failed', [
                        'customer_id' => $customerId,
                        'file_name'   => method_exists($file, 'getClientOriginalName') ? $file->getClientOriginalName() : null,
                        'error'       => $e->getMessage(),
                    ]);
                }
            }
            if (empty($imagePayload)) $imagePayload = null;
        }

        if ($messageText === '' && $hasFiles && $imagePayload === null) {
            return ['error' => 'Could not upload the attachment(s). Please try again.'];
        }

        $message = new Message();
        $message->conversation_id = $convo->id;
        $message->sender_id       = $me->id;
        $message->message         = $messageText !== '' ? $messageText : null;
        // Only one of this customer's own orders in this store may be referenced; the id was stored
        // as sent, so a message could point the store at any order on the platform.
        $orderId = !empty($input['orderId']) ? (int) $input['orderId'] : null;
        $message->order_id = $orderId && \App\Models\Order::whereKey($orderId)
            ->where(['user_id' => $customerId, 'is_guest' => 0])
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->exists() ? $orderId : null;
        if ($imagePayload) {
            $message->file = json_encode($imagePayload, JSON_UNESCAPED_SLASHES);
        }
        $message->save();

        $convo->unread_message_count = (int) ($convo->unread_message_count ?? 0) + 1;
        $convo->last_message_id      = $message->id;
        $convo->last_message_time    = Carbon::now();
        $convo->save();

        return [
            'conversationId' => (int) $convo->id,
            'status'         => true,
        ];
    }

    public function mostRecentConversationId(int $customerId, ?int $storeId, ?string $openWith = null): ?int
    {
        $me = $this->ensureUserInfo($customerId);
        if (!$me) return null;

        $allowedOtherIds = $this->reachableUserInfoIds($customerId, $storeId);
        if (empty($allowedOtherIds)) return null;

        $dmId = $this->parseOpenWithDmId($openWith);
        if ($dmId !== null && $storeId) {
            $dmInfo = $this->ensureDeliveryManUserInfo($dmId);
            if ($dmInfo && in_array((int) $dmInfo->id, $allowedOtherIds, true)) {
                $existing = Conversation::WhereConversation($me->id, $dmInfo->id)
                    ->orderByDesc('last_message_time')
                    ->value('id');
                if ($existing) return (int) $existing;
                return -$dmId;
            }
        }

        $vendorPrefer = $openWith === 'vendor';
        if ($vendorPrefer && $storeId) {
            $vendorInfo = $this->storeVendorUserInfo($storeId);
            if ($vendorInfo) {
                $existing = Conversation::WhereConversation($me->id, $vendorInfo->id)
                    ->orderByDesc('last_message_time')
                    ->value('id');
                if ($existing) return (int) $existing;
                return 0;
            }
        }

        $row = Conversation::query()
            ->where(function ($q) use ($me, $allowedOtherIds) {
                $q->where(function ($s) use ($me, $allowedOtherIds) {
                    $s->where('sender_id', $me->id)->whereIn('receiver_id', $allowedOtherIds);
                })->orWhere(function ($s) use ($me, $allowedOtherIds) {
                    $s->where('receiver_id', $me->id)->whereIn('sender_id', $allowedOtherIds);
                });
            })
            ->orderByDesc('last_message_time')
            ->value('id');

        if ($row) return (int) $row;

        if ($storeId && $this->storeVendorUserInfo($storeId)) {
            return 0;
        }
        return null;
    }

    private function reachableUserInfoIds(int $customerId, ?int $storeId): array
    {
        if (!$storeId) return [];

        static $cache = [];
        $cacheKey = "$customerId:$storeId";
        if (isset($cache[$cacheKey])) return $cache[$cacheKey];

        $vendorId = Store::query()->where('id', $storeId)->value('vendor_id');

        $deliveryManIds = Order::query()
            ->where('user_id', $customerId)
            ->where('store_id', $storeId)
            ->whereNotNull('delivery_man_id')
            ->pluck('delivery_man_id')
            ->unique()
            ->values()
            ->all();

        $rows = UserInfo::query()
            ->where(function ($q) use ($vendorId, $deliveryManIds) {
                if ($vendorId) {
                    $q->where('vendor_id', $vendorId);
                }
                if (!empty($deliveryManIds)) {
                    $q->orWhereIn('deliveryman_id', $deliveryManIds);
                }
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $cache[$cacheKey] = $rows;
    }


    private function mapConversationSummary(Conversation $convo, UserInfo $me): array
    {
        $isMeOriginalSender = (int) $convo->sender_id === (int) $me->id;
        $other              = $isMeOriginalSender ? $convo->receiver : $convo->sender;
        $otherType          = $isMeOriginalSender ? $convo->receiver_type : $convo->sender_type;

        $resolvedType = $otherType;
        if ($other) {
            if (!empty($other->vendor_id))            $resolvedType = 'vendor';
            elseif (!empty($other->deliveryman_id))   $resolvedType = 'delivery_man';
        }

        $name        = $this->fullName($other);
        $lastMessage = $convo->last_message;

        $lastFromOther = $lastMessage && (int) $lastMessage->sender_id !== (int) $me->id;
        $unread = $lastFromOther ? (int) ($convo->unread_message_count ?? 0) : 0;

        return ConversationDTO::fromArray([
            'id'          => (int) $convo->id,
            'type'        => $this->normalizeType($resolvedType),
            'name'        => $name,
            'avatar'      => $this->safeImage($other),
            'initials'    => $this->initials($name),
            'unread'      => $unread,
            'lastMessage' => $this->lastMessagePreview($lastMessage),
            'lastTime'    => $this->formatTime($convo->last_message_time ?? optional($lastMessage)->created_at),
        ])->toArray();
    }

    private function mapMessage(Message $msg, UserInfo $me): array
    {
        $attachments = [];
        try {
            $urls = $msg->file_full_url ?? [];
            if (is_array($urls)) {
                foreach ($urls as $url) {
                    $name = $url ? basename(parse_url($url, PHP_URL_PATH) ?? $url) : null;
                    $attachments[] = [
                        'url'  => $url,
                        'name' => $name,
                        'type' => 'image',
                    ];
                }
            }
        } catch (\Throwable) {
        }

        return MessageDTO::fromArray([
            'id'         => (int) $msg->id,
            'sender'     => (int) $msg->sender_id === (int) $me->id ? 'me' : 'them',
            'text'       => $msg->message,
            'attachments' => $attachments,
            'time'       => $this->formatTime($msg->created_at),
            'createdAt'  => optional($msg->created_at)->toIso8601String(),
            'order'      => $msg->order ? [
                'id'     => (int) $msg->order->id,
                'amount' => (float) $msg->order->order_amount,
                'count'  => (int) ($msg->order->details_count ?? 0),
            ] : null,
        ])->toArray();
    }

    private function ensureUserInfo(int $customerId): ?UserInfo
    {
        $existing = UserInfo::where('user_id', $customerId)->first();
        if ($existing) return $existing;

        $user = User::find($customerId);
        if (!$user) return null;

        $info = new UserInfo();
        $info->user_id = $user->id;
        $info->f_name  = $user->f_name;
        $info->l_name  = $user->l_name;
        $info->phone   = $user->phone;
        $info->email   = $user->email;
        $info->image   = $user->image;
        $info->save();
        return $info;
    }

    private function parseOpenWithDmId(?string $openWith): ?int
    {
        if (!$openWith) return null;
        if (!preg_match('/^dm:(\d+)$/', $openWith, $m)) return null;
        $id = (int) $m[1];
        return $id > 0 ? $id : null;
    }

    private function storeVendorUserInfo(int $storeId): ?UserInfo
    {
        static $cache = [];
        if (array_key_exists($storeId, $cache)) return $cache[$storeId];
        $vendorId = Store::query()->where('id', $storeId)->value('vendor_id');
        return $cache[$storeId] = $vendorId ? $this->ensureVendorUserInfo((int) $vendorId) : null;
    }

    private function ensureVendorUserInfo(int $vendorId): ?UserInfo
    {
        $existing = UserInfo::where('vendor_id', $vendorId)->first();
        if ($existing) return $existing;

        $vendor = Vendor::find($vendorId);
        if (!$vendor) return null;
        $store = $vendor->stores[0] ?? null;

        $info = new UserInfo();
        $info->vendor_id = $vendor->id;
        $info->f_name    = $store?->name ?? $vendor->f_name ?? '';
        $info->l_name    = '';
        $info->phone     = $vendor->phone;
        $info->email     = $vendor->email;
        $info->image     = $store?->logo;
        $info->save();
        return $info;
    }

    private function ensureDeliveryManUserInfo(int $deliveryManId): ?UserInfo
    {
        $existing = UserInfo::where('deliveryman_id', $deliveryManId)->first();
        if ($existing) return $existing;

        $dm = DeliveryMan::find($deliveryManId);
        if (!$dm) return null;

        $info = new UserInfo();
        $info->deliveryman_id = $dm->id;
        $info->f_name         = $dm->f_name;
        $info->l_name         = $dm->l_name;
        $info->phone          = $dm->phone;
        $info->email          = $dm->email;
        $info->image          = $dm->image;
        $info->save();
        return $info;
    }

    private function fullName(?UserInfo $info): string
    {
        if (!$info) return 'System User';
        $name = trim(((string) $info->f_name) . ' ' . ((string) $info->l_name));
        return $name !== '' ? $name : 'System User';
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $initials = '';
        foreach ($parts as $p) {
            if ($p === '') continue;
            $initials .= mb_strtoupper(mb_substr($p, 0, 1));
            if (mb_strlen($initials) >= 2) break;
        }
        return $initials !== '' ? $initials : '?';
    }

    private function safeImage(?UserInfo $info): ?string
    {
        if (!$info) return null;
        try {
            return $info->image_full_url ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeType(?string $type): string
    {
        return match ($type) {
            'vendor'       => 'vendor',
            'delivery_man' => 'delivery',
            'admin'        => 'admin',
            default        => $type ?? 'other',
        };
    }

    private function lastMessagePreview(?Message $msg): string
    {
        if (!$msg) return '';
        if ($msg->message) return (string) $msg->message;
        if ($msg->file)    return '📎 Attachment';
        return '';
    }

    private function formatTime(mixed $value): string
    {
        if (!$value) return '';
        try {
            $c = $value instanceof Carbon ? $value : Carbon::parse((string) $value);
            if ($c->isToday())     return $c->format('h:i A');
            if ($c->isYesterday()) return 'Yesterday';
            if ($c->greaterThan(Carbon::now()->subDays(6))) return $c->format('D');
            return $c->format('M j');
        } catch (\Throwable) {
            return '';
        }
    }
}
