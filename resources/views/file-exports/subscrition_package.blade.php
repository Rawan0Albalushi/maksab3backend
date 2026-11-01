
<div class="row">
    <div class="col-lg-12 text-center "><h1 > {{translate('Subscription package list')}}
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
            <th>{{ translate('Package name') }}</th>
            <th>{{ translate('price') }}</th>
            <th>{{ translate('Duration') }}</th>
            <th>{{ translate('Current subscriber') }}</th>
            <th>{{ translate('Status') }}</th>

        </thead>
        <tbody>
        @foreach($data['data'] as $key => $package)
            <tr>
        <td>{{ $loop->index+1}}</td>
        <td>{{ $package->package_name }}</td>
        <td>
            {{ \App\CentralLogics\Helpers::format_currency($package->price) }}
        </td>
        <td>{{$package->validity}} {{ translate('days') }}</td>
        <td>{{$package->current_subscribers_count ?? 0}}</td>
        <td>{{$package->status == 1 ? translate('messages.Activate') : translate('messages.Inactivate') }}</td>

            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
