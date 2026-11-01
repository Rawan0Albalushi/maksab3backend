<div class="row">
    <div class="col-lg-12 text-center"><h1>{{ translate('rideshare_expense_reports') }}</h1></div>
    <div class="col-lg-12">
        <table>
            <thead>
                <tr>
                    <th>{{ translate('Search criteria') }}</th>
                    <th></th>
                    <th></th>
                    <th>
                        {{ translate('Zone') }} - {{ $data['zone']??translate('All') }}
                        <br>
                        {{ translate('Customer') }} - {{ $data['customer']??translate('All') }}
                        @if ($data['from'])
                            <br>{{ translate('from') }} - {{ Carbon\Carbon::parse($data['from'])->format('d M Y') }}
                        @endif
                        @if ($data['to'])
                            <br>{{ translate('to') }} - {{ Carbon\Carbon::parse($data['to'])->format('d M Y') }}
                        @endif
                        <br>{{ translate('Filter') }} - {{ translate($data['filter']) }}
                        <br>{{ translate('Search bar content') }} - {{ $data['search'] ?? translate('N/A') }}
                    </th>
                    <th></th>
                    <th></th>
                </tr>
                <tr>
                    <th>{{ translate('SL') }}</th>
                    <th>{{ translate('Ride ID') }}</th>
                    <th>{{ translate('Date & time') }}</th>
                    <th>{{ translate('Expense type') }}</th>
                    <th>{{ translate('Customer name') }}</th>
                    <th>{{ translate('Expense amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['expenses'] as $key => $exp)
                    <tr>
                        <td>{{ $key + 1 }}</td>
                        <td>{{ $exp?->ride?->ref_id ?? $exp['ride_id'] }}</td>
                        <td>{{ date('Y-m-d '.config('timeformat'), strtotime($exp->created_at)) }}</td>
                        <td>{{ translate("messages.{$exp['type']}") }}</td>
                        <td>
                            @if ($exp?->ride?->customer)
                                {{ $exp?->ride?->customer?->f_name . ' ' . $exp?->ride?->customer?->l_name }}
                            @else
                                {{ translate('messages.Invalid customer data') }}
                            @endif
                        </td>
                        <td>{{ \App\CentralLogics\Helpers::format_currency($exp['amount']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
