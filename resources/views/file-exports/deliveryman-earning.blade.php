<div class="row">
    <div class="col-lg-12 text-center "><h1 >{{ translate('Deliveryman earning list') }}</h1></div>
    <div class="col-lg-12">



    <table>
        <thead>
            <tr>
                <th>{{ translate('Deliveryman information') }}</th>
                <th></th>
                <th>
                    {{ translate('Name')  }}- {{ $data['dm']->f_name.' '.$data['dm']->l_name}}
                    <br>
                    {{ translate('Phone')  }}- {{ $data['dm']->phone}}
                    <br>
                    {{ translate('email')  }}- {{ $data['dm']->email}}
                    <br>
                    {{ translate('Total order')  }}- {{ $data['dm']->order_count }}
                    <br>
                    {{ translate('Total earning')  }}- {{$data['dm']->wallet->total_earning}}

                </th>
                <th></th>
                <th> </th>
                <th></th>
                <th></th>
                <th></th>
            </tr>
            <tr>
                <th>{{ translate('Filter criteria') }}</th>
                <th></th>
                <th>
                    {{ translate('Date')  }}- {{ $data['date'] ??translate('N/A') }}

                </th>
                <th></th>
                <th> </th>
                <th></th>
                <th></th>
                <th></th>
            </tr>
        <tr>
            <th>{{ translate('SL') }}</th>
            <th>{{translate('messages.Order ID')}}</th>
            <th>{{translate('messages.Date')}}</th>
            <th>{{translate('messages.Delivery fee earned')}}</th>
            <th>{{translate('Tips')}}</th>
            <th>{{translate('messages.Total earning')}}</th>
        </thead>
        <tbody>
        @foreach($data['earnings'] as $key => $earning)
            <tr>
                <td>{{ $key+1}}</td>
                <td>
                    {{ $earning->order_id }}
                </td>
                <td>
                    {{ \App\CentralLogics\Helpers::date_format($earning->created_at ) }}
                </td>
                <td>{{ \App\CentralLogics\Helpers::format_currency($earning->original_delivery_charge) }}</td>
                <td>{{ \App\CentralLogics\Helpers::format_currency($earning->dm_tips) }}</td>
                <td>{{ \App\CentralLogics\Helpers::format_currency($earning->original_delivery_charge + $earning->dm_tips) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
