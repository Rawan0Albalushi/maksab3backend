<div class="row">
    <div class="col-lg-12 text-center "><h1 >{{ translate('Deliveryman review list') }}</h1></div>
    <div class="col-lg-12">



    <table>
        <thead>
            <tr>
                <th>{{ translate('Search criteria') }}

                    @isset($data['delivery_men'])
                         <br>
                        {{ translate('Deliveryman')  }}- {{ $data['delivery_men']}}
                    @endisset

                    @isset($data['order_by'])
                        <br>
                        {{ translate('Order by')  }}- {{ $data['order_by']}}

                    @endisset
                </th>
                <th></th>
                <th>



                </th>
                <th>
                    {{ translate('Search bar content')  }}- {{ $data['search'] ??translate('N/A') }}

                </th>
                <th> </th>
                <th></th>
                <th></th>
                <th></th>
                </tr>
        <tr>
            <th>{{ translate('SL') }}</th>
            <th>{{translate('Deliveryman name')}}</th>
            <th>{{translate('messages.Order ID')}}</th>
            <th>{{translate('Customer name')}}</th>
            <th>{{translate('Store name')}}</th>
            <th>{{translate('messages.Rating')}}</th>
            <th>{{translate('messages.review')}}</th>
        </thead>
        <tbody>
        @foreach($data['reviews'] as $key => $review)
            <tr>
                <td>{{ $key+1}}</td>
                <td>{{$review->delivery_man->f_name.' '.$review->delivery_man->l_name}}</td>
                <td>
                    {{ $review->order_id }}
                </td>
                <td>
                    @if ($review->customer)
                        {{$review->customer?$review->customer->f_name:""}} {{$review->customer?$review->customer->l_name:""}}
                    @else
                        {{translate('No data found')}}
                    @endif
                </td>
                <td>
                    {{$review->order?->store?->name}}
                </td>
                <td>{{ $review->rating }}</td>
                <td>{{ $review->comment }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
