<div class="row">
    <div class="col-lg-12 text-center "><h1 >{{ translate($data['status']) }} {{ translate('Parcel order list') }}</h1></div>
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
                <th></th>
                <th></th>
            </tr>
            <tr>
                <th>{{ translate('messages.SL') }}</th>
                <th>{{ translate('messages.Order ID') }}</th>
                <th>{{ translate('messages.Date') }}</th>
                <th>{{ translate('Parcel category') }}</th>
                {{-- Own columns rather than extra lines inside the category cell: an export is
                     filtered and pivoted, and a band buried in another column cannot be. --}}
                <th>{{ translate('messages.Weight') }}</th>
                <th>{{ translate('messages.Dimension') }}</th>
                <th>{{ translate('Customer name') }}</th>
                <th>{{ translate('Coupon discount') }}</th>
                <th>{{ translate('Discounted amount') }}</th>
                <th>{{ translate('messages.tax') }}</th>
                <th>{{ translate('Total amount') }}</th>
                <th>{{ translate('Payment status') }}</th>
                <th>{{ translate('messages.payment By') }}</th>
                <th>{{ translate('messages.Payment method') }}</th>
                <th>{{ translate('Order status') }}</th>
                <th>{{ translate('Order type') }}</th>
            </tr>
        </thead>
        <tbody>
        @foreach($data['orders'] as $key => $order)
            <tr>
                <td>{{ $key+1 }}</td>
                <td>{{ $order->id }}</td>

                <td>{{ \App\CentralLogics\Helpers::time_date_format($order->created_at) }}</td>
                <td>
                    <div>{{Str::limit($order->parcel_category?$order->parcel_category->name:translate('No data found'),20,'...')}}</div>
            </td>
                {{-- `band_label` / `size_label` carry the configured unit. A parcel with no
                     selection, or one placed before the tiers were recorded, exports blank. --}}
                <td>{{ $order->weight?->band_label ?? '' }}</td>
                <td>{{ $order->dimension?->size_label ?? '' }}</td>
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
                <td>{{ \App\CentralLogics\Helpers::number_format_short($order['coupon_discount_amount']) }}</td>
                <td>{{ \App\CentralLogics\Helpers::number_format_short($order['coupon_discount_amount'] + $order['store_discount_amount']) }}</td>
                <td>{{ \App\CentralLogics\Helpers::number_format_short($order['total_tax_amount']) }}</td>
                <td>{{ \App\CentralLogics\Helpers::number_format_short($order['order_amount']) }}</td>
                <td>{{ translate($order->payment_status) }}</td>
                <td>{{ translate($order->charge_payer) }}</td>
                <td>{{ payment_method_label($order->payment_method) }}</td>
                <td>{{ translate($order->order_status) }}</td>
                <td>{{ translate($order->order_type) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
