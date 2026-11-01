@extends('layouts.vendor.app')

@section('title', translate('Create reels'))
@section('vendor_reels_create', 'active')

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('public/assets/admin/vendor/daterangepicker/daterangepicker.css') }}"/>
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/third-party-setup.css') }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/view-pages/reel-form.css') }}">
@endpush

@section('content')
    <div class="content container-fluid tps rlf">
        <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h1 class="page-header-title">
                    <i class="tio-play-circle-outlined"></i>
                    <span>{{ translate('Create reels') }}</span>
                </h1>
                <p class="page-header-desc">{{ translate('Post a short video for your store, link it to an item and set how long it runs.') }}</p>
            </div>
            <button type="button" class="tps-help" data-toggle="modal" data-target="#reel-how-it-works">
                <i class="tio-help-outlined"></i>
                <span>{{ translate('How it works') }}</span>
            </button>
        </div>

        <form id="reel-form" action="{{ route('vendor.reels.store') }}" method="POST" class="validate-form" enctype="multipart/form-data">
            @csrf
            @include('reelsmodule::vendor.reels.partials._form', ['isEdit' => false])
        </form>
    </div>

    <div class="modal fade" id="reel-how-it-works" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ translate('Create reels') }}</h5>
                    <button type="button" class="close btn btn--reset btn-circle" data-dismiss="modal"
                        aria-label="{{ translate('messages.Close') }}">
                        <span aria-hidden="true" class="tio-clear fs-20 opacity-70"></span>
                    </button>
                </div>
                <div class="modal-body">
                    <ol class="tps-steps mb-3">
                        <li>{{ translate('A reel is a short upright video customers swipe through in the app, posted under one store.') }}</li>
                        <li>{{ translate('The cover image is the still shown in the feed; the video plays once the customer stops on it.') }}</li>
                        <li>{{ translate('The caption is printed over the video, and each language tab holds its own wording.') }}</li>
                        <li>{{ translate('A call to action button sends the customer straight to one item from that store.') }}</li>
                    </ol>
                    <div class="tps-note tps-note--info">
                        <i class="tio-info-outined"></i>
                        <div>{{ translate('Reels only reach customers browsing the module the reel was created under, and only inside the store\'s own zone.') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script_2')
    <script type="text/javascript" src="{{ asset('public/assets/admin/vendor/daterangepicker/moment.min.js') }}"></script>
    <script type="text/javascript" src="{{ asset('public/assets/admin/vendor/daterangepicker/daterangepicker.min.js') }}"></script>
    @php($reelFormConfig = [
        'isEdit' => false,
        'itemsUrl' => null,
        'selectedProductId' => null,
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
