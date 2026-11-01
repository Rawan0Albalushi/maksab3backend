@extends('layouts.admin.app')

@section('title',translate('SEO setup'))

@push('css_or_js')
    <link rel="stylesheet" href="{{asset('public/assets/admin/css/seo-page-meta.css')}}">
@endpush

@section('content')
@php
    // Page labels are mapped from literal keys rather than passed as
    // `translate($page)`: a variable argument grows the language file with
    // every page name (§8). These are the keys the old variable call already
    // persisted, so the ar / bn translations they carry stay attached.
    $pageLabels = [
        'home_page' => translate('home_page'),
        'top_offers_page' => translate('top_offers_page'),
        'brands_page' => translate('brands_page'),
        'search_page' => translate('search_page'),
        'vehicle_search_page' => translate('vehicle_search_page'),
        'about_us_page' => translate('about_us_page'),
        'contact_us_page' => translate('Help & support page'),
        'store_join_page' => translate('store_join_page'),
        'deliveryman_join_page' => translate('deliveryman_join_page'),
        'terms_and_conditions_page' => translate('terms_and_conditions_page'),
        'privacy_policy_page' => translate('privacy_policy_page'),
        'refund_policy_page' => translate('refund_policy_page'),
        'cancellation_policy_page' => translate('cancellation_policy_page'),
        'shipping_policy_page' => translate('shipping_policy_page'),
        'latest_store_page' => translate('latest_store_page'),
        'flash_sales' => translate('Flash sales'),
        'popular_store_page' => translate('popular_store_page'),
        'coupons_page' => translate('coupons_page'),
        'best_sellers_page' => translate('best_sellers_page'),
        'top_rated_page' => translate('top_rated_page'),
        'organic_page' => translate('organic_page'),
        'recently_viewed_page' => translate('recently_viewed_page'),
        'recently_ordered_page' => translate('recently_ordered_page'),
        'wishlist_page' => translate('wishlist_page'),
        'basic_medicine_page' => translate('basic_medicine_page'),
        'common_conditions_page' => translate('common_conditions_page'),
        'store_near_you_page' => translate('store_near_you_page'),
        'restaurant_near_you_page' => translate('restaurant_near_you_page'),
        'recommended_store_page' => translate('recommended_store_page'),
    ];

    // Lengths search engines actually render, not the form's maxlength — the
    // meter is advice, and the form already caps the input at 100 / 200.
    $titleLimit = 60;
    $descriptionLimit = 160;

    // The four robots directives the meta form can switch on beyond index /
    // no-index. Literal keys so every label stays greppable.
    $robotFlags = [
        'meta_no_follow' => ['value' => 'nofollow', 'label' => translate('No follow')],
        'meta_no_image_index' => ['value' => 'noimageindex', 'label' => translate('No image index')],
        'meta_no_archive' => ['value' => 'noarchive', 'label' => translate('No archive')],
        'meta_no_snippet' => ['value' => 'nosnippet', 'label' => translate('No snippet')],
    ];
@endphp
<div class="content container-fluid spm">
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h1 class="page-header-title text-break">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/outline/seo-setting.svg')}}" class="w--26" alt="">
                </span>
                <span>{{ translate('Manage page SEO') }}<span class="badge badge-soft-dark ml-2" id="itemCount">{{ count($pages) }}</span></span>
            </h1>
            <p class="page-header-desc">{{ translate('The title, description and preview image search engines show for each of your pages.') }}</p>
        </div>
        <div class="page-header-actions">
            <a href="#0" class="offcanvas-trigger text--primary-2 d-flex flex-wrap align-items-center" data-target="#global_guideline_offcanvas">
                <strong class="mr-2">{{ translate('How it works') }}</strong>
                <div class="blinkings">
                    <i class="tio-info-outined"></i>
                </div>
            </a>
        </div>
    </div>
    <div class="bg-opacity-primary-10 rounded py-2 px-3 d-flex flex-wrap gap-1 align-items-center mb-20">
        <div class="gap-1 d-flex align-items-center">
            <i class="tio-light-on theme-clr-dark fs-16"></i>
            <p class="m-0 fs-12">{{ translate('Manage meta information to improve page performance in search results') }}</p>
        </div>
    </div>

    {{-- Describes the whole page list, so it does not follow the chips below —
         the count badge on the heading does that. --}}
    <div class="spm-stats">
        <div class="spm-stat">
            <span class="spm-stat__icon"><i class="tio-browser-window"></i></span>
            <span class="spm-stat__text">
                <span class="spm-stat__value">{{ $summary['total'] }}</span>
                <span class="spm-stat__label">{{ translate('Storefront pages') }}</span>
            </span>
        </div>
        <div class="spm-stat spm-stat--ok">
            <span class="spm-stat__icon"><i class="tio-checkmark-circle-outlined"></i></span>
            <span class="spm-stat__text">
                <span class="spm-stat__value">{{ $summary['configured'] }}</span>
                <span class="spm-stat__label">{{ translate('Meta data added') }}</span>
            </span>
        </div>
        <div class="spm-stat spm-stat--pending">
            <span class="spm-stat__icon"><i class="tio-add-circle"></i></span>
            <span class="spm-stat__text">
                <span class="spm-stat__value">{{ $summary['pending'] }}</span>
                <span class="spm-stat__label">{{ translate('Waiting for setup') }}</span>
            </span>
        </div>
        <div class="spm-stat spm-stat--warn">
            <span class="spm-stat__icon"><i class="tio-hidden-outlined"></i></span>
            <span class="spm-stat__text">
                <span class="spm-stat__value">{{ $summary['no_index'] }}</span>
                <span class="spm-stat__label">{{ translate('Hidden from search engines') }}</span>
            </span>
        </div>
    </div>

    <div class="card">
        <div class="card-header flex-wrap pt-3 pb-3 border-0 gap-2">
            <div class="search--button-wrapper mr-1">
                @include('partials._table-head', [
                    'subtitle' => translate('messages.Meta title and description used by each public page.'),
                    'count' => null,
                ])
                <form class="search-form min--260" onsubmit="event.preventDefault()">
                    <div class="input-group input--group">
                        <input id="datatableSearch_" type="search" name="search" class="form-control h--40px" placeholder="{{ translate('Search page name') }}" aria-label="Search" tabindex="1">

                        <button type="button" class="btn btn--secondary bg-modal-btn"><i class="tio-search text-muted"></i></button>
                    </div>
                </form>
            </div>
            <div class="spm-filters" role="group" aria-label="{{ translate('Filter by setup state') }}">
                <button type="button" class="spm-chip is-active" data-filter="all">
                    {{ translate('All pages') }} <span class="spm-chip__count" data-count="all">{{ $summary['total'] }}</span>
                </button>
                <button type="button" class="spm-chip" data-filter="configured">
                    {{ translate('Meta data added') }} <span class="spm-chip__count" data-count="configured">{{ $summary['configured'] }}</span>
                </button>
                <button type="button" class="spm-chip" data-filter="pending">
                    {{ translate('Waiting for setup') }} <span class="spm-chip__count" data-count="pending">{{ $summary['pending'] }}</span>
                </button>
                <button type="button" class="spm-chip" data-filter="no_index">
                    {{ translate('No index') }} <span class="spm-chip__count" data-count="no_index">{{ $summary['no_index'] }}</span>
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive space-around-16 datatable-custom">
                <table class="table table-borderless table-thead-borderless table-align-middle table-nowrap card-table m-0">
                    <thead class="thead-light">
                        <tr>
                            <th class="border-0">{{ translate('Page') }}</th>
                            <th class="border-0">{{ translate('Meta content') }}</th>
                            <th class="border-0">{{ translate('Meta image') }}</th>
                            <th class="border-0">{{ translate('Visibility') }}</th>
                            <th class="border-0">{{ translate('Last updated') }}</th>
                            <th class="border-0 text-right">{{ translate('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($pages as $page)
                            @php
                                $meta = $pageMetaData[$page] ?? null;
                                $label = $pageLabels[$page] ?? ucfirst(str_replace('_', ' ', $page));
                                $robots = $meta?->meta_data ?? [];
                                $indexed = ($robots['meta_index'] ?? 1) != 0;
                                $titleLength = mb_strlen($meta?->title ?? '');
                                $descriptionLength = mb_strlen($meta?->description ?? '');
                            @endphp
                            <tr data-page-key="{{ $page }}" data-page-label="{{ $label }}"
                                data-state="{{ $meta ? 'configured' : 'pending' }}"
                                data-index="{{ $meta ? ($indexed ? 'yes' : 'no') : 'na' }}">
                                <td>
                                    <div class="media align-items-center spm-page">
                                        <span class="spm-page__icon {{ $meta ? '' : 'is-pending' }}"
                                              title="{{ $meta ? translate('Meta data added') : translate('Waiting for setup') }}">
                                            <i class="tio-browser-window"></i>
                                        </span>
                                        <div class="media-body cell--truncate">
                                            <h5 class="spm-page__name" title="{{ $label }}">{{ $label }}</h5>
                                            <small class="d-block spm-key" title="{{ $page }}">{{ $page }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @if($meta)
                                        <div class="spm-snippet">
                                            <div class="spm-snippet__line">
                                                <span class="spm-snippet__title" title="{{ $meta->title }}">{{ Str::limit($meta->title, 90) }}</span>
                                                <small class="spm-meter {{ $titleLength > $titleLimit ? 'spm-meter--over' : '' }}"
                                                       title="{{ translate('Recommended character limit') }}: {{ $titleLimit }}">
                                                    {{ $titleLength }}/{{ $titleLimit }}
                                                </small>
                                            </div>
                                            <div class="spm-snippet__line">
                                                <span class="spm-snippet__desc" title="{{ $meta->description }}">{{ Str::limit($meta->description, 160) }}</span>
                                                <small class="spm-meter {{ $descriptionLength > $descriptionLimit ? 'spm-meter--over' : '' }}"
                                                       title="{{ translate('Recommended character limit') }}: {{ $descriptionLimit }}">
                                                    {{ $descriptionLength }}/{{ $descriptionLimit }}
                                                </small>
                                            </div>
                                        </div>
                                    @else
                                        <span class="spm-none">{{ translate('Not set') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($meta && $meta->image)
                                        <img class="spm-thumb onerror-image" src="{{ $meta->image_full_url }}"
                                             data-onerror-image="{{asset('public/assets/admin/img/100x100/2.jpg')}}"
                                             alt="{{ $label }}" title="{{ translate('Used when this page is shared on social media') }}">
                                    @else
                                        <span class="spm-thumb spm-thumb--empty" title="{{ translate('No meta image uploaded') }}">
                                            <i class="tio-image"></i>
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    @if($meta)
                                        <span class="badge badge-soft-{{ $indexed ? 'success' : 'danger' }}">
                                            {{ $indexed ? translate('Index') : translate('No index') }}
                                        </span>
                                        @php
                                            // Four chips in a narrow cell stack one per line and take the row
                                            // to ~180px. Only the first is drawn and the rest roll into a +N
                                            // chip that names them on hover, which keeps the column from
                                            // widening on the one row in thirty that has directives set.
                                            $activeFlags = collect($robotFlags)
                                                ->filter(fn ($flag, $key) => ($robots[$key] ?? null) === $flag['value'])
                                                ->pluck('label')
                                                ->values();
                                            $hiddenFlags = $activeFlags->slice(1);
                                        @endphp
                                        @if($activeFlags->isNotEmpty())
                                            <div class="spm-robots">
                                                <span class="cell-chips">
                                                    @foreach($activeFlags->take(1) as $flag)
                                                        <span class="cell-chip">{{ $flag }}</span>
                                                    @endforeach
                                                    @if($hiddenFlags->isNotEmpty())
                                                        <span class="cell-chip" title="{{ $hiddenFlags->implode(', ') }}">+{{ $hiddenFlags->count() }}</span>
                                                    @endif
                                                </span>
                                            </div>
                                        @endif
                                    @else
                                        <span class="spm-none" aria-hidden="true">&mdash;</span>
                                    @endif
                                </td>
                                <td>
                                    @if($meta && $meta->updated_at)
                                        {{ date('d M Y', strtotime($meta->updated_at)) }}
                                        <small class="d-block">{{ date(config('timeformat'), strtotime($meta->updated_at)) }}</small>
                                    @else
                                        <span class="spm-none" aria-hidden="true">&mdash;</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="btn--container justify-content-end">
                                        <a href="{{ route('admin.business-settings.seo-settings.pageMetaData', ['page_name' => $page]) }}"
                                           class="btn spm-action {{ $meta ? 'spm-action--edit' : 'spm-action--add' }}"
                                           aria-label="{{ ($meta ? translate('Edit meta content') : translate('Add meta content')) . ': ' . $label }}">
                                            @if($meta)
                                                <i class="tio-edit"></i> {{ translate('Edit content') }}
                                            @else
                                                <i class="tio-add-circle"></i> {{ translate('Add content') }}
                                            @endif
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="empty--data">
                                        <img src="{{asset('public/assets/admin/img/modal/pending-order-off.png')}}" alt="public">
                                        <h5>
                                            {{translate('No data found')}}
                                        </h5>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                        <tr class="empty-data-row" style="display: none;">
                            <td colspan="6">
                                <div class="empty--data">
                                    <img src="{{asset('public/assets/admin/img/modal/pending-order-off.png')}}" alt="public">
                                    <h5>
                                        {{translate('No data found')}}
                                    </h5>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="global_guideline_offcanvas" class="custom-offcanvas d-flex flex-column justify-content-between">
    <div>
        <div class="custom-offcanvas-header bg--secondary d-flex justify-content-between align-items-center px-3 py-3">
            <div class="py-1">
                <h3 class="mb-0 line--limit-1">{{ translate('Meta data setup') }}</h3>
            </div>
            <button type="button" class="btn-close w-25px h-25px border rounded-circle d-center bg--secondary text-dark offcanvas-close fz-15px p-0"aria-label="Close">
                &times;
            </button>
        </div>
        <div class="custom-offcanvas-body custom-offcanvas-body-100  p-20">
            <div class="">
                <div class="py-3 px-3 bg-light rounded mb-3">
                    <div class="d-flex gap-3 align-items-center justify-content-between overflow-hidden">
                        <button class="btn-collapse line--limit-1 d-flex gap-3 align-items-center bg-transparent border-0 p-0 collapse show" type="button"
                                data-toggle="collapse" data-target="#collapseGeneralSetup_01" aria-expanded="true">
                            <div class="btn-collapse-icon w-35px h-35px bg-white d-flex align-items-center justify-content-center border icon-btn rounded-circle fs-12 lh-1">
                                <i class="tio-down-ui top-01 color-656566"></i>
                            </div>
                            <span class="font-semibold text-left fs-14 text-title line--limit-1">{{ translate('What is metadata setup for pages?') }}</span>
                        </button>
                    </div>
                    <div class="collapse mt-3 show" id="collapseGeneralSetup_01">
                        <div class="card rounded border p-3 card-body">
                            <div class="mb-3">
                                <p class="m-0 fs-12 color-656566">
                                    <strong>{{ translate('Meta data setup') }}</strong> {{ translate('Allows you to define how each page of your e-commerce site appears in') }}:
                                </p>
                            </div>
                            <div class="mb-3">
                                <ul class="mb-0 list-group pl-3 d-flex flex-column gap-1px">
                                    <li class="fs-12 color-656566"><strong>{{translate('Search engines')}}</strong>  (Google, Bing, etc.)</li>
                                    <li class="fs-12 color-656566"><strong>{{translate('Social media shares')}}</strong>  (Facebook, WhatsApp, Twitter, LinkedIn)</li>
                                </ul>
                            </div>
                            <p class="m-0 fs-12 color-656566">
                                <strong>{{ translate('Important note') }}:</strong> {{ translate('Metadata does not change page content, but it strongly affects visibility, traffic, and click-through rate.') }}
                            </p>
                        </div>
                    </div>
                </div>
                <div class="p-12 p-sm-20 bg-light rounded mb-3">
                    <div class="d-flex gap-3 align-items-center justify-content-between overflow-hidden">
                        <button class="btn-collapse line--limit-1 d-flex gap-3 align-items-center bg-transparent border-0 p-0 collapsed" type="button"
                                data-toggle="collapse" data-target="#collapseGeneralSetup_032" aria-expanded="true">
                            <div class="btn-collapse-icon w-35px h-35px bg-white d-flex align-items-center justify-content-center border icon-btn rounded-circle fs-12 lh-1 collapsed">
                                <i class="tio-down-ui top-01 color-656566"></i>
                            </div>
                            <span class="font-semibold text-left fs-14 text-title line--limit-1">{{ translate('Why set up metadata for pages?') }}</span>
                        </button>
                    </div>
                    <div class="collapse mt-3" id="collapseGeneralSetup_032">
                        <div class="card rounded border p-3 card-body">
                            <div class="mb-3">
                                <p class="m-0 font-weight-medium color-656566 fs-12">{{translate('Different e-commerce pages serve different purposes, so they need different SEO behaviour.')}}</p>
                            </div>
                            <div class="mb-3">
                                <ul class="mb-0 list-group pl-3 d-flex flex-column gap-1px">
                                    <li class="fs-12 color-656566">{{translate('Improves Google ranking')}}</li>
                                    <li class="fs-12 color-656566">{{translate('Increases organic traffic')}}</li>
                                    <li class="fs-12 color-656566">{{translate('Controls which pages appear in search')}}</li>
                                    <li class="fs-12 color-656566">{{translate('Improves social media sharing previews')}}</li>
                                    <li class="fs-12 color-656566">{{translate('Prevents private pages from being indexed')}}</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="p-12 p-sm-20 bg-light rounded mb-3">
                    <div class="d-flex gap-3 align-items-center justify-content-between overflow-hidden">
                        <button class="btn-collapse line--limit-1 d-flex gap-3 align-items-center bg-transparent border-0 p-0 collapsed" type="button"
                                data-toggle="collapse" data-target="#collapseGeneralSetup_033" aria-expanded="true">
                            <div class="btn-collapse-icon w-35px h-35px bg-white d-flex align-items-center justify-content-center border icon-btn rounded-circle fs-12 lh-1 collapsed">
                                <i class="tio-down-ui top-01 color-656566"></i>
                            </div>
                            <span class="font-semibold text-left fs-14 text-title line--limit-1">{{ translate('How to set up metadata for pages?') }}</span>
                        </button>
                    </div>
                    <div class="collapse mt-3" id="collapseGeneralSetup_033">
                        <div class="card rounded border p-3 card-body">
                            <div class="mb-3">
                                <h6 class="mb-2 fs-12 color-656566">{{translate('Write the meta title and description')}}</h6>
                                <ul class="mb-0 list-group pl-3 d-flex flex-column gap-1px">
                                    <li class="fs-12 color-656566">{{translate('Give the page specific, meaningful text')}}</li>
                                    <li class="fs-12 color-656566">{{translate('Avoid copying the same text across pages, and use keywords naturally.')}}</li>
                                </ul>
                            </div>
                            <div class="mb-3">
                                <h6 class="mb-2 fs-12 color-656566">{{translate('Upload meta image')}}</h6>
                                <ul class="mb-0 list-group pl-3 d-flex flex-column gap-1px">
                                    <li class="fs-12 color-656566">{{translate('Used for social sharing previews')}}</li>
                                    <li class="fs-12 color-656566">{{translate('Maintain the recommended ratio & size')}}</li>
                                </ul>
                            </div>
                            <div class="mb-3">
                                <h6 class="mb-2 fs-12 color-656566">{{translate('Select the necessary options as per instructions')}}</h6>
                                <ul class="mb-0 list-group pl-3 d-flex flex-column gap-1px">
                                    <li class="fs-12 color-656566"><strong class="text-dark">{{ translate('Index') }}:</strong> {{translate('Allow search engines to show this page')}}</li>
                                    <li class="fs-12 color-656566"><strong class="text-dark">{{ translate('No index') }}:</strong> {{translate('Hide page from search results')}}</li>
                                    <li class="fs-12 color-656566"><strong class="text-dark">{{ translate('No follow') }}:</strong> {{translate('Prevents search engines from following links on this page')}}</li>
                                    <li class="fs-12 color-656566"><strong class="text-dark">{{ translate('No image index') }}:</strong> {{translate('Prevents images from appearing in Google image search. Use for private/system pages.')}}</li>
                                    <li class="fs-12 color-656566"><strong class="text-dark">{{ translate('Max snippet') }}:</strong> {{translate('Controls text shown in Google results')}}</li>
                                    <li class="fs-12 color-656566"><strong class="text-dark">{{ translate('Max video preview') }}:</strong> {{translate('Video preview length')}}</li>
                                    <li class="fs-12 color-656566"><strong class="text-dark">{{ translate('Max image preview') }}:</strong> {{translate('Small / large')}}</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div id="offcanvasOverlay" class="offcanvas-overlay"></div>

@endsection

@push('script_2')
    <script>
        (function () {
            "use strict";

            var $rows = $('tbody tr[data-page-key]');
            var $emptyRow = $('.empty-data-row');
            var $chips = $('.spm-chip');
            var activeFilter = 'all';

            function matchesFilter($row) {
                if (activeFilter === 'all') return true;
                if (activeFilter === 'no_index') return $row.data('index') === 'no';
                return $row.data('state') === activeFilter;
            }

            function applyFilters() {
                var term = ($('#datatableSearch_').val() || '').toLowerCase().trim();
                var counts = { all: 0, configured: 0, pending: 0, no_index: 0 };
                var visible = 0;

                $rows.each(function () {
                    var $row = $(this);
                    var haystack = (($row.data('page-label') || '') + ' ' + ($row.data('page-key') || '')).toLowerCase();
                    var matchesTerm = term === '' || haystack.indexOf(term) > -1;

                    // The chip counts follow the search box, so they always say
                    // how many rows a chip would actually reveal right now.
                    if (matchesTerm) {
                        counts.all++;
                        counts[$row.data('state')]++;
                        if ($row.data('index') === 'no') counts.no_index++;
                    }

                    if (matchesTerm && matchesFilter($row)) {
                        $row.show();
                        visible++;
                    } else {
                        $row.hide();
                    }
                });

                $.each(counts, function (key, value) {
                    $('[data-count="' + key + '"]').text(value);
                });

                $('#itemCount').text(visible);
                $emptyRow.toggle(visible === 0);
            }

            $('#datatableSearch_').on('keyup search', applyFilters);

            $chips.on('click', function () {
                $chips.removeClass('is-active');
                $(this).addClass('is-active');
                activeFilter = $(this).data('filter');
                applyFilters();
            });
        })();
    </script>
@endpush
