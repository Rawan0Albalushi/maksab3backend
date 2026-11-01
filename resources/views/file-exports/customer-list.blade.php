<div class="row">
    <div class="col-lg-12 text-center "><h1 >{{ translate('Customer list') }}</h1></div>
    <div class="col-lg-12">



    <table>
        <thead>
            <tr>
                <th>{{ translate('Customer analytics') }}</th>
                <th></th>
                <th></th>
                <th>
                    {{ translate('Total customer')  }}: {{ $data['customers']->count() }}
                    <br>
                    {{ translate('Active customer')  }}: {{ $data['customers']->where('status',1)->count() }}
                    <br>
                    {{ translate('Inactive customer')  }}: {{ $data['customers']->where('status',0)->count() }}

                </th>
                <th> </th>
                <th></th>
                <th></th>
                <th></th>
                </tr>
            <tr>
                <th>{{ translate('Search criteria') }}</th>
                <th></th>
                <th></th>
                <th>
                    {{ translate('Search bar content')  }}: {{ $data['search'] ??translate('N/A') }}
                </th>
                <th> </th>
                <th></th>
                <th></th>
                <th></th>
            </tr>
            <tr>
                <th>{{ translate('Filter criteria') }}</th>
                <th></th>
                <th></th>
                <th>
                    {{ translate('Customer status')  }}: {{ $data['filter'] ?translate($data['filter']):translate('All') }}
                    <br>
                    {{ translate('Sort by')  }}: {{ $data['order_wise'] ??translate('N/A') }}
                    <br>
                    {{ translate('Show limit')  }}: {{ $data['show_limit'] ??translate('N/A') }}
                    <br>
                    {{ translate('Order date range')  }}: {{ $data['order_date'] ??translate('N/A') }}
                    <br>
                    {{ translate('Join date range')  }}: {{ $data['join_date'] ??translate('N/A') }}
                </th>
                <th> </th>
                <th></th>
                <th></th>
                <th></th>
            </tr>
        <tr>
            <th>{{ translate('SL') }}</th>
            <th>{{ translate('First name') }}</th>
            <th>{{ translate('Last name') }}</th>
            <th>{{ translate('Phone') }}</th>
            <th>{{ translate('email') }}</th>
            <th>{{ translate('Saved address') }}</th>
            <th>{{ translate('Total orders') }}</th>
            <th>{{ translate('Total wallet amount') }} </th>
            <th>{{ translate('Total loyalty points') }} </th>
            <th>{{ translate('Status') }} </th>
        </thead>
        <tbody>
        @foreach($data['customers'] as $key => $customer)
            <tr>
        <td>{{ $key+1}}</td>
        <td>{{ $customer['f_name'] }}</td>
        <td>{{ $customer['l_name'] }}</td>
        <td>{{ $customer['phone'] }}</td>
        <td>{{ $customer['email'] }}</td>
        <td>
            @foreach($customer->addresses as $address)
            <br>
            {{$address['address']}}
            @endforeach
        </td>
        <td>{{ $customer['order_count'] }}</td>
        <td>{{ $customer['wallet_balance'] }}</td>
        <td>{{ $customer['loyalty_point'] }}</td>
        <td>{{ $customer->status ? translate('messages.Active') : translate('messages.Inactive') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
