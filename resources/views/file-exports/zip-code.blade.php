<div class="row">
    <div class="col-lg-12 text-center"><h1>{{ translate('Zip code list') }}</h1></div>
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
                </tr>
                <tr>
                    <th>{{ translate('SL') }}</th>
                    <th>{{ translate('Zip code') }}</th>
                    <th>{{ translate('Zone name') }}</th>
                    <th>{{ translate('messages.Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['data'] as $zipCode)
                    <tr>
                        <td>{{ $loop->index + 1 }}</td>
                        <td>{{ $zipCode->zip_code }}</td>
                        <td>{{ $zipCode->zone?->name ?: translate('messages.N/A') }}</td>
                        <td>{{ $zipCode->status ? translate('messages.Active') : translate('messages.Inactive') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
