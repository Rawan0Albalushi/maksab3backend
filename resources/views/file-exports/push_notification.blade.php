
<div class="row">
    <div class="col-lg-12 text-center "><h1 > {{translate('Push notification list')}}
    </h1></div>
    <div class="col-lg-12">

    <table>
        <thead>
            <tr>
                <th>{{ translate('Search criteria') }}</th>
                <th></th>
                <th></th>
                <th>
                    {{ translate('Search bar content')  }}: {{ $data['search'] ??translate('N/A') }}
                </th>
                <th> </th>
                <th></th>
                <th></th>
                <th></th>
                </tr>


        <tr>
            <th>{{ translate('SL') }}</th>
            <th>{{ translate('Notification title') }}</th>
            <th>{{ translate('Created at') }}</th>
            <th>{{ translate('Description') }}</th>
            <th>{{ translate('Image') }}</th>
            <th>{{ translate('Zone') }}</th>
            <th>{{ translate('Targeted users') }}</th>
        </thead>
        <tbody>
        @foreach($data['data'] as $key => $coupon)
            <tr>
        <td>{{ $loop->index+1}}</td>
        <td>{{ $coupon->title }}</td>
        <td>{{ \Carbon\Carbon::parse($coupon->created_at)->format('d M Y') }}</td>
        <td>{{ $coupon->description }}</td>
            <td></td>
        <td>{{ $coupon?->zone?->name ??  translate('All') }}</td>

        <td>{{ translate($coupon->tergat) }}</td>


            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
