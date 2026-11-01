
<div class="row">
    <div class="col-lg-12 text-center "><h1 > {{translate($data['is_provider'] ? 'provider_Cash_Transactions' : 'Store_Cash_Transactions')}}
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
            <th>{{ translate('Transaction ID') }}</th>
            <th>{{ translate('Transaction time') }}</th>
            <th>{{ translate('Balance before transaction') }}</th>
            <th>{{ translate('Transaction amount') }}</th>
            <th>{{ translate('reference') }}</th>
            <th>{{ translate('Payment method') }}</th>

        </thead>
        <tbody>
        @foreach($data['data'] as $key => $tr)
            <tr>
        <td>{{ $loop->index+1}}</td>
        <td>{{ $tr->id }}</td>
        <td>{{ $tr?->created_at->format('Y-m-d '.config('timeformat')) ??  translate('N/A') }}</td>
        <td>
            {{ \App\CentralLogics\Helpers::format_currency($tr->current_balance) }}
        </td>
        <td>
            {{ \App\CentralLogics\Helpers::format_currency($tr->amount) }}
        </td>
        <td>{{ $tr->ref ??  translate('N/A') }}</td>
        <td>{{ $tr->method ??  translate('N/A') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
