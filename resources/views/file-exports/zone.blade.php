
<div class="row">
    <div class="col-lg-12 text-center "><h1 > {{translate('Zone list')}}
    </h1></div>
    <div class="col-lg-12">

    <table>
        <thead>
            <tr>
                <th>{{ translate('Filter criteria') }}</th>
                <th></th>
                <th>

                    {{ translate('Search bar content')  }}: {{ $data['search'] ?? translate('N/A') }}

                </th>
                <th> </th>
                </tr>


        <tr>
            <th>{{ translate('SL') }}</th>
            <th>{{ translate('Zone name') }}</th>
            <th>{{ translate('Zone ID') }}</th>
            <th>{{ translate('Total stores') }}</th>
            <th>{{ translate('Total deliveryman') }}</th>
            <th>{{ translate('Digital payment') }}</th>
            <th>{{ translate('Cash on delivery') }}</th>
            <th>{{ translate('Status') }}</th>

        </thead>
        <tbody>
        @foreach($data['data'] as $key => $addon)
            <tr>
        <td>{{ $loop->index+1}}</td>
        <td>{{ $addon->name }}</td>
        <td>{{ $addon->id }}</td>
        <td>
            {{ $addon->stores_count }}
        </td>
        <td>

            {{ $addon->deliverymen_count }}
        </td>
        <td>{{ $addon?->digital_payment == 1 ? translate('Yes') : translate('No') }}</td>
        <td>{{ $addon?->cash_on_delivery == 1 ? translate('Yes') : translate('No') }}</td>
        <td>{{ $addon?->status == 1 ? translate('Active') : translate('Inactive') }}</td>

            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
