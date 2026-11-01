@extends('layouts.admin.app')

@section('title', translate('Edit reels'))
@section('reels', 'active')
@section('reels_list', 'active')

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('public/assets/admin/vendor/daterangepicker/daterangepicker.css') }}"/>
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/third-party-setup.css') }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/view-pages/reel-form.css') }}">
@endpush

@section('content')
    <div class="content container-fluid tps rlf">
        <div class="page-header">
            <h1 class="page-header-title">
                <i class="tio-play-circle-outlined"></i>
                <span>{{ translate('Edit reels') }}</span>
            </h1>
            <p class="page-header-desc">{{ translate('Change the video, the details or the dates this reel runs.') }}</p>
        </div>

        <form id="reel-form" action="{{ route('admin.reels.update', $reel->id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            @include('reelsmodule::admin.reels.partials._form', ['isEdit' => true])
        </form>
    </div>
@endsection

@push('script_2')
    <script type="text/javascript" src="{{ asset('public/assets/admin/vendor/daterangepicker/moment.min.js') }}"></script>
    <script type="text/javascript" src="{{ asset('public/assets/admin/vendor/daterangepicker/daterangepicker.min.js') }}"></script>
    @php($reelFormConfig = [
        'isEdit' => true,
        'itemsUrl' => route('admin.reels.items'),
        'selectedProductId' => $selectedProductId,
        'lang' => [
            'notSet' => translate('messages.Not set'),
            'clear' => translate('messages.Clear'),
            'alwaysVisible' => translate('Always visible to customers'),
            'noAction' => translate('No action button'),
            'selectProduct' => translate('Select') . ' ' . ($productLabel ?? translate('messages.Product')),
            'somethingWentWrong' => translate('messages.Something went wrong'),
        ],
    ])
    <script>
        "use strict";

        window.reelFormConfig = @json($reelFormConfig);
    </script>
    <script src="{{ asset('public/assets/admin/js/view-pages/reel-form.js') }}"></script>
@endpush
