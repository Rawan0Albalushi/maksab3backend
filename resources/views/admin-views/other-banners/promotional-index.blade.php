@extends('layouts.admin.app')

@section('title', translate('Banner'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/third-party-setup.css') }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/view-pages/promotional-banner.css') }}">
@endpush

@section('content')
<div class="content container-fluid tps pbn">
    <div class="page-header">
        <h1 class="page-header-title">
            <span class="page-header-icon">
                <img src="{{ asset('public/assets/admin/img/outline/3rd-party.svg') }}" alt="">
            </span>
            <span>
                {{ translate('messages.Other Promotional Content Setup') }}
            </span>
        </h1>
        <p class="page-header-desc">{{ translate('The extra artwork and copy shown around the customer app, beyond the main banners.') }}</p>
    </div>
    @include('admin-views.other-banners.partial._bottom-section-banner')
</div>
@endsection
