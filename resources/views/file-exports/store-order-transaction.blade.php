@php
    $tripOrOrder = $data['is_provider'] ? 'trip' : 'order';
    $storeOrProvider = $data['is_provider'] ? 'provider' : 'store';

    /* Whole phrases, not translate($tripOrOrder . '_ID') — see store-list.blade.php. */
    $labels = $data['is_provider']
        ? [
            'id'     => translate('Trip ID'),
            'time'   => translate('Trip time'),
            'amount'   => translate('Total trip amount'),
            'earnings' => translate('Provider earnings'),
        ]
        : [
            'id'     => translate('Order ID'),
            'time'   => translate('Order time'),
            'amount'   => translate('Total order amount'),
            'earnings' => translate('Store earnings'),
        ];
@endphp
<div class="row">
    <div class="col-lg-12 text-center "><h1 > {{translate($data['is_provider'] ? 'Provider_Trip_Transactions' : 'Store_Order_Transactions')}}
    </h1></div>
    <div class="col-lg-12">

    <table>
        <thead>
            <tr>
                <th>{{ translate('Filter criteria') }}</th>
                <th></th>
                <th>
                    {{ translate('Search bar content')  }}: {{ $data['search'] ?? translate('N/A') }}
                </th>
                <th> </th>
                </tr>


        <tr>
            <th>{{ translate('SL') }}</th>
            <th>{{ $labels['id'] }}</th>
            <th>{{ $labels['time'] }}</th>
            <th>{{ $labels['amount'] }}</th>
            <th>{{ $labels['earnings'] }}</th>
            <th>{{ translate('Admin earnings') }}</th>
            @if($data['is_provider'])
                <th>{{ translate('Additional charge') }}</th>
            @else
                <th>{{ translate('Delivery fee') }}</th>
            @endif
            <th>{{ translate('VAT/tax') }}</th>

        </thead>
        <tbody>
        @foreach($data['data'] as $key => $tr)
            <tr>
        <td>{{ $loop->index+1}}</td>
        <td>{{ $data['is_provider'] ? $tr->trip_id : $tr->order_id }}</td>
        <td>{{ $tr->created_at->format('Y-m-d '.config('timeformat')) ??  translate('N/A') }}</td>

        <td>
            {{ \App\CentralLogics\Helpers::format_currency($data['is_provider'] ? $tr->trip_amount : $tr->order_amount) }}
        </td>
        <td>
            {{ \App\CentralLogics\Helpers::format_currency($tr->store_amount - $tr->tax) }}
        </td>
        <td>
            {{ \App\CentralLogics\Helpers::format_currency($tr->admin_commission) }}
        </td>
        <td>
            {{ \App\CentralLogics\Helpers::format_currency($data['is_provider'] ? $tr->additional_charge : $tr->delivery_charge + ($tr->pro_delivery_discount ?? 0)) }}
        </td>
        <td>
            {{ \App\CentralLogics\Helpers::format_currency($tr->tax) }}
        </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
