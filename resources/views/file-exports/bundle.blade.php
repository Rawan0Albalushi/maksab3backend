@use('App\CentralLogics\Helpers')

<div class="row">
    <div class="col-lg-12 text-center">
        <h1>{{ translate('Bundle package') }}</h1>
    </div>
    <div class="col-lg-12">
        <table>
            <thead>
                <tr>
                    <th>{{ translate('Report analytics') }}</th>
                    <th></th>
                    <th></th>
                    <th>{{ translate('Total bundles') }}: {{ $data['data']->count() }}</th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                </tr>
                <tr>
                    <th>{{ translate('Search criteria') }}</th>
                    <th></th>
                    <th></th>
                    <th>{{ translate('Search bar content') }}: {{ $data['search'] ?? translate('N/A') }}</th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                </tr>
                <tr>
                    <th class="border-0">{{ translate('messages.SL') }}</th>
                    <th class="border-0 w--25">{{ translate('Bundle name') }}</th>
                    @if ($data['showStore'] ?? true)
                        <th class="border-0">{{ $data['ownerLabel'] ?? translate('messages.Store') }}</th>
                    @endif
                    <th class="border-0">{{ translate('messages.Items') }}</th>
                    <th class="border-0">{{ translate('messages.Duration') }}</th>
                    <th class="border-0">{{ translate('Base price') }}</th>
                    <th class="border-0">{{ translate('messages.Discount') }}</th>
                    <th class="border-0">{{ translate('After discount') }}</th>
                    <th class="border-0">{{ translate('Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['data'] as $key => $bundle)
                    <tr>
                        <td>{{ $key + 1 }}</td>
                        <td>{{ $bundle->name }}</td>
                        @if ($data['showStore'] ?? true)
                            <td>{{ $bundle->store?->name ?? translate('messages.N/A') }}</td>
                        @endif
                        <td>{{ $bundle->items_count ?? $bundle->items->count() }}</td>
                        <td>
                            {{ $bundle->start_date ? Helpers::time_date_format($bundle->start_date) : translate('messages.N/A') }}
                            -
                            {{ $bundle->end_date ? Helpers::time_date_format($bundle->end_date) : translate('messages.N/A') }}
                        </td>
                        <td>{{ Helpers::format_currency($bundle->base_price) }}</td>
                        <td>{{ $bundle->discount_percentage + 0 }}%</td>
                        <td>{{ Helpers::format_currency($bundle->discounted_price) }}</td>
                        <td>{{ $bundle->status ? translate('Active') : translate('Inactive') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
