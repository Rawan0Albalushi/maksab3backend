{{-- ------------------------------------------------------------------------
     Conversation rows for the admin inbox rail.

     Rendered both on first paint and on every AJAX refresh, so this file must
     stay self-contained: no wrapper, no scripts, rows only. The classes
     .customer-list / .conv-active and the customer-{id} ids are read by
     conversationList() in the admin layout -- keep them.
     ------------------------------------------------------------------------ --}}

@forelse ($conversations as $conv)
    @php
        $user = $conv->sender_type == 'admin' ? $conv->receiver : $conv->sender;
        $lastMessage = $conv->last_message;
    @endphp

    @if ($user)
        @php
            // Only count as unread when the newest message came from the other
            // side: unread_message_count is also bumped for admin's own replies.
            $unread = $lastMessage?->sender_id == $user->id ? (int) $conv->unread_message_count : 0;
            $isReply = $lastMessage && $lastMessage->sender_id != $user->id;
            $preview = trim((string) ($lastMessage?->message ?? ''));
            $hasAttachment = ! empty($lastMessage?->file_full_url);
            $isDeliveryMan = (bool) $user->deliveryman_id;

            $sentAt = $lastMessage?->created_at ?? $conv->last_message_time;
            $sentAt = $sentAt ? \Carbon\Carbon::parse($sentAt) : null;
            $stamp = match (true) {
                ! $sentAt => '',
                $sentAt->isToday() => \App\CentralLogics\Helpers::time_format($sentAt),
                $sentAt->isYesterday() => translate('messages.yesterday'),
                $sentAt->isCurrentYear() => $sentAt->translatedFormat('d M'),
                default => $sentAt->translatedFormat('d M Y'),
            };
        @endphp

        <div class="msg-item customer-list view-conv {{ $unread ? 'is-unread' : '' }}" id="customer-{{ $user->id }}"
            role="button" tabindex="0" data-url="{{ route('admin.message.view', ['conversation_id' => $conv->id, 'user_id' => $user->id]) }}"
            data-conv-id="{{ $conv->id }}" data-sender-id="{{ $user->id }}"
            aria-label="{{ trim($user->f_name . ' ' . $user->l_name) }}">

            @include('partials._user-avatar', [
                'imageUrl' => $user['image_full_url'],
                'proStatus' => $user['pro_status'] ?? false,
                'size' => 42,
                'wrapperClass' => 'msg-item__avatar',
            ])

            <div class="msg-item__body">
                <div class="msg-item__row">
                    <h6 class="msg-item__name">{{ trim($user->f_name . ' ' . $user->l_name) ?: translate('No data found') }}</h6>
                    <span class="msg-item__time">{{ $stamp }}</span>
                </div>

                <div class="msg-item__row">
                    <p class="msg-item__preview">
                        @if ($isReply)
                            {{ translate('messages.You') }}:
                        @endif

                        @if ($preview !== '')
                            {{ Str::limit($preview, 46, '...') }}
                        @elseif ($hasAttachment)
                            <i class="tio-image"></i>{{ translate('Attachment') }}
                        @else
                            {{ translate('messages.No messages yet') }}
                        @endif
                    </p>

                    @if ($unread)
                        <span class="msg-item__badge">{{ $unread > 99 ? '99+' : $unread }}</span>
                    @endif
                </div>

                <div class="msg-item__meta">
                    <span class="msg-chip {{ $isDeliveryMan ? 'msg-chip--deliveryman' : 'msg-chip--customer' }}">
                        <i class="{{ $isDeliveryMan ? 'tio-bike' : 'tio-user-outlined' }}"></i>
                        {{ $isDeliveryMan ? translate('Deliveryman') : translate('messages.Customer') }}
                    </span>
                    @if ($user->phone)
                        <span class="msg-item__phone" dir="ltr">{{ $user->phone }}</span>
                    @endif
                </div>
            </div>
        </div>
    @else
        <div class="msg-item customer-list" aria-disabled="true">
            <div class="msg-item__avatar">
                <img class="rounded-circle" width="42" height="42"
                    src="{{ asset('public/assets/admin/img/160x160/img1.jpg') }}" alt="">
            </div>
            <div class="msg-item__body">
                <div class="msg-item__row">
                    <h6 class="msg-item__name">{{ translate('No data found') }}</h6>
                </div>
                <p class="msg-item__preview">{{ translate('messages.This account is no longer available') }}</p>
            </div>
        </div>
    @endif
@empty
    <div class="msg-empty">
        <i class="tio-comment-outlined"></i>
        <h6>{{ translate('No conversation found') }}</h6>
        <p>{{ translate('messages.Conversations started by customers and delivery men will show up here') }}</p>
    </div>
@endforelse
