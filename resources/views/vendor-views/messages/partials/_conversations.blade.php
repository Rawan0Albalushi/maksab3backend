@php
    use App\CentralLogics\Helpers;

    $isDeliveryMan = $user_type === 'delivery_man';
    $fullName = trim(($user->f_name ?? '') . ' ' . ($user->l_name ?? ''));

    $days = collect($convs)->groupBy(fn ($message) => \Carbon\Carbon::parse($message->created_at)->toDateString());

    $statusTone = fn ($status) => match (true) {
        in_array($status, ['delivered']) => 'done',
        in_array($status, ['refund_requested', 'refunded', 'refund_request_canceled', 'canceled', 'failed']) => 'failed',
        default => 'progress',
    };
@endphp

<div class="msg-thread">

    <header class="msg-thread__head">
        <button type="button" class="msg-thread__back" aria-label="{{ translate('Back') }}">
            <i class="tio-chevron-left"></i>
        </button>

        <div class="msg-thread__identity">
            @include('partials._user-avatar', [
                'imageUrl' => $user['image_full_url'],
                'proStatus' => $user['pro_status'] ?? false,
                'size' => 44,
            ])

            <div class="w-0 flex-grow-1">
                <h5 class="msg-thread__name">{{ $fullName ?: translate('No data found') }}</h5>
                <div class="msg-thread__sub">
                    <span class="msg-chip {{ $isDeliveryMan ? 'msg-chip--deliveryman' : 'msg-chip--customer' }}">
                        <i class="{{ $isDeliveryMan ? 'tio-bike' : 'tio-user-outlined' }}"></i>
                        {{ $isDeliveryMan ? translate('Deliveryman') : translate('messages.Customer') }}
                    </span>
                    @if ($user->phone)
                        <span dir="ltr"><i class="tio-call"></i> {{ $user->phone }}</span>
                    @endif
                </div>
            </div>
        </div>
    </header>

    <div class="msg-thread__body" id="msgThreadBody">
        @forelse ($days as $date => $messages)
            @php $day = \Carbon\Carbon::parse($date); @endphp

            <div class="msg-day">
                <span>
                    @if ($day->isToday())
                        {{ translate('messages.Today') }}
                    @elseif ($day->isYesterday())
                        {{ translate('messages.yesterday') }}
                    @else
                        {{ Helpers::date_format($day) }}
                    @endif
                </span>
            </div>

            @php $previousSenderId = null; @endphp

            @foreach ($messages as $con)
                @php
                    $incoming = $con->sender_id != $vendor->id;
                    $grouped = $previousSenderId === $con->sender_id;
                    $previousSenderId = $con->sender_id;
                    $order = $con->order;
                    $deliveryAddress = $order ? json_decode($order->delivery_address, true) : null;
                @endphp

                <div class="msg-row {{ $incoming ? 'msg-row--in' : 'msg-row--out' }} {{ $grouped && ! $order ? 'is-grouped' : '' }}">
                    <div class="msg-row__avatar">
                        @if ($incoming && ! $grouped)
                            @include('partials._user-avatar', [
                                'imageUrl' => $user['image_full_url'],
                                'proStatus' => false,
                                'size' => 28,
                            ])
                        @endif
                    </div>

                    <div class="msg-row__stack">
                        @if ($order)
                            <div class="msg-order">
                                <div class="msg-order__head">
                                    <span class="msg-order__id">
                                        <i class="tio-shopping-cart-outlined"></i>
                                        {{ translate('messages.Order ID') }} #{{ $order->id }}
                                    </span>
                                    <span class="msg-status msg-status--{{ $statusTone($order->order_status) }}">
                                        {{ translate($order->order_status) }}
                                    </span>
                                </div>

                                <dl class="msg-order__body">
                                    <div class="msg-order__field">
                                        <dt>{{ translate('messages.Total') }}</dt>
                                        <dd class="is-amount">{{ Helpers::format_currency($order->order_amount) }}</dd>
                                    </div>
                                    <div class="msg-order__field">
                                        <dt>{{ translate('Order placed') }}</dt>
                                        <dd>{{ Helpers::date_format($order->created_at) }}</dd>
                                    </div>
                                    @if ($order->details_count > 0)
                                        <div class="msg-order__field">
                                            <dt>{{ translate('messages.Items') }}</dt>
                                            <dd>{{ $order->details_count }}</dd>
                                        </div>
                                    @endif
                                    @if ($deliveryAddress)
                                        <div class="msg-order__field">
                                            <dt>{{ translate('Delivery address') }}</dt>
                                            <dd>
                                                {{ data_get($deliveryAddress, 'address') }}
                                                @if (data_get($deliveryAddress, 'contact_person_number'))
                                                    <br><span dir="ltr">{{ data_get($deliveryAddress, 'contact_person_number') }}</span>
                                                @endif
                                            </dd>
                                        </div>
                                    @endif
                                </dl>
                            </div>
                        @endif

                        @if (trim((string) $con->message) !== '')
                            <div class="msg-bubble">{{ $con->message }}</div>
                        @endif

                        @if (! empty($con->file_full_url))
                            <div class="msg-attachments">
                                @foreach ($con->file_full_url as $image)
                                    <button type="button" class="msg-attachment" data-full-image="{{ $image }}">
                                        <img src="{{ $image }}" alt="{{ translate('Attachment') }}" loading="lazy">
                                    </button>
                                @endforeach
                            </div>
                        @endif

                        <div class="msg-meta">
                            <span>{{ Helpers::time_format($con->created_at) }}</span>
                            @unless ($incoming)
                                <i class="{{ $con->is_seen == 1 ? 'tio-checkmark-circle is-seen' : 'tio-checkmark-circle-outlined' }}"
                                    title="{{ $con->is_seen == 1 ? translate('messages.Seen') : translate('Sent') }}"></i>
                            @endunless
                        </div>
                    </div>
                </div>
            @endforeach
        @empty
            <div class="msg-empty">
                <i class="tio-comment-outlined"></i>
                <h6>{{ translate('messages.No messages yet') }}</h6>
                <p>{{ translate('messages.Send the first message to start this conversation') }}</p>
            </div>
        @endforelse
    </div>

    <form class="msg-composer" id="msgComposer" method="post" enctype="multipart/form-data" action="javascript:"
        data-store-url="{{ route('vendor.message.store', ['user_id' => $user->id, 'user_type' => $user_type]) }}">
        @csrf

        <div class="msg-composer__previews" id="msgComposerPreviews"></div>

        <div class="msg-composer__box">
            <textarea class="msg-composer__input" id="msgComposerInput" name="reply" rows="1"
                placeholder="{{ translate('messages.Write a message') }}"
                aria-label="{{ translate('messages.Write a message') }}"></textarea>

            <div class="msg-composer__tools">
                <label class="msg-tool m-0" title="{{ translate('Attachment') }}">
                    <i class="tio-attachment-diagonal"></i>
                    <input type="file" name="images[]" id="msgComposerFiles" class="d-none" multiple
                        accept="image/jpeg, image/png">
                    <span class="sr-only">{{ translate('Attachment') }}</span>
                </label>

                <div class="msg-emoji">
                    <button type="button" class="msg-tool" id="msgEmojiToggle" title="{{ translate('messages.emoji') }}">
                        <i class="tio-slightly-smilling"></i>
                    </button>
                    <div class="msg-emoji__panel" id="msgEmojiPanel" hidden></div>
                </div>

                <span class="msg-composer__spacer"></span>
                <span class="msg-composer__hint">{{ translate('messages.Shift + Enter for a new line') }}</span>

                <button type="submit" class="msg-send">
                    <i class="tio-send"></i> <span>{{ translate('messages.send') }}</span>
                </button>
            </div>
        </div>
    </form>
</div>
