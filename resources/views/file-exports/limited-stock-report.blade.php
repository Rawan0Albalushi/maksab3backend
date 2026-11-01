<div class="row">
    <div class="col-lg-12 text-center "><h1 >{{ translate('Limited stock report') }}</h1></div>
    <div class="col-lg-12">



    <table>
        <thead>
            <tr>
                <th>{{ translate('Search criteria') }}</th>
                <th></th>
                <th></th>
                <th>
                    {{ translate('Zone' )}} - {{ $data['zone']??translate('All') }}
                    <br>
                    {{ translate('Store' )}} - {{ $data['store']??translate('All') }}
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
            <th>{{ translate('Current stock') }}</th>
            <th>{{ translate('Category name') }}</th>
            <th>{{translate('Unit')}}</th>
            <th>{{translate('variation')}}</th>
            <th>{{translate('price')}}</th>
            <th>{{translate('Store name')}}</th>
            <th>{{translate('Module name')}}</th>
        </thead>
        <tbody>
        @foreach($data['items'] as $key => $item)
            <tr>
                <td>{{ $key+1}}</td>
                <td></td>
                <td>{{$item['name']}}</td>
                <td>
                    @if ($item->module->module_type != 'food')
                    {{ max((int) $item->stock, 0) }}
                    @endif
                </td>
                <td>
                    {{ \App\CentralLogics\Helpers::get_category_name($item->category_ids) }}
                </td>
                <td>{{ $item?->unit?->unit ?? translate('N/A') }}</td>
                <td>
                    @if ($item->module->module_type == 'food')
                    {{ \App\CentralLogics\Helpers::get_food_variations($item->food_variations) == "  "  ? translate('N/A'): \App\CentralLogics\Helpers::get_food_variations($item->food_variations) }}
                    @else
                    {{ \App\CentralLogics\Helpers::get_attributes($item->choice_options) == "  "  ? translate('N/A'): \App\CentralLogics\Helpers::get_attributes($item->choice_options) }}
                    @endif
                </td>
                <td>
                    {{ \App\CentralLogics\Helpers::format_currency($item->price) }}
                </td>
                <td>
                    @if($item->store)
                    {{ $item->store->name }}
                    @else
                    {{translate('messages.Store deleted')}}
                    @endif
                </td>
                <td>
                    {{ $item->module->module_name }}
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
