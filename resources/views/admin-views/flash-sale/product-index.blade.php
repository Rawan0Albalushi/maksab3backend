@extends('layouts.admin.app')

@section('title',translate('Flash sale items'))

@push('css_or_js')

@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/outline/condition.svg')}}" class="w--26" alt="">
                </span>
                <span>
                    {{translate('messages.Flash sale product setup')}}
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('Pick the items going into this flash sale and the discount each one carries.') }}</p>
        </div>
        <div class="row g-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <form action="{{route('admin.flash-sale.store-product')}}" method="post">
                            @csrf
                            <input type="hidden" name="flash_sale_id" value="{{ $flash_sale->id }}">
                            <div class="row g-3 mb-3">
                                <div class="col-12 mb-0">
                                    <div class="form-group mb-0" id="item_wise">
                                        <label class="input-label" for="exampleFormControlInput1">{{translate('messages.Select item')}} <span class="text-danger">*</span></label>
                                        <select name="item_id" id="choice_item" class="form-control js-select2-custom" placeholder="{{translate('messages.Select item')}}" required>

                                        </select>
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="form-group mb-0">
                                        <label class="input-label"
                                            for="total_stock">{{ translate('messages.Total stock') }} <span class="text-danger">*</span></label>
                                        <input type="number" placeholder="{{ translate('messages.Ex') . ': 10' }}" class="form-control" name="stock" min="0" id="quantity" required>
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="form-group mb-0">
                                        <label class="input-label"
                                            for="exampleFormControlInput1">{{ translate('Discount type') }} <span class="text-danger">*</span><span
                                                class="input-label-secondary text--title" data-toggle="tooltip"
                                                data-placement="right"
                                                data-original-title="{{ translate('Admin shares the same percentage/amount on discount as he takes commissions from stores') }}">
                                                <i class="tio-info-outined"></i>
                                            </span>
                                        </label>
                                        <select name="discount_type" id="discount_type"
                                            class="form-control js-select2-custom">
                                            <option value="percent">{{ translate('Percent') }}</option>
                                            <option value="amount">{{ translate('Amount') }}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="form-group mb-0">
                                        <label class="input-label"
                                            for="exampleFormControlInput1">{{ translate('Discount') }} <span class="text-danger">*</span></label>
                                        <input type="number" min="{{ \App\CentralLogics\Helpers::getDecimalPlaces() }}" max="9999999999999999999999" value="0" step="{{ \App\CentralLogics\Helpers::getDecimalPlaces() }}"
                                            name="discount" class="form-control" id="discount_amount"
                                            placeholder="{{ translate('messages.Ex') }}: 100" required>
                                    </div>
                                </div>
                            </div>

                            <div class="btn--container justify-content-end">
                                <button type="reset" class="btn btn--reset"><i class="tio-refresh"></i> {{translate('messages.Reset')}}</button>
                                <button type="submit" class="btn btn--primary"><i class="tio-checkmark-circle-outlined"></i> {{translate('messages.Submit')}}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card">
                    <div class="card-header py-2 border-0">
                        <div class="search--button-wrapper">
                            @include('partials._table-head', [
                                'title'    => translate('messages.Flash sale product list'),
                                'subtitle' => translate('messages.Items taking part in this flash sale and their discounted prices.'),
                                'count'    => $items->total(),
                                'count_id' => 'itemCount',
                            ])
                            <form  class="search-form">

                                <div class="input-group input--group">
                                    <input id="datatableSearch_" value="{{ request()?->search ?? null }}" type="search" name="search" class="form-control"
                                            placeholder="{{translate('Ex') . ' : ' . translate('Product name')}}" aria-label="Search" >
                                    <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="table-responsive datatable-custom">
                        <table id="columnSearchDatatable"
                               class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table"
                               data-hs-datatables-options='{
                                 "order": [],
                                 "orderCellsTop": true,
                                 "paging":false
                               }'>
                            <thead class="thead-light">
                            <tr class="text-center">
                                <th class="border-0">{{translate('SL')}}</th>
                                <th class="border-0">{{translate('messages.Product')}}</th>
                                <th class="border-0">{{translate('messages.Store')}}</th>
                                <th class="border-0">{{translate('messages.Stock for this sale')}}</th>
                                <th class="border-0">{{translate('Quantity sold')}}</th>
                                <th class="border-0">{{translate('Discount')}}</th>
                                <th class="border-0">{{translate('messages.price')}}</th>
                                <th class="border-0">{{translate('messages.Status')}}</th>
                                <th class="border-0">{{translate('messages.Action')}}</th>
                            </tr>

                            </thead>

                            <tbody id="set-rows">
                            @foreach($items as $key=>$item)
                                <tr>
                                    <td class="text-center">
                                        <span class="mr-3">
                                            {{$key+$items->firstItem()}}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        @if ($item->item)
                                        <a class="media align-items-center" href="{{route('admin.item.view',[$item['item_id']])}}">
                                            <img class="avatar avatar-lg mr-3 onerror-image" src="{{ $item->item['image_full_url'] }}"
                                            data-onerror-image="{{asset('public/assets/admin/img/160x160/img2.jpg')}}" alt="{{$item->item->name}} image">
                                            <div class="media-body">
                                                <h5 title="{{ $item->item['name'] }}" class="text-hover-primary mb-0">{{Str::limit($item->item['name'],20,'...')}}</h5>
                                            </div>
                                        </a>
                                        @else
                                            <span class="text--danger">{{ translate('messages.item deleted!') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-center" title="{{ $item->item?->store?->name }}">

                                        @if ($item->item?->store)
                                            <a href="{{route('admin.store.view', $item->item->store->id)}}" class="" > {{  Str::limit($item->item->store->name, 20, '...') }}</a>
                                        @else
                                        {{Str::limit(translate('messages.Store deleted'), 20, '...')}}

                                        @endif
                                        </td>
                                    <td class="text-center">
                                        {{ $item['stock'] }}
                                    </td>
                                    <td class="text-center">
                                        {{ $item['sold'] }}
                                    </td>
                                    <td class="text-center">
                                        {{ \App\CentralLogics\Helpers::format_currency($item['discount_amount']) }}
                                    </td>
                                    <td class="text-center">
                                        {{ \App\CentralLogics\Helpers::format_currency($item['price']) }}
                                    </td>
                                    <td class="text-center">
                                        <label class="toggle-switch toggle-switch-sm" for="publishCheckbox{{$item->id}}">
                                            <input type="checkbox" data-url="{{route('admin.flash-sale.status-product',[$item['id'],$item->status?0:1])}}" class="toggle-switch-input redirect-url" id="publishCheckbox{{$item->id}}" {{$item->status?'checked':''}}>
                                            <span class="toggle-switch-label mx-auto">
                                                <span class="toggle-switch-indicator"></span>
                                            </span>
                                        </label>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn--container justify-content-center">
                                            <a class="btn action-btn action-btn--delete form-control form-alert" href="javascript:" data-id="item-{{$item['id']}}" data-message="{{ translate('Want to delete this item?') }}" title="{{translate('messages.Delete')}}"><i class="tio-delete-outlined"></i>
                                            </a>
                                            <form action="{{route('admin.flash-sale.delete-product',[$item['id']])}}"
                                                    method="post" id="item-{{$item['id']}}">
                                                @csrf @method('delete')
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if(count($items) !== 0)
                    <hr>
                    @endif
                    <div class="page-area">
                        {!! $items->links() !!}
                    </div>
                    @if(count($items) === 0)
                    <div class="empty--data">
                        <img src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="public">
                        <h5>
                            {{translate('No data found')}}
                        </h5>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

@endsection

@push('script_2')
    <script>
        let zone_id = [];
        let module_id = {{Config::get('module.current_module_id')}};

        function get_items()
        {
            let nurl = '{{url('/')}}/admin/item/get-items-flashsale?module_id='+module_id;

            if(!Array.isArray(zone_id))
            {
                nurl += '&zone_id='+zone_id;
            }

            $.get({
                url: nurl,
                dataType: 'json',
                success: function (data) {
                    $('#choice_item').empty().append(data.options);
                }
            });
        }
        $(document).on('ready', function () {

            module_id = {{Config::get('module.current_module_id')}};
            get_items();




                // INITIALIZATION OF SELECT2
                // =======================================================
                $('.js-select2-custom').each(function () {
                    let select2 = $.HSCore.components.HSSelect2.init($(this));
                });
            });

        $('#discount_type').on('change', function() {
         if($('#discount_type').val() == 'current_active_discount')
            {
                $('#discount_amount').attr("readonly","true");
            }
            else
            {
                $('#discount_amount').removeAttr("readonly");
            }
        });

        </script>
@endpush
