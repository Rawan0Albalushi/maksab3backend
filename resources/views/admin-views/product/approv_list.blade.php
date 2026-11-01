@extends('layouts.admin.app')

@section('title', translate('New item requests'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/view-pages/item-list.css') }}">
@endpush

@section('content')
    @php
        $module_type = Config::get('module.current_module_type');
        $has_veg = (bool) config('module.'.$module_type.'.veg_non_veg');
        $drawer_clear = request()->fullUrlWithoutQuery([
            'store_id', 'zone_id', 'category_id', 'sub_category_id',
            'status', 'kind', 'type', 'from_date', 'to_date', 'page',
        ]);
    @endphp

    <div class="content container-fluid itm">
        <div class="page-header">
            <div class="row align-items-center g-2">
                <div class="col-md-7 col-12">
                    <h1 class="page-header-title">
                        <span class="page-header-icon">
                            <img src="{{ asset('public/assets/admin/img/items.png') }}" class="w--22" alt="">
                        </span>
                        <span>{{ translate('New item requests') }}</span>
                    </h1>
                    <p class="page-header-desc">{{ translate('New items stores have submitted, waiting for you to approve or turn down.') }}</p>
                </div>
                <div class="col-md-5 col-12">
                    <div class="itm-actions">
                        <a class="itm-action itm-action--info" href="{{ route('admin.item.list', ['module_id' => request('module_id')]) }}">
                            <span class="itm-action__icon"><i class="tio-format-points"></i></span>
                            <span class="itm-action__label">{{ translate('Item list') }}</span>
                            <i class="itm-action__go tio-chevron-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div id="approval-index" data-ajax-region
             data-ajax-url="{{ url()->full() }}"
             data-ajax-links=".page-link, .list-reset-search"
             data-ajax-forms=".search-form">

            @include('admin-views.product.partials._approval-summary', [
                'summary' => $summary,
                'filters' => $filters,
            ])

            <div class="card">
                <div class="card-header py-2 border-0">
                    <div class="search--button-wrapper">
                        @include('partials._table-head', [
                            'subtitle' => translate('messages.Item submissions from vendors that need your approval.'),
                            'count' => null,
                        ])

                        <form class="search-form">
                            @if(request('module_id'))
                                <input type="hidden" name="module_id" value="{{ request('module_id') }}">
                            @endif
                            @foreach(['store_id', 'zone_id', 'category_id', 'sub_category_id', 'from_date', 'to_date'] as $scalar)
                                @if($filters[$scalar])
                                    <input type="hidden" name="{{ $scalar }}" value="{{ $filters[$scalar] }}">
                                @endif
                            @endforeach
                            @foreach(['status', 'kind', 'type'] as $group)
                                @foreach($filters[$group] as $value)
                                    <input type="hidden" name="{{ $group }}[]" value="{{ $value }}">
                                @endforeach
                            @endforeach
                            <div class="input-group input--group">
                                <input id="datatableSearch" name="search" type="search" class="form-control h--40px"
                                       value="{{ $filters['search'] }}"
                                       placeholder="{{ translate('messages.Search by item or store name') }}"
                                       aria-label="{{ translate('messages.Search by item or store name') }}">
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
                                <a id="export-excel" class="dropdown-item"
                                   href="{{ route('admin.item.export', array_merge(request()->query(), ['type' => 'excel', 'table' => 'TempProduct'])) }}">
                                    <img class="avatar avatar-xss avatar-4by3 mr-2"
                                         src="{{ asset('public/assets/admin') }}/svg/components/excel.svg" alt="">
                                    Excel
                                </a>
                                <a id="export-csv" class="dropdown-item"
                                   href="{{ route('admin.item.export', array_merge(request()->query(), ['type' => 'csv', 'table' => 'TempProduct'])) }}">
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
                           class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table"
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
                            <th class="border-0">{{ translate('messages.Store') }}</th>
                            <th class="border-0 col--numeric">{{ translate('messages.price') }}</th>
                            <th class="border-0">{{ translate('messages.Request') }}</th>
                            <th class="border-0">{{ translate('messages.Submitted') }}</th>
                            <th class="border-0">{{ translate('messages.Status') }}</th>
                            <th class="border-0 text-center">{{ translate('messages.Action') }}</th>
                        </tr>
                        </thead>

                        <tbody id="set-rows">
                        @foreach($items as $item)
                            @include('admin-views.product.partials._approval-row', [
                                'item' => $item,
                                'has_veg' => $has_veg,
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
                            <h5>{{ translate('messages.No request matches these filters.') }}</h5>
                            <p class="text-muted font-size-sm">{{ translate('messages.Clear the search or widen the filters to see more requests.') }}</p>
                        @else
                            <h5>{{ translate('messages.Nothing is waiting for approval.') }}</h5>
                            <p class="text-muted font-size-sm">{{ translate('messages.Requests appear here as soon as a store submits an item.') }}</p>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div id="datatableFilterSidebar" class="filter-drawer sidebar sidebar-bordered sidebar-box-shadow">
        <div class="card card-lg sidebar-card sidebar-footer-fixed">
            @include('partials._filter-drawer-head', [
                'fd_title' => translate('messages.Request filter'),
                'fd_subtitle' => translate('messages.Narrow the approval queue down by store, zone, category, decision and date.'),
            ])

            <form class="card-body sidebar-body sidebar-scrollbar" method="get" id="approval_filter_form">
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
                </div>

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

                <hr class="my-4">

                <small class="text-cap mb-3">{{ translate('messages.Status') }}</small>
                <div class="fd-grid">
                    <div class="custom-control custom-checkbox mb-2">
                        <input type="checkbox" id="filterStatusPending" name="status[]" class="custom-control-input"
                               value="pending" {{ in_array('pending', $filters['status']) ? 'checked' : '' }}>
                        <label class="custom-control-label" for="filterStatusPending">{{ translate('Pending') }}</label>
                    </div>
                    <div class="custom-control custom-checkbox mb-2">
                        <input type="checkbox" id="filterStatusRejected" name="status[]" class="custom-control-input"
                               value="rejected" {{ in_array('rejected', $filters['status']) ? 'checked' : '' }}>
                        <label class="custom-control-label" for="filterStatusRejected">{{ translate('messages.rejected') }}</label>
                    </div>
                </div>

                <hr class="my-4">

                <small class="text-cap mb-3">{{ translate('messages.Request') }}</small>
                <div class="fd-grid">
                    <div class="custom-control custom-checkbox mb-2">
                        <input type="checkbox" id="filterKindNew" name="kind[]" class="custom-control-input"
                               value="new" {{ in_array('new', $filters['kind']) ? 'checked' : '' }}>
                        <label class="custom-control-label" for="filterKindNew">{{ translate('messages.Brand new items') }}</label>
                    </div>
                    <div class="custom-control custom-checkbox mb-2">
                        <input type="checkbox" id="filterKindUpdate" name="kind[]" class="custom-control-input"
                               value="update" {{ in_array('update', $filters['kind']) ? 'checked' : '' }}>
                        <label class="custom-control-label" for="filterKindUpdate">{{ translate('messages.Edits to live items') }}</label>
                    </div>
                </div>

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

                <small class="text-cap mb-3">{{ translate('messages.Date between') }}</small>
                <div class="fd-daterange">
                    <div class="fd-daterange__field">
                        <label class="fd-sublabel" for="request_from_date">{{ translate('messages.from') }}</label>
                        <input type="date" name="from_date" class="form-control" id="request_from_date" value="{{ $filters['from_date'] }}">
                    </div>
                    <div class="fd-daterange__field">
                        <label class="fd-sublabel" for="request_to_date">{{ translate('messages.to') }}</label>
                        <input type="date" name="to_date" class="form-control" id="request_to_date" value="{{ $filters['to_date'] }}">
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

        function initApprovalDatatable($root) {
            $root.find('#datatable').each(function () {
                $.HSCore.components.HSDatatables.init($(this));
            });
        }

        $(document).on('ready', function () {
            initApprovalDatatable($(document));
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

        $(document).on('change', '#category_id', function () {
            $('#sub-categories').val(null).trigger('change');
        });

        $(document).on('change', '#request_from_date, #request_to_date', function () {
            let from = $('#request_from_date').val();
            let to = $('#request_to_date').val();

            if (from && to && from > to) {
                $(this).val('');
                toastr.error('{{ translate('messages.Invalid date range') }}');
            }
        });

        $(document).on('click', '.deny-request', function () {
            let $trigger = $(this);

            Swal.fire({
                title: '{{ translate('messages.Are you sure?') }}',
                html: $trigger.data('message') + '<br/><label>{{ translate('messages.Enter a reason') }}</label>',
                type: 'warning',
                input: 'text',
                inputPlaceholder: '{{ translate('messages.Enter a reason') }}',
                showCancelButton: true,
                cancelButtonColor: 'default',
                confirmButtonColor: '#FC6A57',
                cancelButtonText: '{{ translate('messages.Cancel') }}',
                confirmButtonText: '{{ translate('messages.Submit') }}',
                reverseButtons: true,
                preConfirm: function (note) {
                    if (window.AppAjax) {
                        window.AppAjax.action({
                            origin: $trigger,
                            url: $trigger.data('url'),
                            method: 'get',
                            data: { note: note || '' }
                        });

                        return;
                    }

                    window.location.href = $trigger.data('url') + '&note=' + encodeURIComponent(note || '');
                },
                allowOutsideClick: function () {
                    return ! Swal.isLoading();
                }
            });
        });

        $(document).on('ajax:success', '.search-form', function () {
            $('#filter-search-value').val($(this).find('[name="search"]').val());
        });

        if (window.AppAjax) {
            window.AppAjax.onMount(function ($root) {
                initApprovalDatatable($root);
            });
        }
    </script>
@endpush
