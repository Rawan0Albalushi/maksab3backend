<div class="row">
    <div class="col-lg-12 text-center "><h1 > {{ Config::get('module.current_module_type') === 'service' ? translate('Service campaign list') : (Config::get('module.current_module_type')== 'food' ?  translate('Food campaign list') : translate('Item campaign list')) }}
    </h1></div>
    <div class="col-lg-12">

    <table>
        <thead>
            <tr>
                <th>{{ translate('Filter criteria') }}</th>
                <th></th>
                <th></th>
                <th>
                    {{ translate('Module')  }}: {{ $module_name }}
                    <br>
                    {{ translate('Search bar content')  }}: {{ $search ??translate('N/A') }}
                </th>
                <th> </th>
                <th></th>
                <th></th>
                <th></th>
                </tr>


        <tr>
            <th>{{ translate('SL') }}</th>
            <th>{{ Config::get('module.current_module_type') === 'service' ? translate('Service name') : translate('Item name') }}</th>
            <th>{{ translate('Description') }}</th>
            <th>{{ translate('Category name') }}</th>
            <th>{{ translate('Subcategory name') }}</th>
            <th>{{ Config::get('module.current_module_type') === 'service' ? translate('Service unit') : translate('Item unit') }}</th>
            <th>{{ translate('price') }}</th>
            <th>{{ translate('Available Variations') }} </th>
            <th>{{ translate('Discount') }} </th>
            <th>{{ translate('Discount type') }} </th>
            @if (Config::get('module.current_module_type') != 'food')
            <th>{{ translate('Available stock') }} </th>
            @endif


            <th>{{ translate('Start date') }} </th>
            <th>{{ translate('End date') }} </th>
            <th>{{ translate('Daily start time') }} </th>
            <th>{{ translate('Daily end time') }} </th>
            <th>{{ Config::get('module.current_module_type') === 'service' ? translate('Provider name') : translate('Store name') }} </th>
        </thead>
        <tbody>
        @foreach($data as $key => $campaign)
            <tr>
        <td>{{ $loop->index+1}}</td>
        <td>{{ $campaign->title }}</td>
        <td>{{ $campaign->description }}</td>
        <td>
            {{ \App\CentralLogics\Helpers::get_category_name($campaign->category_ids) }}
        </td>
        <td>
        {{ \App\CentralLogics\Helpers::get_sub_category_name($campaign->category_ids) ?? translate('N/A')  }}
        </td>

        <td>{{ $campaign?->unit?->unit ?? translate('N/A') }}</td>
        <td>
            {{ \App\CentralLogics\Helpers::format_currency($campaign->price) }}
        </td>
        <td>
            @if (Config::get('module.current_module_type') == 'food')
            {{ \App\CentralLogics\Helpers::get_food_variations($campaign->food_variations) == "  "  ? translate('N/A'): \App\CentralLogics\Helpers::get_food_variations($campaign->food_variations) }}
            @else
            {{ \App\CentralLogics\Helpers::get_attributes($campaign->choice_options) == "  "  ? translate('N/A'): \App\CentralLogics\Helpers::get_attributes($campaign->choice_options) }}
            @endif
        </td>
        <td>{{ $campaign->discount }}</td>
        <td>{{ $campaign->discount_type }}</td>


        @if (Config::get('module.current_module_type') != 'food')
            <td>{{ max((int) $campaign->stock, 0) }}</td>
        @endif

        <td>{{ $campaign->start_date->format('d M Y') }}</td>
        <td>{{ $campaign->end_date->format('d M Y') }}</td>
        <td>{{ \Carbon\Carbon::parse($campaign->start_time)->format("H:i A") }}</td>
        <td>{{ \Carbon\Carbon::parse($campaign->end_time)->format("H:i A") }}</td>
        <td>{{ $campaign?->store?->name }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
