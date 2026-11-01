<div class="row">
    <div class="col-lg-12 text-center "><h1 >{{ translate($data['status']) }} {{ translate('Order list') }}</h1></div>
    <div class="col-lg-12">
    <table>
        <thead>
            <tr>
                <th>{{ translate('Filter criteria') }} -</th>
                <th></th>
                <th></th>
                <th>
                    {{ translate('Order status')}} : {{ translate($data['status']) }}
                    @if ($data['search'])
                    <br>
                    {{ translate('Search bar content')}} : {{ $data['search'] }}
                    @endif
                    @if ($data['zones'])
                    <br>
                    {{ translate('zones' )}} : {{ $data['zones'] }}
                    @endif
                    @if ($data['stores'])
                    <br>
                    {{ translate('Stores' )}} : {{ $data['stores'] }}
                    @endif
                    @if ($data['type'])
                    <br>
                    {{ translate('Order type')}} : {{ translate($data['type']) }}
                    @endif
                    @if ($data['from'])
                    <br>
                    {{ translate('from' )}} : {{ $data['from']?Carbon\Carbon::parse($data['from'])->format('d M Y'):'' }}
                    @endif
                    @if ($data['to'])
                    <br>
                    {{ translate('to' )}} : {{ $data['to']?Carbon\Carbon::parse($data['to'])->format('d M Y'):'' }}
                    @endif

                </th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
            </tr>
            <tr>
                <th>{{ translate('messages.SL') }}</th>
                <th>{{ translate('messages.Order ID') }}</th>
                @if ($data['status'] ==  'scheduled')
                <th>{{ translate('Scheduled at') }}</th>
                @else
                <th>{{ translate('messages.Date') }}</th>
                @endif
                <th>{{ translate('Customer name') }}</th>
                <th>{{ translate('Store name') }}</th>
                <th>{{ translate('Item price') }}</th>
                <th>{{ translate('Item discount') }}</th>
                <th>{{ translate('Coupon discount') }}</th>
                <th>{{ translate('Discounted amount') }}</th>
                <th>{{ translate('messages.tax') }}</th>
                <th>{{ translate('Total amount') }}</th>
                <th>{{ translate('Payment status') }}</th>
                <th>{{ translate('Order status') }}</th>
                <th>{{ translate('Order type') }}</th>
            </tr>
        </thead>
        <tbody>
        @foreach($data['orders'] as $key => $order)
            <tr>
                <td>{{ $key+1 }}</td>
                <td>{{ $order->id }}</td>
                @if ($data['status'] ==  'scheduled')
                <td>{{ \App\CentralLogics\Helpers::time_date_format($order->schedule_at) }}</td>
                @else
                <td>{{ \App\CentralLogics\Helpers::time_date_format($order->created_at) }}</td>
                @endif
                <td>
                    @php($delivery_address = is_array($order->delivery_address) ? $order->delivery_address : json_decode($order->delivery_address, true))
                    @if ($order->customer)
                        {{ $order->customer['f_name'] . ' ' . $order->customer['l_name'] }}
                    @elseif (!empty($delivery_address['contact_person_name']))
                        {{ $delivery_address['contact_person_name'] }}
                    @else
                        {{ translate('No data found') }}
                    @endif
                </td>
                <td>
                    @if($order->store)
                        {{$order->store->name}}
                    @else
                        {{ translate('No data found') }}
                    @endif
                </td>
                <td>{{ \App\CentralLogics\Helpers::number_format_short($order['order_amount']-$order['dm_tips']-$order['total_tax_amount']-app(\App\Services\Order\OrderService::class)->adjustedFeeForOrder($order)['adjusted']+$order['coupon_discount_amount'] + $order['store_discount_amount']) }}</td>
                <td>{{ \App\CentralLogics\Helpers::number_format_short($order->details->sum('discount_on_item')) }}</td>
                <td>{{ \App\CentralLogics\Helpers::number_format_short($order['coupon_discount_amount']) }}</td>
                <td>{{ \App\CentralLogics\Helpers::number_format_short($order['coupon_discount_amount'] + $order['store_discount_amount']+ $order['ref_bonus_amount'] + ($order->orderProDiscount?->amount_saved ?? 0) ) }}</td>
                <td>{{ \App\CentralLogics\Helpers::number_format_short($order['total_tax_amount']) }}</td>
                <td>{{ \App\CentralLogics\Helpers::number_format_short($order['order_amount']) }}</td>
                <td>{{ translate($order->payment_status) }}</td>
                <td>{{ translate($order->order_status) }}</td>
                <td>{{ translate($order->order_type) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
