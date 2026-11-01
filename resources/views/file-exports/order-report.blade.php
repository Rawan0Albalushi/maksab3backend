<div class="row">
    <div class="col-lg-12 text-center "><h1 >{{ translate('Order report') }}</h1></div>
    <div class="col-lg-12">



    <table>
        <thead>
            <tr>
                <th>{{ translate('Filter criteria') }} -</th>
                <th></th>
                <th></th>
                <th>
                    {{ translate('Module' )}} - {{ $data['module']?translate($data['module']):translate('All') }}
                    <br>
                    {{ translate('Zone' )}} - {{ $data['zone']??translate('All') }}
                    <br>
                    {{ translate('Store' )}} - {{ $data['store']??translate('All') }}
                    <br>
                    {{ translate('Customer' )}} - {{ $data['customer']??translate('All') }}
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
                <th></th>
                <th></th>
                <th></th>
                <th></th>
            </tr>
            <tr>
                <th>{{ translate('messages.SL') }}</th>
                <th>{{ translate('messages.Order ID') }}</th>
                <th>{{ translate('Customer name') }}</th>
                <th>{{ translate('Store name') }}</th>
                <th>{{ translate('Item price') }}</th>
                <th>{{ translate('Item discount') }}</th>
                <th>{{ translate('Coupon discount') }}</th>
                <th>{{ translate('Referral discount') }}</th>
                <th>{{ translate('messages.Pro discount') }}</th>
                <th>{{ translate('Discounted amount') }}</th>
                <th>{{ translate('Delivery type') }}</th>
                <th>{{  \App\CentralLogics\Helpers::get_business_data('additional_charge_name')??translate('Additional charge')  }}</th>
                <th>{{ translate('Extra packaging amount') }}</th>
                <th>{{ translate('messages.tax') }}</th>
                <th>{{ translate('Total amount') }}</th>
                <th>{{ translate('Payment status') }}</th>
                <th>{{ translate('Order type') }}</th>
            </tr>
        </thead>
        <tbody>
        @foreach($data['orders'] as $key => $order)
            <tr>
                <td>{{ $key+1 }}</td>
                <td>{{ $order->id }}</td>
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
                <td>{{ \App\CentralLogics\Helpers::number_format_short($order['order_amount'] - $order->additional_charge -$order['dm_tips']-$order['total_tax_amount']-app(\App\Services\Order\OrderService::class)->adjustedFeeForOrder($order)['adjusted']+$order['coupon_discount_amount'] + $order['store_discount_amount'] + $order['ref_bonus_amount'] - $order['extra_packaging_amount'] +$order['flash_admin_discount_amount'] +$order['flash_store_discount_amount'] + $order['extra_discount_amount'] + ($order->orderProDiscount?->amount_saved ?? 0) ) }}</td>
                @if ($order->discount_type == 'flash_sale')
                <td>{{ \App\CentralLogics\Helpers::number_format_short($order['flash_admin_discount_amount'] +$order['flash_store_discount_amount'] ) }}</td>
                @else
                <td>{{ \App\CentralLogics\Helpers::number_format_short(($order->item_discount_total ?? 0) ) }}</td>

                @endif
                <td>{{ \App\CentralLogics\Helpers::number_format_short($order['coupon_discount_amount']) }}</td>
                <td>{{ \App\CentralLogics\Helpers::number_format_short($order['ref_bonus_amount']) }}</td>
                <td>{{ \App\CentralLogics\Helpers::number_format_short($order->orderProDiscount?->amount_saved ?? 0) }}</td>
                <td>{{ \App\CentralLogics\Helpers::number_format_short($order['coupon_discount_amount'] + $order['store_discount_amount'] + $order['ref_bonus_amount'] + $order['extra_discount_amount'] + ($order->orderProDiscount?->amount_saved ?? 0) ) }}</td>
                <td>{{ \App\CentralLogics\Helpers::number_format_short($order->delivery_type_charge ?? 0) }} - {{ translate('messages.'.($order->delivery_type ?? 'standard')) }}</td>
                <td>{{ \App\CentralLogics\Helpers::number_format_short($order['additional_charge']) }}</td>
                <td>{{ \App\CentralLogics\Helpers::number_format_short($order['extra_packaging_amount']) }}</td>
                <td>{{ \App\CentralLogics\Helpers::number_format_short($order['total_tax_amount']) }}</td>
                <td>{{ \App\CentralLogics\Helpers::number_format_short($order['order_amount']) }}</td>
                <td>{{ translate($order->payment_status) }}</td>
                <td>{{ translate($order->order_type) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
