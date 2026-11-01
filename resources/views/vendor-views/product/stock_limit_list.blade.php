@use('App\Support\Settings\BusinessRules')

@extends('layouts.vendor.app')

@section('title',translate('Low stock list'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-2 mb-sm-0">
                    <h1 class="page-header-title"><i class="tio-filter-list"></i> {{translate('Low stock list')}}<span class="badge badge-soft-dark ml-2" id="itemCount">{{$items->total()}}</span></h1>
                    <p class="page-header-desc">{{ translate('Items running low, so you can restock before customers find out.') }}</p>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header py-2  border-0">
                <div class="search--button-wrapper justify-content-end">
                    @include('partials._table-head', [
                        'subtitle' => translate('messages.Your items at or below their stock alert level.'),
                    ])

                    <form id="search-form" class="search-form">
                        @csrf
                        <div class="input-group input--group">
                            <input id="datatableSearch" type="search" name="search" class="form-control" placeholder="{{translate('messages.Ex search name')}}" aria-label="{{translate('Search')}}">
                            <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                        </div>
                    </form>
                    @if ($store_data->module->module_type == 'food' && BusinessRules::vegNonVegEnabled())
                    <div class="col-sm-auto mb-1 mb-sm-0">
                        <select name="type" data-url="{{url()->full()}}" data-filter="type" data-placeholder="{{translate('All')}}" class="form-control h--37px set-filter">
                            <option value="all" {{$type=='all'?'selected':''}}>{{translate('All')}}</option>
                            <option value="veg" {{$type=='veg'?'selected':''}}>{{translate('Veg')}}</option>
                            <option value="non_veg" {{$type=='non_veg'?'selected':''}}>{{translate('Non veg')}}</option>
                        </select>
                    </div>
                    @endif

                    <div class="hs-unfold  min--250">
                        <select name="category_id" id="category" data-url="{{url()->full()}}" data-filter="category_id" data-placeholder="{{translate('Select category')}}" class="js-data-example-ajax form-control set-filter">
                            @if($category)
                                <option value="{{$category->id}}" selected>{{$category->name}} ({{$category->position == 0?translate('messages.main'):translate('messages.sub')}})</option>
                            @else
                                <option value="all" selected>{{translate('All categories')}}</option>
                            @endif
                        </select>
                    </div>


                    <div class="hs-unfold d-none">
                        <div id="showHideDropdown" class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-right dropdown-card">
                            <div class="card card-sm">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span class="mr-2">{{translate('Name')}}</span>
                                        <label class="toggle-switch toggle-switch-sm" for="toggleColumn_name">
                                            <input type="checkbox" class="toggle-switch-input" id="toggleColumn_name" checked>
                                            <span class="toggle-switch-label">
                                            <span class="toggle-switch-indicator"></span>
                                            </span>
                                        </label>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span class="mr-2">{{translate('messages.Category')}}</span>

                                        <label class="toggle-switch toggle-switch-sm" for="toggleColumn_type">
                                            <input type="checkbox" class="toggle-switch-input" id="toggleColumn_type" checked>
                                            <span class="toggle-switch-label">
                                            <span class="toggle-switch-indicator"></span>
                                            </span>
                                        </label>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span class="mr-2">{{translate('messages.price')}}</span>

                                        <label class="toggle-switch toggle-switch-sm" for="toggleColumn_status">
                                            <input type="checkbox" class="toggle-switch-input" id="toggleColumn_status" checked>
                                            <span class="toggle-switch-label">
                                            <span class="toggle-switch-indicator"></span>
                                            </span>
                                        </label>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span class="mr-2">{{translate('messages.stock')}}</span>

                                        <label class="toggle-switch toggle-switch-sm" for="toggleColumn_price">
                                            <input type="checkbox" class="toggle-switch-input" id="toggleColumn_price" checked>
                                            <span class="toggle-switch-label">
                                            <span class="toggle-switch-indicator"></span>
                                            </span>
                                        </label>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span class="mr-2">{{translate('messages.Action')}}</span>

                                        <label class="toggle-switch toggle-switch-sm" for="toggleColumn_action">
                                            <input type="checkbox" class="toggle-switch-input" id="toggleColumn_action" checked>
                                            <span class="toggle-switch-label">
                                            <span class="toggle-switch-indicator"></span>
                                            </span>
                                        </label>
                                    </div>
                            </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>


            <div class="table-responsive datatable-custom">
                <table id="datatable" class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table"
                    data-hs-datatables-options='{
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
                        <th class="border-0">#</th>
                        <th class="border-0 w-20p">{{translate('Name')}}</th>
                        <th class="border-0 w-20p">{{translate('messages.Category')}}</th>
                        <th class="border-0">{{translate('messages.price')}}</th>
                        <th class="border-0 text-center">{{translate('messages.stock')}}</th>
                        <th class="border-0 text-center">{{translate('messages.Action')}}</th>
                    </tr>
                    </thead>

                    <tbody id="set-rows">
                    @foreach($items as $key=>$item)
                        <tr>
                            <td>{{$key+$items->firstItem()}}</td>
                            <td>
                                <a class="media align-items-center" href="{{route('vendor.item.view',[$item['id']])}}">
                                    <img class="avatar avatar-lg mr-3 onerror-image" src="{{ $item['image_full_url'] }}"
                                         data-onerror-image="{{asset('public/assets/admin/img/160x160/img2.jpg')}}" alt="{{$item->name}} image">
                                    <div class="media-body">
                                        <h5 class="text-hover-primary mb-0">{{Str::limit($item['name'],20,'...')}}</h5>
                                    </div>
                                </a>
                            </td>
                            <td>
                            {{Str::limit($item->category?$item->category->name:translate('messages.Category deleted'),20,'...')}}
                            </td>
                            <td>
                                {{\App\CentralLogics\Helpers::format_currency($item['price'])}}
                            </td>
                            <td>
                                <div class="text-center">
                                    {{($item['stock'])}}
                                </div>
                            </td>
                            <td>
                                <div class="btn--container justify-content-center">
                                    <a class="btn btn-sm action-btn action-btn--edit update_quantity"
                                        href="javascript:" title="{{translate('messages.Edit quantity')}}" data-id="{{ $item->id }}" data-toggle="modal" data-target="#update-quantity"><i class="tio-edit"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                @if(count($items) === 0)
                <div class="empty--data">
                    <img src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="public">
                    <h5>
                        {{translate('No data found')}}
                    </h5>
                </div>
                @endif
            </div>
            <hr>
            <div class="page-area">
                <table>
                    <tfoot class="border-top">
                    {!! $items->links() !!}
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

@endsection


<div class="modal fade update-quantity-modal" id="update-quantity" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body pt-0">

                <form action="{{route('vendor.item.stock-update')}}" method="post">
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



@push('script_2')

<script>
    "use strict";

    $('.update_quantity').on('click', function (){
        let val = $(this).data('id');
        $.get({
            url: '{{url('/')}}/vendor-panel/item/get-variations?id='+val,
            dataType: 'json',
            success: function (data) {
               console.log(data);
                $('.rest-part').empty().html(data.view);
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


        $(document).on('ready', function () {
            // INITIALIZATION OF DATATABLES
            // =======================================================
            let datatable = $.HSCore.components.HSDatatables.init($('#datatable'), {
          select: {
            style: 'multi',
            classMap: {
              checkAll: '#datatableCheckAll',
              counter: '#datatableCounter',
              counterInfo: '#datatableCounterInfo'
            }
          },
          language: {
            zeroRecords: '<div class="text-center p-4">' +
                '<img class="w-7rem mb-3" src="{{asset('public/assets/admin/svg/illustrations/sorry.svg')}}" alt="Image Description">' +

                '</div>'
          }
        });

        $('#datatableSearch').on('mouseup', function (e) {
          let $input = $(this),
            oldValue = $input.val();

          if (oldValue == "") return;

          setTimeout(function(){
            let newValue = $input.val();

            if (newValue == ""){
              // Gotcha
              datatable.search('').draw();
            }
          }, 1);
        });

        $('#toggleColumn_index').change(function (e) {
          datatable.columns(0).visible(e.target.checked)
        })
        $('#toggleColumn_name').change(function (e) {
          datatable.columns(1).visible(e.target.checked)
        })

        $('#toggleColumn_type').change(function (e) {
          datatable.columns(2).visible(e.target.checked)
        })

        $('#toggleColumn_status').change(function (e) {
          datatable.columns(4).visible(e.target.checked)
        })
        $('#toggleColumn_price').change(function (e) {
          datatable.columns(3).visible(e.target.checked)
        })
        $('#toggleColumn_action').change(function (e) {
          datatable.columns(5).visible(e.target.checked)
        })


            // INITIALIZATION OF SELECT2
            // =======================================================
            $('.js-select2-custom').each(function () {
                let select2 = $.HSCore.components.HSSelect2.init($(this));
            });
        });

        $('#category').select2({
            ajax: {
                url: '{{route("vendor.category.get-all")}}',
                data: function (params) {
                    return {
                        q: params.term, // search term
                        all:true,
                        page: params.page
                    };
                },
                processResults: function (data) {
                    return {
                    results: data
                    };
                },
                __port: function (params, success, failure) {
                    let $request = $.ajax(params);

                    $request.then(success);
                    $request.fail(failure);

                    return $request;
                }
            }
        });

        $('#search-form').on('submit', function (e) {
            e.preventDefault();
            let formData = new FormData(this);
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
            $.post({
                url: '{{route('vendor.item.search')}}',
                data: formData,
                cache: false,
                contentType: false,
                processData: false,
                beforeSend: function () {
                    $('#loading').show();
                },
                success: function (data) {
                    $('#set-rows').html(data.view);
                    $('.page-area').hide();
                },
                complete: function () {
                    $('#loading').hide();
                },
            });
        });
    </script>
@endpush
