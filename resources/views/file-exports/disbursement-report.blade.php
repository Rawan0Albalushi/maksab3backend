
<div class="row">
    <table>
        <thead>
            <tr>

                <th>
                    {{ translate('Disbursement report') }}
                </th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
                <th>

                </th>
            </tr>
            <tr>

                <th>{{ translate('Filter criteria') }} -</th>
                <th></th>
                <th>

                    {{ translate('Zone' )}} - {{ $data['zone']??translate('All') }}
                    <br>
                    @if($data['type'] == 'store')
                        {{ translate('Store' )}} - {{ $data['store']??translate('All') }}
                    @elseif($data['type'] == 'delivery_man')
                        {{ translate('Deliveryman')}} - {{ $data['delivery_man']??translate('All') }}
                    @else
                    {{ translate('Rider')}} - {{ $data['rider']??translate('All') }}
                    @endif
                    @if ($data['from'])
                        <br>
                        {{ translate('from' )}} - {{ $data['from']?Carbon\Carbon::parse($data['from'])->format('d M Y'):'' }}
                    @endif
                    @if ($data['to'])
                        <br>
                        {{ translate('to' )}} - {{ $data['to']?Carbon\Carbon::parse($data['to'])->format('d M Y'):'' }}
                    @endif
                    <br>
                    {{ translate('Filter')  }}- {{  translate($data['filter']) }}
                    <br>
                    {{ translate('Search bar content')  }}- {{ $data['search'] ??translate('N/A') }}
                    <br>
                    {{ translate('Status')  }}: {{ $data['status'] ?? translate('N/A') }}

                </th>
                <th></th>
                <th></th>
                <th>

                </th>
            </tr>
            <tr>

                <th>
                {{ translate('Pending disbursements') }} - {{ $data['pending'] ?? translate('N/A') }}
                </th>
                <th></th>
                <th>{{ translate('Completed disbursements') }} - {{ $data['completed'] ?? translate('N/A') }}
                </th>
                <th></th>
                <th>{{ translate('Canceled transactions') }} - {{ $data['canceled'] ?? translate('N/A') }}
                </th>
                <th>

                </th>
            </tr>
        <tr>
            <th>{{ translate('SL') }}</th>
            @if($data['type'] == 'store')

            <th>{{ translate('Store information') }}</th>
            @elseif($data['type'] == 'delivery_man')
            <th>{{ translate('Deliveryman information') }}</th>
            @else
            <th>{{ translate('Rider information') }}</th>
            @endif
            <th>{{ translate('Created at') }}</th>
            <th>{{ translate('Amount') }}</th>
            <th>{{ translate('Payment method') }}</th>
            <th>{{ translate('Status') }}</th>

        </thead>
        <tbody>
        @foreach($data['disbursements'] as $key => $disb)
            <tr>
        <td>{{ $loop->index+1}}</td>
        @if($data['type'] == 'store')

        <td>{{ $disb->store->name }}</td>
        @elseif($data['type'] == 'delivery_man')
            <td>{{$disb->delivery_man->f_name.' '.$disb->delivery_man->l_name}}</td>
        @else
            <td>{{$disb->rider->f_name.' '.$disb->rider->l_name}}</td>
        @endif
        <td>{{ \App\CentralLogics\Helpers::time_date_format($disb['created_at']) }}</td>
        <td>
            {{\App\CentralLogics\Helpers::format_currency($disb['disbursement_amount'])}}
        </td>
        <td>
            <div class="name">{{translate('Payment method')}} : {{ $disb->withdraw_method?->method_name ?? translate('messages.Payment method removed') }}</div>
            @forelse((is_array($disb->withdraw_method?->method_fields) ? $disb->withdraw_method->method_fields : (json_decode($disb->withdraw_method?->method_fields ?? '', true) ?: [])) as $key=> $item)
            <br>
                <div>
                    <span>{{ ucfirst(str_replace('_', ' ', $key)) }}</span>
                    <span>:</span>
                    <span class="name">{{$item}}</span>
                </div>

            @empty

            @endforelse
        </td>
        <td>{{ $disb['status'] }}</td>

            </tr>
        @endforeach
        </tbody>
    </table>
</div>
