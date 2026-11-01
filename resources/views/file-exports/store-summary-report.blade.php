<div class="row">
    <div class="col-lg-12 text-center "><h1 >{{ translate('Store summary reports') }}</h1></div>
    <div class="col-lg-12">



    <table>
        <thead>
            <tr>
                <th>{{ translate('Search criteria') }}</th>
                <th></th>
                <th></th>
                <th>
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
                    {{ translate('New registered store')  }}- {{ $data['new_stores'] ??translate('N/A') }}
                    <br>
                    {{ translate('Total orders')  }}- {{ $data['orders'] ??translate('N/A') }}
                    <br>
                    {{ translate('Total order amount')  }}- {{ $data['total_order_amount'] ??translate('N/A') }}
                    <br>
                    {{ translate('Completed orders')  }}- {{ $data['total_delivered'] ??translate('N/A') }}
                    <br>
                    {{ translate('Incomplete orders')  }}- {{ $data['total_ongoing'] ??translate('N/A') }}
                    <br>
                    {{ translate('Canceled orders')  }}- {{ $data['total_canceled'] ??translate('N/A') }}
                </th>
                <th> </th>
                <th></th>
                <th></th>
                <th></th>
            </tr>
            <tr>
                <th>{{ translate('Payment statistics') }}</th>
                <th></th>
                <th></th>
                <th>
                    {{ translate('Cash payments')  }} - {{ $data['cash_payments'] ??translate('N/A') }}
                    <br>
                    {{ translate('Digital payments')  }} - {{ $data['digital_payments'] ??translate('N/A') }}
                    <br>
                    {{ translate('Wallet payments')  }} - {{ $data['wallet_payments'] ??translate('N/A') }}
                </th>
                <th> </th>
                <th></th>
                <th></th>
                <th></th>
            </tr>
        <tr>
            <th>{{ translate('SL') }}</th>
            <th>{{translate('Store name')}}</th>
            <th>{{translate('Total order')}}</th>
            <th>{{translate('Total delivered order')}}</th>
            <th>{{translate('Total amount')}}</th>
            <th>{{translate('Completion rate')}}</th>
            <th>{{translate('Ongoing rate')}}</th>
            <th>{{translate('Cancellation rate')}}</th>
            <th>{{translate('Total refund requests')}}</th>
            <th>{{translate('Pending refund requests')}}</th>
        </thead>
        <tbody>
        @foreach($data['stores'] as $key => $store)
        @php($delivered = $store->orders->where('order_status', 'delivered')->count())
        @php($canceled = $store->orders->where('order_status', 'canceled')->count())
        @php($refunded = $store->orders->where('order_status', 'refunded')->count())
        @php($refund_requested = $store->orders->whereNotNull('refund_requested')->count())
        <tr>
            <td>{{$key+1}}</td>
            <td>
                {{  $store->name  }}
            </td>
            <td>
                {{ $store->orders->count() }}
            </td>
            <td>
                {{ $delivered }}
            </td>
            <td>
                {{\App\CentralLogics\Helpers::number_format_short($store->orders->where('order_status','delivered')->sum('order_amount'))}}
            </td>
            <td>
                {{ ($store->orders->count() > 0 && $delivered > 0)? number_format((100*$delivered)/$store->orders->count(), config('round_up_to_digit')): 0 }}%
            </td>
            <td>
                {{ ($store->orders->count() > 0 && $delivered > 0)? number_format((100*($store->orders->count()-($delivered+$canceled)))/$store->orders->count(), config('round_up_to_digit')): 0 }}%
            </td>
            <td>
                {{ ($store->orders->count() > 0 && $canceled > 0)? number_format((100*$canceled)/$store->orders->count(), config('round_up_to_digit')): 0 }}%
            </td>
            <td>
                {{ $refunded }}
            </td>
            <td>
                {{ $refund_requested }}
            </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
