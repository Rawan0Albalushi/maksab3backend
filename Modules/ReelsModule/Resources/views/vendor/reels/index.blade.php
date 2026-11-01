@extends('layouts.vendor.app')

@section('title', translate('Reels list'))
@section('vendor_reels', 'active')
@section('vendor_reels_list', 'active')

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        .reel-overview-card { transition: transform .2s ease, box-shadow .2s ease; }
        .reel-overview-card:hover { transform: translateY(-4px); box-shadow: 0 .5rem 1rem rgba(0, 0, 0, .12); }
    </style>
@endpush

@php
    $isServiceModule = ($store?->module_type ?? $store?->module?->module_type) === 'service';
    $statusClasses = [
        'live' => 'text-success bg-success bg-opacity-10',
        'upcoming' => 'text-info bg-info bg-opacity-10',
        'expired' => 'text-danger bg-danger bg-opacity-10',
        'deactivated' => 'text-warning bg-warning bg-opacity-10',
    ];
    $reelStateLabels = [
        'live' => translate('messages.live'),
        'upcoming' => translate('messages.upcoming'),
        'expired' => translate('Expired'),
        'deactivated' => translate('Deactivated'),
    ];
    $productLabel = $isServiceModule ? translate('messages.Service') : translate('messages.Product');
    $salesLabel = $isServiceModule ? translate('messages.Bookings') : translate('messages.Sales');
    $orderButtonLabel = $isServiceModule ? translate('messages.Book Now') : translate('messages.Order now');
    $reelStatusOptions = ['live', 'upcoming', 'expired', 'deactivated'];
    $selectedReelStatuses = array_values(array_filter((array) request('reel_status', [])));
    $datePreset = request('filter') === 'all_time' ? '' : (string) request('filter', '');
    $activeFilterCount = (empty($selectedReelStatuses) ? 0 : 1)
        + ($datePreset === '' ? 0 : 1)
        + (request()->filled('from') ? 1 : 0)
        + (request()->filled('to') ? 1 : 0);
    $filterClearUrl = route('vendor.reels.index', array_filter(['search' => request('search')]));
@endphp

@section('content')
<div class="content container-fluid">
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h1 class="page-header-title">
                <i class="tio-play-circle-outlined"></i>
                <span>{{ translate('Reels list') }}
                    <span class="badge badge-soft-dark ml-2">{{ $reels->total() }}</span>
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('Short videos you post to promote your items, and how each one is performing.') }}</p>
        </div>
        <a href="{{ route('vendor.reels.create') }}" class="btn btn--primary"><i class="tio-add"></i> {{ translate('Create reels') }}</a>
    </div>

    <div class="row g-3">
        <div class="col-12">
            <div class="card card-body">
                <h4 class="mb-3">{{ translate('Reels overview') }}</h4>
                <div class="row g-3">
                    @foreach ($overviewCards as $card)
                        <div class="col-sm-6 col-lg-4">
                            <a href="javascript:;" class="reel-overview-card h-100 d-block text-reset text-decoration-none">
                                <div class="p-3 rounded-10 border overflow-wrap-anywhere h-100 d-flex justify-content-between align-items-start gap-2 flex-wrap">
                                    <div>
                                        <h3 class="fs-20 mb-1">{{ $card['value'] }}</h3>
                                        <p class="text-muted mb-0 d-flex align-items-center gap-1">
                                            {{ $card['label'] }}
                                            @if (!empty($card['tooltip']))
                                                <span class="form-label-secondary text-dark" data-toggle="tooltip" data-placement="top" data-title="{{ $card['tooltip'] }}">
                                                    <i class="tio-info"></i>
                                                </span>
                                            @endif
                                        </p>
                                    </div>
                                    <div class="{{ $card['bg'] }} p-2 rounded-10 lh--1 w-max-content">
                                        <i class="{{ $card['icon'] }} {{ $card['color'] }} fs-20"></i>
                                    </div>
                                </div>
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-header py-2 border-0">
                    <div class="search--button-wrapper flex-wrap">
                        @include('partials._table-head', [
                            'subtitle' => translate('messages.Every reel you have posted, with how it is performing and when it runs.'),
                        ])
                        <form class="search-form min--260" action="{{ route('vendor.reels.index') }}" method="GET">
                            @foreach(request()->except(['search', 'page']) as $key => $value)
                                @if(is_array($value))
                                    @foreach($value as $item)
                                        <input type="hidden" name="{{ $key }}[]" value="{{ $item }}">
                                    @endforeach
                                @else
                                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                @endif
                            @endforeach
                            <div class="input-group input--group">
                                <input type="search" name="search" class="form-control h--40px" placeholder="{{ translate('Search') }}" value="{{ request('search') }}">
                                <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                            </div>
                        </form>

                        <div class="hs-unfold">
                            <a class="btn btn-sm btn-white h--40px filter-button-show" href="javascript:;"
                               role="button" aria-expanded="false" aria-controls="datatableFilterSidebar">
                                <i class="tio-filter-list mr-1"></i> {{ translate('messages.Filter') }}
                                @if ($activeFilterCount)
                                    <span class="badge badge-success badge-pill ml-1">{{ $activeFilterCount }}</span>
                                @endif
                            </a>
                        </div>
                    </div>
                </div>

                <div class="table-responsive datatable-custom">
                    <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table fz--14px text-title table--wrap-head">
                        <thead class="thead-light">
                            <tr>
                                <th>{{ translate('messages.Reel') }}</th>
                                <th>{{ $productLabel }}</th>
                                <th>{{ translate('messages.Engagement') }}</th>
                                <th class="col--numeric">{{ $salesLabel }}</th>
                                <th>{{ translate('messages.Duration') }}</th>
                                <th class="text-center">{{ translate('messages.Status') }}</th>
                                <th class="text-center">{{ translate('messages.Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($reels as $reel)
                                @php($windowState = $reel->window_state_label)
                                @php($today = \Carbon\Carbon::now()->startOfDay())
                                @php($daysToStart = $reel->start_date ? (int) $today->diffInDays($reel->start_date->copy()->startOfDay(), false) : null)
                                @php($daysLeft = $reel->end_date ? (int) $today->diffInDays($reel->end_date->copy()->startOfDay(), false) : null)
                                <tr>
                                    <td>
                                        <a class="table-rest-info table-rest-info--portrait offcanvas-trigger" href="javascript:;" data-target="#reelsDetailsOffcanvas{{ $reel->id }}" title="{{ $reel->description }}">
                                            <img class="onerror-image" src="{{ $reel->thumbnail_full_url ?? asset('public/assets/admin/img/160x160/img2.jpg') }}" data-onerror-image="{{ asset('public/assets/admin/img/160x160/img2.jpg') }}" alt="">
                                            <div class="info max-w-200px">
                                                <div class="text--title text-wrap line--limit-2">{{ $reel->description }}</div>
                                                <div class="font-light">ID:{{ $reel->id }} &middot; {{ optional($reel->created_at)->format('d M Y') }}</div>
                                            </div>
                                        </a>
                                    </td>
                                    <td>
                                        @if ($reel->productable)
                                            <span class="text--title text-wrap line--limit-1 max-w-200px">{{ $reel->productable->name }}</span>
                                            @if ($reel->order_now_button)
                                                <div class="cell-chips mt-1"><span class="cell-chip">{{ $orderButtonLabel }}</span></div>
                                            @endif
                                        @else
                                            <span class="text-muted">{{ translate('messages.N/A') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="d-block text--title">{{ number_format($reel->total_views) }} {{ translate('messages.Views') }}</span>
                                        <span class="d-block fs-12 text-muted">{{ number_format($reel->total_likes) }} {{ translate('messages.Likes') }} &middot; {{ number_format($reel->total_store_visits) }} {{ translate('Store visits') }}</span>
                                    </td>
                                    <td class="col--numeric">
                                        <span class="d-block text--title">{{ \App\CentralLogics\Helpers::format_currency($reel->total_order_amount ?? 0) }}</span>
                                        <span class="d-block fs-12 text-muted">{{ number_format($reel->total_orders ?? 0) }} {{ $salesLabel }}</span>
                                    </td>
                                    <td>
                                        @if ($reel->is_always_visible)
                                            <span class="d-block text--title">{{ translate('Always visible') }}</span>
                                        @else
                                            <span class="d-block text--title">{{ optional($reel->start_date)->format('d M Y') }} - {{ optional($reel->end_date)->format('d M Y') }}</span>
                                            @if ($windowState === 'upcoming' && $daysToStart !== null && $daysToStart <= 90)
                                                <span class="d-block fs-12 text-muted">{{ $daysToStart > 1 ? translate('Starts') . ' ' . \Carbon\Carbon::parse($reel->start_date)->startOfDay()->diffForHumans(\Carbon\Carbon::now()->startOfDay(), \Carbon\CarbonInterface::DIFF_RELATIVE_TO_NOW) : translate('Starts tomorrow') }}</span>
                                            @elseif ($windowState !== 'upcoming' && $daysLeft !== null && abs($daysLeft) <= 90)
                                                <span class="d-block fs-12 text-muted">
                                                    @if ($daysLeft > 1)
                                                        {{ translate('Ends') }} {{ \Carbon\Carbon::parse($reel->end_date)->startOfDay()->diffForHumans(\Carbon\Carbon::now()->startOfDay(), \Carbon\CarbonInterface::DIFF_RELATIVE_TO_NOW) }}
                                                    @elseif ($daysLeft === 1)
                                                        {{ translate('Ends tomorrow') }}
                                                    @elseif ($daysLeft === 0)
                                                        {{ translate('Ends today') }}
                                                    @elseif ($daysLeft === -1)
                                                        {{ translate('Ended yesterday') }}
                                                    @else
                                                        {{ translate('Ended') }} {{ \Carbon\Carbon::parse($reel->end_date)->startOfDay()->diffForHumans(\Carbon\Carbon::now()->startOfDay(), \Carbon\CarbonInterface::DIFF_RELATIVE_TO_NOW) }}
                                                    @endif
                                                </span>
                                            @endif
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <div class="status-toggle" data-status="{{ $reel->status ? 1 : 0 }}">
                                            <label class="toggle-switch toggle-switch-sm" for="reelStatus{{ $reel->id }}">
                                                <input type="checkbox" data-id="reelStatus{{ $reel->id }}" data-type="status" data-image-on="{{ asset('public/assets/admin/img/modal/reel-stratus-on.png') }}" data-image-off="{{ asset('public/assets/admin/img/modal/reel-stratus-off.png') }}" data-title-on="{{ translate('messages.Want to turn on the reel?') }}" data-title-off="{{ translate('messages.Want to turn off the reel?') }}" data-text-on="<p>{{ translate('messages.If you turn on the reel, it will be visible to customers.') }}</p>" data-text-off="<p>{{ translate('messages.If you turn off the reel, it will no longer be visible to customers.') }}</p>" data-label-on="{{ $reelStateLabels[$windowState] ?? $windowState }}" data-label-off="{{ $reelStateLabels['deactivated'] }}" class="toggle-switch-input dynamic-checkbox" id="reelStatus{{ $reel->id }}" {{ $reel->status ? 'checked' : '' }}>
                                                <span class="toggle-switch-label"><span class="toggle-switch-indicator"></span></span>
                                            </label>
                                            <span class="status-toggle__text {{ $statusClasses[$reel->reel_status_label] ?? '' }} px-2 py-1 rounded-20" aria-live="polite">{{ $reelStateLabels[$reel->reel_status_label] ?? $reel->reel_status_label }}</span>
                                        </div>
                                        <form action="{{ route('vendor.reels.status', [$reel->id, $reel->status ? 0 : 1]) }}" method="GET" id="reelStatus{{ $reel->id }}_form"></form>
                                    </td>
                                    <td>
                                        <div class="btn--container justify-content-center">
                                            <a class="btn action-btn action-btn--view offcanvas-trigger" href="javascript:;" data-target="#reelsDetailsOffcanvas{{ $reel->id }}" title="{{ translate('messages.View') }}"><i class="tio-visible-outlined"></i></a>
                                            <a class="btn action-btn action-btn--edit" href="{{ route('vendor.reels.edit', $reel->id) }}" title="{{ translate('Edit') }}"><i class="tio-edit"></i></a>
                                            <a class="btn action-btn action-btn--delete" data-toggle="modal" data-target="#confirmation-deletes-{{ $reel->id }}" title="{{ translate('messages.Delete') }}"><i class="tio-delete-outlined"></i></a>
                                        </div>

                                        <div class="modal fade" id="confirmation-deletes-{{ $reel->id }}" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered status-warning-modal" role="document">
                                                <div class="modal-content">
                                                    <div class="modal-header px-2 pt-2 border-0">
                                                        <button type="button" class="close btn btn--reset btn-circle" data-dismiss="modal" aria-label="Close">
                                                            <span aria-hidden="true" class="tio-clear fs-20 opacity-70"></span>
                                                        </button>
                                                    </div>
                                                    <form action="{{ route('vendor.reels.destroy', $reel->id) }}" method="post">
                                                        @csrf
                                                        @method('delete')
                                                        <div class="modal-body pb-4 pt-0">
                                                            <div class="max-349 mx-auto mt-2 mb-20">
                                                                <div class="text-center">
                                                                    <img src="{{ asset('public/assets/admin/img/delete.png') }}" alt="icon" class="mb-20">
                                                                    <h3 class="mb-2 fs-18">{{ translate('Want to delete this reel?') }}</h3>
                                                                    <p class="text-wrap mb-0">
                                                                        @if ($reel->reel_status_label == 'live')
                                                                            {{ translate('This reel is currently live and has engagement. If you delete it, it will no longer be visible to customers.') }}
                                                                        @else
                                                                            {{ translate('Please confirm before deleting this reel. This will permanently remove this from the reel list.') }}
                                                                        @endif
                                                                    </p>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer justify-content-center border-0 pt-0 pb-4 gap-2">
                                                                <button type="submit" class="btn min-w-120px btn-danger min-h-45px"><i class="tio-delete-outlined"></i> {{ translate('Yes, delete') }}</button>
                                                                <button type="button" class="btn min-w-120px btn--reset min-h-45px" data-dismiss="modal"><i class="tio-clear-circle-outlined"></i> {{ translate('messages.Cancel') }}</button>
                                                            </div>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7">
                                        <div class="empty--data">
                                            <img src="{{ asset('public/assets/admin/svg/illustrations/sorry.svg') }}" alt="public">
                                            <h5>{{ translate('No data found') }}</h5>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($reels->count())
                    <div class="card-footer border-0">{!! $reels->links() !!}</div>
                @endif
            </div>
        </div>
    </div>

    <div id="datatableFilterSidebar" class="filter-drawer sidebar sidebar-bordered sidebar-box-shadow">
        <div class="card card-lg sidebar-card sidebar-footer-fixed">
            @include('partials._filter-drawer-head', [
                'fd_title' => translate('messages.Reel filter'),
                'fd_subtitle' => translate('messages.Narrow your reel list down by status and upload date.'),
            ])

            <form class="card-body sidebar-body sidebar-scrollbar" action="{{ route('vendor.reels.index') }}" method="GET" id="reel_filter_form">
                <input type="hidden" name="search" value="{{ request('search') }}">

                <small class="text-cap mb-3">{{ translate('messages.Reel Status') }}</small>
                <div class="fd-grid">
                    @foreach ($reelStatusOptions as $status)
                        <div class="custom-control custom-checkbox mb-2">
                            <input type="checkbox" class="custom-control-input" id="filterReelStatus{{ ucfirst($status) }}"
                                   name="reel_status[]" value="{{ $status }}"
                                   {{ in_array($status, $selectedReelStatuses) ? 'checked' : '' }}>
                            <label class="custom-control-label" for="filterReelStatus{{ ucfirst($status) }}">{{ $reelStateLabels[$status] }}</label>
                        </div>
                    @endforeach
                </div>

                <hr class="my-4">

                <small class="text-cap mb-3">{{ translate('messages.Reel Upload Date') }}</small>
                <div class="form-group">
                    <select name="filter" id="reel_filter_period" class="form-control">
                        <option value="">{{ translate('All time') }}</option>
                        <option value="this_week" {{ $datePreset === 'this_week' ? 'selected' : '' }}>{{ translate('This week') }}</option>
                        <option value="this_month" {{ $datePreset === 'this_month' ? 'selected' : '' }}>{{ translate('This month') }}</option>
                        <option value="this_year" {{ $datePreset === 'this_year' ? 'selected' : '' }}>{{ translate('This year') }}</option>
                        <option value="previous_year" {{ $datePreset === 'previous_year' ? 'selected' : '' }}>{{ translate('Previous year') }}</option>
                        <option value="custom" {{ $datePreset === 'custom' ? 'selected' : '' }}>{{ translate('messages.Custom') }}</option>
                    </select>
                </div>

                <div class="form-group {{ $datePreset === 'custom' ? '' : 'd-none' }}" id="reel_filter_custom_range">
                    <div class="fd-daterange">
                        <div>
                            <label class="fd-sublabel" for="reel_filter_from">{{ translate('Start date') }}</label>
                            <input type="date" name="from" id="reel_filter_from" class="form-control" value="{{ request('from') }}"
                                   {{ $datePreset === 'custom' ? 'required' : 'disabled' }}>
                        </div>
                        <div>
                            <label class="fd-sublabel" for="reel_filter_to">{{ translate('End date') }}</label>
                            <input type="date" name="to" id="reel_filter_to" class="form-control" value="{{ request('to') }}"
                                   {{ $datePreset === 'custom' ? 'required' : 'disabled' }}>
                        </div>
                    </div>
                </div>

                <div class="card-footer sidebar-footer">
                    <div class="row gx-2">
                        <div class="col">
                            <a class="btn btn-block btn-white" href="{{ $filterClearUrl }}">
                                <i class="tio-clear-circle-outlined"></i> {{ translate('Clear all') }}
                            </a>
                        </div>
                        <div class="col">
                            <button type="submit" class="btn btn-block btn-primary">
                                <i class="tio-filter-list"></i> {{ translate('messages.Filter') }}
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @foreach ($reels as $reel)
        <div id="reelsDetailsOffcanvas{{ $reel->id }}" style="overflow-y: auto;" class="custom-offcanvas d-flex flex-column justify-content-between global_guideline_offcanvas">
            <div>
                <div class="custom-offcanvas-header bg--secondary d-flex justify-content-between align-items-center px-3 py-3">
                    <h3 class="mb-0">{{ translate('Reels details') }}</h3>
                    <button type="button" class="btn-close w-25px h-25px border rounded-circle d-center bg--secondary offcanvas-close fz-15px p-0" aria-label="Close">&times;</button>
                </div>
                <div class="p-3">
                    <div class="d-flex justify-content-center align-items-center mb-3">
                        <div class="reels-video-wrapper">
                            <img src="{{ $reel->thumbnail_full_url ?? asset('public/assets/admin/img/160x160/img2.jpg') }}" alt="" class="reels-thumbnail">
                            <video class="reels-video" width="400" height="470" preload="none" controls>
                                <source src="{{ $reel->video_full_url }}" type="video/mp4">
                                {{ translate('messages.Your browser does not support the video tag.') }}
                            </video>
                            <div class="reels-play-btn">
                                <div class="d-flex justify-content-center align-items-center w-100 h-100">
                                    <i class="tio-play"></i>
                                </div>
                            </div>
                            <div class="reels-close-btn">✕</div>
                        </div>
                    </div>
                    <div class="bg-light p-3 rounded mb-3">
                        <div class="d-flex gap-2 align-items-center justify-content-between mb-3">
                            <div class="flex-grow-1">
                                {{ translate('Reel ID') }}: <span class="text-title">{{ $reel->id }}</span>
                            </div>
                            <span class="{{ $statusClasses[$reel->reel_status_label] ?? 'text-muted bg-light' }} px-2 py-1 rounded-20 w-max-content flex-shrink-0">
                                {{ $reelStateLabels[$reel->reel_status_label] ?? $reel->reel_status_label }}
                            </span>
                        </div>
                        <h4 class="mb-2">{{ translate('Short description') }}</h4>
                        <p class="fw-medium mb-0">{{ $reel->description }}</p>
                    </div>

                    <div class="bg-light p-3 rounded mb-3">
                        <h4 class="mb-2">{{ translate('Reel validity') }}</h4>
                        <div class="d-flex align-items-stretch">
                            <div class="w-50 pe-3">
                                {{ translate('Upload date') }}: <span class="text-title">{{ optional($reel->created_at)->format('d M Y') }}</span>
                            </div>
                            <div class="border-start"></div>
                            <div class="w-50 ps-3">
                                {{ translate('Expired date') }}:
                                <span class="text-title">
                                    {{ $reel->is_always_visible ? translate('Always visible') : optional($reel->end_date)->format('d M Y') }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-light p-3 rounded mb-3">
                        <h4 class="mb-2">{{ translate('Reel earning') }}</h4>
                        <div class="d-flex gap-2 align-items-center justify-content-between flex-wrap">
                            <div>{{ $productLabel }}: <span class="text-title fw-medium">{{ $reel->productable?->name ?? translate('messages.N/A') }}</span></div>
                            <div>{{ $orderButtonLabel }}: <span class="text-title fw-medium">{{ $reel->order_now_button ? translate('messages.on') : translate('messages.off') }}</span></div>
                        </div>
                    </div>

                    <div class="row g-2 border-top pt-3">
                        <div class="col-sm-4">
                            <div class="bg-light rounded p-2 text-center">
                                <div class="d-flex gap-1 justify-content-center align-items-center fs-12">
                                    <i class="tio-visible-outlined fs-16"></i> {{ translate('messages.Views') }}
                                </div>
                                <h5 class="text-info">{{ $reel->total_views }}</h5>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="bg-light rounded p-2 text-center">
                                <div class="d-flex gap-1 justify-content-center align-items-center fs-12">
                                    <i class="tio-thumbs-up fs-16"></i> {{ translate('messages.Likes') }}
                                </div>
                                <h5 class="text-info">{{ $reel->total_likes }}</h5>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="bg-light rounded p-2 text-center">
                                <div class="d-flex gap-1 justify-content-center align-items-center fs-12">
                                    <i class="tio-shop-outlined fs-16"></i> {{ translate('Store visits') }}
                                </div>
                                <h5 class="text-info">{{ $reel->total_store_visits }}</h5>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="bg-light rounded p-2 text-center">
                                <div class="d-flex gap-1 justify-content-center align-items-center fs-12">
                                    <i class="tio-shopping-cart fs-16"></i> {{ $isServiceModule ? translate('Total booking') : translate('Total sale') }}
                                </div>
                                <h5 class="text-info">{{ number_format($reel->total_orders ?? 0) }}</h5>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="bg-light rounded p-2 text-center">
                                <div class="d-flex gap-1 justify-content-center align-items-center fs-12">
                                    <i class="tio-money fs-16"></i> {{ $isServiceModule ? translate('Total booking amount') : translate('Total sale amount') }}
                                </div>
                                <h5 class="text-info">{{ \App\CentralLogics\Helpers::format_currency($reel->total_order_amount ?? 0) }}</h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
    <div id="offcanvasOverlay" class="offcanvas-overlay"></div>
</div>
@endsection

@push('script_2')
<script>
"use strict";
$(document).on('change', '#reel_filter_period', function () {
    const isCustom = $(this).val() === 'custom';
    $('#reel_filter_custom_range').toggleClass('d-none', !isCustom);
    $('#reel_filter_from, #reel_filter_to').prop({ required: isCustom, disabled: !isCustom });
});
$(document).on('click', '.offcanvas-trigger', function () { $($(this).data('target')).addClass('active'); $('body').addClass('overflow-hidden'); });
$(document).on('click', '.offcanvas-close, #offcanvasOverlay', function () {
    $(this).closest('.custom-offcanvas').removeClass('active');
    $('body').removeClass('overflow-hidden');
    $('video.reels-video').each(function () {
        this.pause();
        this.currentTime = 0;
        const wrapper = $(this).closest('.reels-video-wrapper');
        wrapper.find('.reels-video').hide();
        wrapper.find('.reels-thumbnail, .reels-play-btn').show();
        wrapper.find('.reels-close-btn').hide();
    });
});
$(document).on('click', '.reels-video-wrapper', function (e) {
    if ($(e.target).closest('.reels-close-btn').length) return;
    const wrapper = $(this);
    const video = wrapper.find('.reels-video').get(0);
    $('video.reels-video').each(function () { this.pause(); this.currentTime = 0; const currentWrapper = $(this).closest('.reels-video-wrapper'); currentWrapper.find('.reels-video').hide(); currentWrapper.find('.reels-thumbnail, .reels-play-btn').show(); currentWrapper.find('.reels-close-btn').hide(); });
    wrapper.find('.reels-thumbnail, .reels-play-btn').hide(); wrapper.find('.reels-video').show(); wrapper.find('.reels-close-btn').css('display', 'flex');
    if (video) { wrapper.find('.reels-video').attr('controls', true); video.play(); }
});
$(document).on('click', '.reels-close-btn', function (e) {
    e.stopPropagation();
    const wrapper = $(this).closest('.reels-video-wrapper');
    const video = wrapper.find('.reels-video').get(0);
    if (video) { video.pause(); video.currentTime = 0; }
    wrapper.find('.reels-video').hide(); wrapper.find('.reels-thumbnail, .reels-play-btn').show(); wrapper.find('.reels-close-btn').hide();
});
</script>
@endpush
