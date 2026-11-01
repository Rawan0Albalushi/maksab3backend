
<div class="row">
    <div class="col-lg-12 text-center "><h1 > {{translate('Review list')}}
    </h1></div>
    <div class="col-lg-12">

    <table>
        <thead>
            <tr>
                <th>{{ translate('Filter criteria') }}</th>
                <th></th>
                <th>
                    {{ translate('Store')  }}: {{ $data['store'] ?? translate('All') }}
                    <br>

                    @if (isset($data['category']) )
                    {{ translate('Category')  }}: {{ $data['category'] ?? translate('All') }}
                    <br>
                    @endif

                    {{ translate('Total reviews')  }}: {{ $data['data_count'] ?? $data['data']->count() }}
                    <br>
                    {{ translate('Search bar content')  }}: {{ $data['search'] ?? translate('N/A') }}

                </th>
                <th> </th>
                </tr>


        <tr>
            <th>{{ translate('SL') }}</th>
            <th>{{ translate('Item name') }}</th>
            <th>{{ translate('Order ID') }}</th>
            <th>{{ translate('Customer name') }}</th>
            <th>{{ translate('Store name') }}</th>
            <th>{{ translate('Rating') }}</th>
            <th>{{ translate('review') }}</th>
            <th>{{ translate('Date') }}</th>
            <th>{{ translate('Store reply') }}</th>
            <th>{{ translate('Status') }}</th>

        </thead>
        <tbody>
        @foreach($data['data'] as $key => $review)
            <tr>
        <td>{{ $loop->index+1}}</td>
        <td>{{ $review?->item?->name }}</td>
        <td> {{$review->order_id}}</td>
        <td>
            {{ $review?->customer ?  $review?->customer?->f_name .' '.$review?->customer?->l_name  : translate('No data found')}}
        </td>
        <td>{{ $review?->item?->store?->name ?? translate('messages.Store deleted') }}</td>
        <td> {{$review->rating}}</td>
        <td>{{$review->comment}}</td>
        <td>{{ $review->created_at->format('d-m-Y') }}</td>
        <td>{{$review->reply ?? translate('Not replied yet')}}</td>
        <td>{{ $review->status == 1 ? translate('messages.Active') : translate('messages.Inactive') }}</td>

            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
