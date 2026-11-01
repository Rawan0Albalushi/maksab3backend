@extends('layouts.admin.app')

@section('title', translate('messages.Surge Price'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet"
        href="{{ asset('public/assets/admin/css/admin-shared.css') }}?v={{ @filemtime(public_path('public/assets/admin/css/admin-shared.css')) }}">
    <link rel="stylesheet"
        href="{{ asset('public/assets/admin/css/delivery-rule.css') }}?v={{ @filemtime(public_path('public/assets/admin/css/delivery-rule.css')) }}">
    <link rel="stylesheet"
        href="{{ asset('public/assets/admin/css/surge-price.css') }}?v={{ @filemtime(public_path('public/assets/admin/css/surge-price.css')) }}">
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title mb-0">
                <i class="tio-trending-up"></i>
                <span>
                    {{ translate('messages.Surge Price') }}
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('Temporary uplifts on the delivery charge, for the zones and hours where demand outruns supply.') }}</p>
        </div>

        {{-- The empty state takes over the whole card, but only when nothing has ever been
             added — a search that matches nothing keeps the table so the search box stays
             reachable. --}}
        @if ($surges->total() === 0 && !request()->has('search'))
            <div class="card">
                <div class="card-body empty-state">
                    <img class="empty-state__icon" src="{{ asset('public/assets/admin/img/price-emty.png') }}"
                        alt="{{ translate('messages.Surge Price') }}">
                    <h5 class="empty-state__title">
                        {{ translate('Currently you dont have any surge price') }}</h5>
                    <p class="empty-state__text">
                        {{ translate('To enable surge pricing, you must create at least one surge price. In this page you see all the surge price you added.') }}
                    </p>
                    <a href="{{ route('admin.business-settings.zone.surge-price.create') }}" class="btn btn--primary">
                        <i class="tio-add-circle-outlined"></i> {{ translate('Create surge price') }}
                    </a>
                </div>
            </div>
        @else
            <div class="card">
                <div class="card-header py-2 border-0">
                    <div class="search--button-wrapper">
                        <h5 class="card-title">
                            {{ translate('Surge price list') }}
                            <span class="badge badge-soft-dark ml-2">{{ $surges->total() }}</span>
                        </h5>

                        <form>
                            <div class="input--group input-group input-group-merge input-group-flush">
                                <input id="datatableSearch_" type="search" name="search" class="form-control"
                                    value="{{ request()?->search ?? null }}"
                                    placeholder="{{ translate('Search by title') }}"
                                    aria-label="{{ translate('Search') }}" required>
                                <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                            </div>
                        </form>

                        <div class="hs-unfold">
                            <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle min-height-40"
                                href="javascript:;"
                                data-hs-unfold-options='{"target": "#surgePriceExportDropdown","type": "css-animation"}'>
                                <i class="tio-download-to mr-1"></i> {{ translate('Export') }}
                            </a>
                            <div id="surgePriceExportDropdown"
                                class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">
                                <span class="dropdown-header">{{ translate('messages.Download options') }}</span>
                                <a class="dropdown-item"
                                    href="{{ route('admin.business-settings.zone.surge-price.export', ['type' => 'excel', 'search' => request()->search]) }}">
                                    <img class="avatar avatar-xss avatar-4by3 mr-2"
                                        src="{{ asset('public/assets/admin') }}/svg/components/excel.svg" alt="">
                                    Excel
                                </a>
                                <a class="dropdown-item"
                                    href="{{ route('admin.business-settings.zone.surge-price.export', ['type' => 'csv', 'search' => request()->search]) }}">
                                    <img class="avatar avatar-xss avatar-4by3 mr-2"
                                        src="{{ asset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg" alt="">
                                    CSV
                                </a>
                            </div>
                        </div>

                        <a href="{{ route('admin.business-settings.zone.surge-price.create') }}" class="btn btn--primary">
                            <i class="tio-add-circle-outlined"></i> {{ translate('Add new') }}
                        </a>
                    </div>
                </div>

                <div class="table-responsive datatable-custom">
                    <table class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                        <thead class="thead-light">
                            <tr>
                                <th class="border-0">{{ translate('messages.SL') }}</th>
                                <th class="border-0">{{ translate('messages.Title') }}</th>
                                <th class="border-0">{{ translate('messages.Zone') }}</th>
                                <th class="border-0">{{ translate('messages.Module') }}</th>
                                <th class="border-0">
                                    <div class="min-w-135px">{{ translate('Price increase rate') }}</div>
                                </th>
                                <th class="border-0">
                                    <div class="min-w-135px">{{ translate('Surge price schedule') }}</div>
                                </th>
                                <th class="border-0">
                                    <div class="min-w-200px">{{ translate('messages.Duration') }}</div>
                                </th>
                                <th class="border-0">{{ translate('messages.Status') }}</th>
                                <th class="border-0 text-center">{{ translate('messages.Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($surges as $key => $surge)
                                <tr>
                                    <td class="pl-4">{{ $key + $surges->firstItem() }}</td>
                                    <td>
                                        <span class="line--limit-2 max-w-220px text-title"
                                            title="{{ $surge->surge_price_name }}">{{ $surge->surge_price_name ?? translate('messages.N/A') }}</span>
                                    </td>
                                    <td>
                                        <span class="line--limit-2 max-w-220px"
                                            title="{{ $surge->zone?->name }}">{{ $surge->zone?->name ?? translate('messages.N/A') }}</span>
                                    </td>
                                    <td>
                                        {{-- One module reads by name; several collapse to a count
                                             and carry the names as a tooltip (G2). --}}
                                        @if ($moduleLabels[$surge->id]['many'])
                                            <span class="text-primary border-bottom border-primary cursor-pointer"
                                                data-toggle="tooltip" data-placement="top"
                                                title="{{ $moduleLabels[$surge->id]['tooltip'] }}">{{ $moduleLabels[$surge->id]['text'] }}</span>
                                        @else
                                            {{ $moduleLabels[$surge->id]['text'] }}
                                        @endif
                                    </td>
                                    <td>{{ $rateLabels[$surge->id] }}</td>
                                    <td>{{ $scheduleLabels[$surge->id] }}</td>
                                    <td>
                                        <div class="eta-cell">
                                            @foreach ($durationLines[$surge->id] as $line)
                                                <span class="eta-cell__line">{{ $line }}</span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td>
                                        <label class="toggle-switch toggle-switch-sm" for="status-{{ $surge->id }}">
                                            <input type="checkbox" class="toggle-switch-input dynamic-checkbox"
                                                id="status-{{ $surge->id }}" {{ $surge->status ? 'checked' : '' }}
                                                data-id="status-{{ $surge->id }}" data-type="status"
                                                data-image-on="{{ asset('public/assets/admin/img/status-ons.png') }}"
                                                data-image-off="{{ asset('public/assets/admin/img/off-danger.png') }}"
                                                data-title-on="{{ translate('Turn on the status?') }}"
                                                data-title-off="{{ translate('Turn off the status?') }}"
                                                data-text-on="<p>{{ translate('messages.Are you sure, do you want to turn on this surge price.') }}</p>"
                                                data-text-off="<p>{{ translate('messages.Are you sure, do you want to turn off this surge price.') }}</p>">
                                            <span class="toggle-switch-label">
                                                <span class="toggle-switch-indicator"></span>
                                            </span>
                                        </label>
                                        <form action="{{ route('admin.business-settings.zone.surge-price.status', [$surge->id, $surge->status ? 0 : 1]) }}"
                                            method="get" id="status-{{ $surge->id }}_form"></form>
                                    </td>
                                    <td>
                                        <div class="btn--container justify-content-center">
                                            <a class="btn action-btn action-btn--edit"
                                                href="{{ route('admin.business-settings.zone.surge-price.edit', [$surge->id]) }}"
                                                title="{{ translate('Edit') }}">
                                                <i class="tio-edit"></i>
                                            </a>
                                            <a class="btn action-btn action-btn--delete surge-price-delete-btn"
                                                href="javascript:" data-id="surge-price-{{ $surge->id }}"
                                                title="{{ translate('Delete') }}">
                                                <i class="tio-delete-outlined"></i>
                                            </a>
                                            <form action="{{ route('admin.business-settings.zone.surge-price.delete', [$surge->id]) }}"
                                                method="post" id="surge-price-{{ $surge->id }}" class="d-none">
                                                @csrf @method('delete')
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if (count($surges) !== 0)
                    <div class="page-area px-4 pb-3">
                        {!! $surges->withQueryString()->links() !!}
                    </div>
                @else
                    <div class="empty--data">
                        <img src="{{ asset('public/assets/admin/svg/illustrations/sorry.svg') }}" alt="">
                        <h5>{{ translate('No data found') }}</h5>
                    </div>
                @endif
            </div>
        @endif
    </div>

    @include('admin-views.surge-price.partials._delete-modal')
@endsection

@push('script_2')
    <script>
        "use strict";

        let pendingDeleteFormId = null;

        $(document).on('click', '.surge-price-delete-btn', function () {
            pendingDeleteFormId = $(this).data('id');
            $('#surge-price-delete-modal').modal('show');
        });

        $(document).on('click', '#surge-price-delete-confirm', function () {
            if (pendingDeleteFormId) { $('#' + pendingDeleteFormId).submit(); }
        });
    </script>
@endpush
