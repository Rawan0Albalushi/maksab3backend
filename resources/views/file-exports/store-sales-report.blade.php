<div class="row">
    <div class="col-lg-12 text-center "><h1 >{{ translate('store_sales_reports') }}</h1></div>
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
                <th>{{ translate('Analytics') }}</th>
                <th></th>
                <th></th>
                <th>
                    {{ translate('Gross sale')  }}- {{ \App\CentralLogics\Helpers::number_format_short($data['orders']->order_amount) }}
                    <br>
                    {{ translate('Total tax')  }}- {{ \App\CentralLogics\Helpers::number_format_short($data['orders']->total_tax_amount) }}
                    <br>
                    {{ translate('Total commission')  }}- {{ \App\CentralLogics\Helpers::number_format_short($data['orders']->transaction_sum_admin_commission+$data['orders']->transaction_sum_delivery_fee_comission-$data['orders']->transaction_sum_admin_expense) }}
                    <br>
                    {{ translate('total_store_earning')  }}- {{ \App\CentralLogics\Helpers::number_format_short($data['orders']->transaction_sum_store_amount) }}
                </th>
                <th> </th>
                <th></th>
                <th></th>
                <th></th>
            </tr>
        <tr>
            <th>{{ translate('SL') }}</th>
            <th>{{translate('product_image')}}</th>
            <th>{{ translate('Product name') }}</th>
            <th>{{ translate('Available Variations') }}</th>
            <th>{{ translate('Quantity sold') }}</th>
            <th>
                {{ translate('Gross sale') }}</th>
            <th>
                {{ translate('Discount given') }}</th>
        </thead>
        <tbody>
        @foreach($data['items'] as $key => $item)
        <tr>
            <td>{{$key+1}}</td>
            <td></td>
            <td>{{  $item['name']  }}</td>
            <td>
                @if ($item->module->module_type == 'food')
                {{ \App\CentralLogics\Helpers::get_food_variations($item->food_variations) == "  "  ? translate('N/A'): \App\CentralLogics\Helpers::get_food_variations($item->food_variations) }}
                @else
                {{ \App\CentralLogics\Helpers::get_attributes($item->choice_options) == "  "  ? translate('N/A'): \App\CentralLogics\Helpers::get_attributes($item->choice_options) }}
                @endif
            </td>
            <td>
                {{ $item->orders_sum_quantity ?? 0 }}
            </td>
            <td>
                {{\App\CentralLogics\Helpers::format_currency($item->orders_sum_price) }}
            </td>
            <td>
                {{ \App\CentralLogics\Helpers::format_currency($item->total_discount) }}
            </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
