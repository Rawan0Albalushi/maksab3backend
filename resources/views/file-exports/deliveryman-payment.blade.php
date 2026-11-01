<div class="row">
    <div class="col-lg-12 text-center "><h1 >{{ translate('messages.delivery_man_payments') }}</h1></div>
    <div class="col-lg-12">



    <table>
        <thead>
            <tr>
                <th>{{ translate('Filter criteria') }} -</th>
                <th></th>
                <th></th>
                <th>

                    {{ translate('Search bar content')  }}- {{ $data['search'] ??translate('N/A') }}

                </th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
            </tr>
            <tr>
                <th>{{ translate('messages.SL') }}</th>
                <th>{{ translate('messages.Transaction ID') }}</th>
                <th>{{ translate('messages.provided_st') }}</th>
                <th>{{ translate('messages.Payment amount') }}</th>
                <th>{{ translate('Deliveryman name') }}</th>
                <th>{{ translate('Phone') }}</th>
                <th>{{ translate('messages.Payment method') }}</th>
                <th>{{ translate('messages.references') }}</th>
            </tr>
        </thead>
        <tbody>
        @foreach($data['dm_earnings'] as $key => $at)
            <tr>
                <td>{{ $key+1 }}</td>
                <td>{{$at->id}}</td>
                <td>{{$at->created_at->format('Y-m-d '.config('timeformat'))}}</td>
                <td>{{$at['amount']}}</td>
                <td>
                    @if($at->delivery_man)
                    {{$at->delivery_man->f_name.' '.$at->delivery_man->l_name}}
                    @else
                    {{translate('messages.Deliveryman deleted')}}
                    @endif
                </td>
                <td>
                    @if($at->delivery_man)
                    {{$at->delivery_man->phone}}
                    @else
                    {{translate('messages.Deliveryman deleted')}}
                    @endif
                </td>
                <td>{{payment_method_label($at->method)}}</td>
                @if(  $at['ref'] == 'delivery_man_wallet_adjustment_full')
                    <td>{{ translate('Wallet adjusted') }}</td>
                @elseif( $at['ref'] == 'delivery_man_wallet_adjustment_partial')
                    <td>{{ translate('Wallet adjusted partially') }}</td>
                @else
                    <td>{{$at['ref']}}</td>
                @endif
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
