<table>
    <thead>
        <tr>
            <th>{{ translate('messages.SL') }}</th>
            <th>{{ translate('messages.Category') }}</th>
            <th>{{ translate('Subcategory') }}</th>
            <th>{{ translate('messages.user') }}</th>
            <th>{{ translate('Service name') }}</th>
            <th>{{ translate('messages.Description') }}</th>
            <th>{{ translate('messages.feedback') }}</th>
            <th>{{ translate('messages.Status') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($data['data'] as $key => $serviceRequest)
            <tr>
                <td>{{ $key + 1 }}</td>
                <td>{{ $serviceRequest->category?->name ?? translate('messages.N/A') }}</td>
                <td>{{ $serviceRequest->subCategory?->name ?? translate('messages.N/A') }}</td>
                <td>{{ trim(($serviceRequest->customer?->f_name ?? '') . ' ' . ($serviceRequest->customer?->l_name ?? '')) ?: translate('messages.N/A') }}</td>
                <td>{{ $serviceRequest->service_name }}</td>
                <td>{{ $serviceRequest->description ?? translate('messages.N/A') }}</td>
                <td>{{ $serviceRequest->feedback ?? translate('messages.N/A') }}</td>
                <td>{{ translate('messages.' . $serviceRequest->status) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
