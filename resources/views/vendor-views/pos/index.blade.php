@extends('layouts.vendor.app')

@section('title', translate('POS orders'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/delivery-type.css') }}">
    <link rel="stylesheet"
          href="{{ asset('public/assets/admin/css/view-pages/pos.css') }}?v={{ @filemtime(public_path('assets/admin/css/view-pages/pos.css')) ?: 1 }}">

    <style type="text/css" media="print">
        @page {
            size: auto;
            margin: 0;
        }
    </style>
@endpush

@section('content')
    <div class="content container-fluid pos-screen">

        @include('partials._page-head', [
            'title'    => translate('POS orders'),
            'subtitle' => translate('messages.Build an order on behalf of a customer: add products, set the delivery details and take payment.'),
            'icon_class' => 'tio-shopping-cart',
            'count'    => null,
            'actions'  => 'vendor-views.pos._store-chip',
        ])

        <div class="pos-workspace">

            <section class="pos-panel pos-catalog">
                <div class="pos-panel-head">
                    <h2 class="pos-panel-title">
                        <i class="tio-shopping-basket"></i>{{ translate('Product section') }}
                    </h2>
                    <span class="pos-chip" id="pos-product-count">
                        {{ $products->total() }} {{ translate('messages.Items') }}
                    </span>
                </div>

                <div class="pos-toolbar">
                    <div class="pos-field">
                        <label class="pos-field-label" for="category">{{ translate('messages.Category') }}</label>
                        <select name="category" id="category"
                                data-search-placeholder="{{ translate('messages.Search category') }}"
                                class="form-control js-select2-custom set-filter"
                                data-url="{{ url()->full() }}" data-filter="category_id"
                                title="{{ translate('Select category') }}">
                            <option value="">{{ translate('All categories') }}</option>
                            @foreach ($categories as $item)
                                <option value="{{ $item->id }}" {{ $category == $item->id ? 'selected' : '' }}>
                                    {{ Str::limit($item->name, 20, '...') }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="pos-field">
                        <label class="pos-field-label" for="datatableSearch">{{ translate('messages.Search') }}</label>
                        <form id="search-form" class="pos-search">
                            <i class="tio-search pos-search-icon" aria-hidden="true"></i>
                            <input id="datatableSearch" type="search" value="{{ $search ?? '' }}" name="searchKey"
                                   class="form-control pos-search-input"
                                   placeholder="{{ translate('messages.Search by product name') }}"
                                   aria-label="{{ translate('Search') }}">
                            <button type="button" class="pos-search-clear {{ $search ? '' : 'd-none' }}"
                                    id="pos-search-clear" aria-label="{{ translate('messages.Clear filters') }}">
                                <i class="tio-clear"></i>
                            </button>
                            <button type="submit" class="pos-search-submit"
                                    aria-label="{{ translate('messages.Search') }}">
                                <i class="tio-search"></i>
                            </button>
                        </form>
                    </div>
                </div>

                <div class="pos-panel-body" id="pos-products">
                    @include('vendor-views.pos._single_product_list', ['products' => $products])
                </div>
            </section>

            <aside class="pos-panel pos-ticket">
                <div class="pos-panel-head">
                    <h2 class="pos-panel-title">
                        <i class="tio-receipt-outlined"></i>{{ translate('Billing section') }}
                    </h2>
                    <span class="pos-chip pos-chip--accent" id="pos-cart-count">
                        0 {{ translate('messages.Items') }}
                    </span>
                </div>

                <div class="pos-ticket-body">

                    <div class="pos-block">
                        <div class="pos-block-head">
                            <h3 class="pos-block-title">
                                <i class="tio-user"></i>{{ translate('messages.Customer') }}
                            </h3>
                        </div>

                        <div class="pos-customer-picker">
                            <select id="customer" name="customer_id"
                                    data-search-placeholder="{{ translate('messages.Search customer') }}"
                                    data-placeholder="{{ translate('messages.Walk in customer') }}"
                                    class="js-data-example-ajax form-control">
                                @if ($customer)
                                    <option selected value="{{ $customer->id }}">
                                        {{ $customer->f_name . ' ' . $customer->l_name }} ({{ $customer->phone }})
                                    </option>
                                @endif
                            </select>
                            <button class="btn btn--primary pos-customer-add" id="add_new_customer" type="button"
                                    data-toggle="modal" data-target="#add-customer"
                                    title="{{ translate('Add new customer') }}">
                                <i class="tio-user-add"></i>{{ translate('messages.New') }}
                            </button>
                        </div>

                        <div id="customer_data" class="{{ $customer ? '' : 'd-none' }}">
                            <div class="pos-customer-card">
                                @include('partials._user-avatar', [
                                    'imageUrl'  => $customer ? $customer->image_full_url : '',
                                    'proStatus' => $customer ? ($customer->pro_status ?? false) : false,
                                    'imgId'     => 'customer_image',
                                    'size'      => 40,
                                ])
                                <span class="pos-customer-info">
                                    <span class="pos-customer-name" id="customer_name">
                                        {{ $customer ? $customer->f_name . ' ' . $customer->l_name : '' }}
                                    </span>
                                    <span class="pos-customer-meta">
                                        <span id="customer_phone">{{ $customer ? $customer->phone : '' }}</span>
                                        <span>
                                            {{ translate('messages.Wallet') }}:
                                            <strong id="customer_wallet">
                                                {{ $customer ? \App\CentralLogics\Helpers::format_currency($customer->wallet_balance) : '' }}
                                            </strong>
                                        </span>
                                    </span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="pos-block">
                        <div class="pos-block-head">
                            <h3 class="pos-block-title">
                                <i class="tio-poi"></i>{{ translate('Delivery information') }}
                                <small>({{ translate('Home delivery') }})</small>
                            </h3>
                            <button type="button" class="pos-icon-btn" id="delivery_address"
                                    data-toggle="modal" data-target="#deliveryAddrModal"
                                    aria-label="{{ translate('Update delivery address') }}">
                                <i class="tio-edit"></i>
                            </button>
                        </div>
                        <div id="del-add">
                            @include('vendor-views.pos._address', $address_view)
                        </div>
                    </div>

                    @include('partials.delivery-type-selector', [
                        'getUrl'            => route('vendor.pos.delivery_type.get'),
                        'setUrl'            => route('vendor.pos.delivery_type.set'),
                        'zoneId'            => $store_data?->zone_id ?? '',
                        'moduleId'          => $store_data?->module_id ?? '',
                        'storeId'           => $store_data?->id ?? '',
                        'storeDeliveryTime' => $store_data?->delivery_time ?? '',
                    ])

                    <div id="cart">
                        @include('vendor-views.pos._cart')
                    </div>
                </div>

                <div class="pos-actions">
                    <div class="pos-actions-total">
                        <span>{{ translate('messages.Payable') }}</span>
                        <strong id="pos-payable">{{ \App\CentralLogics\Helpers::format_currency(0) }}</strong>
                    </div>
                    <div class="pos-actions-row">
                        <button type="button" class="btn btn-outline-danger empty-Cart" disabled>
                            <i class="tio-clear-circle-outlined"></i>{{ translate('Clear cart') }}
                        </button>
                        <button type="submit" form="order_place" class="btn btn--primary place-order-submit" disabled>
                            <i class="tio-checkmark-circle-outlined"></i>{{ translate('messages.Place order') }}
                        </button>
                    </div>
                </div>
            </aside>
        </div>

        <div class="modal fade pos-modal" id="quick-view" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content" id="quick-view-modal"></div>
            </div>
        </div>

        @php($order = $last_order)
        @if ($order)
            <div class="modal fade pos-modal pos-modal--receipt" id="print-invoice" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <div class="pos-receipt-head">
                                <span class="pos-receipt-head__icon"><i class="tio-receipt-outlined"></i></span>
                                <span class="pos-receipt-head__text">
                                    <h5 class="modal-title">
                                        {{ translate('messages.Print invoice') }}
                                        <span class="pos-receipt-id">#{{ $order->id }}</span>
                                    </h5>
                                    <span class="pos-receipt-sub">
                                        {{ translate('messages.Receipt preview') }}
                                        <span>80 mm</span>
                                    </span>
                                </span>
                            </div>
                            <button type="button" class="close" data-dismiss="modal"
                                    aria-label="{{ translate('messages.Close') }}">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="pos-receipt-stage">
                                <div class="pos-receipt-paper" id="print-modal-content">
                                    @include('vendor-views.pos.invoice', ['show_actions' => false])
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <p class="pos-receipt-note">
                                <i class="tio-info-outined"></i>
                                {{ translate('messages.Make sure the thermal printer is ready.') }}
                            </p>
                            <div class="pos-receipt-actions">
                                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                                    <i class="tio-clear"></i> {{ translate('messages.Close') }}
                                </button>
                                <button type="button" class="btn btn--primary print-Div">
                                    <i class="tio-print"></i> {{ translate('messages.Print receipt') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="modal fade pos-modal" id="add-customer" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ translate('Add new customer') }}</h5>
                        <button type="button" class="close" data-dismiss="modal"
                                aria-label="{{ translate('messages.Close') }}">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form action="{{ route('vendor.pos.customer-store') }}" method="post" id="product_form">
                            @csrf
                            <div class="row g-2">
                                <div class="col-12 col-lg-6">
                                    <label for="f_name" class="input-label">
                                        {{ translate('First name') }}
                                        <span class="input-label-secondary text-danger">*</span>
                                    </label>
                                    <input id="f_name" type="text" name="f_name" class="form-control"
                                           value="{{ old('f_name') }}" placeholder="{{ translate('First name') }}" required>
                                </div>
                                <div class="col-12 col-lg-6">
                                    <label for="l_name" class="input-label">
                                        {{ translate('Last name') }}
                                        <span class="input-label-secondary text-danger">*</span>
                                    </label>
                                    <input id="l_name" type="text" name="l_name" class="form-control"
                                           value="{{ old('l_name') }}" placeholder="{{ translate('Last name') }}" required>
                                </div>
                                <div class="col-12 col-lg-6">
                                    <label for="email" class="input-label">
                                        {{ translate('email') }}
                                        <span class="input-label-secondary text-danger">*</span>
                                    </label>
                                    <input id="email" type="email" name="email" class="form-control"
                                           value="{{ old('email') }}"
                                           placeholder="{{ translate('Ex') . ' : ex@example.com' }}" required>
                                </div>
                                <div class="col-12 col-lg-6">
                                    <label for="phone" class="input-label">
                                        {{ translate('Phone') }} ({{ translate('With country code') }})
                                        <span class="input-label-secondary text-danger">*</span>
                                    </label>
                                    <input id="phone" type="tel" name="phone" class="form-control"
                                           value="{{ old('phone') }}" placeholder="{{ translate('Phone') }}" required>
                                </div>
                            </div>
                            <div class="btn--container justify-content-end mt-3">
                                <button type="reset" class="btn btn--reset"><i class="tio-refresh"></i> {{ translate('Reset') }}</button>
                                <button type="submit" id="submit_new_customer" class="btn btn--primary">
                                    <i class="tio-checkmark-circle-outlined"></i> {{ translate('Submit') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade pos-modal" id="insertPayableAmount" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ translate('messages.Payment') }}</h5>
                        <button type="button" class="close" data-dismiss="modal"
                                aria-label="{{ translate('messages.Close') }}">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form id="payable_store_amount">
                            @csrf
                            <input type="hidden" id="total_order_amount" value="0">
                            <label class="input-label" for="paid">
                                {{ translate('Amount') }}({{ \App\CentralLogics\Helpers::currency_symbol() }})
                            </label>
                            <input type="number" class="form-control" id="paid" name="paid" min="0" step="0.01" value="0">
                            <div class="btn--container justify-content-end mt-3">
                                <button class="btn btn--primary payable-amount" type="button">
                                    <i class="tio-checkmark-circle-outlined"></i> {{ translate('messages.Submit') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade pos-modal" id="add-discount" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ translate('messages.Update discount') }}</h5>
                        <button type="button" class="close" data-dismiss="modal"
                                aria-label="{{ translate('messages.Close') }}">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form action="{{ route('vendor.pos.discount') }}" method="post" class="row g-2">
                            @csrf
                            <div class="col-sm-6">
                                <label class="input-label" for="discount_input">{{ translate('Discount') }}</label>
                                <input type="number" class="form-control" name="discount" min="0"
                                       id="discount_input" value="0"
                                       max="1000000000">
                            </div>
                            <div class="col-sm-6">
                                <label class="input-label" for="discount_input_type">{{ translate('Type') }}</label>
                                <select name="type" class="form-control" id="discount_input_type">
                                    <option value="amount">
                                        {{ translate('Amount') }}({{ \App\CentralLogics\Helpers::currency_symbol() }})
                                    </option>
                                    <option value="percent">{{ translate('Percent') }}(%)</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <div class="btn--container justify-content-end mt-2">
                                    <button class="btn btn--primary" type="submit">
                                        <i class="tio-checkmark-circle-outlined"></i> {{ translate('messages.Submit') }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        @include('vendor-views.pos._delivery-address-modal', ['customer' => $customer])
    </div>
@endsection

@push('script_2')
    <script async
        src="https://maps.googleapis.com/maps/api/js?key={{ $map_api_key }}&libraries=places,marker&callback=initMap&loading=async&v=weekly">
    </script>

    @php($pos_cart_lang = [
        'title' => translate('Cart'),
        'confirm' => translate('OK'),
        'close' => translate('Close'),
        'minValue' => translate('messages.Sorry, the minimum value was reached'),
        'stockLimit' => translate('messages.Sorry, stock limit exceeded.'),
    ])
    <script>
        window.posCartLang = @json($pos_cart_lang);
    </script>

    <script src="{{ asset('public/assets/admin/js/view-pages/pos.js') }}"></script>
    <script src="{{ asset('public/assets/admin/js/views/delivery-type-selector.js') }}?v={{ @filemtime(public_path('assets/admin/js/views/delivery-type-selector.js')) ?: 1 }}"></script>

    <script>
        "use strict";

        $(document).on('click', '.place-order-submit', function (event) {
            event.preventDefault();

            let $btn = $(this);
            let customer_id = document.getElementById('customer');

            if ($btn.data('submitting')) {
                return;
            }
            $btn.data('submitting', true).prop('disabled', true);

            if (customer_id.value) {
                document.getElementById('customer_id').value = customer_id.value;
            }
            document.getElementById('order_place').submit();
        });

        $(document).on('hidden.bs.modal', '.modal', function () {
            if ($('.modal:visible').length) {
                $('body').addClass('modal-open');
            } else {
                $('.modal-backdrop').remove();
                $('body').removeClass('modal-open').css('padding-right', '');
            }
        });

        function togglePinLoading(isLoading) {
            let $btn = $('.delivery-Address-Store');
            if (!$btn.length) {
                return;
            }
            if (isLoading) {
                if (!$btn.data('original-html')) {
                    $btn.data('original-html', $btn.html());
                }
                $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>{{ translate('calculating') }}...');
            } else {
                $btn.prop('disabled', false).html($btn.data('original-html'));
            }
        }

        function initMap() {
            const mapId = "{{ $map_api_key }}"

            let map = new google.maps.Map(document.getElementById("map"), {
                zoom: 13,
                center: {
                    lat: {{ $store_data ? $store_data['latitude'] : '23.757989' }},
                    lng: {{ $store_data ? $store_data['longitude'] : '90.360587' }}
                },
                mapId: mapId
            });

            let zonePolygon = null;

            let infoWindow = new google.maps.InfoWindow();
            const geoErrorMessages = {
                geolocationFailed: "{{ translate('The geolocation service failed') }}",
                noGeolocationSupport: "{{ translate('Your browser doesn\'t support geolocation') }}",
            };

            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        let myLatlng = {
                            lat: position.coords.latitude,
                            lng: position.coords.longitude,
                        };
                        infoWindow.setPosition(myLatlng);
                        infoWindow.setContent("Location found.");
                        infoWindow.open(map);
                        map.setCenter(myLatlng);
                    },
                    () => {
                        handleLocationError(true, infoWindow, map.getCenter(), map, geoErrorMessages);
                    }
                );
            } else {
                handleLocationError(false, infoWindow, map.getCenter(), map, geoErrorMessages);
            }

            const bounds = new google.maps.LatLngBounds();
            posInitPlaceSearch({
                map: map,
                inputId: "pac-input",
                outOfCoverageMessage: '{{ translate('messages.Out of coverage') }}',
                getZonePolygon: () => zonePolygon,
                @if ($store_data)
                onLocationSelected: function (location, address) {
                    togglePinLoading(true);
                    posCalculateDeliveryDistance({
                        origins: [
                            { lat: {{ $store_data['latitude'] }}, lng: {{ $store_data['longitude'] }} },
                            "{{ $store_data->address }}"
                        ],
                        destinations: [
                            address,
                            { lat: location.lat(), lng: location.lng() }
                        ],
                        geocodedAddress: address,
                        extraChargeUrl: '{{ route('vendor.pos.extra_charge') }}',
                        currencySymbol: '{{ \App\CentralLogics\Helpers::currency_symbol() }}',
                        warningMessage: '{{ translate('Please pin a more precise location to calculate delivery fee') }}',
                        toggleLoading: togglePinLoading,
                    });
                },
                @endif
            });
            @if ($store_data)
                $.get({
                    url: '{{ url('/') }}/admin/zone/get-coordinates/{{ $store_data->zone_id }}',
                    dataType: 'json',
                    success: function (data) {
                        zonePolygon = new google.maps.Polygon({
                            paths: data.coordinates,
                            strokeColor: "#FF0000",
                            strokeOpacity: 0.8,
                            strokeWeight: 2,
                            fillColor: 'white',
                            fillOpacity: 0,
                        });
                        zonePolygon.setMap(map);
                        zonePolygon.getPaths().forEach(function (path) {
                            path.forEach(function (latlng) {
                                bounds.extend(latlng);
                                map.fitBounds(bounds);
                            });
                        });
                        map.setCenter(data.center);
                        google.maps.event.addListener(zonePolygon, 'click', function (mapsMouseEvent) {
                            infoWindow.close();
                            infoWindow = new google.maps.InfoWindow({
                                position: mapsMouseEvent.latLng,
                                content: JSON.stringify(mapsMouseEvent.latLng.toJSON(), null, 2),
                            });
                            let coordinates = JSON.parse(JSON.stringify(mapsMouseEvent.latLng.toJSON(), null, 2));

                            document.getElementById('latitude').value = coordinates['lat'];
                            document.getElementById('longitude').value = coordinates['lng'];
                            infoWindow.open(map);

                            let geocoder = new google.maps.Geocoder();
                            let latlng = new google.maps.LatLng(coordinates['lat'], coordinates['lng']);

                            togglePinLoading(true);

                            geocoder.geocode({ 'latLng': latlng }, function (results, status) {
                                if (status !== google.maps.GeocoderStatus.OK || !results[1]) {
                                    togglePinLoading(false);
                                    toastr.warning('{{ translate('Please pin a more precise location to calculate delivery fee') }}', {
                                        CloseButton: true,
                                        ProgressBar: true
                                    });
                                    return;
                                }

                                let address = results[1].formatted_address;
                                posCalculateDeliveryDistance({
                                    origins: [
                                        { lat: {{ $store_data['latitude'] }}, lng: {{ $store_data['longitude'] }} },
                                        "{{ $store_data->address }}"
                                    ],
                                    destinations: [
                                        address,
                                        { lat: coordinates['lat'], lng: coordinates['lng'] }
                                    ],
                                    geocodedAddress: address,
                                    extraChargeUrl: '{{ route('vendor.pos.extra_charge') }}',
                                    currencySymbol: '{{ \App\CentralLogics\Helpers::currency_symbol() }}',
                                    warningMessage: '{{ translate('Please pin a more precise location to calculate delivery fee') }}',
                                    toggleLoading: togglePinLoading,
                                });
                            });
                        });
                    },
                });
            @endif

            posInitCoveragePicker({
                coverageUrl: '{{ route('vendor.pos.delivery_coverage') }}',
                extraChargeUrl: '{{ route('vendor.pos.extra_charge') }}',
                currencySymbol: '{{ \App\CentralLogics\Helpers::currency_symbol() }}',
                areaLabel: '{{ translate('messages.Select Area') }}',
                zipLabel: '{{ translate('Select zip code') }}',
            });
        }

        function posApplySearch(keyword) {
            let nurl = new URL('{!! url()->full() !!}');
            if (keyword) {
                nurl.searchParams.set('search', keyword);
            } else {
                nurl.searchParams.delete('search');
            }
            nurl.searchParams.delete('page');
            location.href = nurl;
        }

        $('#search-form').on('submit', function (e) {
            e.preventDefault();
            posApplySearch($('#datatableSearch').val().trim());
        });

        $(document).on('input', '#datatableSearch', function () {
            $('#pos-search-clear').toggleClass('d-none', $(this).val().length === 0);
        });

        $(document).on('click', '#pos-search-clear', function () {
            $('#datatableSearch').val('').trigger('input').focus();
            if (new URLSearchParams(window.location.search).has('search')) {
                posApplySearch('');
            }
        });

        $(document).on('click', '.pos-reset-filters', function () {
            let nurl = new URL('{!! url()->full() !!}');
            nurl.searchParams.delete('search');
            nurl.searchParams.delete('category_id');
            nurl.searchParams.delete('page');
            location.href = nurl;
        });

        $(document).on('click', '.quick-View', function () {
            $.get({
                url: '{{ route('vendor.pos.quick-view') }}',
                dataType: 'json',
                data: {
                    product_id: $(this).data('id')
                },
                beforeSend: function () {
                    $('#loading').show();
                },
                success: function (data) {
                    $('#quick-view-modal').empty().html(data.view);
                    $('#quick-view').modal('show');
                },
                error: function () {
                    toastr.error('{{ translate('messages.Something went wrong') }}', { CloseButton: true, ProgressBar: true });
                },
                complete: function () {
                    $('#loading').hide();
                },
            });
        });

        $(document).on('click', '.quick-View-Cart-Item', function () {
            $.get({
                url: '{{ route('vendor.pos.quick-view-cart-item') }}',
                dataType: 'json',
                data: {
                    product_id: $(this).data('product-id'),
                    item_key: $(this).data('item-key'),
                },
                beforeSend: function () {
                    $('#loading').show();
                },
                success: function (data) {
                    if (data.success === 0) {
                        toastr.error(data.message, { CloseButton: true, ProgressBar: true });
                        updateCart();
                        return;
                    }
                    $('#quick-view').modal('show');
                    $('#quick-view-modal').empty().html(data.view);
                },
                complete: function () {
                    $('#loading').hide();
                },
            });
        });

        function checkAddToCartValidity() {
            let names = {};
            $('#add-to-cart-form input:radio').each(function () {
                names[$(this).attr('name')] = true;
            });
            let count = 0;
            $.each(names, function () {
                count++;
            });
            if ($('input:radio:checked').length === count) {
                return true;
            }
            return true;
        }

        function getVariantPrice() {
            if ($('#add-to-cart-form input[name=quantity]').val() > 0 && checkAddToCartValidity()) {
                $.ajaxSetup({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content')
                    }
                });
                $.ajax({
                    type: "POST",
                    url: '{{ route('vendor.pos.variant_price') }}',
                    data: $('#add-to-cart-form').serializeArray(),
                    success: function (data) {
                        if (data.error === 'quantity_error') {
                            toastr.error(data.message);
                        } else {
                            $('#add-to-cart-form #chosen_price_div').removeClass('d-none');
                            $('#add-to-cart-form #chosen_price_div #chosen_price').html(data.price);
                        }
                    }
                });
            }
        }

        $(document).on('click', '.check-stock', function () {
            check_stock();
        });

        function check_stock() {
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content')
                }
            });
            $.post({
                url: '{{ route('vendor.pos.item_stock_view') }}',
                data: $('#add-to-cart-form').serializeArray(),
                beforeSend: function () {
                    $('#loading').show();
                },
                success: function (data) {
                    $('#add-to-cart-form input[name=quantity]').empty()
                    $('#quick-view').modal('show');
                    $('#quick-view-modal').empty().html(data.view);
                },
                error: function () {
                    toastr.error('{{ translate('messages.Something went wrong') }}', { CloseButton: true, ProgressBar: true });
                },
                complete: function () {
                    $('#loading').hide();
                }
            });
        }

        $(document).on('click', '.item-stock-view-update', function () {
            item_stock_view_update();
        });

        function item_stock_view_update() {
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content')
                }
            });
            $.post({
                url: '{{ route('vendor.pos.item_stock_view_update') }}',
                data: $('#add-to-cart-form').serializeArray(),
                success: function (data) {
                    if (data.success === 0) {
                        toastr.error(data.message, { CloseButton: true, ProgressBar: true });
                        updateCart();
                        return;
                    }
                    $('#quick-view').modal('show');
                    $('#quick-view-modal').empty().html(data.view);
                },
                error: function () {
                    toastr.error('{{ translate('messages.Something went wrong') }}', { CloseButton: true, ProgressBar: true });
                },
                complete: function () {
                    $('#loading').hide();
                }
            });
        }

        $(document).on('click', '.add-To-Cart', function () {
            let $btn = $(this);

            if ($btn.data('submitting')) {
                return;
            }
            $btn.data('submitting', true).prop('disabled', true);

            if (!checkAddToCartValidity()) {
                $btn.removeData('submitting').prop('disabled', false);
                posCartAlert('info', '{{ translate('Please choose all the options') }}');
                return;
            }

            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content')
                }
            });
            $.post({
                url: '{{ route('vendor.pos.add-to-cart') }}',
                data: $('#add-to-cart-form').serializeArray(),
                beforeSend: function () {
                    $('#loading').show();
                },
                success: function (data) {
                    if (data.data === 1) {
                        posCartAlert('info', "{{ translate('messages.Product already added in cart') }}");
                        return false;
                    } else if (data.data === 2) {
                        updateCart();
                        posCartAlert('info', "{{ translate('messages.Product has been updated in cart') }}");
                        return false;
                    } else if (data.data === 0) {
                        posCartAlert('error', '{{ translate('messages.Sorry, product out of stock') }}');
                        return false;
                    } else if (data.data === -1) {
                        posCartAlert('error', '{{ translate('messages.Sorry, you cannot add multiple stores data in same cart') }}');
                        return false;
                    } else if (data.data === 'letiation_error' || data.data === 'variation_error') {
                        posCartAlert('error', data.message);
                        return false;
                    }
                    $('.call-when-done').click();

                    toastr.success('{{ translate('messages.Product has been added in cart') }}', {
                        CloseButton: true,
                        ProgressBar: true
                    });

                    updateCart();
                },
                error: function () {
                    toastr.error('{{ translate('messages.Something went wrong') }}', { CloseButton: true, ProgressBar: true });
                },
                complete: function () {
                    $('#loading').hide();
                    $btn.removeData('submitting').prop('disabled', false);
                }
            });
        });

        $(document).on('click', '.delivery-Address-Store', function () {
            if (posCoverageSelectionMissing()) {
                toastr.error(
                    '{{ translate('messages.Please select') }} ' + $('#coverage_picker_label_text').text(),
                    { CloseButton: true, ProgressBar: true }
                );
                return;
            }

            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content')
                }
            });
            $.post({
                url: '{{ route('vendor.pos.add-delivery-info') }}',
                data: $('#delivery_address_store').serializeArray(),
                beforeSend: function () {
                    $('#loading').show();
                },
                success: function (data) {
                    if (data.errors) {
                        for (let i = 0; i < data.errors.length; i++) {
                            toastr.error(data.errors[i].message, {
                                CloseButton: true,
                                ProgressBar: true
                            });
                        }
                    } else {
                        $('#del-add').empty().html(data.view);
                        $('#deliveryAddrModal').modal('hide');
                    }
                    updateCart();
                    $('.call-when-done').click();
                },
                complete: function () {
                    $('#loading').hide();
                }
            });
        });

        $(document).on('click', '.remove-From-Cart', function () {
            let key = $(this).data('product-id');
            $.post('{{ route('vendor.pos.remove-from-cart') }}', {
                _token: '{{ csrf_token() }}',
                key: key
            }, function (data) {
                if (data.errors) {
                    for (let i = 0; i < data.errors.length; i++) {
                        toastr.error(data.errors[i].message, {
                            CloseButton: true,
                            ProgressBar: true
                        });
                    }
                } else {
                    updateCart();
                    toastr.info('{{ translate('messages.Item has been removed from cart') }}', {
                        CloseButton: true,
                        ProgressBar: true
                    });
                }
            });
        });

        $(document).on('click', '.empty-Cart', function () {
            $.post('{{ route('vendor.pos.emptyCart') }}', {
                _token: '{{ csrf_token() }}'
            }, function () {
                $('#del-add').empty();
                $('#customer_id').val('');
                $('#customer_data').addClass('d-none');
                $('#customer').val(null).trigger('change');
                updateCart();
                toastr.info('{{ translate('messages.Item has been removed from cart') }}', {
                    CloseButton: true,
                    ProgressBar: true
                });
            });
        });

        $(document).on('click', '.payable-amount', function (event) {
            if ($('#paid').val() < 0) {
                toastr.error('{{ translate('Amount must be greater than zero') }}', {
                    CloseButton: true,
                    ProgressBar: true
                });
                event.preventDefault();
                return;
            }
            if (Number($('#paid').val()) < Number($('#total_order_amount').val())) {
                toastr.error('{{ translate('This amount must be greater than order amount') }}', {
                    CloseButton: true,
                    ProgressBar: true
                });
                event.preventDefault();
                return;
            }

            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content')
                }
            });
            $.post({
                url: '{{ route('vendor.pos.paid') }}',
                data: $('#payable_store_amount').serializeArray(),
                beforeSend: function () {
                    $('#loading').show();
                },
                success: function () {
                    updateCart();
                    $('.call-when-done').click();
                },
                complete: function () {
                    $('#loading').hide();
                    $('#insertPayableAmount').modal('hide');
                }
            });
        });

        document.addEventListener('delivery-type:changed', function () { updateCart(); });

        function syncDeliveryTypeFromCart() {
            if (window.deliveryTypeSelector && typeof window.deliveryTypeSelector.syncFromCart === 'function') {
                window.deliveryTypeSelector.syncFromCart();
            }
        }

        function posSyncCounts() {
            let items = parseInt($('#cart_item_count').val() || '0', 10);
            $('#pos-cart-count').text(items + ' {{ translate('messages.Items') }}');

            let total = $('#pos_product_total').val();
            if (typeof total !== 'undefined') {
                $('#pos-product-count').text(total + ' {{ translate('messages.Items') }}');
            }

            let payable = $('#cart_payable').val();
            if (typeof payable !== 'undefined') {
                $('#pos-payable').text(payable);
            }
            $('.pos-actions .empty-Cart, .pos-actions .place-order-submit').prop('disabled', items === 0);

            $('#total_order_amount').val($('#cart_total_amount').val() || 0);
            $('#paid').val($('#cart_paid_amount').val() || 0);
            $('#discount_input').val($('#cart_extra_discount').val() || 0);
            $('#discount_input_type').val($('#cart_extra_discount_type').val() || 'amount');

            $('[data-toggle="tooltip"]').tooltip();
        }

        let posCatalogPageSize = 10;
        let posCatalogLoading = false;

        function posCatalogParams(extra) {
            let params = new URLSearchParams(window.location.search);
            params.delete('page');
            let data = { _token: '{{ csrf_token() }}' };
            params.forEach(function (value, key) { data[key] = value; });
            return $.extend(data, extra || {});
        }

        function posCatalogLoaded() {
            return $('#single-list .pos-card').length;
        }

        function posCatalogHasMore() {
            return $('#pos-scroll-more').attr('data-has-more') === '1';
        }

        function posLoadMoreProducts() {
            if (posCatalogLoading || !posCatalogHasMore()) {
                return;
            }
            posCatalogLoading = true;

            $.post('{{ route('vendor.pos.single_items') }}',
                posCatalogParams({
                    page: Math.floor(posCatalogLoaded() / posCatalogPageSize) + 1,
                    per_page: posCatalogPageSize
                }),
                function (data) {
                    let $incoming = $('<div>').html(data);
                    let $cards = $incoming.find('#single-list').children();

                    $('#single-list').append($cards);
                    $('#pos_product_total').val($incoming.find('#pos_product_total').val());
                    $('#pos-scroll-more').attr(
                        'data-has-more',
                        $cards.length ? ($incoming.find('#pos-scroll-more').attr('data-has-more') || '0') : '0'
                    );
                    posSyncCounts();
                }
            ).always(function () {
                posCatalogLoading = false;
                posFillCatalog();
            });
        }

        function posFillCatalog() {
            let panel = document.getElementById('pos-products');

            if (panel && posCatalogHasMore() && panel.scrollHeight - panel.scrollTop - panel.clientHeight <= 160) {
                posLoadMoreProducts();
            }
        }

        $('#pos-products').on('scroll', posFillCatalog);

        function updateCart() {
            $.post('{{ route('vendor.pos.cart_items') }}', { _token: '{{ csrf_token() }}' }, function (data) {
                $('#cart').empty().html(data);
                syncDeliveryTypeFromCart();
                posSyncCounts();
            });

            let panel = $('#pos-products');
            let offset = panel.scrollTop();

            $.post('{{ route('vendor.pos.single_items') }}',
                posCatalogParams({
                    page: 1,
                    per_page: Math.max(posCatalogLoaded(), posCatalogPageSize)
                }),
                function (data) {
                    panel.empty().html(data).scrollTop(offset);
                    posSyncCounts();
                });
        }

        $(function () {
            syncDeliveryTypeFromCart();
            posSyncCounts();
            posFillCatalog();
            $(document).on('click', 'input[type=number]', function () { this.select(); });
            @if ($order)
                $('#print-invoice').modal('show');
            @endif
        });

        $(document).on('click', '.pos-qty-step', function () {
            let $input = $(this).closest('.pos-qty').find('.update-Quantity');
            let step = parseInt($(this).data('step'), 10);
            let next = (parseInt($input.val(), 10) || 0) + step;
            let min = parseInt($input.attr('min'), 10);
            let max = parseInt($input.attr('max'), 10);

            if (next < min || next > max) {
                return;
            }
            $input.val(next).trigger('change');
        });

        $(document).on('change', '.update-Quantity', function (event) {
            let element = $(event.target);
            let minValue = parseInt(element.attr('min'));
            let maxValue = parseInt(element.attr('max'));
            let valueCurrent = parseInt(element.val());
            let key = element.data('key');

            if (valueCurrent >= minValue && valueCurrent <= maxValue) {
                $.post('{{ route('vendor.pos.updateQuantity') }}', {
                    _token: '{{ csrf_token() }}',
                    key: key,
                    quantity: valueCurrent
                }, function () {
                    updateCart();
                });
            } else if (valueCurrent > maxValue) {
                posCartAlert('error', '{{ translate('messages.Sorry, cart limit exceeded.') }}');
                element.val(element.data('oldvalue'));
            } else {
                posCartAlert('error', '{{ translate('Sorry, the minimum value was reached') }}');
                element.val(element.data('oldvalue'));
            }
        });

        $('#customer').select2({
            ajax: {
                url: '{{ route('vendor.pos.customers') }}',
                data: function (params) {
                    return {
                        q: params.term,
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

        $(document).on('change', '#customer', function () {
            let customerId = $(this).val();

            $('#del-add').empty();

            if (!customerId) {
                document.getElementById('contact_person_name').value = '';
                document.getElementById('contact_person_number').value = '';
                $('#customer_id').val('');
                $('#customer_data').addClass('d-none');
                updateCart();
                return;
            }

            $.get({
                url: '{{ route('vendor.pos.getUserData') }}',
                dataType: 'json',
                data: { customer_id: customerId },
                beforeSend: function () {
                    $('#loading').show();
                },
                success: function (data) {
                    $('#customer_id').val(customerId);
                    $('#customer_name').text(data.customer_name);
                    $('#customer_phone').text(data.customer_phone);
                    $('#customer_wallet').text(data.customer_wallet);
                    $('#customer_image').attr('src', data.customer_image);
                    $('#customer_data').removeClass('d-none');
                    document.getElementById('contact_person_name').value = data.customer_name || '';
                    document.getElementById('contact_person_number').value = data.customer_phone || '';
                    if (data.view) {
                        $('#del-add').empty().html(data.view);
                    }
                    updateCart();
                },
                complete: function () {
                    $('#loading').hide();
                },
            });
        });

        $(document).on('click', '#delivery_address', function () {
            if (!$('.iti').length || $('.iti').length == 1) {
                initTelInputs();
            }
        });
    </script>
@endpush
