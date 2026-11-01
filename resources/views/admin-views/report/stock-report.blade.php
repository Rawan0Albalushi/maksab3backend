@extends('layouts.admin.app')

@section('title',translate('Low stock list'))
@section('low_stock_list')
active
@endsection
@section('content')

<div class="content container-fluid">
    <div class="page-header">
        <h1 class="page-header-title">
            <span class="page-header-icon">
                <img src="{{asset('public/assets/admin/img/outline/report.svg')}}" class="w--26" alt="">
            </span>
            <span>
                {{translate('Low stock list')}}
                <span class="badge badge-soft-secondary" id="">{{ $items->total() }}</span>
            </span>
        </h1>
        <p class="page-header-desc">{{ translate('Items that have run low or run out, so you can act before a customer notices.') }}</p>
    </div>
    <div class="card mt-3">
        <div class="card-header border-0 py-2">
            <div class="search--button-wrapper justify-content-end">
                @include('partials._table-head', [
                    'subtitle' => translate('messages.Stock currently held against each item.'),
                ])

                <form class="search-form">
                    <div class="input-group input--group">
                        <input id="datatableSearch" name="search" type="search" class="form-control" placeholder="{{translate('Ex') . ' : ' . translate('Search by name')}}" aria-label="{{translate('Search')}}" value="{{request()->query('search')}}">
                        <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                    </div>
                </form>
                <div class="min--200">
                    <select name="zone_id" class="form-control js-select2-custom set-filter" data-url="{{ url()->full() }}" data-filter="zone_id" id="zone">
                        <option value="all">{{translate('All zones')}}</option>
                        @foreach(\App\CentralLogics\Helpers::zones_dropdown() as $z)
                            <option value="{{$z['id']}}" {{isset($zone) && $zone->id == $z['id']?'selected':''}}>
                                {{($z['name'])}}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="min--200">
                    <select name="store_id" data-placeholder="{{translate('Select store')}}" class="js-data-example-ajax form-control set-filter" data-url="{{ url()->full() }}" data-filter="store_id">
                        @if(isset($store))
                            <option value="{{$store->id}}" data-verified="{{ (int) $store->verified_seller }}" selected>{{$store->name}}</option>
                        @else
                            <option value="all" selected>{{translate('All stores')}}</option>
                        @endif
                    </select>
                </div>
                <div class="hs-unfold mr-2">
                    <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle min-height-40" href="javascript:;"
                        data-hs-unfold-options='{
                                "target": "#usersExportDropdown",
                                "type": "css-animation"
                            }'>
                        <i class="tio-download-to mr-1"></i> {{ translate('messages.Export') }}
                    </a>

                    <div id="usersExportDropdown"
                        class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">
                        <span class="dropdown-header">{{ translate('messages.Download options') }}</span>
                        <a id="export-excel" class="dropdown-item" href="{{route('admin.transactions.report.stock-wise-report-export', ['type'=>'excel', 'module_id'=>Config::get('module.current_module_id') ?? request()->query('module_id'), request()->getQueryString()])}}">
                            <img class="avatar avatar-xss avatar-4by3 mr-2"
                                src="{{ asset('public/assets/admin') }}/svg/components/excel.svg"
                                alt="Image Description">
                            Excel
                        </a>
                        <a id="export-csv" class="dropdown-item" href="{{route('admin.transactions.report.stock-wise-report-export', ['type'=>'csv', 'module_id'=>Config::get('module.current_module_id') ?? request()->query('module_id'), request()->getQueryString()])}}">
                            <img class="avatar avatar-xss avatar-4by3 mr-2"
                                src="{{ asset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg"
                                alt="Image Description">
                            CSV
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-responsive datatable-custom" id="table-div">
            <table id="datatable" class="table table-borderless table-thead-bordered table-nowrap card-table" data-hs-datatables-options='{
                        "columnDefs": [{
                            "targets": [],
                            "width": "5%",
                            "orderable": false
                        }],
                        "order": [],
                        "info": {
                        "totalQty": "#datatableWithPaginationInfoTotalQty"
                        },

                        "entries": "#datatableEntries",

                        "isResponsive": false,
                        "isShowPaging": false,
                        "paging":false
                    }'>
                <thead class="thead-light">
                    <tr>
                        <th class="border-0 w--2">{{translate('Name')}}</th>
                        <th class="border-0 w--2">{{translate('messages.Store')}}</th>
                        <th class="border-0">{{translate('messages.Zone')}}</th>
                        <th class="border-0">{{translate('Current stock')}}</th>
                        <th class="border-0">{{translate('messages.Action')}}</th>
                    </tr>
                </thead>

                <tbody id="set-rows">

                    @foreach($items as $item)
                    @php($stock = max((int) $item->stock, 0))
                    @php($warn_below = (int) ($item->store?->storeConfig?->minimum_stock_for_warning ?? 0))
                    <tr>
                        <td>
                            <a class="media align-items-center min-w-220" href="{{route('admin.item.view',[$item['id'],'module_id'=>$item['module_id']])}}">
                                <img class="avatar avatar-lg mr-3 onerror-image"

                                src="{{ $item['image_full_url'] ?? asset('public/assets/admin/img/160x160/img2.jpg') }}"
                                 data-onerror-image="{{asset('public/assets/admin/img/160x160/img2.jpg')}}" alt="{{$item->name}} image">
                                <div class="media-body">
                                    <h5 class="text-hover-primary mb-0 max-width-200px word-break line--limit-2">{{$item['name']}}</h5>
                                    <span class="d-block fs-12 text-muted">ID:{{$item['id']}}</span>
                                </div>
                            </a>
                        </td>
                        <td>
                            @if($item->store)
                            {{Str::limit($item->store->name,25,'...')}}
                            @else
                            {{translate('messages.Store deleted')}}
                            @endif
                        </td>
                        <td>
                            @if($item->store)
                            {{$item->store?->zone?->name}}
                            @else
                            {{translate('No data found')}}
                            @endif
                        </td>
                        <td data-order="{{ $stock }}">
                            @if($stock === 0)
                                <span class="badge badge-soft-danger">{{translate('Out of stock')}}</span>
                            @else
                                <span class="d-block text-title font-semibold">{{ $stock }}</span>
                            @endif
                            @if($warn_below)
                                <span class="d-block fs-12 text-muted">{{ translate('Stock alert level') }}: {{ $warn_below }}</span>
                            @endif
                        </td>
                        <td>
                            <a class="btn action-btn action-btn--edit update-quantity" href="javascript:" title="{{translate('messages.Edit quantity')}}" data-id="{{ $item->id }}" data-toggle="modal" data-target="#update-quantity"><i class="tio-edit"></i>
                            </a>
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
<div class="modal fade update-quantity-modal" id="update-quantity" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body pt-0">

                <form action="{{route('admin.item.stock-update')}}" method="post">
                    @csrf
                    <div class="mt-2 rest-part w-100"></div>
                    <div class="btn--container justify-content-end">
                        <button type="reset" data-dismiss="modal" aria-label="Close" class="btn btn--reset"><i class="tio-clear-circle-outlined"></i> {{translate('Cancel')}}</button>
                        <button type="submit" id="submit_new_customer" class="btn btn--primary"><i class="tio-save"></i> {{translate('Update stock')}}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection




@push('script_2')

<script src="{{asset('public/assets/admin')}}/vendor/chart.js/dist/Chart.min.js"></script>
<script src="{{asset('public/assets/admin')}}/vendor/chartjs-chart-matrix/dist/chartjs-chart-matrix.min.js"></script>
<script src="{{asset('public/assets/admin')}}/js/hs.chartjs-matrix.js"></script>

<script>
    "use strict";
    $('.update-quantity').on('click', function (){
        let val = $(this).data('id');
        $.get({
            url: '{{url('/')}}/admin/item/get-variations?id='+val,
            dataType: 'json',
            success: function (data) {

                $('.rest-part').empty().html(data.view);
                update_qty();
            },
        });
    })

    function update_qty() {
        let total_qty = 0;
        let qty_elements = $('input[name^="stock_"]');
        for (let i = 0; i < qty_elements.length; i++) {
            total_qty += parseInt(qty_elements.eq(i).val());
        }
        if(qty_elements.length > 0)
        {

            $('input[name="current_stock"]').attr("readonly", 'readonly');
            $('input[name="current_stock"]').val(total_qty);
        }
        else{
            $('input[name="current_stock"]').attr("readonly", false);
        }
    }

    $(document).on('ready', function() {
        $('.js-data-example-ajax').select2({
            ajax: {
                url: '{{ route('admin.store.get-stores') }}',
                data: function(params) {
                    return {
                        q: params.term, // search term
                        all:true,
                        @if(isset($zone))
                            zone_ids: [{{$zone->id}}],
                        @endif
                        @if(Config::get('module.current_module_id'))
                        module_id: {{Config::get('module.current_module_id')}}
                        ,
                        @endif
                        page: params.page
                    };
                },
                processResults: function(data) {
                    return {
                        results: data
                    };
                },
                __port: function(params, success, failure) {
                    let $request = $.ajax(params);

                    $request.then(success);
                    $request.fail(failure);

                    return $request;
                }
            }
        });
    });

</script>


@endpush
