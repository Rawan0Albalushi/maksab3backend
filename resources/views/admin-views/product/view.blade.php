@extends('layouts.admin.app')

@section('title', translate('Item preview'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/view-pages/item-reviews.css') }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/view-pages/item-detail.css') }}">
@endpush

@section('content')
    @php
        $module_type = $product->module?->module_type;
        $has_stock = (bool) config('module.'.$module_type.'.stock');
        $has_veg = (bool) config('module.'.$module_type.'.veg_non_veg');
        $has_addon = (bool) config('module.'.$module_type.'.add_on');
        $has_time = (bool) config('module.'.$module_type.'.item_available_time');
        $has_organic = (bool) config('module.'.$module_type.'.organic');
        $has_nutrition = (bool) config('module.'.$module_type.'.nutrition');
        $has_allergy = (bool) config('module.'.$module_type.'.allergy');
        $has_generic = (bool) config('module.'.$module_type.'.generic_name');
        $languages = \App\CentralLogics\Helpers::decodeJsonToArray(
            \App\CentralLogics\Helpers::get_business_settings('language', false)
        );
        $discount_amount = \App\CentralLogics\Helpers::discount_calculate($product, $product->price);
        $final_price = max($product->price - $discount_amount, 0);
        $category_name = $product->category?->parent?->name ?? $product->category?->name;
        $sub_category_name = $product->category?->parent ? $product->category?->name : null;
        $rating_breakdown = \App\CentralLogics\Helpers::decodeJsonToArray($product->rating);
        $rating_total = array_sum($rating_breakdown);
        $stock = max((int) $product->stock, 0);
        $store_label = \App\CentralLogics\Helpers::moduleStoreLabel();
    @endphp

    <div class="content container-fluid idt rvw">
        <div class="page-header">
            <div class="row align-items-center g-2">
                <div class="col-md-7 col-12">
                    <h1 class="page-header-title text-break">
                        <span class="page-header-icon">
                            <img src="{{ asset('public/assets/admin/img/items.png') }}" class="w--22" alt="">
                        </span>
                        <span>{{ $product->name }}</span>
                    </h1>
                    <p class="page-header-desc">{{ translate('Everything about this item, from its price and stock to how it has sold.') }}</p>
                    <div class="idt-pills">
                        <span class="idt-pill idt-pill--{{ $product->status ? 'ok' : 'muted' }}">
                            <i class="{{ $product->status ? 'tio-checkmark-circle-outlined' : 'tio-invisible' }}"></i>
                            {{ $product->status ? translate('messages.Active') : translate('messages.Inactive') }}
                        </span>

                        @if(! $product->is_approved)
                            @if($pending_request)
                                <a class="idt-pill idt-pill--wait" href="{{ route('admin.item.requested_item_view', ['id' => $pending_request]) }}">
                                    <i class="tio-hourglass-outlined"></i> {{ translate('messages.Waiting for approval') }}
                                </a>
                            @else
                                <span class="idt-pill idt-pill--wait">
                                    <i class="tio-hourglass-outlined"></i> {{ translate('messages.Waiting for approval') }}
                                </span>
                            @endif
                        @elseif($pending_request)
                            <a class="idt-pill idt-pill--wait" href="{{ route('admin.item.requested_item_view', ['id' => $pending_request]) }}">
                                <i class="tio-edit"></i> {{ translate('messages.Edit awaiting approval') }}
                            </a>
                        @endif

                        @if($has_veg)
                            <span class="idt-pill idt-pill--muted">
                                {{ $product->veg ? translate('Veg') : translate('Non veg') }}
                            </span>
                        @endif

                        <span class="idt-pill idt-pill--muted">#{{ $product->id }}</span>
                    </div>
                </div>
                <div class="col-md-5 col-12">
                    <div class="idt-actions">
                        @if($has_stock)
                            <a class="btn btn--primary-light update-quantity" href="javascript:"
                               data-toggle="modal" data-target="#update-quantity" data-id="{{ $product->id }}">
                                <i class="tio-save"></i> {{ translate('Update stock') }}
                            </a>
                        @endif
                        <a class="btn btn--primary" href="{{ route('admin.item.edit', [$product->id]) }}">
                            <i class="tio-edit"></i> {{ translate('messages.Edit information') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-2">
            <div class="col-lg-8 col-12">
                <div class="card mb-3">
                    <div class="idt-hero">
                        <div>
                            @include('partials._product-media-slider', ['product' => $product])
                        </div>

                        <div class="idt-hero__body">
                            @if(count($languages))
                                <ul class="nav nav-tabs mb-3">
                                    <li class="nav-item">
                                        <a class="nav-link lang_link active" href="#" id="default-link">{{ translate('Default') }}</a>
                                    </li>
                                    @foreach($languages as $lang)
                                        <li class="nav-item">
                                            <a class="nav-link lang_link" href="#" id="{{ $lang }}-link">
                                                {{ \App\CentralLogics\Helpers::get_language_name($lang).' ('.strtoupper($lang).')' }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            <div class="lang_form" id="default-form">
                                <h2 class="idt-hero__title">{{ $product->getRawOriginal('name') }}</h2>
                                <span class="idt-hero__label">{{ translate('messages.Description') }}</span>
                                {{-- Folded to five lines; the toggle below stays hidden until the
                                     script measures that there is a sixth. --}}
                                <p class="idt-hero__desc idt-hero__desc--clamped">{{ strip_tags($product->getRawOriginal('description')) }}</p>
                                <button type="button" class="idt-hero__desc-toggle">{{ translate('See more') }}</button>
                            </div>

                            @foreach($languages as $lang)
                                @php
                                    $translated = collect($product->translations)->where('locale', $lang);
                                    $translated_name = $translated->firstWhere('key', 'name')?->value;
                                    $translated_description = $translated->firstWhere('key', 'description')?->value;
                                @endphp
                                <div class="d-none lang_form" id="{{ $lang }}-form">
                                    <h2 class="idt-hero__title">{{ $translated_name }}</h2>
                                    <span class="idt-hero__label">{{ translate('messages.Description') }}</span>
                                    <p class="idt-hero__desc idt-hero__desc--clamped">{{ strip_tags($translated_description) }}</p>
                                    <button type="button" class="idt-hero__desc-toggle">{{ translate('See more') }}</button>
                                </div>
                            @endforeach

                            <div class="idt-price">
                                <span class="idt-price__now">{{ \App\CentralLogics\Helpers::format_currency($final_price) }}</span>
                                @if($discount_amount > 0)
                                    <span class="idt-price__was">
                                        <del>{{ \App\CentralLogics\Helpers::format_currency($product->price) }}</del>
                                    </span>
                                    <span class="idt-price__off">
                                        -{{ $product->discount_type == 'percent'
                                            ? rtrim(rtrim(number_format($product->discount, 2, '.', ''), '0'), '.').'%'
                                            : \App\CentralLogics\Helpers::format_currency($discount_amount) }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="idt-card__head">
                        <h2 class="idt-card__title">{{ translate('Item details') }}</h2>
                    </div>

                    <div class="idt-specs">
                        <div class="idt-spec">
                            <span class="idt-spec__label">{{ translate('messages.Category') }}</span>
                            <span class="idt-spec__value">{{ $category_name ?? translate('messages.uncategorize') }}</span>
                        </div>

                        @if($sub_category_name)
                            <div class="idt-spec">
                                <span class="idt-spec__label">{{ translate('Subcategory') }}</span>
                                <span class="idt-spec__value">{{ $sub_category_name }}</span>
                            </div>
                        @endif

                        <div class="idt-spec">
                            <span class="idt-spec__label">{{ translate('Unit price') }}</span>
                            <span class="idt-spec__value">{{ \App\CentralLogics\Helpers::format_currency($product->price) }}</span>
                        </div>

                        <div class="idt-spec">
                            <span class="idt-spec__label">{{ translate('Discount') }}</span>
                            <span class="idt-spec__value">
                                {{ $product->discount > 0
                                    ? ($product->discount_type == 'percent'
                                        ? rtrim(rtrim(number_format($product->discount, 2, '.', ''), '0'), '.').'%'
                                        : \App\CentralLogics\Helpers::format_currency($product->discount))
                                    : translate('messages.No discount') }}
                            </span>
                        </div>

                        @if($has_stock)
                            <div class="idt-spec">
                                <span class="idt-spec__label">{{ translate('messages.Total stock') }}</span>
                                <span class="idt-spec__value">{{ $stock }}</span>
                            </div>
                            @if($product->unit)
                                <div class="idt-spec">
                                    <span class="idt-spec__label">{{ translate('Unit') }}</span>
                                    <span class="idt-spec__value">{{ $product->unit->unit }}</span>
                                </div>
                            @endif
                        @endif

                        @if($has_organic)
                            <div class="idt-spec">
                                <span class="idt-spec__label">{{ translate('Is organic') }}</span>
                                <span class="idt-spec__value">{{ $product->organic ? translate('messages.Yes') : translate('messages.No') }}</span>
                            </div>
                        @endif

                        @if($product->maximum_cart_quantity)
                            <div class="idt-spec">
                                <span class="idt-spec__label">{{ translate('messages.Maximum cart quantity') }}</span>
                                <span class="idt-spec__value">{{ $product->maximum_cart_quantity }}</span>
                            </div>
                        @endif

                        @if($has_time && $product->available_time_starts && $product->available_time_ends)
                            <div class="idt-spec">
                                <span class="idt-spec__label">{{ translate('messages.Available time') }}</span>
                                <span class="idt-spec__value">
                                    {{ date(config('timeformat'), strtotime($product->available_time_starts)) }}
                                    - {{ date(config('timeformat'), strtotime($product->available_time_ends)) }}
                                </span>
                            </div>
                        @endif

                        @if($productWiseTax)
                            <div class="idt-spec">
                                <span class="idt-spec__label">{{ translate('VAT/tax') }}</span>
                                <span class="idt-spec__value">
                                    @forelse($product?->taxVats?->pluck('tax.name', 'tax.tax_rate')->toArray() as $rate => $tax)
                                        <span class="d-block">{{ $tax }} ({{ $rate }}%)</span>
                                    @empty
                                        <span class="idt-blank">{{ translate('messages.No tax') }}</span>
                                    @endforelse
                                </span>
                            </div>
                        @endif
                    </div>
                </div>

                @include('admin-views.product.partials._detail-options', [
                    'food_variations' => $module_type === 'food'
                        ? \App\CentralLogics\Helpers::decodeJsonToArray($product->food_variations)
                        : [],
                    'variations' => $module_type === 'food'
                        ? []
                        : \App\CentralLogics\Helpers::decodeJsonToArray($product->variations),
                    'addons' => $has_addon
                        ? \App\CentralLogics\Helpers::addons_by_ids(\App\CentralLogics\Helpers::decodeJsonToArray($product->add_ons))
                        : collect(),
                    'chip_groups' => [
                        ['label' => translate('messages.Tags'), 'values' => $product->tags->pluck('tag')],
                        ['label' => translate('messages.Nutrition'), 'values' => $has_nutrition ? $product->nutritions->pluck('nutrition') : collect()],
                        ['label' => translate('messages.Allergy'), 'values' => $has_allergy ? $product->allergies->pluck('allergy') : collect()],
                        ['label' => translate('Generic name'), 'values' => $has_generic ? $product->generic->pluck('generic_name') : collect()],
                    ],
                ])
            </div>

            <div class="col-lg-4 col-12">
                <div class="card mb-3">
                    <div class="idt-metrics">
                        <div class="idt-metric">
                            <span class="idt-metric__value">{{ $product->order_count }}</span>
                            <span class="idt-metric__label">{{ translate('messages.Orders') }}</span>
                        </div>
                        @if($has_stock)
                            <div class="idt-metric idt-metric--{{ $stock === 0 ? 'danger' : ($stock <= 10 ? 'warn' : 'ok') }}">
                                <span class="idt-metric__value">{{ $stock }}</span>
                                <span class="idt-metric__label">{{ translate('messages.In stock') }}</span>
                            </div>
                        @else
                            <div class="idt-metric">
                                <span class="idt-metric__value">{{ $reviews->total() }}</span>
                                <span class="idt-metric__label">{{ translate('messages.Reviews') }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="idt-card__head">
                        <h2 class="idt-card__title">{{ translate('messages.Customer rating') }}</h2>
                    </div>
                    <div class="card-body">
                        @if($reviews->total())
                            <div class="idt-rating">
                                <span class="idt-rating__figure">
                                    <span class="idt-rating__value">{{ number_format($product->avg_rating, 1) }}</span>
                                    <span class="idt-rating__scale">/5</span>
                                </span>
                                @include('admin-views.product.partials._rating-stars', ['rating' => $product->avg_rating, 'stars_size' => 'lg'])
                                <span class="idt-rating__meta">
                                    {{ translate('messages.Reviews') }}: {{ $reviews->total() }}
                                </span>
                            </div>

                            <ul class="rvw-bars idt-bars">
                                @foreach([5, 4, 3, 2, 1] as $star)
                                    @php($count = (int) data_get($rating_breakdown, $star, 0))
                                    <li>
                                        <span class="rvw-bar">
                                            <span class="rvw-bar__label">{{ $star }} <i class="tio-star"></i></span>
                                            <span class="rvw-bar__track">
                                                <span class="rvw-bar__fill" style="inline-size: {{ $rating_total ? round($count * 100 / $rating_total) : 0 }}%"></span>
                                            </span>
                                            <span class="rvw-bar__count">{{ $count }}</span>
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="idt-blank mb-0">{{ translate('messages.No customer has rated this item yet.') }}</p>
                        @endif
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="idt-card__head">
                        <h2 class="idt-card__title">{{ $store_label }}</h2>
                    </div>
                    <div class="card-body">
                        @if($product->store)
                            <a class="idt-store" href="{{ route('admin.store.view', [$product->store->id]) }}">
                                <img class="idt-store__logo onerror-image"
                                     src="{{ $product->store->logo_full_url }}"
                                     data-onerror-image="{{ asset('public/assets/admin/img/160x160/img1.jpg') }}"
                                     alt="{{ $product->store->name }}">
                                <span class="idt-store__body">
                                    <span class="idt-store__name">{{ $product->store->name }}</span>
                                    @if($product->store->zone)
                                        <span class="idt-store__meta">
                                            <i class="tio-poi-outlined"></i> {{ $product->store->zone->name }}
                                        </span>
                                    @endif
                                </span>
                            </a>
                        @else
                            <p class="idt-blank">{{ translate('messages.Store deleted') }}</p>
                        @endif

                        <div class="idt-facts">
                            <div class="idt-fact">
                                <span class="idt-fact__label">{{ translate('messages.Module') }}</span>
                                <span class="idt-fact__value">{{ $product->module?->module_name }}</span>
                            </div>
                            <div class="idt-fact">
                                <span class="idt-fact__label">{{ translate('messages.Added') }}</span>
                                <span class="idt-fact__value">{{ \App\CentralLogics\Helpers::date_format($product->created_at) }}</span>
                            </div>
                            <div class="idt-fact">
                                <span class="idt-fact__label">{{ translate('messages.Last updated') }}</span>
                                <span class="idt-fact__value" title="{{ \App\CentralLogics\Helpers::time_date_format($product->updated_at) }}">
                                    {{ $product->updated_at?->diffForHumans() }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header py-2 border-0">
                <div class="search--button-wrapper justify-content-end">
                    @include('partials._table-head', [
                        'title' => translate('messages.Product reviews'),
                        'subtitle' => translate('messages.What customers said about this item.'),
                        'count' => $reviews->total(),
                        'count_id' => 'itemCount',
                    ])

                    <div class="hs-unfold mr-2">
                        <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle min-height-40" href="javascript:;"
                            data-hs-unfold-options='{
                                "target": "#usersExportDropdown",
                                "type": "css-animation"
                            }'>
                            <i class="tio-download-to mr-1"></i> {{ translate('messages.Export') }}
                        </a>

                        <div id="usersExportDropdown" class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">
                            <span class="dropdown-header">{{ translate('messages.Download options') }}</span>
                            <a id="export-excel" class="dropdown-item"
                               href="{{ route('admin.item.item_wise_reviews_export', array_merge(request()->query(), ['type' => 'excel', 'store' => $product->store?->name, 'id' => $product->id])) }}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                     src="{{ asset('public/assets/admin') }}/svg/components/excel.svg" alt="">
                                Excel
                            </a>
                            <a id="export-csv" class="dropdown-item"
                               href="{{ route('admin.item.item_wise_reviews_export', array_merge(request()->query(), ['type' => 'csv', 'store' => $product->store?->name, 'id' => $product->id])) }}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                     src="{{ asset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg" alt="">
                                CSV
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="table-responsive datatable-custom">
                <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                    <thead class="thead-light">
                    <tr>
                        <th class="border-0">{{ translate('Review ID') }}</th>
                        <th class="border-0">{{ translate('messages.Reviewer') }}</th>
                        <th class="border-0">{{ translate('messages.review') }}</th>
                        <th class="border-0">{{ $store_label.' '.translate('Reply') }}</th>
                        <th class="border-0">{{ translate('messages.Date') }}</th>
                        <th class="border-0">{{ translate('messages.Status') }}</th>
                    </tr>
                    </thead>

                    <tbody>
                    @foreach($reviews as $review)
                        <tr>
                            <td><span class="rvw-id">{{ $review->review_id }}</span></td>
                            <td>
                                @if($review->customer)
                                    <a class="media align-items-center rvw-customer" href="{{ route('admin.users.customer.view', [$review->user_id]) }}">
                                        @include('partials._user-avatar', [
                                            'imageUrl' => $review->customer->image_full_url,
                                            'proStatus' => $review->customer->pro_status ?? false,
                                            'size' => 40,
                                        ])
                                        <div class="media-body cell--truncate ml-2">
                                            <span class="rvw-customer__name">{{ $review->customer->f_name.' '.$review->customer->l_name }}</span>
                                            <span class="rvw-meta">{{ $review->customer->email }}</span>
                                        </div>
                                    </a>
                                @else
                                    <span class="rvw-muted">{{ translate('No data found') }}</span>
                                @endif
                                @if($review->order_id)
                                    <a class="rvw-meta rvw-meta--link" href="{{ route('admin.order.details', ['id' => $review->order_id]) }}">
                                        <i class="tio-receipt-outlined"></i> {{ translate('messages.Order ID') }}: {{ $review->order_id }}
                                    </a>
                                @endif
                            </td>
                            <td class="rvw-cell--text">
                                <div class="rvw-rating">
                                    @include('admin-views.product.partials._rating-stars', ['rating' => $review->rating])
                                    <span class="rvw-rating__value">{{ $review->rating }}</span>
                                </div>
                                @if($review->comment)
                                    <p class="rvw-comment" data-toggle="tooltip" data-placement="top" title="{{ $review->comment }}">{{ $review->comment }}</p>
                                @else
                                    <span class="rvw-muted">{{ translate('messages.No comment left') }}</span>
                                @endif
                            </td>
                            <td class="rvw-cell--text">
                                @if($review->reply)
                                    <p class="rvw-comment" data-toggle="tooltip" data-placement="top" title="{{ $review->reply }}">{{ $review->reply }}</p>
                                @else
                                    <span class="rvw-pill rvw-pill--warn">{{ translate('messages.Awaiting reply') }}</span>
                                @endif
                            </td>
                            <td>
                                <div class="rvw-date">
                                    <span>{{ \App\CentralLogics\Helpers::date_format($review->created_at) }}</span>
                                    <span class="rvw-meta">{{ \App\CentralLogics\Helpers::time_format($review->created_at) }}</span>
                                </div>
                            </td>
                            <td>
                                <div class="status-toggle" data-status="{{ $review->status ? 1 : 0 }}">
                                    <label class="toggle-switch toggle-switch-sm" for="reviewCheckbox{{ $review->id }}">
                                        <input type="checkbox" id="reviewCheckbox{{ $review->id }}"
                                               class="toggle-switch-input status_form_alert"
                                               data-id="status-{{ $review->id }}"
                                               data-label-on="{{ translate('messages.Visible') }}"
                                               data-label-off="{{ translate('messages.Hidden') }}"
                                               data-message="{{ $review->status
                                                    ? translate('messages.You want to hide this review for customer')
                                                    : translate('messages.You want to show this review for customer') }}"
                                               {{ $review->status ? 'checked' : '' }}>
                                        <span class="toggle-switch-label">
                                            <span class="toggle-switch-indicator"></span>
                                        </span>
                                    </label>
                                    <span class="status-toggle__text" aria-live="polite">
                                        {{ $review->status ? translate('messages.Visible') : translate('messages.Hidden') }}
                                    </span>
                                </div>
                                <form action="{{ route('admin.item.reviews.status', [$review->id, $review->status ? 0 : 1]) }}"
                                      method="get" id="status-{{ $review->id }}"></form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            @if(count($reviews) !== 0)
                <hr>
            @endif
            <div class="page-area">
                {!! $reviews->links() !!}
            </div>

            @if(count($reviews) === 0)
                <div class="empty--data">
                    <img src="{{ asset('/public/assets/admin/svg/illustrations/sorry.svg') }}" alt="">
                    <h5>{{ translate('messages.No review yet.') }}</h5>
                    <p class="text-muted font-size-sm">{{ translate('messages.Reviews appear here once customers rate this item.') }}</p>
                </div>
            @endif
        </div>
    </div>

    @if($has_stock)
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
                        <form action="{{ route('admin.item.stock-update') }}" method="post">
                            @csrf
                            <div class="mt-2 rest-part w-100"></div>
                            <div class="btn--container justify-content-end">
                                <button type="reset" data-dismiss="modal" class="btn btn--reset">
                                    <i class="tio-clear-circle-outlined"></i> {{ translate('messages.Cancel') }}
                                </button>
                                <button type="submit" class="btn btn--primary">
                                    <i class="tio-save"></i> {{ translate('Update stock') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('script_2')
    <script>
        "use strict";

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

        // The toggle can only be offered once the browser has laid the text out -- whether a
        // description runs past five lines depends on the rendered width, which Blade cannot
        // know. So every description ships clamped with its toggle hidden, and this reveals the
        // toggle only where there is a sixth line to reveal.
        const DESC_MORE = '{{ translate('See more') }}';
        const DESC_LESS = '{{ translate('See less') }}';

        function syncDescriptionToggles() {
            document.querySelectorAll('.idt-hero__desc').forEach(function (desc) {
                const toggle = desc.nextElementSibling;

                if (! toggle || ! toggle.classList.contains('idt-hero__desc-toggle')) {
                    return;
                }

                // A description on a language tab that is not open measures 0 -- it is
                // display:none -- so leave it alone and measure it when its tab opens.
                if (desc.offsetParent === null) {
                    return;
                }

                // Only meaningful while clamped; once the reader has expanded it, the toggle
                // has to stay regardless of what the heights now say.
                if (! desc.classList.contains('idt-hero__desc--clamped')) {
                    return;
                }

                toggle.classList.toggle('is-visible', desc.scrollHeight > desc.clientHeight + 1);
            });
        }

        $(document).on('click', '.idt-hero__desc-toggle', function () {
            const desc = this.previousElementSibling;
            const clamped = desc.classList.toggle('idt-hero__desc--clamped');

            this.textContent = clamped ? DESC_MORE : DESC_LESS;
        });

        // Deferred: common.js's own `.lang_link` handler is what un-hides the tab, and the
        // order the two listeners run in is not guaranteed. Measuring after the current task
        // means the form is visible by then either way.
        $(document).on('click', '.lang_link', function () {
            window.setTimeout(syncDescriptionToggles, 0);
        });

        let descResizeTimer = null;
        window.addEventListener('resize', function () {
            window.clearTimeout(descResizeTimer);
            descResizeTimer = window.setTimeout(syncDescriptionToggles, 150);
        });

        $(syncDescriptionToggles);

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
    </script>
@endpush
