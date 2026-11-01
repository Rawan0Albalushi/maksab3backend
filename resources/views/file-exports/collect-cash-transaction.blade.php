<div class="row">
    <div class="col-lg-12 text-center "><h1 >{{ translate('messages.collect_cash_transactions') }}</h1></div>
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
                <th>{{ translate('messages.transaction_time') }}</th>
                <th>{{ translate('messages.Collected amount') }}</th>
                <th>{{ translate('messages.Collected from') }}</th>
                <th>{{ translate('User type') }}</th>
                <th>{{ translate('Phone') }}</th>
                <th>{{ translate('messages.email') }}</th>
                <th>{{ translate('messages.Payment method') }}</th>
                <th>{{ translate('messages.references') }}</th>
            </tr>
        </thead>
        <tbody>
        @foreach($data['account_transactions'] as $key => $at)
            <tr>
                <td>{{ $key+1 }}</td>
                <td>{{$at->id}}</td>
                <td>{{$at->created_at->format('Y-m-d '.config('timeformat'))}}</td>
                <td>{{$at['amount']}}</td>
                <td>
                    @if($at->store)
                    {{ $at->store->name}}
                    @elseif($at->deliveryman)
                    {{ $at->deliveryman->f_name }} {{ $at->deliveryman->l_name }}
                    @elseif($at->rider)
                    {{ $at->rider->f_name }} {{ $at->rider->l_name }}
                    @else
                        {{translate('No data found')}}
                    @endif
                </td>
                <td>{{translate($at['from_type'])}}</td>
                <td>
                    @if($at->store)
                    {{ $at->store->phone}}
                    @elseif($at->deliveryman)
                    {{ $at->deliveryman->phone }}
                    @elseif($at->rider)
                    {{ $at->rider->phone }}
                    @else
                        {{translate('No data found')}}
                    @endif
                </td>
                <td>
                    @if($at->store)
                    {{ $at->store->email}}
                    @elseif($at->deliveryman)
                    {{ $at->deliveryman->email }}
                    @elseif($at->rider)
                    {{ $at->rider->email }}
                    @else
                        {{translate('No data found')}}
                    @endif
                </td>
                <td>{{payment_method_label($at->method)}}</td>
                <td>{{$at['ref']}}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
