@extends('layouts.admin.app')

@section('title', translate('messages.Item reviews'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/view-pages/item-reviews.css') }}">
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{ asset('public/assets/admin/img/items.png') }}" class="w--22" alt="">
                </span>
                <span>{{ translate('messages.Item reviews') }}</span>
            </h1>
            <p class="page-header-desc">{{ translate('What customers said about the items they bought.') }}</p>
        </div>

        <div class="rvw" id="review-index" data-ajax-region
             data-ajax-url="{{ url()->full() }}"
             data-ajax-links=".page-link, .list-reset-search"
             data-ajax-forms=".search-form">

            @include('admin-views.product.partials._review-summary', ['summary' => $summary, 'filters' => $filters])

            <div class="card">
                <div class="card-header py-2 border-0">
                    <div class="search--button-wrapper">
                        @include('partials._table-head', [
                            'subtitle' => translate('messages.Ratings and comments customers left on items.'),
                            'count' => null,
                        ])

                        <form class="search-form">
                            @if(request('module_id'))
                                <input type="hidden" name="module_id" value="{{ request('module_id') }}">
                            @endif
                            @foreach($filters['rating'] as $rating)
                                <input type="hidden" name="rating[]" value="{{ $rating }}">
                            @endforeach
                            @foreach($filters['visibility'] as $visibility)
                                <input type="hidden" name="visibility[]" value="{{ $visibility }}">
                            @endforeach
                            @foreach($filters['reply'] as $reply)
                                <input type="hidden" name="reply[]" value="{{ $reply }}">
                            @endforeach
                            @if($filters['from_date'])
                                <input type="hidden" name="from_date" value="{{ $filters['from_date'] }}">
                            @endif
                            @if($filters['to_date'])
                                <input type="hidden" name="to_date" value="{{ $filters['to_date'] }}">
                            @endif
                            <div class="input-group input--group">
                                <input id="datatableSearch_" type="search" name="search" class="form-control"
                                       value="{{ $filters['search'] }}"
                                       placeholder="{{ translate('Search by item, customer, rating or review ID') }}"
                                       aria-label="{{ translate('Search by item, customer, rating or review ID') }}">
                                <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
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
                                <a id="export-excel" class="dropdown-item" href="{{ route('admin.item.reviews_export', array_merge(request()->query(), ['type' => 'excel'])) }}">
                                    <img class="avatar avatar-xss avatar-4by3 mr-2"
                                         src="{{ asset('public/assets/admin') }}/svg/components/excel.svg" alt="">
                                    Excel
                                </a>
                                <a id="export-csv" class="dropdown-item" href="{{ route('admin.item.reviews_export', array_merge(request()->query(), ['type' => 'csv'])) }}">
                                    <img class="avatar avatar-xss avatar-4by3 mr-2"
                                         src="{{ asset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg" alt="">
                                    CSV
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive datatable-custom">
                    <table id="columnSearchDatatable"
                           class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table"
                           data-hs-datatables-options='{
                                "order": [],
                                "orderCellsTop": true,
                                "paging": false,
                                "columnDefs": [{
                                    "targets": [0, -1],
                                    "orderable": false
                                }]
                            }'>
                        <thead class="thead-light">
                        <tr>
                            <th class="border-0">{{ translate('Review ID') }}</th>
                            <th class="border-0">{{ translate('messages.item') }}</th>
                            <th class="border-0">{{ translate('messages.Customer') }}</th>
                            <th class="border-0">{{ translate('messages.review') }}</th>
                            <th class="border-0">{{ translate('Store reply') }}</th>
                            <th class="border-0">{{ translate('messages.Date') }}</th>
                            <th class="border-0">{{ translate('messages.Visibility') }}</th>
                        </tr>
                        </thead>

                        <tbody id="set-rows">
                        @foreach($reviews as $review)
                            @include('admin-views.product.partials._review-row', [
                                'review' => $review,
                                'filters' => $filters,
                            ])
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
                        @if($filters['search'] || $filter_count)
                            <h5>{{ translate('messages.No review matches these filters.') }}</h5>
                            <p class="text-muted font-size-sm">{{ translate('Clear the search or widen the filters to see more reviews.') }}</p>
                        @else
                            <h5>{{ translate('messages.No review yet.') }}</h5>
                            <p class="text-muted font-size-sm">{{ translate('Reviews appear here as soon as customers rate a delivered item.') }}</p>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div id="datatableFilterSidebar" class="filter-drawer sidebar sidebar-bordered sidebar-box-shadow">
        <div class="card card-lg sidebar-card sidebar-footer-fixed">
            @include('partials._filter-drawer-head', [
                'fd_title' => translate('messages.Review filter'),
                'fd_subtitle' => translate('messages.Narrow the review list down by rating, visibility, store reply and date range.'),
            ])

            <form class="card-body sidebar-body sidebar-scrollbar" method="get" id="review_filter_form">
                @if(request('module_id'))
                    <input type="hidden" name="module_id" value="{{ request('module_id') }}">
                @endif
                <input type="hidden" name="search" value="{{ $filters['search'] }}" id="filter-search-value">

                <small class="text-cap mb-3">{{ translate('messages.Rating') }}</small>
                <div class="fd-grid">
                    @foreach([5, 4, 3, 2, 1] as $star)
                        <div class="custom-control custom-checkbox mb-2">
                            <input type="checkbox" id="filterRating{{ $star }}" name="rating[]" class="custom-control-input"
                                   value="{{ $star }}" {{ in_array($star, $filters['rating']) ? 'checked' : '' }}>
                            <label class="custom-control-label" for="filterRating{{ $star }}">
                                {{ $star }} <i class="tio-star rvw-filter-star"></i>
                            </label>
                        </div>
                    @endforeach
                </div>

                <hr class="my-4">

                <small class="text-cap mb-3">{{ translate('messages.Visibility') }}</small>
                <div class="fd-grid">
                    <div class="custom-control custom-checkbox mb-2">
                        <input type="checkbox" id="filterVisible" name="visibility[]" class="custom-control-input"
                               value="visible" {{ in_array('visible', $filters['visibility']) ? 'checked' : '' }}>
                        <label class="custom-control-label" for="filterVisible">{{ translate('messages.Visible') }}</label>
                    </div>
                    <div class="custom-control custom-checkbox mb-2">
                        <input type="checkbox" id="filterHidden" name="visibility[]" class="custom-control-input"
                               value="hidden" {{ in_array('hidden', $filters['visibility']) ? 'checked' : '' }}>
                        <label class="custom-control-label" for="filterHidden">{{ translate('messages.Hidden') }}</label>
                    </div>
                </div>

                <hr class="my-4">

                <small class="text-cap mb-3">{{ translate('Store reply') }}</small>
                <div class="fd-grid">
                    <div class="custom-control custom-checkbox mb-2">
                        <input type="checkbox" id="filterReplied" name="reply[]" class="custom-control-input"
                               value="replied" {{ in_array('replied', $filters['reply']) ? 'checked' : '' }}>
                        <label class="custom-control-label" for="filterReplied">{{ translate('messages.Replied') }}</label>
                    </div>
                    <div class="custom-control custom-checkbox mb-2">
                        <input type="checkbox" id="filterAwaiting" name="reply[]" class="custom-control-input"
                               value="awaiting" {{ in_array('awaiting', $filters['reply']) ? 'checked' : '' }}>
                        <label class="custom-control-label" for="filterAwaiting">{{ translate('messages.Awaiting reply') }}</label>
                    </div>
                </div>

                <hr class="my-4">

                <small class="text-cap mb-3">{{ translate('messages.Date between') }}</small>
                <div class="fd-daterange">
                    <div class="fd-daterange__field">
                        <label class="fd-sublabel" for="review_from_date">{{ translate('messages.from') }}</label>
                        <input type="date" name="from_date" class="form-control" id="review_from_date" value="{{ $filters['from_date'] }}">
                    </div>
                    <div class="fd-daterange__field">
                        <label class="fd-sublabel" for="review_to_date">{{ translate('messages.to') }}</label>
                        <input type="date" name="to_date" class="form-control" id="review_to_date" value="{{ $filters['to_date'] }}">
                    </div>
                </div>

                <div class="card-footer sidebar-footer">
                    <div class="row gx-2">
                        <div class="col">
                            <a class="btn btn-block btn-white" href="{{ request()->fullUrlWithoutQuery(['rating', 'visibility', 'reply', 'from_date', 'to_date', 'page']) }}">
                                <i class="tio-clear-circle-outlined"></i> {{ translate('Clear all') }}
                            </a>
                        </div>
                        <div class="col">
                            <button type="submit" class="btn btn-block btn-primary"><i class="tio-filter-list"></i> {{ translate('Apply filters') }}</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('script_2')
    <script>
        "use strict";

        $(document).on('ready', function () {
            $.HSCore.components.HSDatatables.init($('#columnSearchDatatable'));
        });

        if (window.AppAjax) {
            window.AppAjax.onMount(function ($root) {
                $root.find('#columnSearchDatatable').each(function () {
                    $.HSCore.components.HSDatatables.init($(this));
                });
            });
        }

        $(document).on('ajax:success', '.search-form', function () {
            $('#filter-search-value').val($(this).find('[name="search"]').val());
        });
    </script>
@endpush
