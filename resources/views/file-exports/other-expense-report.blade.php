<div class="row">
    <div class="col-lg-12 text-center"><h1>{{ translate('Other expense reports') }}</h1></div>
    <div class="col-lg-12">
        <table>
            <thead>
                <tr>
                    <th>{{ translate('Search criteria') }}</th>
                    <th></th>
                    <th></th>
                    <th>
                        {{ translate('Customer') }} - {{ $data['customer']??translate('All') }}
                        <br>
                        {{ translate('Expense type') }} - {{ isset($data['type']) && $data['type'] != 'all' ? translate("messages.{$data['type']}") : translate('All') }}
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
                </tr>
                <tr>
                    <th>{{ translate('SL') }}</th>
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
                        <td>{{ date('Y-m-d '.config('timeformat'), strtotime($exp->created_at)) }}</td>
                        <td>{{ translate("messages.{$exp['type']}") }}</td>
                        <td>
                            @if ($exp->user)
                                {{ $exp->user->f_name . ' ' . $exp->user->l_name }}
                            @else
                                {{ translate('messages.N/A') }}
                            @endif
                        </td>
                        <td>{{ \App\CentralLogics\Helpers::format_currency($exp['amount']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
