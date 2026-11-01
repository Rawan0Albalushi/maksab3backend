
<div class="row">
    <div class="col-lg-12 text-center "><h1 > {{translate('Store wise review list')}}
    </h1></div>
    <div class="col-lg-12">

    <table>
        <thead>
            <tr>
                <th>{{ translate('Store details') }}</th>
                <th></th>
                <th>
                    {{ translate('Store name')  }}- {{ $data['store_name'] ?? translate('All') }}
                    <br>
                    {{ translate('Store ID')  }}- {{ $data['store_id'] ?? translate('All') }}
                    <br>

                    {{ translate('Rating')  }}- {{ $data['rating']?? translate('All') }}
                    <br>
                    {{ translate('Reviews')  }}- {{ $data['total_reviews'] ?? translate('All') }}
                </th>
                <th> </th>
                </tr>


        <tr>
            <th>{{ translate('SL') }}</th>
            <th>{{translate('Review ID')}}</th>
            <th>{{ translate('Item name') }}</th>
            <th>{{ translate('Order ID') }}</th>
            <th>{{ translate('Customer name') }}</th>
            <th>{{ translate('Rating') }}</th>
            <th>{{ translate('review') }}</th>
            <th >{{translate('Store reply')}}</th>
            <th>{{ translate('Status') }}</th>

        </thead>
        <tbody>
        @foreach($data['data'] as $key => $review)

            <tr>
        <td>{{ $loop->index+1}}</td>
        <td>{{$review?->review_id}}</td>
        <td>{{ $review?->item?->name }}</td>
        <td> {{$review->order_id}}</td>
        <td>
            {{$review?->customer ? $review?->customer?->f_name .' '.$review?->customer?->l_name  : translate('No data found')}}
        </td>
        <td> {{$review->rating}}</td>
        <td>{{$review->comment}}</td>
        <td>{{ $review?->reply ?? translate('Not given') }}</td>
        <td>{{ $review->status == 1 ? translate('messages.Active') : translate('messages.Inactive') }}</td>

            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
