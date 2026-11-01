<div class="row">
    <div class="col-lg-12 text-center"><h1>{{ translate('parcel_expense_reports') }}</h1></div>
    <div class="col-lg-12">
        <table>
            <thead>
                <tr>
                    <th>{{ translate('Search criteria') }}</th>
                    <th></th>
                    <th></th>
                    <th>
                        @if(isset($data['module']))
                            {{ translate('Module') }} - {{ $data['module']?translate($data['module']):translate('All') }}
                            <br>
                        @endif
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
                    <th>{{ translate('messages.Order ID') }}</th>
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
                        <td>{{ $exp['order_id'] }}</td>
                        <td>{{ date('Y-m-d '.config('timeformat'), strtotime($exp->created_at)) }}</td>
                        <td>{{ translate("messages.{$exp['type']}") }}</td>
                        <td>
                            @if ($exp->order?->is_guest)
                                @php($customer_details = json_decode($exp->order['delivery_address'], true))
                                {{ $customer_details['contact_person_name'] ?? translate('messages.Guest user') }}
                            @elseif ($exp->order?->customer)
                                {{ $exp->order?->customer['f_name'].' '.$exp->order?->customer['l_name'] }}
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
