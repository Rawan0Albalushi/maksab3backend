@php($order_status_labels = [
    'pending' => translate('Pending'),
    'confirmed' => translate('messages.confirmed'),
    'accepted' => translate('Accepted'),
    'processing' => translate('Processing'),
    'handover' => translate('messages.handover'),
    'picked_up' => translate('Out for delivery'),
    'delivered' => translate('Delivered'),
    'canceled' => translate('Canceled'),
    'failed' => translate('Payment failed'),
    'refund_requested' => translate('messages.Refund requested'),
    'refunded' => translate('Refunded'),
    'refund_request_canceled' => translate('messages.Refund request canceled'),
])
@php($order_status_badges = [
    'pending' => 'badge-soft-primary',
    'confirmed' => 'badge-soft-info',
    'accepted' => 'badge-soft-info',
    'processing' => 'badge-soft-warning',
    'handover' => 'badge-soft-warning',
    'picked_up' => 'badge-soft-warning',
    'delivered' => 'badge-soft-success',
    'canceled' => 'badge-soft-danger',
    'failed' => 'badge-soft-danger',
    'refund_requested' => 'badge-soft-warning',
    'refunded' => 'badge-soft-info',
    'refund_request_canceled' => 'badge-soft-secondary',
])
@php($payment_status_badges = [
    'paid' => 'badge-soft-success',
    'partially_paid' => 'badge-soft-warning',
])
@php($payment_status_labels = [
    'paid' => translate('messages.paid'),
    'partially_paid' => translate('messages.Partially paid'),
])
@foreach($orders as $order)
    @php($guest_details = $order->is_guest ? \App\CentralLogics\Helpers::decodeJsonToArray($order['delivery_address']) : [])
    <tr class="status-{{$order['order_status']}} class-all">
        <td data-order="{{$order['id']}}">
            <div>
                <a class="font-weight-bold" href="{{route($parcel_order?'admin.parcel.order.details':'admin.order.details',['id'=>$order['id']])}}">
                    #{{$order['id']}}
                </a>
            </div>
            @if($order->edited || $order->is_pos || $order->scheduled)
                <div class="cell-chips mt-1">
                    @if($order->is_pos)
                        <span class="cell-chip">{{ translate('messages.POS') }}</span>
                    @endif
                    @if($order->scheduled)
                        <span class="cell-chip">{{ translate('Scheduled') }}</span>
                    @endif
                    @if($order->edited)
                        <span class="cell-chip">{{ translate('Edited') }}</span>
                    @endif
                </div>
            @endif
        </td>
        <td data-order="{{$order['created_at']}}">
            <span class="table-when">
                <span class="table-when__day">{{ \App\CentralLogics\Helpers::date_format($order['created_at']) }}</span>
                <span class="table-when__ago text-uppercase" title="{{ \App\CentralLogics\Helpers::time_date_format($order['created_at']) }}">
                    {{ \App\CentralLogics\Helpers::time_format($order['created_at']) }}
                </span>
            </span>
        </td>
        <td>
            @if($order->is_guest)
                <strong>{{ $guest_details['contact_person_name'] ?? translate('messages.guest') }}</strong>
                @if(!empty($guest_details['contact_person_number']))
                    <a class="d-block text-body" href="tel:{{$guest_details['contact_person_number']}}">{{$guest_details['contact_person_number']}}</a>
                @endif
                <div class="cell-chips mt-1"><span class="cell-chip">{{translate('messages.guest')}}</span></div>
            @elseif($order->customer)
                <a class="d-block text-body" href="{{route('admin.users.customer.view',[$order['user_id']])}}">
                    <strong>{{$order->customer['f_name'].' '.$order->customer['l_name']}}</strong>
                </a>
                <a class="d-block text-body" href="tel:{{$order->customer['phone']}}">{{$order->customer['phone']}}</a>
            @else
                <span class="badge badge-soft-danger">{{translate('messages.Invalid customer data')}}</span>
            @endif
        </td>
        <td>
            @if($order->delivery_man)
                <strong>{{$order->delivery_man['f_name'].' '.$order->delivery_man['l_name']}}</strong>
                <a class="d-block text-body" href="tel:{{$order->delivery_man['phone']}}">{{$order->delivery_man['phone']}}</a>
                @if($order['order_type'] == 'take_away')
                    <div class="cell-chips mt-1"><span class="cell-chip">{{translate('messages.Take away')}}</span></div>
                @endif
            @elseif($order['order_type'] == 'take_away')
                <span class="text-muted">{{translate('messages.Take away')}}</span>
            @else
                <span class="text-muted">{{translate('messages.Unassigned')}}</span>
            @endif
        </td>
        <td>
            @if ($parcel_order)
                <div>{{Str::limit($order->parcel_category?$order->parcel_category->name:translate('No data found'),20,'...')}}</div>
            @elseif ($order->store)
                <a class="text--title" href="{{route('admin.store.view', $order->store_id)}}" title="{{ $order->store->name }}">{{Str::limit($order->store->name,20,'...')}}</a>
            @else
                <span class="text-muted">{{translate('messages.Store deleted')}}</span>
            @endif
        </td>
        @if (!$parcel_order)
            <td class="text-center" data-order="{{ $order->items_count ?? 0 }}">
                {{ $order->items_count ?? 0 }}
            </td>
        @endif
        <td>
            <div>{{ payment_method_label($order['payment_method']) ?: translate('messages.N/A') }}</div>
            <span class="badge {{ $payment_status_badges[$order->payment_status] ?? 'badge-soft-danger' }} mt-1">
                {{ $payment_status_labels[$order->payment_status] ?? translate('messages.unpaid') }}
            </span>
        </td>
        <td class="col--numeric" data-order="{{$order['order_amount']}}">
            {{\App\CentralLogics\Helpers::format_currency($order['order_amount'])}}
        </td>
        <td class="text-center">
            <div>
                <span class="badge {{ $order_status_badges[$order['order_status']] ?? 'badge-soft-secondary' }}">
                    {{ $order_status_labels[$order['order_status']] ?? ucfirst(str_replace('_',' ',$order['order_status'])) }}
                </span>
            </div>
            @if(in_array($order->delivery_type, ['express', 'slightly_delay'], true))
                <div class="cell-chips mt-1">
                    @include('partials.delivery-type-badge', ['order' => $order])
                </div>
            @endif
        </td>
        <td>
            <div class="btn--container justify-content-center">
                <a class="btn btn-sm action-btn action-btn--view" href="{{route($parcel_order?'admin.parcel.order.details':'admin.order.details',['id'=>$order['id']])}}" title="{{translate('View details')}}">
                    <i class="tio-visible-outlined"></i>
                </a>
                <a class="btn btn-sm action-btn action-btn--print" target="_blank" href="{{route('admin.order.generate-invoice',['id'=>$order['id']])}}" title="{{translate('messages.Print invoice')}}">
                    <i class="tio-print"></i>
                </a>
            </div>
        </td>
    </tr>
@endforeach

@if(count($orders) === 0)
<tr>
    <td colspan="10">
        <div class="empty--data">
            <img src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="public">
            <h5>
                {{translate('No data found')}}
            </h5>
        </div>
    </td>
</tr>
@endif
