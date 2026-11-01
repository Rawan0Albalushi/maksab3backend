<div class="row">
    <div class="col-lg-12 text-center "><h1 >{{ translate('Deliveryman review list') }}</h1></div>
    <div class="col-lg-12">



    <table>
        <thead>
            <tr>
                <th>{{ translate('Deliveryman information') }}</th>
                <th></th>
                <th></th>
                <th>
                    {{ translate('Name')  }}- {{ $data['dm']->f_name.' '.$data['dm']->l_name}}
                    <br>
                    {{ translate('Phone')  }}- {{ $data['dm']->phone}}
                    <br>
                    {{ translate('email')  }}- {{ $data['dm']->email}}
                    <br>
                    {{ translate('Total rating')  }}- {{ count($data['dm']->rating)}}
                    <br>
                    {{ translate('Average review')  }}- {{count($data['dm']->rating)>0?number_format($data['dm']->rating[0]->average, 1, '.', ' '):0}}

                </th>
                <th> </th>
                <th></th>
                <th></th>
                <th></th>
            </tr>
        <tr>
            <th>{{ translate('SL') }}</th>
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
