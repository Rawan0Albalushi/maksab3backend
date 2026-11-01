@extends('layouts.admin.app')

@section('title', translate('Item list'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/view-pages/item-list.css') }}">
@endpush

@section('content')
    @php
        $module_type = Config::get('module.current_module_type');
        $has_stock = (bool) config('module.'.$module_type.'.stock');
        $has_veg = (bool) config('module.'.$module_type.'.veg_non_veg');
        $has_brand = (bool) config('module.'.$module_type.'.brand');
        $has_condition = (bool) config('module.'.$module_type.'.common_condition');
        $product_approval = \App\CentralLogics\Helpers::get_business_settings('product_approval');
        $header_actions = [];
        if ($has_stock) {
            $header_actions[] = [
                'url' => route('admin.report.stock-report'),
                'icon' => 'tio-warning-outlined',
                'tone' => 'warn',
                'label' => translate('Low stock list'),
                'count' => null,
            ];
        }
        if ($product_approval) {
            $header_actions[] = [
                'url' => route('admin.item.approval_list'),
                'icon' => 'tio-file-add-outlined',
                'tone' => 'info',
                'label' => translate('New product request'),
                'count' => $pending_requests,
            ];
        }
        $drawer_clear = request()->fullUrlWithoutQuery([
            'store_id', 'zone_id', 'category_id', 'sub_category_id', 'store_category_id',
            'condition_id', 'brand_id', 'type', 'status', 'stock', 'flag', 'page',
        ]);
    @endphp

    <div class="content container-fluid itm">
        <div class="page-header">
            <div class="row align-items-center g-2">
                <div class="col-md-6 col-12">
                    <h1 class="page-header-title">
                        <span class="page-header-icon">
                            <img src="{{ asset('public/assets/admin/img/items.png') }}" class="w--22" alt="">
                        </span>
                        <span>{{ translate('Item list') }}</span>
                    </h1>
                    <p class="page-header-desc">{{ translate('Everything on sale in this module, with the store and price of each.') }}</p>
                </div>
                @if($header_actions)
                    <div class="col-md-6 col-12">
                        <div class="itm-actions">
                            @foreach($header_actions as $action)
                                <a class="itm-action itm-action--{{ $action['tone'] }}" href="{{ $action['url'] }}">
                                    <span class="itm-action__icon"><i class="{{ $action['icon'] }}"></i></span>
                                    <span class="itm-action__label">{{ $action['label'] }}</span>
                                    @if($action['count'])
                                        <span class="itm-action__count">{{ $action['count'] > 99 ? '99+' : $action['count'] }}</span>
                                    @endif
                                    <i class="itm-action__go tio-chevron-right"></i>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <div id="item-index" data-ajax-region
             data-ajax-url="{{ url()->full() }}"
             data-ajax-links=".page-link, .list-reset-search"
             data-ajax-forms=".search-form">

            @include('admin-views.product.partials._item-summary', [
                'summary' => $summary,
                'filters' => $filters,
                'has_stock' => $has_stock,
            ])

            <div class="card">
                <div class="card-header py-2 border-0">
                    <div class="search--button-wrapper">
                        @include('partials._table-head', [
                            'subtitle' => translate('messages.Every item listed across your stores, with price, stock and sales.'),
                            'count' => null,
                        ])

                        <form class="search-form">
                            @if(request('module_id'))
                                <input type="hidden" name="module_id" value="{{ request('module_id') }}">
                            @endif
                            @foreach(['store_id', 'zone_id', 'category_id', 'sub_category_id', 'store_category_id', 'condition_id', 'brand_id'] as $scalar)
                                @if($filters[$scalar])
                                    <input type="hidden" name="{{ $scalar }}" value="{{ $filters[$scalar] }}">
                                @endif
                            @endforeach
                            @foreach(['type', 'status', 'stock', 'flag'] as $group)
                                @foreach($filters[$group] as $value)
                                    <input type="hidden" name="{{ $group }}[]" value="{{ $value }}">
                                @endforeach
                            @endforeach
                            <div class="input-group input--group">
                                <input id="datatableSearch" name="search" type="search" class="form-control h--40px"
                                       value="{{ $filters['search'] }}"
                                       placeholder="{{ translate('messages.Search by item or category name') }}"
                                       aria-label="{{ translate('messages.Search by item or category name') }}">
                                <button type="submit" class="btn btn--primary h--40px"><i class="tio-search"></i></button>
                            </div>
                        </form>

                        @if($filters['search'])
                            <a class="btn btn--primary ml-2 list-reset-search" href="{{ request()->fullUrlWithoutQuery(['search', 'page']) }}">
                                <i class="tio-refresh"></i> {{ translate('messages.Reset') }}
                            </a>
                        @endif

                        <div class="hs-unfold mr-2">
                            <a class="btn btn-sm btn-white h--40px filter-button-show" href="javascript:;"
                               role="button" aria-expanded="false" aria-controls="datatableFilterSidebar">
                                <i class="tio-filter-list mr-1"></i> {{ translate('messages.Filter') }}
                                @if($filter_count)
                                    <span class="badge badge-success badge-pill ml-1">{{ $filter_count }}</span>
                                @endif
                            </a>
                        </div>

                        <div class="hs-unfold mr-2">
                            <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle min-height-40 w-max-content" href="javascript:;"
                               data-hs-unfold-options='{
                                    "target": "#usersExportDropdown",
                                    "type": "css-animation"
                                }'>
                                <i class="tio-download-to mr-1"></i> {{ translate('messages.Export') }}
                            </a>

                            <div id="usersExportDropdown" class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">
                                <span class="dropdown-header">{{ translate('messages.Download options') }}</span>
                                <a id="export-excel" class="dropdown-item" href="{{ route('admin.item.export', array_merge(request()->query(), ['type' => 'excel'])) }}">
                                    <img class="avatar avatar-xss avatar-4by3 mr-2"
                                         src="{{ asset('public/assets/admin') }}/svg/components/excel.svg" alt="">
                                    Excel
                                </a>
                                <a id="export-csv" class="dropdown-item" href="{{ route('admin.item.export', array_merge(request()->query(), ['type' => 'csv'])) }}">
                                    <img class="avatar avatar-xss avatar-4by3 mr-2"
                                         src="{{ asset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg" alt="">
                                    CSV
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive datatable-custom">
                    <table id="datatable"
                           class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table table--wrap-head table--sticky-actions"
                           data-hs-datatables-options='{
                                "columnDefs": [{
                                    "targets": [-1],
                                    "orderable": false
                                }],
                                "order": [],
                                "isResponsive": false,
                                "isShowPaging": false,
                                "paging": false
                            }'>
                        <thead class="thead-light">
                        <tr>
                            <th class="border-0">{{ translate('messages.item') }}</th>
                            <th class="border-0">{{ translate('messages.Category') }}</th>
                            @if($has_stock)
                                <th class="border-0">{{ translate('messages.stock') }}</th>
                            @endif
                            <th class="border-0">{{ translate('messages.Store') }}</th>
                            <th class="border-0 col--numeric">{{ translate('messages.price') }}</th>
                            @if($productWiseTax)
                                <th class="border-0">{{ translate('VAT/tax') }}</th>
                            @endif
                            <th class="border-0">{{ translate('messages.Rating') }}</th>
                            <th class="border-0 col--numeric">{{ translate('messages.Orders') }}</th>
                            <th class="border-0">{{ translate('messages.Status') }}</th>
                            <th class="border-0 text-center">{{ translate('messages.Action') }}</th>
                        </tr>
                        </thead>

                        <tbody id="set-rows">
                        @foreach($items as $item)
                            @include('admin-views.product.partials._item-row', [
                                'item' => $item,
                                'filters' => $filters,
                                'has_stock' => $has_stock,
                                'has_veg' => $has_veg,
                                'productWiseTax' => $productWiseTax,
                            ])
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
                        <img src="{{ asset('/public/assets/admin/svg/illustrations/sorry.svg') }}" alt="">
                        @if($filters['search'] || $filter_count)
                            <h5>{{ translate('messages.No item matches these filters.') }}</h5>
                            <p class="text-muted font-size-sm">{{ translate('messages.Clear the search or widen the filters to see more items.') }}</p>
                        @else
                            <h5>{{ translate('messages.No item yet.') }}</h5>
                            <p class="text-muted font-size-sm">{{ translate('messages.Items appear here as soon as a store adds one to this module.') }}</p>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div id="datatableFilterSidebar" class="filter-drawer sidebar sidebar-bordered sidebar-box-shadow">
        <div class="card card-lg sidebar-card sidebar-footer-fixed">
            @include('partials._filter-drawer-head', [
                'fd_title' => translate('messages.Item filter'),
                'fd_subtitle' => translate('messages.Narrow the item list down by store, zone, category, availability and stock.'),
            ])

            <form class="card-body sidebar-body sidebar-scrollbar" method="get" id="item_filter_form">
                @if(request('module_id'))
                    <input type="hidden" name="module_id" value="{{ request('module_id') }}">
                @endif
                <input type="hidden" name="search" value="{{ $filters['search'] }}" id="filter-search-value">

                <small class="text-cap mb-3">{{ translate('messages.Store') }}</small>
                <div class="form-group">
                    <select name="store_id" id="store" class="form-control js-data-example-ajax"
                            data-url="{{ route('admin.store.get-stores') }}"
                            data-placeholder="{{ translate('Select store') }}">
                        @if($store)
                            <option value="{{ $store->id }}" data-verified="{{ (int) $store->verified_seller }}" selected>{{ $store->name }}</option>
                        @else
                            <option value="all" selected>{{ translate('All stores') }}</option>
                        @endif
                    </select>
                    @if(\App\CentralLogics\Helpers::storeCategoryStatus() && ! $store)
                        <small class="itm-fd-store-hint">{{ translate('messages.Pick a store to filter by its own categories.') }}</small>
                    @endif
                </div>

                @if(\App\CentralLogics\Helpers::storeCategoryStatus() && $store_categories->isNotEmpty())
                    <small class="text-cap mb-3">{{ translate('Store category') }}</small>
                    <div class="form-group">
                        <select name="store_category_id" class="form-control js-select2-custom">
                            <option value="all">{{ translate('messages.All store categories') }}</option>
                            @foreach($store_categories as $store_category)
                                <option value="{{ $store_category->id }}" {{ $filters['store_category_id'] == $store_category->id ? 'selected' : '' }}>
                                    {{ $store_category->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if(! auth('admin')?->user()?->zone_id)
                    <small class="text-cap mb-3">{{ translate('messages.Zone') }}</small>
                    <div class="form-group">
                        <select name="zone_id" class="form-control js-select2-custom">
                            <option value="all">{{ translate('All zones') }}</option>
                            @foreach(\App\CentralLogics\Helpers::zones_dropdown() as $z)
                                <option value="{{ $z['id'] }}" {{ $filters['zone_id'] == $z['id'] ? 'selected' : '' }}>{{ $z['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <hr class="my-4">

                <small class="text-cap mb-3">{{ translate('messages.Category') }}</small>
                <div class="form-group">
                    <select name="category_id" id="category_id" class="form-control js-data-example-ajax"
                            data-url="{{ route('admin.category.get-all') }}"
                            data-placeholder="{{ translate('Select category') }}">
                        @if($category)
                            <option value="{{ $category->id }}" selected>{{ $category->name }}</option>
                        @else
                            <option value="all" selected>{{ translate('messages.All category') }}</option>
                        @endif
                    </select>
                </div>

                <div class="form-group">
                    <label class="fd-sublabel" for="sub-categories">{{ translate('Subcategory') }}</label>
                    <select name="sub_category_id" id="sub-categories" class="form-control js-data-example-ajax"
                            data-url="{{ route('admin.item.get-categories') }}"
                            data-placeholder="{{ translate('Select subcategory') }}">
                        @if($sub_category)
                            <option value="{{ $sub_category->id }}" selected>{{ $sub_category->name }}</option>
                        @else
                            <option value="all" selected>{{ translate('All subcategory') }}</option>
                        @endif
                    </select>
                </div>

                @if($has_brand)
                    <div class="form-group">
                        <label class="fd-sublabel" for="brand_id">{{ translate('messages.Brand') }}</label>
                        <select name="brand_id" id="brand_id" class="form-control js-data-example-ajax"
                                data-url="{{ route('admin.brand.get-all') }}"
                                data-placeholder="{{ translate('messages.Select brand') }}">
                            @if($brand)
                                <option value="{{ $brand->id }}" selected>{{ $brand->name }}</option>
                            @else
                                <option value="all" selected>{{ translate('messages.All brands') }}</option>
                            @endif
                        </select>
                    </div>
                @endif

                @if($has_condition)
                    <div class="form-group">
                        <label class="fd-sublabel" for="condition_id">{{ translate('messages.Condition') }}</label>
                        <select name="condition_id" id="condition_id" class="form-control js-data-example-ajax"
                                data-url="{{ route('admin.common-condition.get-all') }}"
                                data-placeholder="{{ translate('Select condition') }}">
                            @if($condition)
                                <option value="{{ $condition->id }}" selected>{{ $condition->name }}</option>
                            @else
                                <option value="all" selected>{{ translate('messages.All conditions') }}</option>
                            @endif
                        </select>
                    </div>
                @endif

                <hr class="my-4">

                <small class="text-cap mb-3">{{ translate('messages.Status') }}</small>
                <div class="fd-grid">
                    <div class="custom-control custom-checkbox mb-2">
                        <input type="checkbox" id="filterStatusActive" name="status[]" class="custom-control-input"
                               value="active" {{ in_array('active', $filters['status']) ? 'checked' : '' }}>
                        <label class="custom-control-label" for="filterStatusActive">{{ translate('messages.Active') }}</label>
                    </div>
                    <div class="custom-control custom-checkbox mb-2">
                        <input type="checkbox" id="filterStatusInactive" name="status[]" class="custom-control-input"
                               value="inactive" {{ in_array('inactive', $filters['status']) ? 'checked' : '' }}>
                        <label class="custom-control-label" for="filterStatusInactive">{{ translate('messages.Inactive') }}</label>
                    </div>
                </div>

                @if($has_stock)
                    <hr class="my-4">

                    <small class="text-cap mb-3">{{ translate('messages.stock') }}</small>
                    <div class="fd-grid">
                        <div class="custom-control custom-checkbox mb-2">
                            <input type="checkbox" id="filterStockIn" name="stock[]" class="custom-control-input"
                                   value="in" {{ in_array('in', $filters['stock']) ? 'checked' : '' }}>
                            <label class="custom-control-label" for="filterStockIn">{{ translate('messages.In stock') }}</label>
                        </div>
                        <div class="custom-control custom-checkbox mb-2">
                            <input type="checkbox" id="filterStockOut" name="stock[]" class="custom-control-input"
                                   value="out" {{ in_array('out', $filters['stock']) ? 'checked' : '' }}>
                            <label class="custom-control-label" for="filterStockOut">{{ translate('Out of stock') }}</label>
                        </div>
                    </div>
                @endif

                @if($has_veg)
                    <hr class="my-4">

                    <small class="text-cap mb-3">{{ translate('messages.Item type') }}</small>
                    <div class="fd-grid">
                        <div class="custom-control custom-checkbox mb-2">
                            <input type="checkbox" id="filterTypeVeg" name="type[]" class="custom-control-input"
                                   value="veg" {{ in_array('veg', $filters['type']) ? 'checked' : '' }}>
                            <label class="custom-control-label" for="filterTypeVeg">{{ translate('Veg') }}</label>
                        </div>
                        <div class="custom-control custom-checkbox mb-2">
                            <input type="checkbox" id="filterTypeNonVeg" name="type[]" class="custom-control-input"
                                   value="non_veg" {{ in_array('non_veg', $filters['type']) ? 'checked' : '' }}>
                            <label class="custom-control-label" for="filterTypeNonVeg">{{ translate('Non veg') }}</label>
                        </div>
                    </div>
                @endif

                <hr class="my-4">

                <small class="text-cap mb-3">{{ translate('messages.Highlights') }}</small>
                <div class="fd-grid">
                    <div class="custom-control custom-checkbox mb-2">
                        <input type="checkbox" id="filterFlagDiscounted" name="flag[]" class="custom-control-input"
                               value="discounted" {{ in_array('discounted', $filters['flag']) ? 'checked' : '' }}>
                        <label class="custom-control-label" for="filterFlagDiscounted">{{ translate('messages.On discount') }}</label>
                    </div>
                    <div class="custom-control custom-checkbox mb-2">
                        <input type="checkbox" id="filterFlagUnsold" name="flag[]" class="custom-control-input"
                               value="never_ordered" {{ in_array('never_ordered', $filters['flag']) ? 'checked' : '' }}>
                        <label class="custom-control-label" for="filterFlagUnsold">{{ translate('messages.Never ordered') }}</label>
                    </div>
                </div>

                <div class="card-footer sidebar-footer">
                    <div class="row gx-2">
                        <div class="col">
                            <a class="btn btn-block btn-white" href="{{ $drawer_clear }}">
                                <i class="tio-clear-circle-outlined"></i> {{ translate('Clear all') }}
                            </a>
                        </div>
                        <div class="col">
                            <button type="submit" class="btn btn-block btn-primary"><i class="tio-filter-list"></i> {{ translate('messages.Filter') }}</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade update-quantity-modal" id="update-quantity" tabindex="-1">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ translate('Update stock') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="{{ translate('messages.Close') }}">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body pt-0">
                    <form action="{{ route('admin.item.stock-update') }}" method="post"
                          data-ajax-form data-ajax-close="#update-quantity" data-ajax-refresh="[data-ajax-region]">
                        @csrf
                        <div class="mt-2 rest-part w-100"></div>
                        <div class="btn--container justify-content-end">
                            <button type="reset" data-dismiss="modal" class="btn btn--reset"><i class="tio-clear-circle-outlined"></i> {{ translate('messages.Cancel') }}</button>
                            <button type="submit" class="btn btn--primary"><i class="tio-save"></i> {{ translate('Update stock') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <input type="hidden" id="current_module_id" value="{{ Config::get('module.current_module_id') }}">
@endsection

@push('script_2')
    <script>
        "use strict";

        function ajaxSelect2(selector, extraData) {
            let $select = $(selector);

            if (! $select.length) {
                return;
            }

            $select.select2({
                ajax: {
                    url: $select.data('url'),
                    data: function (params) {
                        return $.extend({ q: params.term, page: params.page }, extraData());
                    },
                    processResults: function (data) {
                        return { results: data };
                    },
                    __port: function (params, success, failure) {
                        let $request = $.ajax(params);

                        $request.then(success);
                        $request.fail(failure);

                        return $request;
                    }
                }
            });
        }

        function initItemDatatable($root) {
            $root.find('#datatable').each(function () {
                $.HSCore.components.HSDatatables.init($(this));
            });
        }

        function update_qty() {
            let total_qty = 0;
            let qty_elements = $('input[name^="stock_"]');

            qty_elements.each(function () {
                total_qty += parseInt($(this).val(), 10) || 0;
            });

            if (qty_elements.length > 0) {
                $('input[name="current_stock"]').attr('readonly', 'readonly').val(total_qty);
            } else {
                $('input[name="current_stock"]').attr('readonly', false);
            }
        }

        $(document).on('ready', function () {
            initItemDatatable($(document));
        });

        ajaxSelect2('#store', function () {
            return { all: true, module_id: $('#current_module_id').val() };
        });

        ajaxSelect2('#category_id', function () {
            return { all: true, module_id: $('#current_module_id').val(), position: 0 };
        });

        ajaxSelect2('#sub-categories', function () {
            return {
                module_id: $('#current_module_id').val(),
                parent_id: $('#category_id').val(),
                sub_category: true
            };
        });

        ajaxSelect2('#brand_id', function () {
            return {};
        });

        ajaxSelect2('#condition_id', function () {
            return { all: true };
        });

        $(document).on('change', '#category_id', function () {
            $('#sub-categories').val(null).trigger('change');
        });

        $(document).on('click', '.update-quantity', function () {
            $.get({
                url: '{{ route('admin.item.get_stock') }}',
                data: { id: $(this).data('id') },
                dataType: 'json',
                success: function (data) {
                    $('.rest-part').empty().html(data.view);
                    update_qty();
                }
            });
        });

        $(document).on('ajax:success', '.search-form', function () {
            $('#filter-search-value').val($(this).find('[name="search"]').val());
        });

        if (window.AppAjax) {
            window.AppAjax.onMount(function ($root) {
                initItemDatatable($root);
            });
        }
    </script>
@endpush
