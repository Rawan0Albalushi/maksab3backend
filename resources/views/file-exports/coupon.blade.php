
<div class="row">
    <div class="col-lg-12 text-center "><h1 > {{translate('Coupon list')}}
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
            <th>{{ translate('Coupon title') }}</th>
            <th>{{ translate('Coupon code') }}</th>
            <th>{{ translate('Module') }}</th>
            <th>{{ translate('Coupon type') }}</th>
            <th>{{ translate('Number of uses') }}</th>
            <th>{{ translate('Min purchase amount') }}</th>
            <th>{{ translate('Max discount amount') }} </th>
            <th>{{ translate('Discount type') }} </th>
            <th>{{ translate('Discount') }} </th>
            <th>{{ translate('Start date') }} </th>
            <th>{{ translate('End date') }} </th>
        </thead>
        <tbody>
        @foreach($data['data'] as $key => $coupon)
            <tr>
        <td>{{ $loop->index+1}}</td>
        <td>{{ $coupon->title }}</td>
        <td>{{ $coupon->code }}</td>
        <td>{{ $coupon->module->module_name }}</td>
        <td>{{ translate($coupon->coupon_type) }}</td>
        <td>{{ $coupon->total_uses }}</td>
        <td>{{ $coupon->min_purchase }}</td>
        <td>{{ $coupon->max_discount }}</td>
        <td>{{ $coupon->discount }}</td>
        <td>{{ translate($coupon->discount_type) }}</td>
        <td>{{ \Carbon\Carbon::parse($coupon->start_date)->format('d M Y') }}</td>
        <td>{{ \Carbon\Carbon::parse($coupon->expire_date)->format('d M Y') }}</td>

            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
