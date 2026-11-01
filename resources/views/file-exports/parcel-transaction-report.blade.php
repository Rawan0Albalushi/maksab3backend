<div class="row">
    <div class="col-lg-12 text-center ">
        <h1>{{ translate('Parcel transaction report') }}</h1>
    </div>
    <div class="col-lg-12">
        <table>
            <thead>
                <tr>
                    <th>{{ translate('Search criteria') }}</th>
                    <th></th>
                    <th></th>
                    <th>
                        {{ translate('Module')}} - {{ $data['module'] ? translate($data['module']) : translate('All') }}
                        <br>
                        {{ translate('Zone')}} - {{ $data['zone'] ?? translate('All') }}
                        @if ($data['from'])
                            <br>
                            {{ translate('from')}} -
                            {{ $data['from'] ? Carbon\Carbon::parse($data['from'])->format('d M Y') : '' }}
                        @endif
                        @if ($data['to'])
                            <br>
                            {{ translate('to')}} - {{ $data['to'] ? Carbon\Carbon::parse($data['to'])->format('d M Y') : '' }}
                        @endif
                        <br>
                        {{ translate('Filter')  }}- {{  translate($data['filter']) }}
                        <br>
                        {{ translate('Search bar content')  }}- {{ $data['search'] ?? translate('N/A') }}
                    </th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                </tr>
                <tr>
                    <th>{{ translate('Transaction analytics') }}</th>
                    <th></th>
                    <th></th>
                    <th>
                        {{ translate('Completed transactions')  }}- {{ $data['delivered'] ?? translate('N/A') }}
                        <br>
                        {{ translate('Refunded transactions')  }}- {{ $data['canceled'] ?? translate('N/A') }}
                    </th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                </tr>
                <tr>
                    <th>{{ translate('Earning analytics') }}</th>
                    <th></th>
                    <th></th>
                    <th>
                        {{ translate('Admin earnings')  }} - {{ $data['admin_earned'] ?? translate('N/A') }}
                        <br>
                        {{ translate('Deliveryman earnings')  }} - {{ $data['deliveryman_earned'] ?? translate('N/A') }}
                    </th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                </tr>
                <tr>
                    <th>{{ translate('SL') }}</th>
                    <th>{{ translate('messages.Order ID') }}</th>
                    <th>{{ translate('Customer name') }}</th>
                    <th>{{ translate('Referral discount') }}</th>
                    <th>{{ translate('VAT/tax') }}</th>
                    <th>{{ translate('Delivery charge') }}</th>
                    <th>{{ translate('Order amount') }}</th>
                    <th>{{ translate('Admin discount') }}</th>
                    <th>{{ translate('Admin commission') }}</th>
                    <th>{{ \App\CentralLogics\Helpers::get_business_data('additional_charge_name') ?? translate('Additional charge') }}</th>
                    <th>{{ translate('Commision on delivery charge') }}</th>
                    <th>{{ translate('Admin net income') }}</th>
                    <th>{{ translate('messages.Amount received by') }}</th>
                    <th>{{ translate('messages.Payment method') }}</th>
                    <th>{{ translate('Payment status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['order_transactions'] as $key => $ot)
                    <tr>
                        <td>{{ $key + 1 }}</td>
                        <td>{{ $ot->order_id }}</td>
                        <td>
                            @php($delivery_address = $ot->order ? (is_array($ot->order->delivery_address) ? $ot->order->delivery_address : json_decode($ot->order->delivery_address, true)) : null)
                            @if ($ot->order && $ot->order->customer)
                                {{ $ot->order->customer['f_name'] . ' ' . $ot->order->customer['l_name'] }}
                            @elseif (!empty($delivery_address['contact_person_name']))
                                {{ $delivery_address['contact_person_name'] }}
                            @else
                                {{ translate('No data found') }}
                            @endif
                        </td>
                        <td>{{ \App\CentralLogics\Helpers::format_currency($ot->order['ref_bonus_amount']) }}</td>
                        <td>{{ \App\CentralLogics\Helpers::format_currency($ot->tax) }}</td>
                        <td>{{ \App\CentralLogics\Helpers::format_currency($ot->delivery_charge + ($ot->pro_delivery_discount ?? 0)) }}</td>
                        <td>{{ \App\CentralLogics\Helpers::format_currency($ot->order_amount) }}</td>
                        <td>{{ \App\CentralLogics\Helpers::format_currency($ot->admin_expense) }}</td>
                        <td>{{ \App\CentralLogics\Helpers::format_currency(($ot->admin_commission + $ot->admin_expense) - $ot->delivery_fee_comission - $ot->additional_charge) }}</td>
                        <td>{{ \App\CentralLogics\Helpers::format_currency(($ot->additional_charge)) }}</td>
                        <td>{{ \App\CentralLogics\Helpers::format_currency($ot->delivery_fee_comission) }}</td>
                        <td>{{ \App\CentralLogics\Helpers::format_currency(app(\App\Services\Order\OrderTransactionService::class)->adminNetIncome($ot)) }}</td>
                        @if ($ot->received_by == 'admin')
                            <td>{{ translate('messages.admin') }}</td>
                        @elseif ($ot->received_by == 'deliveryman')
                            <td>
                                <div>{{ translate('Deliveryman') }}</div>
                                <div>
                                    @if (isset($ot->delivery_man) && $ot->delivery_man->earning == 1)
                                        {{ translate('Freelancer') }}
                                    @elseif (isset($ot->delivery_man) && $ot->delivery_man->earning == 0 && $ot->delivery_man->type == 'restaurant_wise')
                                        {{ translate('messages.Restaurant') }}
                                    @elseif (isset($ot->delivery_man) && $ot->delivery_man->earning == 0 && $ot->delivery_man->type == 'zone_wise')
                                        {{ translate('messages.admin') }}
                                    @endif
                                </div>
                            </td>
                        @elseif ($ot->received_by == 'store')
                            <td>{{ translate('messages.Store') }}</td>
                        @else
                            <td></td>
                        @endif
                        <td>{{ payment_method_label($ot->order['payment_method']) }}</td>
                        <td>
                            @if ($ot->status)
                                {{ translate('Refunded') }}
                            @else
                                {{ translate('messages.Completed') }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
