<div class="row">
    <div class="col-lg-12 text-center">
        <h1>{{ translate('Pro customer subscription list') }}</h1>
    </div>
    <div class="col-lg-12">
        <table>
            <thead>
                <tr>
                    <th>{{ translate('Subscription analytics') }}</th>
                    <th></th>
                    <th></th>
                    <th>
                        {{ translate('Total subscriber') }}: {{ $data['stats']['total'] ?? 0 }}
                        <br>
                        {{ translate('Active subscriber') }}: {{ $data['stats']['active'] ?? 0 }}
                        <br>
                        {{ translate('Inactive subscriber') }}: {{ $data['stats']['inactive'] ?? 0 }}
                        <br>
                        {{ translate('Total earned') }}: {{ \App\CentralLogics\Helpers::format_currency((float) ($data['stats']['total_earned'] ?? 0)) }}
                    </th>
                    <th></th>
                    <th></th>
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
                    <th></th>
                    <th></th>
                </tr>
                <tr>
                    <th>{{ translate('Filter criteria') }}</th>
                    <th></th>
                    <th></th>
                    <th>
                        {{ translate('messages.Tab') }}: {{ ucfirst($data['tab'] ?? 'all') }}
                        <br>
                        {{ translate('messages.Plan') }}: {{ $data['plan_name'] ?? translate('All') }}
                        <br>
                        {{ translate('Subscription status') }}: {{ $data['subscription_status'] ? ucfirst($data['subscription_status']) : translate('All') }}
                        <br>
                        {{ translate('Renewal status') }}: {{ $data['renewal_status'] ? ucfirst($data['renewal_status']) : translate('All') }}
                        <br>
                        {{ translate('Date range') }}: {{ $data['dates'] ?? translate('messages.N/A') }}
                    </th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                </tr>
                <tr>
                    <th>{{ translate('messages.SL') }}</th>
                    <th>{{ translate('Customer name') }}</th>
                    <th>{{ translate('messages.email') }}</th>
                    <th>{{ translate('Phone') }}</th>
                    <th>{{ translate('Plan name') }}</th>
                    <th>{{ translate('Plan type') }}</th>
                    <th>{{ translate('Plan price') }}</th>
                    <th>{{ translate('Start date') }}</th>
                    <th>{{ translate('End date') }}</th>
                    <th>{{ translate('messages.Total orders') }}</th>
                    <th>{{ translate('messages.Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['subscriptions'] as $key => $sub)
                    <tr>
                        <td>{{ $key + 1 }}</td>
                        <td>{{ $sub->user ? trim(($sub->user->f_name ?? '') . ' ' . ($sub->user->l_name ?? '')) : translate('messages.N/A') }}</td>
                        <td>{{ $sub->user?->email ?? translate('messages.N/A') }}</td>
                        <td>{{ $sub->user?->phone ?? translate('messages.N/A') }}</td>
                        <td>{{ $sub->plan_name }}</td>
                        <td>{{ $sub->plan_type === 'free_trial' ? translate('Free trial') : translate('messages.paid') }}</td>
                        <td>{{ \App\CentralLogics\Helpers::format_currency((float) $sub->plan_price) }}</td>
                        <td>{{ $sub->start_at ? \Carbon\Carbon::parse($sub->start_at)->format('d M Y H:i') : translate('messages.N/A') }}</td>
                        <td>{{ $sub->end_at ? \Carbon\Carbon::parse($sub->end_at)->format('d M Y H:i') : translate('messages.N/A') }}</td>
                        <td>{{ $sub->total_orders ?? 0 }}</td>
                        <td>{{ ucfirst($sub->status) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
