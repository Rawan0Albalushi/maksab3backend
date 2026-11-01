
<div class="row">
    <div class="col-lg-12 text-center "><h1 > {{translate($data['is_provider'] ? 'provider_Withdraw_Transactions' : 'Store_Withdraw_Transactions')}}
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
            <th>{{ translate('Request created at') }}</th>
            <th>{{ translate('Requested amount') }}</th>
            <th>{{ translate('Status') }}</th>
        </thead>
        <tbody>
        @foreach($data['data'] as $key => $tr)
            <tr>
                <td>{{ $loop->index+1}}</td>
                <td>{{ $tr?->created_at->format('Y-m-d '.config('timeformat')) ??  translate('N/A') }}</td>
                <td> {{ \App\CentralLogics\Helpers::format_currency($tr->amount) }}</td>
                <td>
                    @if($tr->approved==0)
                    {{ translate('Pending') }}
                    @elseif($tr->approved==1)
                    {{ translate('Approved') }}
                    @else
                    {{ translate('Denied') }}
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
