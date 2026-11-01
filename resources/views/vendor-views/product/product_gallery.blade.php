@extends('layouts.vendor.app')

@section('title',translate('Product gallery'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/product-gallery.css') }}">
@endpush

@section('content')
    @php
        $search_term = request()->query('search');
        $has_filters = $category || ($search_term !== null && $search_term !== '');

        $filter_url = function (array $except) {
            $params = request()->except(array_merge($except, ['page']));

            return url()->current() . (count($params) ? '?' . http_build_query($params) : '');
        };
    @endphp

    <div class="content container-fluid pgal">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{ asset('public/assets/admin/img/outline/group.svg') }}" alt="">
                </span>
                <span>
                    {{ translate('Product gallery') }}
                    <span class="badge badge-soft-dark ml-2" id="itemCount">{{ $items->total() }}</span>
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('Search a ready-made catalogue and reuse its details instead of typing an item from scratch.') }}</p>
        </div>

        <div class="pg-toolbar">
            <form class="pg-filters search-form">
                <input type="hidden" value="1" name="product_gallery">

                <div class="pg-field">
                    <label class="pg-field__label" for="category_id">{{ translate('messages.Category') }}</label>
                    <select name="category_id" id="category_id"
                        data-placeholder="{{ translate('Select category') }}"
                        class="js-data-example-ajax form-control set-filter" data-url="{{ url()->full() }}"
                        data-filter="category_id">
                        @if ($category)
                            <option value="{{ $category->id }}" selected>{{ $category->name }}</option>
                        @else
                            <option value="all" selected>{{ translate('messages.All category') }}</option>
                        @endif
                    </select>
                </div>

                <div class="pg-field pg-field--search">
                    <label class="pg-field__label" for="pg-search">{{ translate('messages.keyword') }}</label>
                    <div class="pg-search">
                        <i class="tio-search"></i>
                        <input id="pg-search" type="search" value="{{ $search_term }}" name="search"
                            class="form-control" placeholder="{{ translate('messages.Ex search name') }}"
                            aria-label="{{ translate('Search') }}">
                    </div>
                </div>

                <div class="pg-filters__actions">
                    <button type="submit" class="btn btn--primary"><i class="tio-search"></i> {{ translate('messages.Search') }}</button>
                    @if ($has_filters)
                        <a href="{{ $filter_url(['category_id', 'search']) }}"
                            class="btn pg-btn-reset"><i class="tio-refresh"></i> {{ translate('messages.Reset') }}</a>
                    @endif
                </div>
            </form>
        </div>

        <div class="pg-resultbar">
            <span class="pg-resultbar__count">
                @if ($items->total() > 0)
                    {{ translate('messages.showing') }}
                    <strong>{{ $items->firstItem() }}&ndash;{{ $items->lastItem() }}</strong>
                    {{ translate('messages.of') }} <strong>{{ $items->total() }}</strong>
                    {{ strtolower(translate('messages.products')) }}
                @else
                    <strong>0</strong> {{ strtolower(translate('messages.products')) }}
                @endif
            </span>

            @if ($has_filters)
                <span class="pg-resultbar__sep"></span>

                @if ($category)
                    <a class="pg-filterchip" href="{{ $filter_url(['category_id']) }}">
                        <span class="pg-filterchip__key">{{ translate('messages.Category') }}:</span>
                        <span class="pg-filterchip__val">{{ $category->name }}</span>
                        <span class="pg-filterchip__x" aria-hidden="true">&times;</span>
                    </a>
                @endif

                @if ($search_term !== null && $search_term !== '')
                    <a class="pg-filterchip" href="{{ $filter_url(['search']) }}">
                        <span class="pg-filterchip__key">{{ translate('messages.keyword') }}:</span>
                        <span class="pg-filterchip__val">{{ $search_term }}</span>
                        <span class="pg-filterchip__x" aria-hidden="true">&times;</span>
                    </a>
                @endif

                <a class="pg-resultbar__clear"
                    href="{{ $filter_url(['category_id', 'search']) }}">{{ translate('Clear all') }}</a>
            @endif
        </div>

        <div id="set-rows">
            @include('vendor-views.product.partials._gallery', ['items' => $items])
        </div>

        @if(count($items) === 0)
            <div class="pg-empty">
                <img src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="">
                <h5>{{ translate('messages.No products found') }}</h5>
                <p>{{ translate('messages.Nothing matched this search. Try a different keyword or clear the filters.') }}</p>
                @if ($has_filters)
                    <a href="{{ $filter_url(['category_id', 'search']) }}"
                        class="btn btn--primary pg-empty__action"><i class="tio-clear-circle-outlined"></i> {{ translate('Clear all') }}</a>
                @endif
            </div>
        @endif

        <hr>
        <div class="page-area">
            {!! $items->withQueryString()->links() !!}
        </div>
    </div>

@endsection

@push('script_2')
    <script src="{{ asset('public/assets/admin/js/product-gallery.js') }}"></script>
    <script>
        "use strict";

        $('#category_id').select2({
            ajax: {
                url: '{{ route('vendor.category.get-all') }}',
                data: function(params) {
                    return {
                        q: params.term,
                        all: true,
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

        $(document).on('click', '.data-info-show', function() {
            fetch_data($(this).data('url'))
        })

        function fetch_data(url) {
            $.ajax({
                url: url,
                type: "get",
                beforeSend: function() {
                    $('#data-view').empty();
                    $('#loading').show()
                },
                success: function(data) {
                    $("#data-view").append(data.view);
                    $(document).trigger('pgal:drawer-loaded');
                },
                complete: function() {
                    $('#loading').hide()
                }
            })
        }
    </script>
@endpush
