@extends('layouts.admin.app')

@section('title', translate('messages.Add vehicle category'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet"
        href="{{ asset('public/assets/admin/css/admin-shared.css') }}?v={{ @filemtime(public_path('public/assets/admin/css/admin-shared.css')) }}">
    <link rel="stylesheet"
        href="{{ asset('public/assets/admin/css/delivery-rule.css') }}?v={{ @filemtime(public_path('public/assets/admin/css/delivery-rule.css')) }}">
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title mb-0">
                <i class="tio-car"></i>
                <span>{{ translate('messages.Add vehicle category') }}</span>
            </h1>
        </div>

        <form action="{{ route('admin.business-settings.zone.vehicle-category.store') }}" method="post"
            id="vehicle-category-form">
            @csrf
            @include('admin-views.vehicle-category.partials._form')
        </form>
    </div>
@endsection
