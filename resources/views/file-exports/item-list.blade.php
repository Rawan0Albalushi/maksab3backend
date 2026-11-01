<div class="row">
    <div class="col-lg-12 text-center ">
        <h1> {{ Config::get('module.current_module_type') == 'food' ? translate('Food list') : translate('Item list') }}
        </h1>
    </div>
    <div class="col-lg-12">

        <table>
            <thead>
                <tr>
                    <th>{{ translate('Filter criteria') }}</th>
                    <th></th>
                    <th></th>
                    <th>
                        {{ translate('Store') }}: {{ $data['store'] ?? translate('All') }}

                        @isset($data['zone'])
                        <br>
                        {{ translate('Zone') }}: {{ $data['zone'] ?? translate('All') }}
                        @endisset


                        <br>
                        {{ translate('Module') }}: {{ $data['module_name'] ?? translate('N/A') }}
                        <br>
                        {{ translate('Category') }}: {{ $data['category'] ?? translate('N/A') }}


                        @isset($data['filter'])

                        <br>
                        {{ translate('Filter') }}:
                            @if ($data['filter'] == 'custom' && isset($data['from'] , $data['to']))
                            {{ translate('Custom date') }} : {{ $data['from'] }} to {{ $data['to'] }}

                        @else
                            {{ translate($data['filter']) ?? translate('N/A') }}
                            @endif

                        @endisset



                        <br>
                        {{ translate('Search bar content') }}: {{ $data['search'] ?? translate('N/A') }}
                    </th>
                    <th> </th>
                    <th></th>
                    <th></th>
                    <th></th>
                </tr>


                <tr>
                    <th>{{ translate('SL') }}</th>
                    <th>{{ translate('Image') }}</th>
                    <th>{{ translate('Item name') }}</th>
                    <th>{{ translate('Description') }}</th>
                    <th>{{ translate('Category name') }}</th>
                    <th>{{ translate('Subcategory name') }}</th>
                    @if (Config::get('module.current_module_type') == 'food')
                        <th>{{ translate('Food type') }}</th>
                    @else
                        <th>{{ translate('Available stock') }} </th>
                    @endif
                    <th>{{ translate('price') }}</th>
                    <th>{{ translate('Available Variations') }} </th>


                    @if (Config::get('module.current_module_type') == 'food')
                        <th>{{ translate('Available addons') }} </th>
                    @else
                        <th>{{ translate('Item unit') }}</th>
                    @endif
                    <th>{{ translate('Discount') }} </th>
                    <th>{{ translate('Discount type') }} </th>


                    <th>{{ translate('Available from') }} </th>
                    <th>{{ translate('Available till') }} </th>
                    <th>{{ translate('Store name') }} </th>
                    <th>{{ translate('Tags') }} </th>


                    <th>{{ translate('Status') }} </th>
                    @if ($data['productWiseTax'])
                        <th class="border-0 w--1">{{ translate('VAT/tax') }}</th>
                    @endif
            </thead>
            <tbody>
                @foreach ($data['data'] as $key => $item)
                    <tr>
                        <td>{{ $loop->index + 1 }}</td>
                        <td> &nbsp;</td>
                        <td>{{ $item->name }}</td>
                        <td>{{ $item->description }}</td>
                        <td>
                            {{ \App\CentralLogics\Helpers::get_category_name($item->category_ids) }}
                        </td>
                        <td>
                            {{ \App\CentralLogics\Helpers::get_sub_category_name($item->category_ids) ?? translate('N/A') }}
                        </td>
                        @if (Config::get('module.current_module_type') == 'food')
                            <td> {{ $item->veg == 1 ? translate('Veg') : translate('Non veg') }}</td>
                        @else
                            <td>{{ max((int) $item->stock, 0) }}</td>
                        @endif
                        <td>
                            {{ \App\CentralLogics\Helpers::format_currency($item->price) }}
                        </td>
                        <td>
                            @if (Config::get('module.current_module_type') == 'food')
                                {{ \App\CentralLogics\Helpers::get_food_variations($item->food_variations) == '  ' ? translate('N/A') : \App\CentralLogics\Helpers::get_food_variations($item->food_variations) }}
                            @else
                                {{ \App\CentralLogics\Helpers::get_attributes($item->choice_options) == '  ' ? translate('N/A') : \App\CentralLogics\Helpers::get_attributes($item->choice_options) }}
                            @endif
                        </td>


                        <td>
                            @if (Config::get('module.current_module_type') == 'food')
                                {{ \App\CentralLogics\Helpers::get_addon_data($item->add_ons) == 0 ? translate('N/A') : \App\CentralLogics\Helpers::get_addon_data($item->add_ons) }}
                            @else
                                {{ $item?->unit?->unit ?? translate('N/A') }}
                            @endif

                        </td>
                        <td>{{ $item->discount == 0 ? translate('N/A') : $item->discount }}</td>
                        <td>{{ $item->discount_type }}</td>


                        <td>{{ Config::get('module.current_module_type') != 'grocery' ? \Carbon\Carbon::parse($item->available_time_starts)->format('H:i A') : translate('N/A') }}
                        </td>
                        <td>{{ Config::get('module.current_module_type') != 'grocery' ? \Carbon\Carbon::parse($item->available_time_ends)->format('H:i A') : translate('N/A') }}
                        </td>
                        <td>{{ $item?->store?->name }}</td>

                        @if (isset($data['table']) && $data['table'] == 'TempProduct')
                            <td>
                                @php($tagids = json_decode($item?->tag_ids) ?? [])
                                @php($tags = \App\CentralLogics\Helpers::tags_by_ids($tagids))
                                @forelse($tags as $c)
                                {{ $c->tag . ',' }} @empty {{ translate('N/A') }}
                                @endforelse
                            </td>
                            <td> {{ $item->is_rejected == 1 ? translate('rejected') : translate('Pending') }}</td>
                        @else
                            <td>
                                @forelse ($item->tags as $c)
                                {{ $c->tag . ',' }} @empty {{ translate('N/A') }}
                                @endforelse
                            </td>
                            <td> {{ $item->status == 1 ? translate('Active') : translate('Inactive') }}</td>
                        @endif
                        @if ($data['productWiseTax'])
                            <td>
                                <span class="d-block font-size-sm text-body">

                                    @forelse ($item?->taxVats?->pluck('tax.name', 'tax.tax_rate')->toArray() as $key => $tax)
                                        <br>
                                        <span> {{ $tax }} : <span class="font-bold">
                                                ({{ $key }}%)
                                            </span> </span>
                                        <br>
                                    @empty
                                        <span> {{ translate('messages.No tax') }} </span>
                                    @endforelse
                                </span>
                            </td>
                        @endif

                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
