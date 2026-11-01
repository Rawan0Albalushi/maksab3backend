@php use App\CentralLogics\Helpers; @endphp
<div class="row">
    <div class="col-lg-12 text-center">
        <h1>{{ translate('Service list') }}</h1>
    </div>
    <div class="col-lg-12">

        <table>
            <thead>
                <tr>
                    <th>{{ translate('Filter criteria') }}</th>
                    <th></th>
                    <th></th>
                    <th>
                        {{ translate('Store') }}: {{ $data['store'] ?? translate('All') }}
                        <br>
                        {{ translate('Zone') }}: {{ $data['zone'] ?? translate('All') }}
                        <br>
                        {{ translate('Module') }}: {{ $data['module_name'] ?? translate('N/A') }}
                        <br>
                        {{ translate('Category') }}: {{ $data['category'] ?? translate('N/A') }}
                        <br>
                        {{ translate('Search bar content') }}: {{ $data['search'] ?? translate('N/A') }}
                    </th>
                    <th></th>
                    <th></th>
                    <th></th>
                    <th></th>
                </tr>

                <tr>
                    <th>{{ translate('SL') }}</th>
                    <th>{{ translate('Image') }}</th>
                    <th>{{ translate('Service name') }}</th>
                    <th>{{ translate('Category name') }}</th>
                    <th>{{ translate('Zone') }}</th>
                    <th>{{ translate('Base price') }}</th>
                    <th>{{ translate('Minimum bidding price') }}</th>
                    <th>{{ translate('Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['data'] as $key => $service)
                    <tr>
                        <td>{{ $loop->index + 1 }}</td>
                        <td> &nbsp;</td>
                        <td>{{ $service->name }}</td>
                        <td>{{ $service->category?->name ?? translate('messages.N/A') }}</td>
                        <td>{{ $service->store?->zone?->name ?? translate('messages.N/A') }}</td>
                        <td>{{ Helpers::format_currency($service->base_price) }}</td>
                        <td>{{ $service->min_bid_price ? Helpers::format_currency($service->min_bid_price) : translate('messages.N/A') }}</td>
                        <td>{{ $service->status ? translate('Active') : translate('Inactive') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
