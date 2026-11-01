@extends('layouts.admin.app')

@section('title', $store->name)

@push('css_or_js')
    <link href="{{ asset('public/assets/admin/css/croppie.css') }}" rel="stylesheet">
@endpush

@section('content')
    <div class="content container-fluid">
        @php
            $verified_seller_badge = \App\CentralLogics\Helpers::get_business_settings('verified_seller_badge');
        @endphp
        @include('admin-views.vendor.view.partials._header', ['store' => $store])


        @if (isset($store->vendor->status) && $store->vendor->status == 1)
            @php
                $reviewsInfo = $store->reviews()->where('reviews.status', 1)->selectRaw('AVG(reviews.rating) as average_rating, COUNT(reviews.id) as total_reviews')->first();
                $average_rating = round((float) ($reviewsInfo->average_rating ?? 0), 1);
                $review_count = (int) ($reviewsInfo->total_reviews ?? 0);
                $order_stats = $store->orders()->StoreOrder()->selectRaw("
                COUNT(*) as total_orders,
                SUM(CASE WHEN order_status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders,
                SUM(CASE WHEN order_status = 'canceled' THEN 1 ELSE 0 END) as canceled_orders
            ")->first();
                $total_orders = (int) ($order_stats->total_orders ?? 0);
                $delivered_orders = (int) ($order_stats->delivered_orders ?? 0);
                $canceled_orders = (int) ($order_stats->canceled_orders ?? 0);
                $comparison_orders = $delivered_orders + $canceled_orders;
                $performance_rate = $average_rating > 2 && $comparison_orders > 0 ? round(($delivered_orders / $comparison_orders) * 100) : null;
            @endphp
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title m-0 d-flex align-items-center">
                        <span class="ml-1">{{ translate('Store information') }}</span>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="taxi-banner taxi-banner2 radius-10 mb-20"
                        style="background-image: url('{{ $store->cover_photo_full_url }}'); background-repeat: no-repeat; background-position: center; background-size: cover;">
                        <div class="taxi-info-wrapper d-flex flex-wrap flex-sm-nowrap gap-30px">
                            <div class="logo bg-white rounded-8 h-135px ratio--1 p-10px">
                                <img   src="{{ $store->logo_full_url ?? asset('public/assets/admin/img/100x100/1.png') }}" width="150" class="rounded-8"
                                    alt="">
                            </div>
                            <div class="taxi-info flex-grow-1">
                                <div class="info-area mb-20">
                                    <h3 class="fs-20 mb-0 fw-bold text--title d-flex align-items-center gap-2">
                                        {{ $store->name }}
                                        @include('partials._verified_store_badge', ['store' => $store])
                                    </h3>
                                    <span class="fs-12 lh--12 text-8797AB">{{ translate('Created at') }} {{ \App\CentralLogics\Helpers::date_format($store->created_at) }}</span>
                                </div>
                                <div class="details row g-xl-4 g-3 justify-content-between">
                                    <div class="col-sm-6 col-lg-4 col-xxl-3">
                                        <div class="details-single d-flex align-items-center gap-2">
                                            <img src="{{ asset('public/assets/admin/img/icons/s-zone.png') }}" width="36" height="36"
                                                class="rounded" alt="">
                                            <div>
                                                <h5 class="lh--12 mb-2px color-3C3C3C">
                                                     {{ translate('messages.Zone') }}
                                                </h5>
                                                <span class="fs-13 lh--12 color-484848 opacity-70 d-block">
                                                    {{ $store?->zone?->name ?? translate('Zone deleted') }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-lg-4 col-xxl-3">
                                        <div class="details-single d-flex align-items-center gap-2">
                                            <img src="{{ asset('public/assets/admin/img/icons/s-phone.png') }}" width="36" height="36"
                                                class="rounded" alt="">
                                            <div>
                                                <h5 class="lh--12 mb-2px color-3C3C3C">
                                                     {{ translate('messages.Phone') }}
                                                </h5>
                                                <span class="fs-13 lh--12 color-484848 opacity-70 d-block">
                                                    <a href="tel:+{{ $store->phone }}">{{ $store->phone }}</a>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-lg-4 col-xxl-3">
                                        <div class="details-single d-flex align-items-center gap-2">
                                            <img src="{{ asset('public/assets/admin/img/icons/s-email.png') }}" width="36" height="36"
                                                class="rounded" alt="">
                                            <div>
                                                <h5 class="lh--12 mb-2px color-3C3C3C">
                                                     {{ translate('messages.Email') }}
                                                </h5>
                                                <span class="fs-13 lh--12 text-break color-484848 opacity-70 d-block">

                                                    <a href="mailto:{{ $store->email }}" target="_blank">{{ $store->email }}</a>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-lg-4 col-xxl-3">
                                        <div class="details-single d-flex align-items-center gap-2">
                                            <img src="{{ asset('public/assets/admin/img/icons/s-address.png') }}" width="36" height="36"
                                                class="rounded" alt="">
                                            <div>
                                                <h5 class="lh--12 mb-2px color-3C3C3C">
                                                     {{ translate('messages.Address') }}
                                                </h5>
                                                <span class="fs-13 lh--12 color-primary d-block">
                                                        <a href="https://www.google.com/maps/search/?api=1&query={{ data_get($store, 'latitude', 0) }},{{ data_get($store, 'longitude', 0) }}"
                                                            target="_blank">{{ $store->address }}</a>

                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <h4 class="fs-16 fw-700 text-title mb-3">
                        {{ translate('messages.Performance Evaluation') }}
                    </h4>
                    <div class="row g-xl-4 g-3">
                        <div class="col-sm-6 col-lg-3">
                            <div class="resturant-card g-100 bg--secondary p-3 d-flex align-items-center gap-1 justify-content-between">
                                <p class="fs-14 mb-0 color-22232466">
                                    {{ translate('messages.ratting') }}
                                </p>
                                <h4 class="fs-16 fw-700 text-title mb-0">
                                    {{ $average_rating }}/5
                                </h4>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="resturant-card g-100 card--bg-2 p-3 d-flex align-items-center gap-1 justify-content-between">
                                <p class="fs-14 mb-0 color-22232466">
                                    {{ translate('Total order') }}
                                </p>
                                <h4 class="fs-16 fw-700 text-warning mb-0">
                                    {{ $total_orders }}
                                </h4>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="resturant-card g-100 card--bg-3 p-3 d-flex align-items-center gap-1 justify-content-between">
                                <p class="fs-14 mb-0 color-22232466">
                                    {{ translate('messages.Delivered') }}
                                </p>
                                <h4 class="fs-16 fw-700 text-success mb-0">
                                    {{ $delivered_orders }}
                                </h4>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="resturant-card g-100 card--bg-4 p-3 d-flex align-items-center gap-1 justify-content-between">
                                <p class="fs-14 mb-0 color-22232466">
                                    {{ translate('messages.Cancel') }}
                                </p>
                                <h4 class="fs-16 fw-700 text-danger mb-0">
                                    {{ $canceled_orders }}
                                </h4>
                            </div>
                        </div>
                    </div>
                    <div class="info-notes-bg px-3 py-2 rounded fz-11  gap-2 align-items-center d-flex mt-20">
                        <img src="{{asset('public/assets/admin/img/info-idea.svg')}}" alt="">
                        <span>

                            @if (!is_null($performance_rate))
                                {{translate('This store’s performance is rated as')}}
                                <span class="fz-12px font-semibold {{ $performance_rate >= 80 ? 'text-success' : ($performance_rate >= 50 ? 'text-warning' : 'text-danger') }}">
                                    <a class="{{ $performance_rate >= 80 ? 'text-success' : ($performance_rate >= 50 ? 'text-warning' : 'text-danger') }}" href="#0">
                                        {{ $performance_rate >= 80 ? translate('Good') : ($performance_rate >= 50 ? translate('Average') : translate('Needs improvement')) }}
                                    </a>
                                </span>
                                {{translate('Based on its overall activity, reliability, and service quality.')}}
                            @else
                                {{ translate('This store does not have much available data.') }}
                            @endif
                        </span>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title m-0 d-flex align-items-center">
                        <span class="ml-1">{{ translate('messages.wallet info') }}</span>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3 text-capitalize">
                        <div class="col-md-4">
                            <div class="card h-100 border-0 bg-icon-primary rounded">
                                <div class="card-body pb-0 text-center d-flex flex-column justify-content-center align-items-center">
                                    <div class="d-flex align-items-center mb-2 justify-content-center">
                                        <h2 class="cash--title text-white">
                                            {{ \App\CentralLogics\Helpers::format_currency($wallet->collected_cash) }}</h2>
                                    </div>
                                    <p class="fs-14 text-title mb-20 text-white">
                                        {{ translate('messages.Collected cash by store') }}
                                    </p>
                                    <div class="d-flex text-center justify-content-center pt-0 bg-transparent border-0">
                                        <button class="btn px-4 btn-primary text-white text-capitalize h--45px fs-14" id="collect_cash"
                                            type="button" data-toggle="modal" data-target="#collect-cash"
                                            title="Collect Cash"><i class="tio-money"></i> {{ translate('messages.Collect cash from store') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <div class="resturant-card bg--secondary">
                                        <h4 class="title text-info">
                                            {{ \App\CentralLogics\Helpers::format_currency($wallet->pending_withdraw) }}</h4>
                                        <div class="subtitle">{{ translate('messages.Pending withdraw') }}</div>
                                        <div class="resturant-icon w-45px max-w-1000 mb-20 h-45px bg-white rounded-circle d-center min-w-45px">
                                            <img class="" width="20" height="20"
                                                src="{{ asset('public/assets/admin/img/transactions/pending.png') }}" alt="transaction">
                                        </div>
                                    </div>
                                </div>

                                <div class="col-sm-6">
                                    <div class="resturant-card bg--secondary">
                                        <h4 class="title text-success">
                                            {{ \App\CentralLogics\Helpers::format_currency($wallet->total_withdrawn) }}</h4>
                                        <div class="subtitle">{{ translate('messages.Total withdrawal amount') }}</div>
                                        <div class="resturant-icon w-45px max-w-1000 mb-20 h-45px bg-white rounded-circle d-center min-w-45px">
                                            <img class="" width="20" height="20"
                                                src="{{ asset('public/assets/admin/img/transactions/withdraw-amount.png') }}"
                                                alt="transaction">
                                        </div>
                                    </div>
                                </div>

                                <div class="col-sm-6">
                                    <div class="resturant-card bg--secondary">
                                        <h4 class="title text-danger">
                                            {{ \App\CentralLogics\Helpers::format_currency($wallet->balance > 0 ? $wallet->balance : 0) }}
                                        </h4>
                                        <div class="subtitle">{{ translate('Withdraw able balance') }}</div>
                                        <div class="resturant-icon w-45px max-w-1000 h-45px bg-white rounded-circle d-center min-w-45px">
                                            <img class="" width="20" height="20"
                                                src="{{ asset('public/assets/admin/img/transactions/withdraw-balance.png') }}"
                                                alt="transaction">
                                        </div>
                                    </div>
                                </div>

                                <div class="col-sm-6">
                                    <div class="resturant-card bg--secondary">
                                        <h4 class="title text-warning">
                                            {{ \App\CentralLogics\Helpers::format_currency($wallet->total_earning) }}</h4>
                                        <div class="subtitle">{{ translate('messages.Total earning') }}</div>
                                        <div class="resturant-icon w-45px max-w-1000  h-45px bg-white rounded-circle d-center min-w-45px">
                                            <img class="" width="20" height="20"
                                                src="{{ asset('public/assets/admin/img/transactions/earning.png') }}"
                                                alt="transaction">
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title m-0 d-flex align-items-center">
                        <span class="card-header-icon mr-2">
                            <i class="tio-shop-outlined"></i>
                        </span>
                        <span class="ml-1">{{ translate('Store information') }}</span>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3 align-items-center">
                        <div class="col-lg-6">
                            <div class="resturant--info-address">
                                <div class="logo">
                                    <img class="onerror-image"
                                        data-onerror-image="{{ asset('public/assets/admin/img/100x100/1.png') }}"
                                        src="{{ $store->logo_full_url ?? asset('public/assets/admin/img/100x100/1.png') }}"
                                        alt="{{ $store->name }} Logo">
                                </div>
                                <ul class="address-info list-unstyled list-unstyled-py-3 text-dark">
                                    <li>
                                        <h5 class="name">{{ $store->name }}</h5>
                                    </li>
                                    <li>

                                        <i class="tio-city nav-icon"></i>
                                        <span>{{ translate('messages.Address') }}</span> <span>:</span> &nbsp; <span>

                                            <a href="https://www.google.com/maps/search/?api=1&query={{ data_get($store, 'latitude', 0) }},{{ data_get($store, 'longitude', 0) }}"
                                                target="_blank">{{ $store->address }}</a></span>

                                    </li>

                                    <li>
                                        <i class="tio-email nav-icon"></i>
                                        <span>{{ translate('messages.email') }}</span> <span>:</span> &nbsp; <a
                                            href="mailto:{{ $store->email }}"><span>{{ $store->email }}</span></a>
                                    </li>
                                    <li>
                                        <i class="tio-call-talking  nav-icon"></i>
                                        <span>{{ translate('Phone') }}</span> <span>:</span> &nbsp; <a
                                            href="tel:{{ $store->phone }}"><span>{{ $store->phone }}</span></a>
                                    </li>
                                    <li>
                                        <i class="tio-map nav-icon"></i>
                                        <span>{{ translate('messages.Zone') }}</span> <span>:</span> &nbsp;
                                        <span>{{ $store?->zone?->name ?? translate('Zone deleted') }}</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div id="map" class="single-page-map"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row pt-3 g-3">
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title m-0 d-flex align-items-center">
                                <span class="ml-1">{{ translate('Owner information') }}</span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="resturant--info-address">
                                <div class="avatar avatar-xxl avatar-circle avatar-border-lg">
                                    <img class="avatar-img onerror-image"
                                        data-onerror-image="{{ asset('public/assets/admin/img/160x160/img1.jpg') }}"
                                        src="{{ $store->vendor->image_full_url ?? asset('public/assets/admin/img/160x160/img1.jpg') }}"
                                        alt="Image Description">
                                </div>
                                <ul class="address-info address-info-2 list-unstyled list-unstyled-py-3 text-dark">
                                    <li>
                                        <h5 class="name">{{ $store->vendor->f_name }} {{ $store->vendor->l_name }}</h5>
                                    </li>
                                    <li>
                                        <i class="tio-email nav-icon"></i>
                                        <span class="pl-1"><a
                                                href="mailto:{{ $store->vendor->email }}">{{ $store->vendor->email }}</a>
                                        </span>
                                    </li>
                                    <li>
                                        <i class="tio-call-talking nav-icon"></i>
                                        <span class="pl-1"> <a href="tel:{{ $store->vendor->phone }}">
                                                {{ $store->vendor->phone }} </a></span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title m-0 d-flex align-items-center">
                                <span class="ml-1">{{ translate('Business plan') }}</span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="resturant--info-address">
                                <ul class="address-info address-info-2 p-0 list-unstyled list-unstyled-py-3 text-dark">

                                    @if ($store->store_business_model == 'commission')
                                        <li>
                                            <span> <strong>{{ translate('Business plan') }}</span></strong>
                                            <span>:</span> &nbsp; {{ translate($store->store_business_model) }}
                                        </li>
                                        @php($admin_commission = \App\CentralLogics\Helpers::get_business_settings('admin_commission', false))
                                        <li>
                                            <span><strong>{{ translate('messages.Commission percentage') }}</strong></span>
                                            <span>:</span> &nbsp;
                                            {{ $store->comission ?? $admin_commission }} %
                                        </li>
                                    @elseif ($store->store_business_model == 'subscription')
                                        <li>
                                            <span> <strong>{{ translate('Business plan') }}</span></strong>
                                            <span>:</span> &nbsp; {{ translate($store->store_business_model) }} &nbsp;
                                            @if ($store?->store_sub_update_application->is_trial == '1')
                                                <small> <span
                                                        class="badge badge-info">{{ translate('messages.Free trial') }}</span>
                                                </small>
                                            @endif
                                        </li>
                                        <li>
                                            <span> <strong>{{ translate('Package name') }}</strong></span>
                                            <span>:</span> &nbsp;
                                            {{ $store?->store_sub_update_application?->package?->package_name ?? translate('No data found') }}
                                        </li>
                                    @elseif ($store->store_business_model == 'unsubscribed')
                                        <li>
                                            <span> <strong>{{ translate('Business plan') }}</span></strong>
                                            <span>:</span> &nbsp; {{ translate($store->store_business_model) }} &nbsp;

                                            <small> <span
                                                    class="badge badge-danger">{{ translate('messages.Expired') }}</span>
                                            </small>

                                        </li>
                                        <li>
                                            <span> <strong>{{ translate('Package name') }}</strong></span>
                                            <span>:</span> &nbsp;
                                            {{ $store?->store_sub_update_application?->package?->package_name ?? translate('No data found') }}
                                        </li>
                                    @elseif($store->store_business_model == 'none' && $store->package_id)
                                        <li>
                                            <span> <strong>{{ translate('Business plan') }}</span></strong>
                                            <span>:</span> &nbsp; {{ translate('messages.Subscription') }}
                                        </li>
                                        <li>
                                            <span> <strong>{{ translate('Package name') }}</span></strong>
                                            <span>:</span> &nbsp;
                                            {{ $store?->package?->package_name }}
                                        </li>
                                        <li>
                                            <span> <strong>{{ translate('Payment status') }}</span></strong> <span>:</span>
                                            &nbsp; {{ translate('Payment failed') }}
                                        </li>
                                    @else
                                        <li>
                                            <span> <strong>{{ translate('Business plan') }}</span></strong>
                                            <span>:</span> &nbsp; {{ translate('Haven\'t selected yet.') }}
                                        </li>
                                    @endif




                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @if ($store->tin)
                <div class="row pt-3 g-3">
                    <div class="col-12">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="card-title m-0 d-flex align-items-center">
                                    <span class="card-header-icon mr-2">
                                        <i class="tio-user"></i>
                                    </span>
                                    <span class="ml-1">{{ translate('Business TIN') }}</span>
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="resturant--info-address flex-sm-nowrap flex-wrap gap-2">
                                    <div class="pdf-single  cus-document-responsive"
                                        data-pdf-url="{{ $store->tin_certificate_image_full_url ?? asset('public/assets/admin/img/upload-cloud.png') }}">
                                        <div class="pdf-frame">
                                            @php($imgPath = $store->tin_certificate_image_full_url ?? asset('public/assets/admin/img/upload-cloud.png'))
                                            @if (Str::endsWith($imgPath, ['.pdf', '.doc', '.docx']))
                                                @php($imgPath = asset('public/assets/admin/img/document.svg'))
                                            @endif
                                            <img class="pdf-thumbnail-alt" src="{{ $imgPath }}"
                                                alt="File Thumbnail">
                                        </div>
                                        <div class="overlay">
                                            <a href="javascript:void(0);" class="download-btn" title="">
                                                <i class="tio-download-to"></i>
                                            </a>
                                            <div class="pdf-info d-flex gap-10px align-items-center">
                                                @if (Str::endsWith($imgPath, ['.pdf', '.doc', '.docx']))
                                                    <img src="{{ asset('public/assets/admin/img/document.svg') }}"
                                                        width="34" alt="File Type Logo">
                                                @else
                                                    <img src="{{ asset('public/assets/admin/img/picture.svg') }}"
                                                        width="34" alt="File Type Logo">
                                                @endif
                                                <div class="fs-13 text--title d-flex flex-column">
                                                    <span class="file-name js-filename-truncate"></span>
                                                    <span
                                                        class="opacity-50">{{ translate('Click to view the file') }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div
                                        class="d-flex-column address-info address-info-2 list-unstyled list-unstyled-py-3">

                                        <div class=" d-flex justify-content-start gap-1">
                                            <span class="text-custom-nowrap text-wrap"><strong class=" text-dark">
                                                    {{ translate('Taxpayer identification Number(TIN)') }}:
                                                </strong></span>
                                            <span class="pl-1">{{ $store->tin }}</span>
                                        </div>

                                        <div class=" d-flex justify-content-start gap-1">
                                            <span class="text-custom-nowrap text-wrap"><strong
                                                    class=" text-dark">{{ translate('Expire date') }}: </strong></span>
                                            <span class="pl-1">{{ $store->tin_expire_date }}</span>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        @else
            <div class="store-details-banner mb-1 position-relative rounded-10">
            @if (isset($store->vendor->rejection_note))
            <div id="info_notes" class="bg--12 px-2 py-2 mb-4 rounded fz-11  gap-2 align-items-center d-flex ">
                    <span  class="mr-2">
                        {{$store->vendor->rejection_note }}
                    </span>
                </div>

            @endif
                <div class="banner overflow-hidden rounded-10">
                    <img src="{{ $store->cover_photo_full_url ?? asset('public/assets/admin/img/100x100/1.png') }}" alt="banner"
                        class="w-100">
                </div>
                <div class="store-details__inner overflow-hidden d-flex flex-sm-nowrap flex-wrap align-items-sm-end">
                    <div class="store-logo rounded-8 overflow-hidden h-150 ratio--1">
                        <img src="{{ $store->logo_full_url }}" alt="banner" class="w-100">
                    </div>
                    <div class="flex-grow-1">
                        <h2 class="mb-15 text-title">{{ $store->name }}</h2>
                        <div class="row g-3">
                            <div class="col-lg-3 col-md-6 col-sm-6 col-auto">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="icon rounded-circle bg-theme1 d-center w-35px h-35px">
                                        <img width="20" height="20"
                                            src="{{ asset('/public/assets/admin/img/location-business.png') }}"
                                            alt="banner" class="object-contain">
                                    </div>
                                    <div>
                                        <h5 class="fs-14 font-semibold color-3C3C3C m-0">{{ translate('Business zone') }}</h5>
                                        <span class="d-block fs-12 color-484848">{{ $store->address }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6 col-sm-6 col-auto">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="icon rounded-circle bg-theme2 d-center w-35px h-35px">
                                        <img width="20" height="20"
                                            src="{{ asset('/public/assets/admin/img/plan-business.png') }}"
                                            alt="banner" class="object-contain">
                                    </div>
                                    <div>
                                        <h5 class="fs-14 font-semibold color-3C3C3C m-0">{{ translate('Business plan') }}</h5>
                                         @if($store->store_business_model == 'none')
                                    <span class="d-block fs-12 color-484848">{{ translate($store?->package?->package_name ) }}</span><br>
                                    <span class="d-block fs-12 color-484848">{{ translate('Payment failed') }}</span>
                                @else
                                <span class="d-block fs-12 color-484848">{{ translate($store->store_business_model ) }}</span>
                                @endif



                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6 col-sm-6 col-auto">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="icon rounded-circle bg-theme3 d-center w-35px h-35px">
                                        <img width="20" height="20"
                                            src="{{ asset('/public/assets/admin/img/pickup-business.png') }}"
                                            alt="banner" class="object-contain">
                                    </div>
                                    <div>
                                        <h5 class="fs-14 font-semibold color-3C3C3C m-0">{{ translate('Approximate pickup time') }}</h5>
                                        <span class="d-block fs-12 color-484848">{{  $store->delivery_time  }}</span>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

            <div class="row pt-3 g-3">
                <div class="col-12">
                    <div class="card h-100">
                        <div class="card-header">
                            <div>
                                <h4 class="text-title m-1">
                                    {{ translate('Registration information') }}
                                </h4>
                                <p class="fs-12 m-0 color-334257B2">{{ translate('Here you can see all the information that vendor submit during registration') }}</p>
                            </div>
                        </div>
                           <div class="card-body">
                <div class="row g-3">
                    <div class="col-lg-6">
                        <div class="card __bg-FAFAFA border-0 h-100">
                            <div class="card-body">
                                <h5 class="mb-10px font-bold"> {{ translate('General information') }}
                                </h5>
                                @php($language = \App\CentralLogics\Helpers::get_business_settings('language', false) ?? null)
                                <div class="div">
                                    @if ($language)
                                        <ul class="nav nav-tabs mb-4">
                                            <li class="nav-item">
                                                <a class="nav-link lang_link active" href="#"
                                                   id="default-link">{{ translate('Default') }}</a>
                                            </li>
                                            @foreach (json_decode($language) as $lang)
                                                <li class="nav-item">
                                                    <a class="nav-link lang_link" href="#"
                                                       id="{{ $lang }}-link">{{ \App\CentralLogics\Helpers::get_language_name($lang) . '(' . strtoupper($lang) . ')' }}</a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                    @if ($language)
                                        <div class="lang_form" id="default-form">
                                            <div class="resturant--info-address">
                                                <ul class="address-info address-info-2 p-0 text-dark">
                                                    <li class="d-flex align-items-start">
                                                        <span class="label min-w-sm-auto">{{ translate('Vendor name') }}</span>
                                                        <span>: {{$store->getRawOriginal('name')}} </span>
                                                    </li>
                                                    <li class="d-flex align-items-start">
                                                        <span class="label min-w-sm-auto">{{ translate('messages.Business address') }}</span>
                                                        <span>: {{$store->getRawOriginal('address')}} </span>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                        @foreach (json_decode($language) as $lang)
                                            <?php
                                            $store?->load('translations');
                                                if(count($store?->translations ?? [])){
                                                    $translate = [];
                                                    foreach($store['translations'] as $t)
                                                    {
                                                        if($t->locale == $lang && $t->key=="name"){
                                                            $translate[$lang]['name'] = $t->value;
                                                        }
                                                          if($t->locale == $lang && $t->key=="address"){
                                                                $translate[$lang]['address'] = $t->value;
                                                            }
                                                    }
                                                }
                                            ?>
                                            <div class="d-none lang_form" id="{{ $lang }}-form">
                                                <div class="resturant--info-address">
                                                    <ul class="address-info address-info-2 p-0 text-dark">
                                                        <li class="d-flex align-items-start">
                                                            <span class="label min-w-sm-auto">{{ translate('Vendor name') }}</span>
                                                            <span>: {{$translate[$lang]['name']??''}}</span>
                                                        </li>
                                                        <li class="d-flex align-items-start">
                                                            <span class="label min-w-sm-auto">{{ translate('messages.Business address') }}</span>
                                                            <span>: {{ $translate[$lang]['address']??'' }} </span>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                        @endforeach
                                    @else
                                        <div id="default-form">
                                            <div class="resturant--info-address">
                                                <ul class="address-info address-info-2 p-0 text-dark">
                                                    <li class="d-flex align-items-start">
                                                        <span class="label min-w-sm-auto">{{ translate('messages.Provider name') }}</span>
                                                        <span>: {{ $store->name }}</span>
                                                    </li>
                                                    <li class="d-flex align-items-start">
                                                        <span class="label min-w-sm-auto">{{ translate('messages.Business address') }}</span>
                                                        <span>: {{ $store->address }}</span>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                    </div>
                    <div class="col-lg-6">
                        <div class="card __bg-FAFAFA border-0 h-100">
                            <div class="card-body">
                                <h5 class="mb-10px font-bold"> {{ translate('Owner information') }}
                                </h5>
                                <div class="resturant--info-address">
                                    <ul class="address-info address-info-2 p-0 text-dark">
                                        <li class="d-flex align-items-start">
                                            <span class="label min-w-sm-auto">{{ translate('First name') }}</span>
                                            <span>: {{$store->vendor->f_name}} </span>
                                        </li>
                                        <li class="d-flex align-items-start">
                                            <span class="label min-w-sm-auto">{{ translate('Last name') }}</span>
                                            <span>: {{$store->vendor->l_name}}</span>
                                        </li>
                                        <li class="d-flex align-items-start">
                                            <span class="label min-w-sm-auto">{{ translate('messages.Phone') }}</span>
                                            <span>: {{$store->vendor->phone}}</span>
                                        </li>
                                    </ul>
                                </div>


                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="card __bg-FAFAFA border-0 h-100">
                            <div class="card-body">
                                <h5 class="mb-10px font-bold"> {{ translate('Login information') }}
                                </h5>


                                <div class="resturant--info-address">
                                    <ul class="address-info address-info-2 p-0 text-dark">
                                        <li class="d-flex align-items-start">
                                            <span class="label min-w-sm-auto">{{ translate('messages.Email') }}</span>
                                            <span>: {{ $store->vendor->email }}</span>
                                        </li>
                                        <li class="d-flex align-items-start">
                                            <span class="label min-w-sm-auto">{{ translate('messages.Password') }}</span>
                                            <span>: *************</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
                    </div>
                </div>
            </div>


        @endif








    </div>

    <div class="modal fade" id="collect-cash" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ translate('messages.Collect cash from store') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="{{ route('admin.transactions.account-transaction.store') }}" method='post'
                        id="add_transaction">
                        @csrf
                        <input type="hidden" name="type" value="store">
                        <input type="hidden" name="store_id" value="{{ $store->id }}">
                        <div class="form-group">
                            <label class="input-label">{{ translate('Payment method') }} <span
                                    class="input-label-secondary text-danger">*</span></label>
                            <input class="form-control" type="text" name="method" id="method" required
                                maxlength="191" placeholder="{{ translate('Ex') . ': ' . translate('Card') }}">
                        </div>
                        <div class="form-group">
                            <label class="input-label">{{ translate('messages.reference') }}</label>
                            <input class="form-control" type="text" name="ref" id="ref" maxlength="191">
                        </div>
                        <div class="form-group">
                            <label class="input-label">{{ translate('Amount') }} <span
                                    class="input-label-secondary text-danger">*</span></label>
                            <input class="form-control" type="number" min=".01" step="0.01" name="amount"
                                id="amount" max="999999999999.99"
                                placeholder="{{ translate('Ex') . ': 1000' }}">
                        </div>
                        <div class="btn--container justify-content-end">
                            <button type="submit" id="submit_new_customer"
                                class="btn btn--primary"><i class="tio-checkmark-circle-outlined"></i> {{ translate('Submit') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script_2')
    <script src="{{ asset('public/assets/admin/js/file-preview/details-multiple-document-upload.js') }}"></script>
    <script
        src="https://maps.googleapis.com/maps/api/js?key={{ \App\CentralLogics\Helpers::get_business_settings('map_api_key', false) }}&callback=initMap&libraries=marker&v=3.61">
    </script>
    <script>
        "use strict";

        $('.swal_fire_alert').on('click', function (event) {
            let url = $(this).data('url');
            let message = $(this).data('message');
            let title = $(this).data('title');
            let imageUrl = $(this).data('image_url');
            let cancelButtonText = $(this).data('cancel_button_text');
            let confirmButtonText = $(this).data('confirm_button_text');
            swalFire(url,title, message, imageUrl,cancelButtonText, confirmButtonText)
        })
        // Call the dataTables jQuery plugin
        $(document).ready(function() {
            $('#dataTable').DataTable();
        });

        const myLatLng = {
            lat: {{ $store->latitude }},
            lng: {{ $store->longitude }}
        };
        let map;
        initMap();

        function initMap() {
        const mapId = "{{ \App\CentralLogics\Helpers::get_business_settings('map_api_key', false) }}"

            map = new google.maps.Map(document.getElementById("map"), {
                zoom: 15,
                center: myLatLng,
                mapId: mapId
            });
            const { AdvancedMarkerElement } = google.maps.marker;

            new AdvancedMarkerElement({
                position: myLatLng,
                map,
                title: "{{ $store->name }}",
            });
        }

        $(document).on('ready', function() {
            // INITIALIZATION OF DATATABLES
            // =======================================================
            let datatable = $.HSCore.components.HSDatatables.init($('#columnSearchDatatable'));

            $('#column1_search').on('keyup', function() {
                datatable
                    .columns(1)
                    .search(this.value)
                    .draw();
            });

            $('#column2_search').on('keyup', function() {
                datatable
                    .columns(2)
                    .search(this.value)
                    .draw();
            });

            $('#column3_search').on('change', function() {
                datatable
                    .columns(3)
                    .search(this.value)
                    .draw();
            });

            $('#column4_search').on('keyup', function() {
                datatable
                    .columns(4)
                    .search(this.value)
                    .draw();
            });


            // INITIALIZATION OF SELECT2
            // =======================================================
            $('.js-select2-custom').each(function() {
                let select2 = $.HSCore.components.HSSelect2.init($(this));
            });
        });



        $('#add_transaction').on('submit', function(e) {
            e.preventDefault();
            let formData = new FormData(this);
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
            $.post({
                url: '{{ route('admin.transactions.account-transaction.store') }}',
                data: formData,
                cache: false,
                contentType: false,
                processData: false,
                success: function(data) {
                    if (data.errors) {
                        for (let i = 0; i < data.errors.length; i++) {
                            toastr.error(data.errors[i].message, {
                                CloseButton: true,
                                ProgressBar: true
                            });
                        }
                    } else {
                        toastr.success('{{ translate('messages.Transaction saved') }}', {
                            CloseButton: true,
                            ProgressBar: true
                        });
                        setTimeout(function() {
                            location.href = '{{ route('admin.store.view', $store->id) }}';
                        }, 2000);
                    }
                }
            });
        });
    </script>
@endpush
