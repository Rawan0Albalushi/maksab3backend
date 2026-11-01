<div class="row">
    <div class="col-lg-12 text-center"><h1>{{ translate('Area list') }}</h1></div>
    <div class="col-lg-12">
        <table>
            <thead>
                <tr>
                    <th>{{ translate('Filter criteria') }}</th>
                    <th></th>
                    <th>
                        {{ translate('Search bar content') }}: {{ $data['search'] ?? translate('N/A') }},
                        {{ translate('messages.Zone') }}: {{ $data['zone'] ?: translate('All') }}
                    </th>
                    <th></th>
                    <th></th>
                </tr>
                <tr>
                    <th>{{ translate('SL') }}</th>
                    <th>{{ translate('Area name') }}</th>
                    <th>{{ translate('messages.Display Name') }}</th>
                    <th>{{ translate('Zone name') }}</th>
                    <th>{{ translate('messages.Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['data'] as $area)
                    <tr>
                        <td>{{ $loop->index + 1 }}</td>
                        <td>{{ $area->name }}</td>
                        <td>{{ $area->display_name ?: translate('messages.N/A') }}</td>
                        <td>{{ $area->zone?->name ?: translate('messages.N/A') }}</td>
                        <td>{{ $area->status ? translate('messages.Active') : translate('messages.Inactive') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
