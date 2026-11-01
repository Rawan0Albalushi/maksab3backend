@php($attachments = \App\CentralLogics\Helpers::decodeJsonToArray($review->attachment))

<tr>
    <td><span class="rvw-id">{{ $review->review_id }}</span></td>
    <td>
        @if($review->item)
            <div class="media align-items-center rvw-item">
                <img class="avatar avatar-lg mr-3 onerror-image"
                     src="{{ $review->item->image_full_url ?? asset('public/assets/admin/img/160x160/img2.jpg') }}"
                     data-onerror-image="{{ asset('public/assets/admin/img/160x160/img2.jpg') }}"
                     alt="{{ $review->item->name }}">
                <div class="media-body cell--truncate">
                    <a class="rvw-item__name" href="{{ route('admin.item.view', [$review->item->id]) }}">
                        {{ Str::limit($review->item->name, 30) }}
                    </a>
                    @if($review->item->store)
                        <span class="rvw-meta"><i class="tio-shop"></i> {{ Str::limit($review->item->store->name, 26) }}</span>
                    @endif
                    @if($review->order_id)
                        <a class="rvw-meta rvw-meta--link" href="{{ route('admin.order.details', ['id' => $review->order_id]) }}">
                            <i class="tio-receipt-outlined"></i> {{ translate('messages.Order ID') }}: {{ $review->order_id }}
                        </a>
                    @endif
                </div>
            </div>
        @else
            <span class="rvw-muted">{{ translate('messages.Item deleted') }}</span>
        @endif
    </td>
    <td>
        @if($review->customer)
            <a class="media align-items-center rvw-customer" href="{{ route('admin.users.customer.view', [$review->user_id]) }}">
                <img class="avatar avatar-sm avatar-circle mr-2 onerror-image"
                     src="{{ $review->customer->image_full_url ?? asset('public/assets/admin/img/160x160/img1.jpg') }}"
                     data-onerror-image="{{ asset('public/assets/admin/img/160x160/img1.jpg') }}"
                     alt="{{ $review->customer->f_name }}">
                <div class="media-body cell--truncate">
                    <span class="rvw-customer__name">{{ Str::limit($review->customer->f_name.' '.$review->customer->l_name, 22) }}</span>
                    <span class="rvw-meta">{{ $review->customer->phone }}</span>
                </div>
            </a>
        @else
            <span class="rvw-muted">{{ translate('No data found') }}</span>
        @endif
    </td>
    <td class="rvw-cell--text">
        <div class="rvw-rating">
            @include('admin-views.product.partials._rating-stars', ['rating' => $review->rating])
            <span class="rvw-rating__value">{{ $review->rating }}</span>
        </div>
        @if($review->comment)
            <p class="rvw-comment" data-toggle="tooltip" data-placement="top" title="{{ $review->comment }}">{{ $review->comment }}</p>
        @else
            <span class="rvw-muted">{{ translate('messages.No comment left') }}</span>
        @endif
        @if(count($attachments))
            <span class="rvw-chip"><i class="tio-image"></i> {{ count($attachments) }}</span>
        @endif
    </td>
    <td class="rvw-cell--text">
        @if($review->reply)
            <p class="rvw-comment" data-toggle="tooltip" data-placement="top" title="{{ $review->reply }}">{{ $review->reply }}</p>
            @if($review->replied_at)
                <span class="rvw-meta">{{ \App\CentralLogics\Helpers::date_format($review->replied_at) }}</span>
            @endif
        @else
            <span class="rvw-pill rvw-pill--warn">{{ translate('messages.Awaiting reply') }}</span>
        @endif
    </td>
    <td>
        <div class="rvw-date">
            <span>{{ \App\CentralLogics\Helpers::date_format($review->created_at) }}</span>
            <span class="rvw-meta">{{ \App\CentralLogics\Helpers::time_format($review->created_at) }}</span>
        </div>
    </td>
    <td>
        <div class="status-toggle" data-status="{{ $review->status ? 1 : 0 }}">
            <label class="toggle-switch toggle-switch-sm" for="reviewCheckbox{{ $review->id }}">
                <input type="checkbox" id="reviewCheckbox{{ $review->id }}"
                       class="toggle-switch-input status_form_alert"
                       data-id="status-{{ $review->id }}"
                       data-label-on="{{ translate('messages.Visible') }}"
                       data-label-off="{{ translate('messages.Hidden') }}"
                       data-message="{{ $review->status ? translate('messages.You want to hide this review for customer') : translate('messages.You want to show this review for customer') }}"
                       @if($filters['visibility']) data-ajax-refresh="[data-ajax-region]" @endif
                       {{ $review->status ? 'checked' : '' }}>
                <span class="toggle-switch-label">
                    <span class="toggle-switch-indicator"></span>
                </span>
            </label>
            <span class="status-toggle__text" aria-live="polite">
                {{ $review->status ? translate('messages.Visible') : translate('messages.Hidden') }}
            </span>
        </div>
        <form action="{{ route('admin.item.reviews.status', [$review->id, $review->status ? 0 : 1]) }}" method="get" id="status-{{ $review->id }}"></form>
    </td>
</tr>
