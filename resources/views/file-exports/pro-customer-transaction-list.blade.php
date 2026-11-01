<div class="row">
    <div class="col-lg-12 text-center">
        <h1>{{ translate('Pro customer transaction list') }}</h1>
    </div>
    <div class="col-lg-12">
        <table>
            <thead>
                <tr>
                    <th>{{ translate('Transaction analytics') }}</th>
                    <th></th>
                    <th></th>
                    <th>
                        {{ translate('Total transactions') }}: {{ $data['transactions']->count() }}
                        <br>
                        {{ translate('Total earned') }}: {{ \App\CentralLogics\Helpers::format_currency((float) $data['transactions']->where('plan_type', 'paid')->where('payment_status', 'success')->sum('plan_price')) }}
                    </th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                </tr>
                <tr>
                    <th>{{ translate('Search criteria') }}</th>
                    <th></th>
                    <th></th>
                    <th>
                        {{ translate('Search bar content') }}: {{ $data['search'] ?? translate('messages.N/A') }}
                    </th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                </tr>
                <tr>
                    <th>{{ translate('Filter criteria') }}</th>
                    <th></th>
                    <th></th>
                    <th>
                        {{ translate('messages.Plan') }}: {{ $data['plan_name'] ?? translate('All') }}
                        <br>
                        {{ translate('Plan type') }}: {{ $data['plan_type'] ? ucfirst(str_replace('_', ' ', $data['plan_type'])) : translate('All') }}
                        <br>
                        {{ translate('Date range') }}: {{ $data['dates'] ?? translate('messages.N/A') }}
                    </th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                </tr>
                <tr>
                    <th>{{ translate('messages.SL') }}</th>
                    <th>{{ translate('messages.Transaction ID') }}</th>
                    <th>{{ translate('Transaction date') }}</th>
                    <th>{{ translate('Customer name') }}</th>
                    <th>{{ translate('messages.email') }}</th>
                    <th>{{ translate('Plan name') }}</th>
                    <th>{{ translate('messages.Pricing') }}</th>
                    <th>{{ translate('Plan validity') }}</th>
                    <th>{{ translate('messages.payment By') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['transactions'] as $key => $tx)
                    <tr>
                        <td>{{ $key + 1 }}</td>
                        <td>#{{ $tx->id }}</td>
                        <td>{{ ($tx->paid_at ?? $tx->created_at)?->format('d M Y H:i') ?? translate('messages.N/A') }}</td>
                        <td>{{ $tx->user ? trim(($tx->user->f_name ?? '') . ' ' . ($tx->user->l_name ?? '')) : translate('messages.N/A') }}</td>
                        <td>{{ $tx->user?->email ?? translate('messages.N/A') }}</td>
                        <td>{{ $tx->plan_name }}</td>
                        <td>{{ $tx->plan_type === 'free_trial' ? translate('Free trial') : \App\CentralLogics\Helpers::format_currency((float) $tx->plan_price) }}</td>
                        <td>
                            {{ $tx->subscription?->start_at ? \Carbon\Carbon::parse($tx->subscription->start_at)->format('d M Y') : '' }}
                            -
                            {{ $tx->subscription?->end_at ? \Carbon\Carbon::parse($tx->subscription->end_at)->format('d M Y') : '' }}
                        </td>
                        <td>{{ $tx->payment_method ? ucwords(str_replace('_', ' ', $tx->payment_method)) : ($tx->plan_type === 'free_trial' ? translate('Free trial') : translate('messages.N/A')) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
