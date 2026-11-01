@foreach($reviews as $review)
@if(isset($review->delivery_man))
    @php($attachments = \App\CentralLogics\Helpers::decodeJsonToArray($review->attachment))
    <tr>
        <td>
            <a class="text-dark" href="{{route((isset($review->order) && $review?->order?->order_type=='parcel')?'admin.parcel.order.details':'admin.order.details',[$review->order_id,'module_id'=>$review?->order?->module_id])}}">{{$review->order_id}}</a>
        </td>
        <td>
            <span class="d-block font-size-sm text-body">
                <a href="{{route('admin.users.delivery-man.preview',[$review['delivery_man_id']])}}"
                   class="media gap-2 align-items-center text-dark">
                    <img src="{{ $review->delivery_man->image_full_url }}"
                         class="rounded-circle object-cover" width="48" height="48"
                         alt="{{$review->delivery_man->f_name.' '.$review->delivery_man->l_name}}">
                    <div class="meida-body">
                        <div title="{{$review->delivery_man->f_name.' '.$review->delivery_man->l_name}}">{{$review->delivery_man->f_name.' '.$review->delivery_man->l_name}}</div>
                        <div>{{$review?->delivery_man?->phone}}</div>
                    </div>
                </a>
            </span>
        </td>
        <td>
            @if ($review->customer)
                <a href="{{route('admin.users.customer.view',[$review->user_id])}}" class="text-dark">
                    {{$review->customer->f_name ?? ""}} {{$review->customer->l_name ?? ""}}
                </a>
            @else
                <div class="text-muted">{{translate('No data found')}}</div>
            @endif
        </td>
        <td>
            <div class="d-flex">
                <label class="badge badge-soft-warning mb-0 d-flex align-items-center gap-1 justify-content-center">
                    <span class="d-inline-block mt-3px">{{$review->rating}}</span>
                    <i class="tio-star"></i>
                </label>
            </div>
        </td>
        <td>
            @if($review->comment)
                <div class="cursor-pointer text-wrap max-349 min-w-100px max-text-2-line"
                     data-toggle="tooltip" data-placement="top" title="{{$review->comment}}">
                    {{$review->comment}}
                </div>
            @else
                <span class="text-muted font-size-sm font-italic">{{translate('messages.No comment left')}}</span>
            @endif
            @if(count($attachments))
                <span class="cell-chips d-block mt-1">
                    <span class="cell-chip" title="{{ translate('messages.Review attachment') }}"><i class="tio-image"></i> {{ count($attachments) }}</span>
                </span>
            @endif
        </td>
        <td data-order="{{ $review->created_at }}">
            <span class="table-when">
                <span class="table-when__day">{{ \App\CentralLogics\Helpers::date_format($review->created_at) }}</span>
                <span class="table-when__ago text-uppercase" title="{{ \App\CentralLogics\Helpers::time_date_format($review->created_at) }}">
                    {{ \App\CentralLogics\Helpers::time_format($review->created_at) }}
                </span>
            </span>
        </td>
        <td>
            <div class="status-toggle" data-status="{{ $review->status ? 1 : 0 }}">
                <label class="toggle-switch toggle-switch-sm" for="dmReviewCheckbox{{ $review->id }}">
                    <input type="checkbox" id="dmReviewCheckbox{{ $review->id }}"
                           class="toggle-switch-input status_form_alert"
                           data-id="dm-review-status-{{ $review->id }}"
                           data-label-on="{{ translate('messages.Visible') }}"
                           data-label-off="{{ translate('messages.Hidden') }}"
                           data-message="{{ $review->status ? translate('messages.You want to hide this review for customer') : translate('messages.You want to show this review for customer') }}"
                           {{ $review->status ? 'checked' : '' }}>
                    <span class="toggle-switch-label">
                        <span class="toggle-switch-indicator"></span>
                    </span>
                </label>
                <span class="status-toggle__text" aria-live="polite">
                    {{ $review->status ? translate('messages.Visible') : translate('messages.Hidden') }}
                </span>
            </div>
            <form action="{{ route('admin.users.delivery-man.reviews.status', [$review->id, $review->status ? 0 : 1]) }}" method="get" id="dm-review-status-{{ $review->id }}"></form>
        </td>
        <td>
            <div class="btn--container justify-content-center">
                <a class="btn action-btn action-btn--view" id="view-details" href="#"
                   title="{{ translate('messages.View') }}" data-order_id="{{$review->order_id}}"
                   data-date="{{ \App\CentralLogics\Helpers::time_date_format($review->created_at) }}"
                   data-name="{{$review?->delivery_man?->f_name.' '.$review?->delivery_man?->l_name}}"
                   data-image="{{ $review?->delivery_man?->image_full_url }}"
                   data-phone="{{$review?->delivery_man?->phone}}"
                   data-rating="{{$review->rating}}"
                   data-customer="{{ $review->customer ? trim($review->customer->f_name.' '.$review->customer->l_name) : translate('No data found') }}"
                   data-attachments="{{ json_encode($review->attachment_full_url) }}"
                   data-comment="{{$review->comment}}">
                    <i class="tio-visible-outlined"></i>
                </a>
            </div>
        </td>
    </tr>
@endif
@endforeach
