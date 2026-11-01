<div class="row">
    <div class="col-lg-12 text-center "><h1 >{{ translate('store_order_reports') }}</h1></div>
    <div class="col-lg-12">



    <table>
        <thead>
            <tr>
                <th>{{ translate('Search criteria') }}</th>
                <th></th>
                <th></th>
                <th>
                    {{ translate('Zone' )}} - {{ $data['zone']??translate('All') }}
                    <br>
                    {{ translate('Store' )}} - {{ $data['store']??translate('All') }}
                    @if ($data['from'])
                    <br>
                    {{ translate('from' )}} - {{ $data['from']?Carbon\Carbon::parse($data['from'])->format('d M Y'):'' }}
                    @endif
                    @if ($data['to'])
                    <br>
                    {{ translate('to' )}} - {{ $data['to']?Carbon\Carbon::parse($data['to'])->format('d M Y'):'' }}
                    @endif
                    <br>
                    {{ translate('Filter')  }}- {{  translate($data['filter']) }}
                    <br>
                    {{ translate('Search bar content')  }}- {{ $data['search'] ??translate('N/A') }}

                </th>
                <th> </th>
                <th></th>
                <th></th>
                <th></th>
                </tr>
            <tr>
                <th>{{ translate('Analytics') }}</th>
                <th></th>
                <th></th>
                <th>
                    {{ translate('Total orders')  }}- {{ $data['total_orders'] }}
                    <br>
                    {{ translate('Total order amount')  }}- {{ $data['total_order_amount'] }}
                    <br>
                    {{ translate('Canceled order')  }}- {{ $data['total_canceled_count'] }}
                    <br>
                    {{ translate('Completed orders')  }}- {{ $data['total_delivered_count'] }}
                    <br>
                    {{ translate('Incomplete orders')  }}- {{ $data['total_ongoing_count'] }}
                </th>
                <th> </th>
                <th></th>
                <th></th>
                <th></th>
            </tr>
        <tr>
            <th>{{ translate('SL') }}</th>
            <th>{{ translate('messages.Order ID') }}</th>
            <th>{{ translate('Order date') }}</th>
            <th>{{ translate('Customer name') }}</th>
            <th>{{ translate('Store name') }}</th>
            <th>{{ translate('Total amount') }}</th>
            <th>{{ translate('Payment status') }}</th>
            <th>{{ translate('Discounted amount') }}</th>
            <th>{{ translate('messages.tax') }}</th>
            <th>{{ translate('Delivery charge') }}</th>
        </thead>
        <tbody>
            @foreach($data['orders'] as $key => $order)
            <tr>
                <td>{{ $key+1 }}</td>
                <td>{{ $order->id }}</td>
                <td><div>
                    {{ date('d M Y', strtotime($order['created_at'])) }}
                </div>
                <br>
                <div>
                    {{ date(config('timeformat'), strtotime($order['created_at'])) }}
                </div></td>
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
                <td>{{ \App\CentralLogics\Helpers::number_format_short($order['order_amount']) }}</td>
                <td>{{ translate($order->payment_status) }}</td>
                <td>{{ \App\CentralLogics\Helpers::number_format_short($order['coupon_discount_amount']  + $order['ref_bonus_amount'] +  $order['store_discount_amount'] + ($order->orderProDiscount?->amount_saved ?? 0)) }}</td>
                <td>{{ \App\CentralLogics\Helpers::number_format_short($order['total_tax_amount']) }}</td>
                <td>{{ \App\CentralLogics\Helpers::number_format_short(app(\App\Services\Order\OrderService::class)->proDeliveryBreakdown($order)['original_fee']) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
