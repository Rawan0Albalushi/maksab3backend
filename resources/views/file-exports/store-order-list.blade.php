
<div class="row">
    <div class="col-lg-12 text-center "><h1 > {{translate('Store order list')}}
    </h1></div>
    <div class="col-lg-12">

    <table>
        <thead>
            <tr>
                <th>{{ translate('Store details') }}</th>
                <th></th>
                <th>
                    {{ translate('Store name')  }}: {{ $data['store'] ?? translate('N/A') }}
                    <br>
                    {{ translate('Zone')  }}: {{ $data['zone'] ?? translate('N/A') }}
                    <br>
                    {{ translate('Total order')  }}: {{ $data['data']->count() ?? translate('N/A') }}

                  @isset($data['filter'])
                    <br>
                    {{ translate('Filter')  }}: {{ translate($data['filter']) ?? translate('All') }}
                  @endisset
                </th>
                <th> </th>
            </tr>

            <tr>
                <th></th>
                <th></th>
                @if (!isset($data['filter']))
                    <th>
                        {{ translate('Scheduled order')  }}: {{ $data['data']->where('scheduled', '1')->count() ?? translate('N/A') }}
                    </th>
                    <th>
                        {{ translate('Pending order')  }}: {{ $data['data']->where('order_status' ,'pending')->count() ?? translate('N/A') }}
                    </th>
                    <th>
                        {{ translate('Delivered order')  }}: {{ $data['data']->where('order_status' ,'delivered')->count() ?? translate('N/A') }}
                    </th>
                    <th>
                        {{ translate('Canceled order')  }}: {{ $data['data']->where('order_status' ,'canceled')->count() ?? translate('N/A') }}
                    </th>
                    <th>
                        {{ translate('Refunded order')  }}: {{ $data['data']->where('order_status' ,'refunded')->count() ?? translate('N/A') }}
                    </th>
                @endif
                <th> </th>
            </tr>


        <tr>
            <th>{{ translate('SL') }}</th>
            <th>{{ translate('Order ID') }}</th>
            <th>{{ translate('Order date') }}</th>
            <th>{{ translate('Customer name') }}</th>
            <th>{{ translate('Store name') }}</th>
            <th>{{ translate('Total items') }}</th>
            <th>{{ translate('Item price') }}</th>
            <th>{{ translate('Item discount') }}</th>
            <th>{{ translate('Coupon discount') }}</th>
            <th>{{ translate('Discounted amount') }}</th>
            <th>{{ translate('VAT/tax') }}</th>
            <th>{{ translate('Total amount') }}</th>
            <th>{{ translate('Payment status') }}</th>
            <th>{{ translate('Order status') }}</th>
            <th>{{ translate('Order type') }}</th>

        </thead>
        <tbody>
        @foreach($data['data'] as $key => $order)
            <tr>
                <td>{{ $loop->index+1}}</td>
                <td>{{ $order->id}}</td>
                <td>{{ \Carbon\Carbon::parse($order->created_at)->format('Y-m-d '.config('timeformat')) ??  translate('N/A') }}</td>
                @php($delivery_address = is_array($order->delivery_address) ? $order->delivery_address : json_decode($order->delivery_address, true))
                <td>{{  $order?->customer ?  $order?->customer?->f_name.' '.$order?->customer?->l_name  : (!empty($delivery_address['contact_person_name']) ? $delivery_address['contact_person_name'] : translate('No data found'))  }}</td>
                <td>{{ $order?->store?->name }}</td>
                <td>{{$order->details->count() }}</td>
                <td> {{ \App\CentralLogics\Helpers::number_format_short($order['order_amount']-$order['dm_tips']-$order['total_tax_amount']-app(\App\Services\Order\OrderService::class)->adjustedFeeForOrder($order)['adjusted']+$order['coupon_discount_amount'] + $order['store_discount_amount']) }}
                </td>
                <td> {{ \App\CentralLogics\Helpers::number_format_short($order->details->sum('discount_on_item')) }} </td>
                <td> {{ \App\CentralLogics\Helpers::number_format_short($order['coupon_discount_amount']) }}</td>
                <td> {{ \App\CentralLogics\Helpers::number_format_short($order['coupon_discount_amount'] + $order['store_discount_amount']) }}</td>
                <td> {{ \App\CentralLogics\Helpers::number_format_short($order['total_tax_amount']) }}</td>
                <td> {{ \App\CentralLogics\Helpers::number_format_short($order['order_amount']) }}</td>
                <td>{{translate($order->payment_status)}}</td>
                <td> {{ translate($order->order_status)}}</td>
                <td> {{ translate($order->order_type)}}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
