@extends('layouts.vendor.app')

@section('title', translate('Item preview'))

@push('css_or_js')
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="d-flex flex-wrap justify-content-between">
                <div>
                    <h1 class="page-header-title text-break">
                        <span class="page-header-icon">
                            <img src="{{ asset('public/assets/admin/img/outline/stock.svg') }}" class="w--26" alt="">
                        </span>
                        <span>{{ translate('Product details') }}</span>
                    </h1>
                    <p class="page-header-desc">{{ translate('What you asked to change on this item, next to what is live now.') }}</p>
                </div>

            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <div class="row flex-wrap">
                    <div>
                        <div class="d-flex flex-wrap align-items-start gap-3 food--media position-relative mr-4">
                            <div class="position-relative">
                                @include('partials._product-media-slider', ['product' => $product])
                                @if ($product['is_rejected'] == 1 )
                                    <div class="reject-info"> {{ translate('Your item has been rejected') }}</div>
                                @else
                                    <div class="pending-info"> {{ translate('This item is under review') }}</div>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="w-70 flex-grow">
                        <div class="d-flex flex-wrap gap-2 justify-content-between">
                            @if ($language)
                            <ul class="nav nav-tabs border-0 mb-3">
                                <li class="nav-item">
                                    <a class="nav-link lang_link active" href="#"
                                        id="default-link">{{ translate('Default') }}</a>
                                </li>
                                @foreach ($languages as $lang)
                                    <li class="nav-item">
                                        <a class="nav-link lang_link" href="#"
                                        id="{{ $lang }}-link">{{ $language_labels[$lang] }}</a>
                                    </li>
                                    @endforeach
                                </ul>
                                @endif
                                <div class="d-flex flex-wrap gap-2 align-items-start">
                                    <a class="btn btn--sm btn-outline-danger form-alert" href="javascript:"
                                    data-id="food-{{$product['id']}}" data-message="{{ translate('Want to delete this item?') }}" title="{{translate('messages.Delete item')}}">{{ translate('messages.Delete') }} <i class="tio-delete-outlined"></i>
                                    </a>
                                    <a href="{{ route('vendor.item.edit', [$product['id'],'temp_product' => true]) }}" class="btn btn--sm btn-outline-primary">
                                        <i class="tio-edit"></i>  {{ translate('Edit & resubmit') }}
                                    </a>
                                <form action="{{route('vendor.item.delete',[$product['id']])}}"
                                        method="post" id="food-{{$product['id']}}">
                                    @csrf @method('delete')
                                    <input type="hidden" value="1" name="temp_product" >
                                </form>


                                </div>
                            </div>

                        <div class="lang_form" id="default-form">
                            <h2 class="mt-3">{{ $product?->getRawOriginal('name') }} </h2>
                            <h6> {{ translate('Description') }}:</h6>
                            <P> {{ $product?->getRawOriginal('description') }}</P>
                        </div>

                        @foreach ($languages as $lang)
                                    <?php
                                    if (count($product['translations'])) {
                                        $translate = [];
                                        foreach ($product['translations'] as $t) {
                                            if ($t->locale == $lang && $t->key == 'name') {
                                                $translate[$lang]['name'] = $t->value;
                                            }
                                            if ($t->locale == $lang && $t->key == 'description') {
                                                $translate[$lang]['description'] = $t->value;
                                            }
                                        }
                                    }
                                    ?>
                                    <div class="d-none lang_form" id="{{ $lang }}-form">
                                        <h2>{{ $translate[$lang]['name'] ?? '' }} </h2>
                                        <h6> {{ translate('Description') }}:</h6>
                                        <P> {!! $translate[$lang]['description'] ?? '' !!}</P>
                                    </div>
                        @endforeach
                    </div>
                </div>


            </div>
        </div>

    <div class="card mb-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-borderless table-thead-bordered">
                    <thead class="thead-light">
                        <tr>
                            <th class="px-4 border-0">
                                <h4 class="m-0 text-capitalize">{{ translate('General information') }}</h4>
                            </th>
                            <th class="px-4 border-0">
                                <h4 class="m-0 text-capitalize">{{ translate('Price information') }}</h4>
                            </th>

                            @if (in_array($product->module->module_type ,['food','grocery']))
                            <th class="px-4 border-0">
                                <h4 class="m-0 text-capitalize">{{ translate('Nutrition') }}</h4>
                            </th>
                            <th class="px-4 border-0">
                                <h4 class="m-0 text-capitalize">{{ translate('Allergy') }}</h4>
                            </th>

                        @endif
                        @if (in_array($product->module->module_type ,['pharmacy']))
                            <th class="px-4 border-0">
                                <h4 class="m-0 text-capitalize">{{ translate('Generic name') }}</h4>
                            </th>
                        @endif
                            <th class="px-4 border-0">
                                <h4 class="m-0 text-capitalize">{{ translate('Available Variations') }}</h4>
                            </th>
                            @if ($product->module->module_type == 'food')
                                <th class="px-4 border-0">
                                    <h4 class="m-0 text-capitalize">{{ translate('Addons') }}</h4>
                                </th>
                            @endif
                            <th class="px-4 border-0">
                                <h4 class="m-0 text-capitalize">{{ translate('Tags') }}</h4>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="px-4 max-w--220px">
                                <span class="d-block mb-1">
                                    <span>{{ translate('messages.Store') }} : </span>
                                    <strong>{{ $product?->store?->name }}</strong>
                                </span>
                                <span class="d-block mb-1">
                                    <span>{{ translate('messages.Category') }} : </span>
                                    <strong>{{ Str::limit(($product?->category?->parent ? $product?->category?->parent?->name : $product?->category?->name )  ?? translate('messages.uncategorize')
                                        , 20, '...') }}</strong>
                                </span>

                                <span class="d-block mb-1">
                                    <span>{{ translate('Subcategory') }} : </span>
                                    <strong>{{ Str::limit(( $product?->category?->parent?->name ? $product?->category?->name : '---' )
                                        , 20, '...') }}</strong>
                                </span>

                                @if ($product->module->module_type == 'grocery')
                                <span class="d-block mb-1">
                                    <span>{{ translate('Is organic') }} : </span>
                                    <strong> {{  $product->organic == 1 ?  translate('messages.Yes') : translate('messages.No') }}</strong>
                                </span>
                                @endif
                                @if ($product->module->module_type == 'food')
                                <span class="d-block mb-1">
                                    <span>{{ translate('messages.Item type') }} : </span>
                                    <strong> {{  $product->veg == 1 ?  translate('Veg') : translate('Non veg') }}</strong>
                                </span>
                                @else
                                <span class="d-block mb-1">
                                    <span>{{ translate('messages.Total stock') }} : </span>
                                    <strong> {{  max((int) $product->stock, 0)  }}</strong>
                                </span>

                                    @if ($product?->unit)
                                    <span class="d-block mb-1">
                                        <span>{{ translate('Unit') }} : </span>
                                        <strong> {{ $product?->unit?->unit  }}</strong>
                                    </span>
                                    @endif
                                @endif
                                @if (config('module.' . $product->module->module_type)['item_available_time'])
                                <span class="d-block mb-1">
                                    {{ translate('messages.Available time starts') }} :
                                    <strong>{{ date(config('timeformat'), strtotime($product['available_time_starts'])) }}</strong>
                                </span>
                                <span class="d-block mb-1">
                                    {{ translate('messages.Available time ends') }} :
                                    <strong>{{ date(config('timeformat'), strtotime($product['available_time_ends'])) }}</strong>
                                </span>
                            @endif
                            </td>
                            <td class="px-4">
                                <span class="d-block mb-1">
                                    <span>{{ translate('Unit price') }} : </span>
                                    <strong>{{ \App\CentralLogics\Helpers::format_currency($product['price']) }}</strong>
                                </span>
                                <span class="d-block mb-1">
                                    <span>{{ translate('Discounted amount') }} :</span>
                                    <strong>{{ \App\CentralLogics\Helpers::format_currency(\App\CentralLogics\Helpers::discount_calculate($product, $product['price'])) }}</strong>
                                </span>
                                <span class="d-block mb-1">
                                    <span>{{ translate('Discount') }} :</span>
                                    <strong> {{ $product->discount_type == 'percent' ? $product->discount .'%' :  \App\CentralLogics\Helpers::format_currency($product['discount']) }} </strong>
                                </span>



                            </td>


                            @if (in_array($product->module->module_type ,['food','grocery']))
                            <td class="px-4 product-gallery-info">

                                    @foreach($product_nutritions as $nutrition)
                                        {{$nutrition}}{{ !$loop->last ? ',' : '.'}}
                                    @endforeach

                            </td>
                            <td class="px-4 product-gallery-info">
                                    @foreach($product_allergies as $allergy)
                                        {{$allergy}}{{ !$loop->last ? ',' : '.'}}
                                    @endforeach

                            </td>
                            @endif
                            @if (in_array($product->module->module_type ,['pharmacy']))
                                <td class="px-4 product-gallery-info">
                                    {{ $generic_name }}
                                </td>
                            @endif




                            <td class="px-4">
                                @if ($product->module->module_type == 'food')
                                    @if ($product->food_variations && is_array(json_decode($product['food_variations'], true)))
                                        @foreach (json_decode($product->food_variations, true) as $variation)
                                            @if (isset($variation['price']))
                                                <span class="d-block mb-1 text-capitalize">
                                                    <strong>
                                                        {{ translate('Please update the food variations.') }}
                                                    </strong>
                                                </span>
                                            @break

                                        @else
                                            <span class="d-block text-capitalize">
                                                <strong>
                                                    {{ $variation['name'] }} -
                                                </strong>
                                                @if ($variation['type'] == 'multi')
                                                    {{ translate('messages.Multiple select') }}
                                                @elseif($variation['type'] == 'single')
                                                    {{ translate('messages.Single select') }}
                                                @endif
                                                @if ($variation['required'] == 'on')
                                                    - ({{ translate('messages.Required.') }})
                                                @endif
                                            </span>

                                            @if ($variation['min'] != 0 && $variation['max'] != 0)
                                                ({{ translate('messages.Min select') }}: {{ $variation['min'] }} -
                                                {{ translate('messages.Max select') }}: {{ $variation['max'] }})
                                            @endif

                                            @if (isset($variation['values']))
                                                @foreach ($variation['values'] as $value)
                                                    <span class="d-block text-capitalize">
                                                        &nbsp; &nbsp; {{ $value['label'] }} :
                                                        <strong>{{ \App\CentralLogics\Helpers::format_currency($value['optionPrice']) }}</strong>
                                                    </span>
                                                @endforeach
                                            @endif
                                        @endif
                                    @endforeach
                                @endif
                            @else
                                @if ($product->variations && is_array(json_decode($product['variations'], true)))
                                    @foreach (json_decode($product['variations'], true) as $variation)
                                        <span class="d-block mb-1 text-capitalize">
                                            {{ $variation['type'] }} :
                                            {{ \App\CentralLogics\Helpers::format_currency($variation['price']) }}
                                        </span>
                                    @endforeach
                                @endif
                        </td>
                        @endif
                        @if ($product->module->module_type == 'food')
                            <td class="px-4">
                                    @foreach ($addons as $addon)
                                        <span class="d-block mb-1 text-capitalize">
                                            {{ $addon['name'] }} :
                                            {{ \App\CentralLogics\Helpers::format_currency($addon['price']) }}
                                        </span>
                                    @endforeach
                            </td>
                        @endif

                            <td>
                                @foreach($tags as $c) {{$c->tag}}{{ !$loop->last ? ',' : '.'}} @endforeach
                            </td>

                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

</div>
@endsection

@push('script_2')
<script>
    "use strict";
        function request_alert(url, message) {
            Swal.fire({
                title: '{{translate('messages.Are you sure?')}}',
                text: message,
                type: 'warning',
                showCancelButton: true,
                cancelButtonColor: 'default',
                confirmButtonColor: '#FC6A57',
                cancelButtonText: '{{translate('messages.No')}}',
                confirmButtonText: '{{translate('messages.Yes')}}',
                reverseButtons: true
            }).then((result) => {
                if (result.value) {
                    location.href = url;
                }
            })
        }

    function cancelled_status(route, message, processing = false) {
            Swal.fire({
                    //text: message,
                    title: '{{ translate('messages.Are you sure?') }}',
                    type: 'warning',
                    showCancelButton: true,
                    cancelButtonColor: 'default',
                    confirmButtonColor: '#FC6A57',
                    cancelButtonText: '{{ translate('messages.Cancel') }}',
                    confirmButtonText: '{{ translate('messages.Submit') }}',
                    inputPlaceholder: "{{ translate('Enter a reason') }}",
                    input: 'text',
                    html: message + '<br/>'+'<label>{{ translate('Enter a reason') }}</label>',
                    inputValue: processing,
                    preConfirm: (note) => {
                        location.href = route + '&note=' + note;
                    },
                    allowOutsideClick: () => !Swal.isLoading()
                })
        }
</script>
@endpush
