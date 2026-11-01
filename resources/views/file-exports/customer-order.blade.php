<div class="row">
    <div class="col-lg-12 text-center "><h1 >{{ translate('messages.customer_order_list') }}</h1></div>
    <div class="col-lg-12">



    <table>
        <thead>
            <tr>
                <th>{{ translate('Customer information') }} -</th>
                <th></th>
                <th></th>
                <th> 
                    {{ translate('Customer ID')}} : {{ translate($data['customer_id']) }}
                    <br>
                    {{ translate('Name')}} : {{ $data['customer_name'] }}
                    <br>
                    {{ translate('Phone')}} : {{ $data['customer_phone'] }}
                    <br>
                    {{ translate('email' )}} : {{ $data['customer_email'] }}
                    <br>
                    {{ translate('Total orders' )}} : {{ $data['orders']->count() }}

                </th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
            </tr>
            <tr>
                <th>{{ translate('messages.SL') }}</th>
                <th>{{ translate('messages.Order ID') }}</th>
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
                <td>
                    @if($order->store)
                        {{$order->store->name}}
                    @else
                        {{ translate('No data found') }}
                    @endif
                </td>
                <td>{{ \App\CentralLogics\Helpers::number_format_short($order['order_amount']-$order['dm_tips']-$order['total_tax_amount']-app(\App\Services\Order\OrderService::class)->adjustedFeeForOrder($order)['adjusted']+$order['coupon_discount_amount'] + $order['store_discount_amount'] + ($order->orderProDiscount?->amount_saved ?? 0)) }}</td>
                <td>{{ \App\CentralLogics\Helpers::number_format_short($order->details->sum('discount_on_item')) }}</td>
                <td>{{ \App\CentralLogics\Helpers::number_format_short($order['coupon_discount_amount']) }}</td>
                <td>{{ \App\CentralLogics\Helpers::number_format_short($order['coupon_discount_amount'] + $order['store_discount_amount'] + ($order->orderProDiscount?->amount_saved ?? 0)) }}</td>
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
