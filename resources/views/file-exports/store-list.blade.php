@php
    $isRental = $data['is_rental'] == 1 ? 'Provider' : 'Store';
    $isVehicle = $data['is_rental'] == 1 ? 'vehicle' : 'item';
    $isTrip = $data['is_rental'] == 1 ? 'trip' : 'order';

    /*
     * Written out as whole phrases instead of translate($isRental . '_Name').
     * A key assembled at runtime is invisible to the translation tooling — the
     * concatenated forms were never in messages.php, so every export silently
     * re-created them — and languages that put the noun first cannot be served
     * by gluing two translated fragments together.
     */
    $labels = $data['is_rental'] == 1
        ? [
            'list'     => translate('Provider list'),
            'total'    => translate('Total provider'),
            'active'   => translate('Active provider'),
            'inactive' => translate('Inactive provider'),
            'id'       => translate('Provider ID'),
            'logo'     => translate('Provider logo'),
            'name'     => translate('Provider name'),
            'units'    => translate('Total vehicles'),
            'jobs'     => translate('Total trips'),
        ]
        : [
            'list'     => translate('Store list'),
            'total'    => translate('Total store'),
            'active'   => translate('Active store'),
            'inactive' => translate('Inactive store'),
            'id'       => translate('Store ID'),
            'logo'     => translate('Store logo'),
            'name'     => translate('Store name'),
            'units'    => translate('Total items'),
            'jobs'     => translate('Total orders'),
        ];
@endphp
<div class="row">
    <div class="col-lg-12 text-center "><h1 >{{ $labels['list'] }}</h1></div>
    <div class="col-lg-12">



    <table>
        <thead>




        <tr>

            <th>{{ $labels['total'] }} - {{ $data['data']->count() ?? translate('N/A') }} </th>
            <th></th>
            <th></th>
            <th> {{ $labels['active'] }} - {{ $data['data']->where('status',1)->count() ?? translate('N/A') }} </th>
            <th></th>
            <th></th>
            <th> {{ $labels['inactive'] }} - {{ $data['data']->where('status',0)->count() ?? translate('N/A') }} </th>
            <th></th>
            <th></th>
            <th> {{ translate('Newly joined') }} - {{ $data['data']->where('created_at', '>=', now()->subDays(30)->toDateTimeString())->count() ?? translate('N/A') }} </th>
            <th></th>

        </tr>
            <tr>
                <th>{{ translate('Filter criteria') }}</th>
                <th></th>
                <th></th>
                <th>
                    {{ translate('Zone' )}} - {{ $data['zone']??translate('All') }}

                    <br>
                    {{ translate('Module' )}} - {{ $data['module']??translate('All') }}

                    <br>
                    {{ translate('Search bar content')  }}- {{ $data['search'] ??translate('N/A') }}
                </th>
                <th> </th>
                <th></th>
                <th></th>
                <th></th>
            </tr>
        <tr>
            <th>{{ translate('SL') }}</th>
            <th>{{ $labels['id'] }}</th>
            <th>{{ $labels['logo'] }}</th>
            <th>{{ $labels['name'] }}</th>
            <th>{{ translate('Ratings') }}</th>
            <th>  {{ translate('Owner information') }}</th>
            <th>   {{ translate('Address') }}</th>
            <th> {{ $labels['units'] }}</th>
            <th> {{ $labels['jobs'] }}</th>
            <th>{{ translate('featured') }}</th>
            <th>{{ translate('Status') }}</th>
        </thead>
        <tbody>
        @foreach($data['data'] as $key => $store)
        <tr>
            <td>{{$key+1}}</td>
            <td>{{  $store['id']  }}</td>
            <td>&nbsp;</td>
            <td>{{  $store['name']  }}</td>
            <td>
                @if($isRental == 'Provider')
                    {{ number_format($store->vehicle_reviews->avg('rating')) }}
                @else
                    @php($store_reviews = app(\App\Services\Store\StoreService::class)->calculateRating($store['rating']))
                    {{ number_format($store_reviews['rating'], 1)}}
                @endif

            </td>
            <td> {{ $store->vendor->f_name .' '  .$store->vendor->l_name   }}
                        <br>
                    {{ $store->vendor->phone  }}
            </td>
            <td> {{ $store->address }} </td>
            <td>
                @if($isRental == 'Provider')
                    {{ count($store->vehicles) }}
                @else
                    {{ $store->items_count }}
                @endif
            </td>
            <td>
                @if($isRental == 'Provider')
                    {{ $store->trips_count }}
                @else
                    {{ $store->store_orders_count }}
                @endif
            </td>
            <td>
                {{ $store->featured == 1 ? translate('Yes') : translate('No') }}
            </td>
            <td>
                {{ $store->status == 1 ? translate('Active') : translate('Inactive') }}
            </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
