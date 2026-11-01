@extends('layouts.admin.app')

@section('title', translate('Free delivery'))

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
                <i class="tio-gift"></i>
                <span>
                    {{ translate('Free delivery') }}
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('When a zone waives the delivery charge, and the modules each offer covers.') }}</p>
        </div>

        {{-- The empty state takes over the whole card, but only when nothing has ever been
             added — a search that matches nothing keeps the table so the search box stays
             reachable. --}}
        @if ($setups->total() === 0 && !request()->has('search'))
            <div class="card">
                <div class="card-body empty-state">
                    <img class="empty-state__icon" src="{{ asset('public/assets/admin/img/price-emty.png') }}"
                        alt="{{ translate('Free delivery setup') }}">
                    <h5 class="empty-state__title">
                        {{ translate('Currently you dont have any free delivery setup') }}</h5>
                    <p class="empty-state__text">
                        {{ translate('messages.Set up free delivery rules to offer free delivery based on your defined conditions.') }}
                    </p>
                    {{-- The mock labels this button "Create Surge Price", which is a copy-paste
                         slip from the surge screen. It says what it does instead. --}}
                    <a href="{{ route('admin.business-settings.zone.free-delivery.create') }}" class="btn btn--primary">
                        <i class="tio-add-circle-outlined"></i> {{ translate('Create free delivery') }}
                    </a>
                </div>
            </div>
        @else
            {{-- Amber, above the card. The mock's copy says "ETA Configuration setup" — another
                 slip from a neighbouring screen; it names free delivery here. --}}
            <div class="rule-note mb-20">
                <i class="tio-info-outined"></i>
                <span>{{ translate('Duplicate configurations are not allowed. Each zone and module combination can have only one free delivery setup.') }}</span>
            </div>

            <div class="card">
                <div class="card-header py-2 border-0">
                    <div class="search--button-wrapper">
                        <h5 class="card-title">
                            {{ translate('Free delivery list') }}
                            <span class="badge badge-soft-dark ml-2">{{ $setups->total() }}</span>
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
                                data-hs-unfold-options='{"target": "#freeDeliveryExportDropdown","type": "css-animation"}'>
                                <i class="tio-download-to mr-1"></i> {{ translate('Export') }}
                            </a>
                            <div id="freeDeliveryExportDropdown"
                                class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">
                                <span class="dropdown-header">{{ translate('messages.Download options') }}</span>
                                <a class="dropdown-item"
                                    href="{{ route('admin.business-settings.zone.free-delivery.export', ['type' => 'excel', 'search' => request()->search]) }}">
                                    <img class="avatar avatar-xss avatar-4by3 mr-2"
                                        src="{{ asset('public/assets/admin') }}/svg/components/excel.svg" alt="">
                                    Excel
                                </a>
                                <a class="dropdown-item"
                                    href="{{ route('admin.business-settings.zone.free-delivery.export', ['type' => 'csv', 'search' => request()->search]) }}">
                                    <img class="avatar avatar-xss avatar-4by3 mr-2"
                                        src="{{ asset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg" alt="">
                                    CSV
                                </a>
                            </div>
                        </div>

                        <a href="{{ route('admin.business-settings.zone.free-delivery.create') }}" class="btn btn--primary">
                            <i class="tio-add-circle-outlined"></i> {{ translate('Add new') }}
                        </a>
                    </div>
                </div>

                <div class="table-responsive datatable-custom">
                    <table class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                        <thead class="thead-light">
                            <tr>
                                <th class="border-0">{{ translate('messages.SL') }}</th>
                                <th class="border-0">{{ translate('messages.Zone') }}</th>
                                <th class="border-0">{{ translate('messages.Module') }}</th>
                                <th class="border-0">{{ translate('Free delivery type') }}</th>
                                <th class="border-0">{{ translate('Minimum order amount') }}</th>
                                <th class="border-0">{{ translate('messages.Status') }}</th>
                                <th class="border-0 text-center">{{ translate('messages.Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($setups as $key => $setup)
                                <tr>
                                    <td class="pl-4">{{ $key + $setups->firstItem() }}</td>
                                    <td>
                                        <span class="line--limit-2 max-w-220px"
                                            title="{{ $setup->zone?->name }}">{{ $setup->zone?->name ?? translate('messages.N/A') }}</span>
                                    </td>
                                    <td>
                                        {{-- One module reads by name; several collapse to a count
                                             and carry the names as a tooltip, per the design. --}}
                                        @if ($moduleLabels[$setup->id]['many'])
                                            <span class="text-primary border-bottom border-primary cursor-pointer"
                                                data-toggle="tooltip" data-placement="top"
                                                title="{{ $moduleLabels[$setup->id]['tooltip'] }}">{{ $moduleLabels[$setup->id]['text'] }}</span>
                                        @else
                                            {{ $moduleLabels[$setup->id]['text'] }}
                                        @endif
                                    </td>
                                    <td>{{ $typeLabels[$setup->id] }}</td>
                                    <td>{{ $minimumLabels[$setup->id] }}</td>
                                    <td>
                                        <label class="toggle-switch toggle-switch-sm" for="status-{{ $setup->id }}">
                                            <input type="checkbox" class="toggle-switch-input dynamic-checkbox"
                                                id="status-{{ $setup->id }}" {{ $setup->status ? 'checked' : '' }}
                                                data-id="status-{{ $setup->id }}" data-type="status"
                                                data-image-on="{{ asset('public/assets/admin/img/status-ons.png') }}"
                                                data-image-off="{{ asset('public/assets/admin/img/off-danger.png') }}"
                                                data-title-on="{{ translate('Turn on the status?') }}"
                                                data-title-off="{{ translate('Turn off the status?') }}"
                                                data-text-on="<p>{{ translate('messages.Are you sure, do you want to turn on this free delivery setup.') }}</p>"
                                                data-text-off="<p>{{ translate('messages.Are you sure, do you want to turn off this free delivery setup.') }}</p>">
                                            <span class="toggle-switch-label">
                                                <span class="toggle-switch-indicator"></span>
                                            </span>
                                        </label>
                                        <form action="{{ route('admin.business-settings.zone.free-delivery.status', [$setup->id, $setup->status ? 0 : 1]) }}"
                                            method="get" id="status-{{ $setup->id }}_form"></form>
                                    </td>
                                    <td>
                                        <div class="btn--container justify-content-center">
                                            <a class="btn action-btn action-btn--edit"
                                                href="{{ route('admin.business-settings.zone.free-delivery.edit', [$setup->id]) }}"
                                                title="{{ translate('Edit') }}">
                                                <i class="tio-edit"></i>
                                            </a>
                                            <a class="btn action-btn action-btn--delete free-delivery-delete-btn"
                                                href="javascript:" data-id="free-delivery-{{ $setup->id }}"
                                                title="{{ translate('Delete') }}">
                                                <i class="tio-delete-outlined"></i>
                                            </a>
                                            <form action="{{ route('admin.business-settings.zone.free-delivery.delete', [$setup->id]) }}"
                                                method="post" id="free-delivery-{{ $setup->id }}" class="d-none">
                                                @csrf @method('delete')
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if (count($setups) !== 0)
                    <div class="page-area px-4 pb-3">
                        {!! $setups->withQueryString()->links() !!}
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

    @include('admin-views.free-delivery.partials._delete-modal')
@endsection

@push('script_2')
    <script>
        "use strict";

        let pendingDeleteFormId = null;

        $(document).on('click', '.free-delivery-delete-btn', function () {
            pendingDeleteFormId = $(this).data('id');
            $('#free-delivery-delete-modal').modal('show');
        });

        $(document).on('click', '#free-delivery-delete-confirm', function () {
            if (pendingDeleteFormId) { $('#' + pendingDeleteFormId).submit(); }
        });
    </script>
@endpush
