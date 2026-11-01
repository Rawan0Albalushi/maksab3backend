<div class="row">
    <div class="col-lg-12 text-center"><h1>{{ translate('Parcel report') }}</h1></div>
    <div class="col-lg-12">
        <table>
            <thead>
                <tr>
                    <th>{{ translate('Filter criteria') }} -</th>
                    <th></th>
                    <th></th>
                    <th>
                        {{ translate('Module') }} - {{ $data['module']?translate($data['module']):translate('All') }}
                        <br>
                        {{ translate('Zone') }} - {{ $data['zone']??translate('All') }}
                        <br>
                        {{ translate('Customer') }} - {{ $data['customer']??translate('All') }}
                        @if ($data['from'])
                            <br>{{ translate('from') }} - {{ Carbon\Carbon::parse($data['from'])->format('d M Y') }}
                        @endif
                        @if ($data['to'])
                            <br>{{ translate('to') }} - {{ Carbon\Carbon::parse($data['to'])->format('d M Y') }}
                        @endif
                        <br>{{ translate('Filter') }} - {{ translate($data['filter']) }}
                        <br>{{ translate('Search bar content') }} - {{ $data['search'] ?? translate('N/A') }}
                    </th>
                    <th></th>
                    <th></th>
                    <th></th>
                </tr>
                <tr>
                    <th>{{ translate('messages.SL') }}</th>
                    <th>{{ translate('messages.Order ID') }}</th>
                    <th>{{ translate('Customer name') }}</th>
                    <th>{{ translate('Referral discount') }}</th>
                    <th>{{ translate('messages.Pro discount') }}</th>
                    <th>{{ translate('messages.tax') }}</th>
                    <th>{{ translate('Delivery charge') }}</th>
                    <th>{{ \App\CentralLogics\Helpers::get_business_data('additional_charge_name')??translate('Additional charge') }}</th>
                    <th>{{ translate('Total amount') }}</th>
                    <th>{{ translate('messages.Amount received by') }}</th>
                    <th>{{ translate('messages.Payment method') }}</th>
                    <th>{{ translate('Order status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['orders'] as $key => $order)
                    <tr>
                        <td>{{ $key + 1 }}</td>
                        <td>{{ $order->id }}</td>
                        <td>
                            @if ($order->is_guest)
                                @php($customer_details = json_decode($order['delivery_address'], true))
                                {{ $customer_details['contact_person_name'] ?? translate('messages.Guest user') }}
                            @elseif ($order->customer)
                                {{ $order->customer['f_name'] . ' ' . $order->customer['l_name'] }}
                            @else
                                {{ translate('No data found') }}
                            @endif
                        </td>
                        <td>{{ \App\CentralLogics\Helpers::number_format_short($order['ref_bonus_amount']) }}</td>
                        <td>{{ \App\CentralLogics\Helpers::number_format_short(app(\App\Services\Order\OrderTransactionService::class)->proDiscountTotal($order)) }}</td>
                        <td>{{ \App\CentralLogics\Helpers::number_format_short($order['total_tax_amount']) }}</td>
                        <td>{{ \App\CentralLogics\Helpers::number_format_short(app(\App\Services\Order\OrderService::class)->proDeliveryBreakdown($order)['original_fee']) }}</td>
                        <td>{{ \App\CentralLogics\Helpers::number_format_short($order['additional_charge']) }}</td>
                        <td>{{ \App\CentralLogics\Helpers::number_format_short($order['order_amount']) }}</td>
                        <td>{{ isset($order->transaction) ? $order->transaction->received_by : translate('messages.Not received yet') }}</td>
                        <td>{{ payment_method_label($order['payment_method']) }}</td>
                        <td>{{ translate($order->order_status) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
