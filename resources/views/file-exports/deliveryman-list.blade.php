<div class="row">
    <div class="col-lg-12 text-center "><h1 >{{ translate('Deliveryman list') }}</h1></div>
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
                    {{ translate('Total deliveryman')  }}- {{ $data['delivery_men']->count() }}
                    <br>
                    {{ translate('Active deliveryman')  }}- {{ $data['delivery_men']->where('status',1)->count()}}
                    <br>
                    {{ translate('Inactive deliveryman')  }}- {{ $data['delivery_men']->where('status',0)->count() }}
                </th>
                <th> </th>
                <th></th>
                <th></th>
                <th></th>
            </tr>
        <tr>
            <th>{{ translate('SL') }}</th>
            <th>{{translate('Image')}}</th>
            <th>{{ translate('First name') }}</th>
            <th>{{ translate('Last name') }}</th>
            <th>{{ translate('Phone') }}</th>
            <th>{{ translate('email') }}</th>
            <th>{{ translate('Deliveryman type') }}</th>
            <th>{{ translate('Total completed') }}</th>
            <th>{{ translate('Total running orders') }}</th>
            <th>{{ translate('Status') }}</th>
            <th>{{ translate('Zone') }}</th>
            <th>{{ translate('Vehicle type') }}</th>
            <th>{{ translate('Identity type') }}</th>
            <th>{{ translate('Identity number') }}</th>
        </thead>
        <tbody>
        @foreach($data['delivery_men'] as $key => $item)
        <tr>
            <td>{{$key+1}}</td>
            <td></td>
            <td>{{  $item['f_name']  }}</td>
            <td>{{  $item['l_name']  }}</td>
            <td>{{  $item['phone']  }}</td>
            <td>{{  $item['email']  }}</td>
            <td>{{ $item->earning?translate('Freelancer'):translate('Salary based') }}</td>
            <td>{{ $item['order_count'] }}</td>
            <td>{{ $item['current_orders'] }}</td>
            <td>{{ $item->active?translate('messages.online'):translate('messages.offline') }}</td>
            <td>{{ $item->zone?$item->zone->name:'' }}</td>
            <td>{{ $item->vehicle?$item->vehicle->type:'' }}</td>
            <td>{{ identity_type_label($item->identity_type) }}</td>
            <td>{{ $item->identity_number }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
