@extends('layouts.admin.app')

@section('title', translate('messages.Recommended stores'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/third-party-setup.css') }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/recommended-store.css') }}">
@endpush

@section('content')
    @php($store_service = app(\App\Services\Store\StoreService::class))
    @php($is_shuffled = $shuffle_recommended_store == 1)
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/outline/condition.svg')}}" class="w--26" alt="">
                </span>
                <span>
                    {{translate('messages.Recommended stores')}}
                <span class="badge badge-soft-dark ml-2" id="itemCount">{{ $stores->total() }}</span></span>
            </h1>
            <p class="page-header-desc">{{ translate('Stores you have chosen to push to the front of the customer app.') }}</p>
        </div>
        <div class="row g-3">
            <div class="col-12 tps">
                <div class="tps-card rcs">
                    <form action="{{ route('admin.store.recommended_store_add') }}" method="GET" id="recommended-store-form">
                        <div class="tps-card__head">
                            <span class="tps-card__brand"><i class="tio-add-circle-outlined"></i></span>
                            <div class="tps-card__titles">
                                <h5 class="tps-card__title">{{ translate('Add stores to the list') }}</h5>
                                <p class="tps-card__subtitle">{{ translate('Search a store by name, pick as many as you need, then submit them together.') }}</p>
                            </div>
                            <div class="tps-card__aside">
                                <span class="tps-pill tps-pill--off" id="selectedStorePill" aria-live="polite">
                                    {{ translate('Selected') }} <span class="rcs-count" id="selectedStoreCount">0</span>
                                </span>
                            </div>
                        </div>

                        <div class="tps-card__body">
                            <input type="hidden" id="store_ids" name="selected_store_ids" value="">

                            <div class="tps-field mb-0">
                                <label class="tps-field__label" for="storeSearchInput">{{ translate('Search stores') }}</label>
                                <div class="rcs-search">
                                    <i class="tio-search rcs-search__icon" aria-hidden="true"></i>
                                    <input type="text" id="storeSearchInput" class="form-control search-bar-input rcs-search__input"
                                           autocomplete="off"
                                           placeholder="{{translate('messages.Ex')}}: {{translate('Store name')}}">
                                    <button type="button" class="rcs-search__clear d-none" id="storeSearchClear"
                                            aria-label="{{ translate('Clear search') }}"><i class="tio-clear"></i></button>
                                </div>
                                <small class="tps-field__hint">{{ translate('Stores already on the list below are left out of the results.') }}</small>
                                <div class="rcs-results d-none" id="hide_class" aria-live="polite"
                                     data-searching="{{ translate('Searching') }}..."></div>
                            </div>

                            <div class="rcs-selected d-none" id="hide_class_2">
                                <div class="rcs-selected__head">
                                    <span class="rcs-selected__title">{{ translate('Selected stores') }}</span>
                                    <button type="button" class="rcs-selected__clear remove_all_data">{{ translate('Clear all') }}</button>
                                </div>
                                <div class="rcs-chips selected_store_list"></div>
                            </div>
                        </div>

                        <div class="tps-card__foot">
                            <span class="tps-foot-note">{{ translate('A store shows up in the recommended section as soon as it is added.') }}</span>
                            <button type="reset" class="btn btn--reset remove_all_data"><i class="tio-refresh"></i> {{translate('messages.Reset')}}</button>
                            <button type="submit" class="btn btn--primary" id="recommendedStoreSubmit" disabled><i class="tio-checkmark-circle-outlined"></i> {{translate('messages.Submit')}}</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-12 tps">
                <form action="{{ route('admin.store.shuffle_recommended_store', ['status' => $shuffle_recommended_store ?? 0]) }}" method="get" id="store_shffle_form"></form>
                <div class="tps-switchbar">
                    <div class="tps-switchbar__text">
                        <h6>{{translate('Shuffle store when page reload?')}}</h6>
                        <p>
                            {{ $is_shuffled
                                ? translate('Stores are reordered on every load, so each one gets a turn at the front.')
                                : translate('Stores keep the order below every time the section is shown.') }}
                        </p>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <span class="tps-pill {{ $is_shuffled ? 'tps-pill--on' : 'tps-pill--off' }}">
                            {{ $is_shuffled ? translate('Shuffling') : translate('Fixed order') }}
                        </span>
                        <label class="toggle-switch toggle-switch-sm p-0 m-0" for="store_shffle">
                            <input type="checkbox" id="store_shffle"
                                   data-id="store_shffle"
                                   data-type="status"
                                   data-image-on='{{asset('/public/assets/admin/img/modal')}}/counter-on.png'
                                   data-image-off="{{asset('/public/assets/admin/img/modal')}}/counter-off.png"
                                   data-title-on="{{translate('Want to shuffle the store list?')}}"
                                   data-title-off="{{translate('Want to disable shuffle store list?')}}"
                                   data-text-on="<p>{{translate('If enabled, store recommended section will be shuffled.')}}</p>"
                                   data-text-off="<p>{{translate('If disabled, store recommended section will not be shuffled.')}}</p>"
                                   class="toggle-switch-input dynamic-checkbox" name="shuffle_store" value="1" {{ $is_shuffled ? 'checked' : '' }}>
                            <span class="toggle-switch-label p-0">
                                <span class="toggle-switch-indicator"></span>
                            </span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card">
                    <div class="card-header py-2 border-0">
                        <div class="search--button-wrapper">
                            @include('partials._table-head', [
                                'subtitle' => translate('Switch a store off to drop it from the section without losing its place on this list.'),
                            ])
                            <form class="search-form">
                                <div class="input-group input--group">
                                    <input id="datatableSearch_" value="{{ request()?->search ?? '' }}" type="search" name="search" class="form-control"
                                            placeholder="{{translate('messages.Ex')}}: {{translate('Store name')}}" aria-label="{{translate('Search')}}" >
                                    <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                                </div>
                            </form>
                            @if(request()->input('search'))
                                <a class="btn btn--primary ml-2" href="{{ request()->fullUrlWithoutQuery(['search', 'page']) }}"><i class="tio-refresh"></i> {{translate('messages.Reset')}}</a>
                            @endif
                        </div>
                    </div>
                    <div class="table-responsive datatable-custom">
                        <table id="columnSearchDatatable"
                               class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table"
                               data-hs-datatables-options='{
                                 "order": [],
                                 "orderCellsTop": true,
                                 "paging":false
                               }'>
                            <thead class="thead-light">
                            <tr >
                                <th class="border-0">{{translate('Store information')}}</th>
                                <th class="border-0">{{translate('messages.Zone')}}</th>
                                <th class="border-0">{{translate('messages.Ratings')}}</th>
                                <th class="border-0 col--numeric">{{translate('messages.Total Products')}}</th>
                                <th class="border-0 col--numeric">{{translate('messages.Total orders')}}</th>
                                <th class="border-0">{{translate('Status')}}</th>
                                <th class="border-0">{{translate('Recommended')}}</th>
                                <th class="text-center">{{translate('messages.Action')}}</th>
                            </tr>

                            </thead>

                            <tbody id="set-rows">
                            @foreach($stores as $store)
                                @php($ratings = $store_service->calculateRating($store['rating']))
                                <tr>
                                    <td>
                                        <a href="{{route('admin.store.view', $store->id)}}" class="table-rest-info" alt="view store">
                                            <img class="img--60 circle onerror-image" data-onerror-image="{{asset('public/assets/admin/img/160x160/img1.jpg')}}"
                                            src="{{ $store['logo_full_url'] ?? asset('public/assets/admin/img/160x160/img1.jpg') }}"  >
                                            <div class="info max-w-200px">
                                                <div title="{{ $store?->name }}" class="text--title">
                                                    {{Str::limit($store->name, 20, '...')}}
                                                    @include('partials._verified_store_badge', ['store' => $store])
                                                </div>
                                                <div class="font-light">
                                                    ID:{{$store->id}}
                                                </div>
                                            </div>
                                        </a>
                                    </td>
                                    <td>
                                        {{$store->zone?$store->zone->name:translate('messages.Zone deleted')}}
                                        <span class="d-block fs-12 text-muted">
                                            {{$store->self_delivery_system ? translate('Self delivery') : translate('Platform delivery')}}
                                        </span>
                                    </td>
                                    <td data-order="{{ $ratings['total'] ? $ratings['rating'] : -1 }}">
                                        @if($ratings['total'])
                                            <span class="rating text-star" title="{{ translate('messages.Ratings') . ': ' . $ratings['total'] }}">
                                                <i class="tio-star"></i> {{number_format($ratings['rating'], 1)}} ({{$ratings['total']}})
                                            </span>
                                        @else
                                            <span class="text-muted font-size-sm">{{translate('messages.Not rated yet')}}</span>
                                        @endif
                                    </td>
                                    <td class="col--numeric" data-order="{{ $store->items_count }}">
                                        {{ $store->items_count }}
                                    </td>
                                    <td class="col--numeric" data-order="{{ $store->orders_count }}">
                                        <span class="badge badge-soft-{{$store->orders_count ? 'success' : 'secondary'}}"
                                                title="{{ $store->orders_count ? translate('messages.Total orders') . ': ' . $store->orders_count : translate('messages.Never ordered yet') }}">
                                            {{ $store->orders_count }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-soft-{{$store->status ? 'success' : 'danger'}}">
                                            {{$store->status ? translate('messages.Active') : translate('messages.Inactive')}}
                                        </span>
                                        <span class="cell-chips d-block mt-1">
                                            <span class="cell-chip">{{$store->active ? translate('messages.Open') : translate('Temporarily closed')}}</span>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="status-toggle" data-status="{{$store->storeConfig->is_recommended ? 1 : 0}}">
                                            <label class="toggle-switch toggle-switch-sm" for="publishCheckbox{{$store->id}}">
                                                <input type="checkbox" data-url="{{route('admin.store.recommended_store_status', [$store['id'], $store->storeConfig->is_recommended ? 0 : 1])}}" class="toggle-switch-input redirect-url" id="publishCheckbox{{$store->id}}"
                                                        data-label-on="{{translate('Recommended')}}" data-label-off="{{translate('Not recommended')}}" {{$store->storeConfig->is_recommended ? 'checked' : ''}}>
                                                <span class="toggle-switch-label">
                                                    <span class="toggle-switch-indicator"></span>
                                                </span>
                                            </label>
                                            <span class="status-toggle__text" aria-live="polite">
                                                {{$store->storeConfig->is_recommended ? translate('Recommended') : translate('Not recommended')}}
                                            </span>
                                        </div>
                                    </td>
                                    <td >
                                        <div class="btn--container justify-content-center">
                                            <a class="btn action-btn action-btn--delete form-alert" href="javascript:" data-id="item-{{$store['id']}}" data-message="{{ translate('Want to remove the store from the list?') }}" title="{{translate('messages.Delete')}}"><i class="tio-delete-outlined"></i>
                                            </a>
                                            <form action="{{route('admin.store.recommended_store_remove', [$store['id']])}}"
                                                    method="post" id="item-{{$store['id']}}">
                                                @csrf @method('delete')
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if(count($stores) !== 0)
                    <hr>
                    @endif
                    <div class="page-area">
                        {!! $stores->links() !!}
                    </div>
                    @if(count($stores) === 0)
                    <div class="empty--data">
                        <img src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="public">
                        <h5>
                            {{ request()->input('search') ? translate('No store matches this search') : translate('No recommended store yet') }}
                        </h5>
                        <p>
                            {{ request()->input('search')
                                ? translate('Try another name, or reset the search to see the whole list.')
                                : translate('Search a store above and add it to see it here.') }}
                        </p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script_2')
    <script>
        "use strict";

        let selected_store_ids = [];
        let store_search_timer = null;
        let store_search_request = null;

        function render_store_selection() {
            let count = selected_store_ids.length;

            $('#store_ids').val(selected_store_ids.join(','));
            $('#selectedStoreCount').text(count);
            $('#selectedStorePill').toggleClass('tps-pill--on', count > 0).toggleClass('tps-pill--off', count === 0);
            $('#recommendedStoreSubmit').prop('disabled', count === 0);
            $('#hide_class_2').toggleClass('d-none', count === 0);
        }

        function hide_store_results() {
            $('#hide_class').empty().addClass('d-none');
        }

        function show_store_results(html) {
            $('#hide_class').html(html).removeClass('d-none');
        }

        function search_stores(name) {
            if (store_search_request) {
                store_search_request.abort();
            }

            store_search_request = $.get("{{ route('admin.get_all_stores') }}", {
                name: name,
                exclude_recommended: 1
            }, function (response) {
                show_store_results(response.result);
            });
        }

        function selected_stores(key, remove = false) {
            key = parseInt(key);

            if (remove) {
                selected_store_ids = selected_store_ids.filter(function (e) { return e !== key });
            } else if (selected_store_ids.includes(key)) {
                return;
            } else {
                selected_store_ids.push(key);
            }

            $('#storeSearchInput').val('');
            $('#storeSearchClear').addClass('d-none');
            hide_store_results();
            render_store_selection();

            if (selected_store_ids.length === 0) {
                $('.selected_store_list').empty();
                return;
            }

            $.get("{{route('admin.store.selected_stores')}}", { id: selected_store_ids }, function (response) {
                $('.selected_store_list').html(response.result);
            });
        }

        $(document).on('input', '#storeSearchInput', function () {
            let name = $(this).val().trim();

            $('#storeSearchClear').toggleClass('d-none', name.length === 0);
            clearTimeout(store_search_timer);

            if (name.length === 0) {
                if (store_search_request) {
                    store_search_request.abort();
                }
                hide_store_results();
                return;
            }

            show_store_results('<p class="rcs-results__status">' + $('#hide_class').data('searching') + '</p>');
            store_search_timer = setTimeout(function () {
                search_stores(name);
            }, 300);
        });

        $(document).on('keydown', '#storeSearchInput', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
            }
        });

        $(document).on('click', '#storeSearchClear', function () {
            $('#storeSearchInput').val('').trigger('focus');
            $(this).addClass('d-none');
            clearTimeout(store_search_timer);
            hide_store_results();
        });

        $(document).on('click', '.remove_all_data', function () {
            selected_store_ids = [];
            $('.selected_store_list').empty();
            $('#storeSearchInput').val('');
            $('#storeSearchClear').addClass('d-none');
            hide_store_results();
            render_store_selection();
        });
    </script>
@endpush
