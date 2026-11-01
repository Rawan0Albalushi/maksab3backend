@use('App\Support\Settings\BusinessRules')
@extends('layouts.vendor.app')

@section('title',translate('Item list'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/view-pages/item-list.css') }}">
@endpush

@section('content')
    @php($module_type = $store_data->module->module_type)
    @php($has_stock = (bool) config('module.'.$module_type.'.stock'))
    @php($has_veg = (bool) config('module.'.$module_type.'.veg_non_veg') && BusinessRules::vegNonVegEnabled())
    @php($header_actions = array_values(array_filter([
        $has_stock ? [
            'url' => route('vendor.item.stock-limit-list'),
            'icon' => 'tio-warning-outlined',
            'tone' => 'warn',
            'label' => translate('Low stock list'),
            'count' => null,
        ] : null,
        \App\CentralLogics\Helpers::get_business_settings('product_approval') ? [
            'url' => route('vendor.item.pending_item_list'),
            'icon' => 'tio-file-add-outlined',
            'tone' => 'info',
            'label' => translate('messages.pending_item_list'),
            'count' => $pending_requests,
        ] : null,
    ])))
    @php($drawer_clear = request()->fullUrlWithoutQuery([
        'category_id', 'sub_category_id', 'store_category_id', 'type', 'status', 'stock', 'flag', 'page',
    ]))

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
                    <p class="page-header-desc">{{ translate('Everything you have on sale, with the price and stock of each.') }}</p>
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
                            'subtitle' => translate('messages.Your items, with price, stock and current status.'),
                            'count' => null,
                        ])

                        <form class="search-form">
                            @foreach(['category_id', 'sub_category_id', 'store_category_id'] as $scalar)
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
                                <input id="datatableSearch" type="search" name="search" class="form-control h--40px"
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

                        <div>
                            <a href="{{ route('vendor.item.add-new') }}" class="btn btn--primary m-0">
                                <i class="tio-add-circle"></i> {{ translate('messages.Add new item') }}
                            </a>
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
                                <th class="border-0 col--numeric">{{ translate('messages.price') }}</th>
                                @if($productWiseTax)
                                    <th class="border-0">{{ translate('VAT/tax') }}</th>
                                @endif
                                <th class="border-0">{{ translate('messages.Rating') }}</th>
                                <th class="border-0 col--numeric">{{ translate('messages.Orders') }}</th>
                                <th class="border-0 text-center">{{ translate('messages.Recommended') }}</th>
                                <th class="border-0">{{ translate('messages.Status') }}</th>
                                <th class="border-0 text-center">{{ translate('messages.Action') }}</th>
                            </tr>
                        </thead>

                        <tbody id="set-rows">
                        @foreach($items as $item)
                            @php($discount_amount = $item->discount > 0
                                ? ($item->discount_type == 'percent' ? ($item->price * $item->discount / 100) : $item->discount)
                                : 0)
                            @php($final_price = max($item->price - $discount_amount, 0))
                            @php($stock = max((int) $item->stock, 0))
                            @php($category_name = $item->category ? $item->category->name : translate('messages.Category deleted'))
                            <tr>
                                <td>
                                    <a class="itm-item" href="{{ route('vendor.item.view', [$item->id]) }}" title="{{ $item->name }}">
                                        <img class="itm-item__thumb onerror-image" src="{{ $item['image_full_url'] }}"
                                             data-onerror-image="{{ asset('public/assets/admin/img/160x160/img2.jpg') }}"
                                             alt="{{ $item->name }}">
                                        <span class="itm-item__body">
                                            <span class="itm-item__name">{{ $item->name }}</span>
                                            <span class="itm-meta">
                                                @if($has_veg)
                                                    <i class="itm-veg itm-veg--{{ $item->veg ? 'yes' : 'no' }}"
                                                       title="{{ $item->veg ? translate('Veg') : translate('Non veg') }}"></i>
                                                @endif
                                                <span class="itm-id">#{{ $item->id }}</span>
                                            </span>
                                        </span>
                                    </a>
                                </td>

                                <td>
                                    <span class="itm-tag" title="{{ $category_name }}">{{ $category_name }}</span>
                                </td>

                                @if($has_stock)
                                    <td>
                                        <span class="itm-stock {{ $stock === 0 ? 'itm-stock--out' : ($stock <= 10 ? 'itm-stock--low' : '') }}">
                                            <span class="itm-stock__value">{{ $stock }}</span>
                                            <span class="itm-stock__edit tio-add-circle update-quantity" role="button" tabindex="0"
                                                  data-toggle="modal" data-target="#update-quantity" data-id="{{ $item->id }}"
                                                  title="{{ translate('Update stock') }}"></span>
                                        </span>
                                    </td>
                                @endif

                                <td class="col--numeric" data-order="{{ $final_price }}">
                                    <span class="itm-price">
                                        <span class="itm-price__now">{{ \App\CentralLogics\Helpers::format_currency($final_price) }}</span>
                                        @if($discount_amount > 0)
                                            <span class="itm-price__was">
                                                <del>{{ \App\CentralLogics\Helpers::format_currency($item->price) }}</del>
                                                <span class="itm-price__off">
                                                    -{{ $item->discount_type == 'percent'
                                                        ? rtrim(rtrim(number_format($item->discount, 2, '.', ''), '0'), '.').'%'
                                                        : \App\CentralLogics\Helpers::format_currency($discount_amount) }}
                                                </span>
                                            </span>
                                        @endif
                                    </span>
                                </td>

                                @if($productWiseTax)
                                    <td>
                                        @forelse($item?->taxVats?->pluck('tax.name', 'tax.tax_rate')->toArray() as $rate => $tax)
                                            <span class="itm-tax">{{ $tax }} <b>({{ $rate }}%)</b></span>
                                        @empty
                                            <span class="itm-blank">{{ translate('messages.No tax') }}</span>
                                        @endforelse
                                    </td>
                                @endif

                                <td data-order="{{ $item->rating_count ? $item->avg_rating : -1 }}">
                                    @if($item->rating_count)
                                        <span class="itm-rating"
                                              title="{{ translate('messages.Ratings') }}">
                                            <i class="tio-star"></i>
                                            {{ number_format($item->avg_rating, 1) }}
                                            <span class="itm-rating__count">({{ $item->rating_count }})</span>
                                        </span>
                                    @else
                                        <span class="itm-blank">{{ translate('messages.Not rated yet') }}</span>
                                    @endif
                                </td>

                                <td class="col--numeric" data-order="{{ $item->order_count }}">
                                    <span class="{{ $item->order_count ? 'itm-count' : 'itm-blank' }}"
                                          title="{{ $item->order_count
                                                ? translate('messages.Total orders')
                                                : translate('messages.Never ordered yet') }}">
                                        {{ $item->order_count }}
                                    </span>
                                </td>

                                <td class="text-center" data-order="{{ $item->recommended ? 1 : 0 }}">
                                    <label class="toggle-switch toggle-switch-sm" data-toggle="tooltip" data-placement="top"
                                           title="{{ translate('messages.Recommend to customers') }}" for="recCheckbox{{ $item->id }}">
                                        <input type="checkbox" id="recCheckbox{{ $item->id }}"
                                               class="toggle-switch-input redirect-url"
                                               data-url="{{ route('vendor.item.recommended', [$item->id, $item->recommended ? 0 : 1]) }}"
                                               @if(in_array('recommended', $filters['flag'], true)) data-ajax-refresh="[data-ajax-region]" @endif
                                               {{ $item->recommended ? 'checked' : '' }}>
                                        <span class="toggle-switch-label mx-auto">
                                            <span class="toggle-switch-indicator"></span>
                                        </span>
                                    </label>
                                </td>

                                <td>
                                    <div class="status-toggle" data-status="{{ $item->status ? 1 : 0 }}">
                                        <label class="toggle-switch toggle-switch-sm" for="stocksCheckbox{{ $item->id }}">
                                            <input type="checkbox" id="stocksCheckbox{{ $item->id }}"
                                                   class="toggle-switch-input redirect-url"
                                                   data-url="{{ route('vendor.item.status', [$item->id, $item->status ? 0 : 1]) }}"
                                                   aria-label="{{ translate('messages.Item status') }}"
                                                   @if($filters['status']) data-ajax-refresh="[data-ajax-region]" @endif
                                                   {{ $item->status ? 'checked' : '' }}>
                                            <span class="toggle-switch-label">
                                                <span class="toggle-switch-indicator"></span>
                                            </span>
                                        </label>
                                        <span class="status-toggle__text" aria-live="polite">
                                            {{ $item->status ? translate('messages.Active') : translate('messages.Inactive') }}
                                        </span>
                                    </div>
                                </td>

                                <td>
                                    <div class="btn--container justify-content-center">
                                        <a class="btn action-btn action-btn--view" href="{{ route('vendor.item.view', [$item->id]) }}"
                                           title="{{ translate('messages.View item') }}"><i class="tio-visible-outlined"></i></a>
                                        <a class="btn action-btn action-btn--edit" href="{{ route('vendor.item.edit', [$item->id]) }}"
                                           title="{{ translate('Edit item') }}"><i class="tio-edit"></i></a>
                                        <a class="btn action-btn action-btn--delete form-alert" href="javascript:"
                                           data-id="food-{{ $item->id }}"
                                           data-message="{{ translate('Want to delete this item?') }}"
                                           title="{{ translate('messages.Delete item') }}"><i class="tio-delete-outlined"></i></a>
                                        <form action="{{ route('vendor.item.delete', [$item->id]) }}" method="post" id="food-{{ $item->id }}"
                                              data-ajax-form data-ajax-remove="closest:tr" data-ajax-refresh="[data-ajax-region]">
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
                        <img src="{{ asset('/public/assets/admin/svg/illustrations/sorry.svg') }}" alt="">
                        @if($filters['search'] || $filter_count)
                            <h5>{{ translate('messages.No item matches these filters.') }}</h5>
                            <p class="text-muted font-size-sm">{{ translate('messages.Clear the search or widen the filters to see more items.') }}</p>
                        @else
                            <h5>{{ translate('messages.No item yet.') }}</h5>
                            <p class="text-muted font-size-sm">{{ translate('messages.Add your first item and it shows up here.') }}</p>
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
                'fd_subtitle' => translate('messages.Narrow your item list down by category, availability and stock.'),
            ])

            <form class="card-body sidebar-body sidebar-scrollbar" method="get" id="item_filter_form">
                <input type="hidden" name="search" value="{{ $filters['search'] }}" id="filter-search-value">

                <small class="text-cap mb-3">{{ translate('messages.Category') }}</small>
                <div class="form-group">
                    <select name="category_id" id="category_id" class="form-control"
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
                    <select name="sub_category_id" id="sub-categories" class="form-control js-select2-custom"
                            data-placeholder="{{ translate('Select subcategory') }}">
                        <option value="all">{{ translate('All subcategory') }}</option>
                        @foreach($sub_categories as $sub)
                            <option value="{{ $sub['id'] }}" {{ $filters['sub_category_id'] == $sub['id'] ? 'selected' : '' }}>
                                {{ $sub['name'] }}
                            </option>
                        @endforeach
                    </select>
                    @if(! $category)
                        <small class="itm-fd-store-hint">{{ translate('Pick a category to filter by its subcategories.') }}</small>
                    @endif
                </div>

                @if($store_categories->isNotEmpty())
                    <div class="form-group">
                        <label class="fd-sublabel" for="store_category_id">{{ translate('Store category') }}</label>
                        <select name="store_category_id" id="store_category_id" class="form-control js-select2-custom">
                            <option value="all">{{ translate('messages.All store categories') }}</option>
                            @foreach($store_categories as $store_category)
                                <option value="{{ $store_category->id }}" {{ $filters['store_category_id'] == $store_category->id ? 'selected' : '' }}>
                                    {{ $store_category->name }}
                                </option>
                            @endforeach
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
                        <input type="checkbox" id="filterFlagRecommended" name="flag[]" class="custom-control-input"
                               value="recommended" {{ in_array('recommended', $filters['flag']) ? 'checked' : '' }}>
                        <label class="custom-control-label" for="filterFlagRecommended">{{ translate('messages.Recommended') }}</label>
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
                    <button type="button" class="close" data-dismiss="modal" aria-label="{{ translate('messages.Close') }}">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body pt-0">
                    <form action="{{ route('vendor.item.stock-update') }}" method="post"
                          data-ajax-form data-ajax-close="#update-quantity" data-ajax-refresh="[data-ajax-region]">
                        @csrf
                        <div class="mt-2 rest-part w-100"></div>
                        <div class="btn--container justify-content-end">
                            <button type="reset" data-dismiss="modal" aria-label="{{ translate('messages.Close') }}" class="btn btn--reset">
                                <i class="tio-clear-circle-outlined"></i> {{ translate('Cancel') }}
                            </button>
                            <button type="submit" class="btn btn--primary"><i class="tio-save"></i> {{ translate('Update stock') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script_2')
    <script>
        "use strict";

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

        $('.js-select2-custom').each(function () {
            $.HSCore.components.HSSelect2.init($(this));
        });

        $('#category_id').select2({
            ajax: {
                url: '{{ route('vendor.category.get-all') }}',
                data: function (params) {
                    return { q: params.term, all: true, page: params.page };
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

        $(document).on('change', '#category_id', function () {
            let $sub = $('#sub-categories');
            let parent = $(this).val();
            let blank = '<option value="all">{{ translate('All subcategory') }}</option>';

            $sub.siblings('.itm-fd-store-hint').toggle(! parent || parent === 'all');

            if (! parent || parent === 'all') {
                $sub.html(blank).val('all').trigger('change');

                return;
            }

            $.get({
                url: '{{ route('vendor.item.get-categories') }}',
                data: { parent_id: parent },
                dataType: 'json',
                success: function (data) {
                    $sub.html(blank + data.options);
                    $sub.find('option[value="0"]').remove();
                    $sub.val('all').trigger('change');
                }
            });
        });

        $(document).on('click', '.update-quantity', function () {
            $.get({
                url: '{{ route('vendor.item.get_stock') }}',
                data: { id: $(this).data('id') },
                dataType: 'json',
                success: function (data) {
                    $('.rest-part').empty().html(data.view);
                    update_qty();
                }
            });
        });

        $(document).on('ajax:refreshed', '[data-ajax-region]', function () {
            $('#filter-search-value').val($(this).find('.search-form [name="search"]').val() || '');
        });

        if (window.AppAjax) {
            window.AppAjax.onMount(function ($root) {
                initItemDatatable($root);
            });
        }
    </script>
@endpush
