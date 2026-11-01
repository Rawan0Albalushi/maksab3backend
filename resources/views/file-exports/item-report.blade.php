<div class="row">
    <div class="col-lg-12 text-center "><h1 >{{ translate('Item report') }}</h1></div>
    <div class="col-lg-12">



    <table>
        <thead>
            <tr>
                <th>{{ translate('Search criteria') }}</th>
                <th></th>
                <th></th>
                <th>
                    {{ translate('Module' )}} - {{ $data['module']?translate($data['module']):translate('All') }}
                    <br>
                    {{ translate('Zone' )}} - {{ $data['zone']??translate('All') }}
                    <br>
                    {{ translate('Store' )}} - {{ $data['store']??translate('All') }}
                    @if ($data['from'])
                    <br>
                    {{ translate('from' )}} - {{ $data['from']?Carbon\Carbon::parse($data['from'])->format('d M Y'):'' }}
                    @endif
                    @if ($data['to'])
                    <br>
                    {{ translate('to' )}} - {{ $data['to']?Carbon\Carbon::parse($data['to'])->format('d M Y'):'' }}
                    @endif
                    <br>
                    {{ translate('Filter')  }}- {{  translate($data['filter']) }}
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
            <th>{{translate('Item image')}}</th>
            <th>{{translate('Item name')}}</th>
            <th>{{translate('messages.Module')}}</th>
            <th>{{translate('Store name')}}</th>
            <th>{{translate('messages.stock')}}</th>
            <th>{{translate('messages.Total order count')}}</th>
            <th>{{translate('Unit price')}}</th>
            <th>{{translate('messages.Total amount sold')}}</th>
            <th>{{translate('Total discount given')}}</th>
            <th>{{translate('messages.Average sale value')}}</th>
            <th>{{translate('messages.Total ratings given')}}</th>
            <th>{{translate('messages.Average ratings')}}</th>
        </thead>
        <tbody>
        @foreach($data['items'] as $key => $item)
            <tr>
                <td>{{ $key+1}}</td>
                <td></td>
                <td>{{$item['name']}}</td>
                <td>
                    {{ $item->module->module_name }}
                </td>
                <td>
                    @if($item->store)
                    {{ $item->store->name }}
                    @else
                    {{translate('messages.Store deleted')}}
                    @endif
                </td>
                <td>
                    {{$item->module->module_type == 'food'? translate('N/A') : max((int) $item->stock, 0)}}
                </td>
                <td>
                    {{$item->orders_sum_quantity ?? 0}}
                </td>
                <td>
                    {{ \App\CentralLogics\Helpers::format_currency($item->price) }}
                </td>
                <td>
                    {{ \App\CentralLogics\Helpers::format_currency($item->orders_sum_price) }}
                </td>
                <td>
                    {{ \App\CentralLogics\Helpers::format_currency($item->total_discount) }}
                </td>
                <td>
                    {{ $item->orders_count>0? \App\CentralLogics\Helpers::format_currency(($item->orders_sum_price-$item->total_discount)/($item->orders_sum_quantity ?? 0) ) :0 }}
                </td>
                <td>{{ $item->rating_count }}</td>
                <td>{{ round($item->avg_rating,1) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
